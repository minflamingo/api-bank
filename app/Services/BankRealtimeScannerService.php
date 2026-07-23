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
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BankRealtimeScannerService
{
    private const BANKS = ['acb', 'vcb', 'vpbank', 'techcombank', 'mbbank'];
    private const MAX_CONSECUTIVE_SCAN_FAILURES = 3;

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
            $this->markAccount($model, false, 'Không tìm thấy user API', 600, trackFailure: false);
            return ['scanned' => 0, 'created' => 0, 'updated' => 0, 'failed' => 0, 'events' => 0];
        }

        $user = ApiPackage::applyDueScheduledPlan($user) ?: $user;
        $userId = (int) $user->id;
        if (ApiPackage::isExpired($user)) {
            $this->markAccount($model, false, 'API package expired', 600, trackFailure: false);
            ApiPackage::pauseBankAccountsForExpiredPackage($user);
            return ['scanned' => 0, 'created' => 0, 'updated' => 0, 'failed' => 0, 'events' => 0];
        }

        $snapshot = app(PaymentController::class)->internalBankSnapshotForScanner($bank, (int) $model->id, $limit);
        if (empty($snapshot['ok'])) {
            $message = (string) ($snapshot['message'] ?? 'Không quét được ' . strtoupper($bank));
            if (!empty($snapshot['deferred']) || !empty($snapshot['rate_limited'])) {
                $this->deferAccount($model, $message, (int) ($snapshot['next_scan_after'] ?? 90));

                return ['scanned' => 1, 'created' => 0, 'updated' => 0, 'failed' => 0, 'events' => 0];
            }

            $forceStop = !empty($snapshot['credential_error']) || !empty($snapshot['requires_reauth']);
            $failureDelay = (int) ($snapshot['next_scan_after'] ?? (!empty($snapshot['session_expired']) ? 300 : 90));
            $paused = $this->markAccount(
                $model,
                false,
                $message,
                $failureDelay,
                forceStop: $forceStop
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
        $nextScanAfter = (int) ($snapshot['next_scan_after'] ?? $this->scanInterval($model));
        $this->markAccount($model, true, null, $nextScanAfter, $balance, (string) ($snapshot['account_name'] ?? ''));

        $fresh = $model->fresh() ?: $model;
        $freshAccount = $this->cache->accountArray($bank, $fresh);
        $this->cache->refreshCachesForAccount($freshAccount, $limit);

        $eventCount = 0;
        foreach (($upsert['created_rows'] ?? []) as $row) {
            if (!$this->shouldDispatchTransactionWebhook($row)) {
                continue;
            }

            $eventCount += $this->events->dispatch($userId, 'transaction.created', [
                'account' => $this->accountPayload($freshAccount),
                'transaction' => $this->transactionPayload($row),
            ]);
        }
        foreach (($upsert['updated_rows'] ?? []) as $row) {
            if (!$this->shouldDispatchTransactionWebhook($row)) {
                continue;
            }

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

    private function shouldDispatchTransactionWebhook(array $row): bool
    {
        $postedAt = $row['posted_at'] ?? $row['happened_at'] ?? null;
        if (empty($postedAt)) {
            return false;
        }

        try {
            return Carbon::parse((string) $postedAt)->greaterThanOrEqualTo(now()->subHours(6));
        } catch (\Throwable $e) {
            return false;
        }
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
        $query->whereNotNull('user_id')->whereNotNull('token')->where('token', '<>', '');
        if (Schema::hasColumn($table, 'is_active')) {
            $query->where('is_active', 1);
        }
        if (Schema::hasColumn($table, 'is_deleted')) {
            $query->where(function ($query) use ($table) {
                $query->whereNull($table . '.is_deleted')->orWhere($table . '.is_deleted', 0);
            });
        }
        if (Schema::hasColumn($table, 'next_scan_at')) {
            $query->where(function ($query) {
                $query->whereNull('next_scan_at')->orWhere('next_scan_at', '<=', now());
            })->orderBy('next_scan_at');
        }

        return $query->orderBy('id')->limit(max(1, min(200, $batch)))->get();
    }

    private function markAccount(Model $model, bool $ok, ?string $error, int $delaySeconds, ?int $balance = null, string $accountName = '', bool $trackFailure = true, bool $forceStop = false): bool
    {
        $table = $model->getTable();
        $updates = [];
        $paused = false;
        $this->setIfColumn($updates, $table, 'last_scan_status', $ok ? 'ok' : 'error');
        $this->setIfColumn($updates, $table, 'last_scan_error', $error);
        $this->setIfColumn($updates, $table, 'next_scan_at', now()->addSeconds($this->normalizeScanDelay($model, $delaySeconds))->toDateTimeString());

        if ($ok) {
            $this->setIfColumn($updates, $table, 'last_synced_at', now()->toDateTimeString());
            $this->setIfColumn($updates, $table, 'scan_failed_count', 0);
            $this->setIfColumn($updates, $table, 'status_note', null);
        } elseif ($trackFailure) {
            $failedCount = $this->readModelInt($model, 'scan_failed_count') + 1;
            $this->setIfColumn($updates, $table, 'scan_failed_count', $failedCount);
            $credentialFailure = $this->isCredentialFailure($error);

            if (($forceStop || $credentialFailure || $failedCount >= self::MAX_CONSECUTIVE_SCAN_FAILURES) && Schema::hasColumn($table, 'is_active')) {
                $paused = true;
                $updates['is_active'] = 0;
                $this->setIfColumn($updates, $table, 'stopped_at', now()->toDateTimeString());
                $this->setIfColumn(
                    $updates,
                    $table,
                    'status_note',
                    mb_substr(
                        (($credentialFailure || $forceStop)
                            ? 'Tự dừng ngay vì cần cập nhật/xác thực lại tài khoản ngân hàng: '
                            : 'Tự dừng sau ' . $failedCount . ' lần lỗi scan liên tiếp: ')
                        . (string) $error,
                        0,
                        500
                    )
                );
                if (Schema::hasColumn($table, 'next_scan_at')) {
                    $updates['next_scan_at'] = null;
                }
            }
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

        return $paused;
    }

    private function deferAccount(Model $model, string $message, int $delaySeconds): void
    {
        $table = $model->getTable();
        $updates = [];
        $this->setIfColumn($updates, $table, 'last_scan_status', 'waiting');
        $this->setIfColumn($updates, $table, 'last_scan_error', mb_substr($message, 0, 500));
        $this->setIfColumn($updates, $table, 'scan_failed_count', 0);
        $this->setIfColumn(
            $updates,
            $table,
            'next_scan_at',
            now()->addSeconds($this->normalizeScanDelay($model, $delaySeconds))->toDateTimeString()
        );

        if ($updates) {
            DB::table($table)->where('id', (int) $model->id)->update($updates);
        }
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

        return $this->normalizeScanDelay($model, $value);
    }

    private function normalizeScanDelay(Model $model, int $seconds): int
    {
        $min = $model->getTable() === 'account_acb' ? 60 : 15;

        return max($min, min(900, $seconds > 0 ? $seconds : 60));
    }

    private function isCredentialFailure(?string $message): bool
    {
        $message = mb_strtolower((string) $message);
        if ($message === '') {
            return false;
        }

        foreach ([
            'mật khẩu',
            'mat khau',
            'password',
            'credential',
            'incorrect username',
            'invalid username',
            'invalid login',
            'tài khoản hoặc mật khẩu',
            'tai khoan hoac mat khau',
            'không đúng',
            'khong dung',
            'sai thông tin',
            'sai thong tin',
            'unauthorized',
            'access denied',
        ] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function readModelInt(Model $model, string $field): int
    {
        $value = $model->{$field} ?? null;

        if ($value === null || $value === '') {
            return $field === 'last_balance' ? (int) ($model->balance ?? 0) : 0;
        }

        return (int) $value;
    }
}
