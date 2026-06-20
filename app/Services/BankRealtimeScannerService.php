<?php

namespace App\Services;

use App\Http\Controllers\PaymentController;
use App\Models\AccountAcb;
use App\Models\AccountMbbank;
use App\Models\AccountTechcombank;
use App\Models\AccountVietcombank;
use App\Models\AccountVpbank;
use App\Models\User;
use App\Support\ApiPackage;
use App\Support\BankTransactionRecorder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BankRealtimeScannerService
{
    private const BANKS = ['acb', 'vcb', 'vpbank', 'techcombank', 'mbbank'];

    public function __construct(
        private readonly BankRealtimeCacheService $cache,
        private readonly BankTransactionRecorder $recorder,
        private readonly WebhookEventService $events
    ) {}

    public function runOnce(?string $onlyBank = null, int $batch = 10, int $limit = 100): array
    {
        $onlyBank = $onlyBank ? $this->cache->normalizeBank($onlyBank) : null;
        $banks = $onlyBank ? [$onlyBank] : self::BANKS;
        $summary = ['scanned' => 0, 'created' => 0, 'updated' => 0, 'failed' => 0, 'events' => 0];

        foreach ($banks as $bank) {
            foreach ($this->dueAccounts($bank, $batch) as $model) {
                $result = $this->scanAccount($bank, $model, $limit);
                foreach ($summary as $key => $value) {
                    $summary[$key] += (int) ($result[$key] ?? 0);
                }
            }
        }

        return $summary;
    }

    private function scanAccount(string $bank, Model $model, int $limit): array
    {
        $account = $this->cache->accountArray($bank, $model);
        $userId = (int) ($account['user_id'] ?? 0);
        $user = $userId > 0 ? User::find($userId) : null;

        if (!$user) {
            $this->markAccount($model, false, 'Không tìm thấy user sở hữu tài khoản APIBank.', 600);
            return ['scanned' => 0, 'created' => 0, 'updated' => 0, 'failed' => 0, 'events' => 0];
        }

        if ((int) ((ApiPackage::applyDueScheduledPlan($user) ?: $user)->time_end ?? 0) <= time()) {
            $this->markAccountFailed($model, 'Gói API đã hết hạn, vui lòng gia hạn để scanner chạy lại.', 600, true);
            return ['scanned' => 0, 'created' => 0, 'updated' => 0, 'failed' => 0, 'events' => 0];
        }

        $snapshot = app(PaymentController::class)->internalBankSnapshotForScanner($bank, (int) $model->id, $limit);
        if (empty($snapshot['ok'])) {
            $message = (string) ($snapshot['message'] ?? 'Không quét được ' . strtoupper($bank));
            $pauseImmediately = !empty($snapshot['credential_error'])
                || !empty($snapshot['requires_reauth'])
                || $this->looksLikeCredentialError($message);
            $paused = $this->markAccountFailed(
                $model,
                $message,
                !empty($snapshot['session_expired']) ? 300 : 90,
                $pauseImmediately
            );
            $events = 0;
            if (!empty($snapshot['session_expired'])) {
                $events += $this->events->dispatch($userId, 'account.session_expired', [
                    'account' => $this->accountPayload($account),
                    'message' => $message,
                    'paused' => $paused,
                ]);
            }

            return ['scanned' => 1, 'created' => 0, 'updated' => 0, 'failed' => 1, 'events' => $events];
        }

        $transactions = is_array($snapshot['transactions'] ?? null) ? $snapshot['transactions'] : [];
        $upsert = $this->recorder->upsert($bank, (int) $model->id, $userId, (string) $account['account_no'], $transactions);

        $previousBalance = $this->readModelInt($model, 'last_balance');
        $balance = (int) ($snapshot['balance'] ?? $previousBalance);
        $this->markAccount($model, true, null, $this->scanInterval($model), $balance, (string) ($snapshot['account_name'] ?? ''));

        $fresh = $model->fresh() ?: $model;
        $freshAccount = $this->cache->accountArray($bank, $fresh);
        $this->cache->refreshCachesForAccount($freshAccount, $limit);

        $eventCount = 0;
        foreach (($upsert['created_rows'] ?? []) as $row) {
            $eventCount += $this->events->dispatch($userId, 'transaction.created', [
                'account' => $this->accountPayload($freshAccount),
                'transaction' => $this->transactionPayload($row),
            ]);
        }
        foreach (($upsert['updated_rows'] ?? []) as $row) {
            $eventCount += $this->events->dispatch($userId, 'transaction.updated', [
                'account' => $this->accountPayload($freshAccount),
                'transaction' => $this->transactionPayload($row),
            ]);
        }
        if ($previousBalance !== $balance) {
            $eventCount += $this->events->dispatch($userId, 'balance.updated', [
                'account' => $this->accountPayload($freshAccount),
                'balance' => $balance,
                'previous_balance' => $previousBalance,
            ]);
        }

        return [
            'scanned' => 1,
            'created' => (int) ($upsert['created'] ?? 0),
            'updated' => (int) ($upsert['updated'] ?? 0),
            'failed' => 0,
            'events' => $eventCount,
        ];
    }

    private function dueAccounts(string $bank, int $batch)
    {
        $query = match ($bank) {
            'acb' => AccountAcb::query(),
            'vcb' => AccountVietcombank::query(),
            'vpbank' => AccountVpbank::query(),
            'techcombank' => AccountTechcombank::query(),
            'mbbank' => AccountMbbank::query(),
        };

        $table = $query->getModel()->getTable();
        $query->whereNotNull('token')->where('token', '<>', '');
        if (Schema::hasColumn($table, 'is_active')) {
            $query->where('is_active', 1);
        }
        if (Schema::hasColumn($table, 'next_scan_at')) {
            $query->where(function ($query) {
                $query->whereNull('next_scan_at')->orWhere('next_scan_at', '<=', now());
            })->orderBy('next_scan_at');
        }

        return $query->orderBy('id')->limit(max(1, min(200, $batch)))->get();
    }

    private function markAccount(Model $model, bool $ok, ?string $error, int $delaySeconds, ?int $balance = null, string $accountName = ''): void
    {
        $table = $model->getTable();
        $updates = [];
        $this->setIfColumn($updates, $table, 'last_scan_status', $ok ? 'ok' : 'error');
        $this->setIfColumn($updates, $table, 'last_scan_error', $error);
        $this->setIfColumn($updates, $table, 'next_scan_at', now()->addSeconds(max(15, $delaySeconds))->toDateTimeString());

        if ($ok) {
            $this->setIfColumn($updates, $table, 'last_synced_at', now()->toDateTimeString());
            $this->setIfColumn($updates, $table, 'scan_failed_count', 0);
            $this->setIfColumn($updates, $table, 'status_note', null);
        }
        if ($balance !== null) {
            $this->setIfColumn($updates, $table, 'last_balance', $balance);
            $this->setIfColumn($updates, $table, 'last_balance_at', now()->toDateTimeString());
        }
        if ($accountName !== '' && Schema::hasColumn($table, 'name') && trim((string) ($model->name ?? '')) === '') {
            $updates['name'] = $accountName;
        }

        if ($updates) {
            DB::table($table)->where('id', (int) $model->id)->update($updates);
        }
    }

    private function markAccountFailed(Model $model, string $message, int $delaySeconds, bool $pauseImmediately = false): bool
    {
        $table = $model->getTable();
        $failureCount = $this->nextFailureCount($model);
        $paused = $pauseImmediately || $failureCount >= 3;
        $updates = [];

        $this->setIfColumn($updates, $table, 'last_scan_status', 'error');
        $this->setIfColumn($updates, $table, 'last_scan_error', $message);
        $this->setIfColumn($updates, $table, 'scan_failed_count', $failureCount);

        if ($paused) {
            $note = $pauseImmediately
                ? $message
                : 'Scanner tự dừng sau 3 lỗi liên tiếp: ' . $message;
            $this->setIfColumn($updates, $table, 'is_active', 0);
            $this->setIfColumn($updates, $table, 'stopped_at', now()->toDateTimeString());
            $this->setIfColumn($updates, $table, 'status_note', mb_substr($note, 0, 255));
            $this->setIfColumn($updates, $table, 'next_scan_at', null);
        } else {
            $this->setIfColumn($updates, $table, 'next_scan_at', now()->addSeconds(max(15, $delaySeconds))->toDateTimeString());
        }

        if ($updates) {
            DB::table($table)->where('id', (int) $model->id)->update($updates);
        }

        return $paused;
    }

    private function nextFailureCount(Model $model): int
    {
        $table = $model->getTable();
        if (!Schema::hasColumn($table, 'scan_failed_count')) {
            return 1;
        }

        $current = DB::table($table)->where('id', (int) $model->id)->value('scan_failed_count');

        return min(255, ((int) ($current ?? $model->scan_failed_count ?? 0)) + 1);
    }

    private function looksLikeCredentialError(string $message): bool
    {
        $message = mb_strtolower($message);
        foreach ([
            'sai mật khẩu',
            'mat khau',
            'mật khẩu không đúng',
            'tài khoản hoặc mật khẩu',
            'tai khoan hoac mat khau',
            'incorrect password',
            'wrong password',
            'invalid password',
            'invalid credential',
            'invalid username',
            'access denied',
            'unauthorized',
        ] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function accountPayload(array $account): array
    {
        $link = DB::table('quanly_account_links')
            ->where('apibank_user_id', (int) ($account['user_id'] ?? 0))
            ->first();

        return [
            'bank_code' => (string) $account['bank'],
            'account_no' => (string) $account['account_no'],
            'account_name' => (string) ($account['account_name'] ?? ''),
            'token_hash' => $this->cache->tokenHash((string) $account['token']),
            'apibank_user_id' => (int) ($account['user_id'] ?? 0),
            'apibank_account_id' => (int) ($account['id'] ?? 0),
            'quanly_user_id' => $link ? (int) $link->quanly_user_id : null,
            'quanly_tenant_id' => $link && $link->quanly_tenant_id !== null ? (int) $link->quanly_tenant_id : null,
        ];
    }

    private function transactionPayload(array $row): array
    {
        return [
            'uid' => (string) ($row['transaction_uid'] ?? $row['transaction_hash'] ?? ''),
            'bank_code' => (string) ($row['bank_code'] ?? $row['bank'] ?? ''),
            'account_no' => (string) ($row['account_no'] ?? ''),
            'direction' => (string) ($row['direction'] ?? 'unknown'),
            'amount' => abs((int) ($row['amount'] ?? 0)),
            'signed_amount' => (int) ($row['amount'] ?? 0),
            'ref_id' => (string) ($row['ref_id'] ?? $row['transaction_id'] ?? ''),
            'active_ms' => (int) ($row['active_ms'] ?? 0),
            'description' => (string) ($row['description'] ?? ''),
            'happened_at' => (string) ($row['happened_at'] ?? $row['posted_at'] ?? ''),
            'raw' => is_string($row['raw'] ?? null) ? json_decode((string) $row['raw'], true) : ($row['raw'] ?? []),
        ];
    }

    private function setIfColumn(array &$updates, string $table, string $column, mixed $value): void
    {
        if (Schema::hasColumn($table, $column)) {
            $updates[$column] = $value;
        }
    }

    private function scanInterval(Model $model): int
    {
        $value = (int) ($model->scan_interval_seconds ?? 60);

        return max(15, min(900, $value > 0 ? $value : 60));
    }

    private function readModelInt(Model $model, string $field): int
    {
        $value = $model->{$field} ?? null;

        return $value === null || $value === '' ? (int) ($model->balance ?? 0) : (int) $value;
    }
}
