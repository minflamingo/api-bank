<?php

namespace App\Services;

use App\Models\AccountAcb;
use App\Models\AccountMbbank;
use App\Models\AccountTechcombank;
use App\Models\AccountVietcombank;
use App\Models\AccountVpbank;
use App\Models\User;
use App\Support\ApiPackage;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BankRealtimeCacheService
{
    private const BANKS = ['acb', 'vcb', 'vpbank', 'techcombank', 'mbbank'];

    public function transactionHistory(string $bank, string $token, int $limit = 100, string $accountNo = ''): array
    {
        $accountChoices = $this->accountNumberChoices($bank, $token, $accountNo);
        if (count($accountChoices) > 1) {
            return $this->accountNoRequiredResponse($bank, $accountChoices);
        }

        $account = $this->findAccount($bank, $token, $accountNo);
        if (!$account) {
            return $this->accountNotFoundResponse($bank, $token);
        }

        $inactive = $this->inactiveResponse($account);
        if ($inactive) {
            return $inactive;
        }

        $expired = $this->expiredResponse($account);
        if ($expired) {
            return $expired;
        }

        $limit = max(1, min(500, $limit));
        $tokenHash = $this->tokenHash($token);
        $accountKey = $this->cacheAccountKey((string) $account['account_no']);
        $listKey = "tx:list:{$tokenHash}:{$accountKey}:{$limit}";
        $metaKey = "tx:meta:{$tokenHash}:{$accountKey}";

        $rows = $this->cacheGet($listKey);
        $meta = $this->cacheGet($metaKey) ?: [];
        $source = 'redis';

        if (!is_array($rows)) {
            $rows = $this->sqlTransactions($account, $limit);
            $meta = $this->metaForAccount($account);
            $this->cachePut($listKey, $rows, 60);
            $this->cachePut($metaKey, $meta, 60);
            $source = 'sql';
        }

        return $this->historyEnvelope($bank, $rows, $meta, $source);
    }

    public function balance(string $bank, string $token, string $accountNo = ''): array
    {
        $accountChoices = $this->accountNumberChoices($bank, $token, $accountNo);
        if (count($accountChoices) > 1) {
            return $this->accountNoRequiredResponse($bank, $accountChoices);
        }

        $account = $this->findAccount($bank, $token, $accountNo);
        if (!$account) {
            return $this->accountNotFoundResponse($bank, $token);
        }

        $inactive = $this->inactiveResponse($account);
        if ($inactive) {
            return $inactive;
        }

        $expired = $this->expiredResponse($account);
        if ($expired) {
            return $expired;
        }

        $tokenHash = $this->tokenHash($token);
        $accountKey = $this->cacheAccountKey((string) $account['account_no']);
        $key = "balance:{$tokenHash}:{$accountKey}";
        $payload = $this->cacheGet($key);
        $source = 'redis';

        if (!is_array($payload)) {
            $payload = $this->balanceSnapshotFromAccount($account);
            $this->cachePut($key, $payload, 60);
            $source = 'sql';
        }

        $meta = $this->metaForAccount($account);

        return [
            'ok' => true,
            'status' => 200,
            'SoDu' => (int) ($payload['balance'] ?? 0),
            'balance' => (int) ($payload['balance'] ?? 0),
            'account_number' => (string) $account['account_no'],
            'account_no' => (string) $account['account_no'],
            'accountNo' => (string) $account['account_no'],
            'accountDescription' => (string) ($payload['account_name'] ?? $account['account_name'] ?? ''),
            'source' => $source,
            'last_synced_at' => $meta['last_synced_at'] ?? null,
            'stale_seconds' => (int) ($meta['stale_seconds'] ?? 0),
            'is_stale' => (bool) ($meta['is_stale'] ?? true),
        ];
    }

    public function refreshCachesForAccount(array $account, int $limit = 100): void
    {
        $token = (string) $account['token'];
        if ($token === '') {
            return;
        }

        $tokenHash = $this->tokenHash($token);
        $limit = max(1, min(500, $limit));
        $accountKey = $this->cacheAccountKey((string) $account['account_no']);
        $this->cachePut("tx:list:{$tokenHash}:{$accountKey}:{$limit}", $this->sqlTransactions($account, $limit), 60);
        $this->cachePut("tx:meta:{$tokenHash}:{$accountKey}", $this->metaForAccount($account), 60);
        $this->cachePut("balance:{$tokenHash}:{$accountKey}", $this->balanceSnapshotFromAccount($account), 60);
    }

    public function findAccount(string $bank, string $token, string $accountNo = ''): ?array
    {
        $bank = $this->normalizeBank($bank);
        $token = trim($token);
        if ($bank === null || $token === '') {
            return null;
        }

        $accountNo = $this->normalizeAccountNo($accountNo);
        $query = match ($bank) {
            'acb' => AccountAcb::query()->where('token', $token),
            'vcb' => AccountVietcombank::query()->where('token', $token),
            'vpbank' => AccountVpbank::query()->where('token', $token),
            'techcombank' => AccountTechcombank::query()->where('token', $token),
            'mbbank' => AccountMbbank::query()->where('token', $token),
        };

        if ($accountNo !== '') {
            $query->where($bank === 'acb' ? 'stk' : 'account', $accountNo);
        }

        $model = $this->withoutArchived($query)
            ->orderByRaw('CASE WHEN user_id IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('id')
            ->first();

        return $model ? $this->accountArray($bank, $model) : null;
    }

    private function accountNumberChoices(string $bank, string $token, string $accountNo = ''): array
    {
        $bank = $this->normalizeBank($bank);
        $token = trim($token);
        if ($bank === null || $token === '' || $this->normalizeAccountNo($accountNo) !== '') {
            return [];
        }

        $query = match ($bank) {
            'acb' => AccountAcb::query()->where('token', $token),
            'vcb' => AccountVietcombank::query()->where('token', $token),
            'vpbank' => AccountVpbank::query()->where('token', $token),
            'techcombank' => AccountTechcombank::query()->where('token', $token),
            'mbbank' => AccountMbbank::query()->where('token', $token),
        };

        $choices = [];
        $rows = $this->withoutArchived($query)
            ->orderByRaw('CASE WHEN user_id IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        foreach ($rows as $model) {
            $account = $this->accountArray($bank, $model);
            $number = $this->normalizeAccountNo((string) ($account['account_no'] ?? ''));
            if ($number !== '') {
                $choices[$number] = $number;
            }
        }

        return array_values($choices);
    }

    private function accountNoRequiredResponse(string $bank, array $accountNumbers): array
    {
        return $this->errorResponse(
            $bank,
            'Token này đang dùng cho nhiều tài khoản. Vui lòng truyền account_no để lấy đúng số dư/lịch sử từng tài khoản.',
            'ACCOUNT_NO_REQUIRED',
            [
                'account_numbers' => array_values($accountNumbers),
                'hint' => 'Thêm ?account_no=SO_TAI_KHOAN vào cuối URL API.',
            ]
        );
    }

    private function normalizeAccountNo(string $accountNo): string
    {
        return preg_replace('/\s+/', '', trim($accountNo)) ?: '';
    }

    private function cacheAccountKey(string $accountNo): string
    {
        return hash('sha256', $this->normalizeAccountNo($accountNo));
    }

    public function accountArray(string $bank, Model $model): array
    {
        $bank = $this->normalizeBank($bank) ?: $bank;
        $accountNo = match ($bank) {
            'acb' => (string) ($model->stk ?? ''),
            default => (string) ($model->account ?? ''),
        };

        $login = match ($bank) {
            'acb' => (string) ($model->phone ?? ''),
            default => (string) ($model->username ?? ''),
        };

        return [
            'bank' => $bank,
            'table' => $model->getTable(),
            'id' => (int) ($model->id ?? 0),
            'user_id' => $model->user_id === null ? null : (int) $model->user_id,
            'account_no' => $accountNo,
            'account_name' => (string) ($model->name ?? ''),
            'login_name' => $login,
            'token' => (string) ($model->token ?? ''),
            'is_active' => !Schema::hasColumn($model->getTable(), 'is_active') || (int) ($model->is_active ?? 1) === 1,
            'stopped_at' => $this->modelDate($model, 'stopped_at'),
            'last_synced_at' => $this->modelDate($model, 'last_synced_at'),
            'last_balance' => $this->modelInt($model, 'last_balance'),
            'last_balance_at' => $this->modelDate($model, 'last_balance_at'),
            'last_scan_status' => (string) ($model->last_scan_status ?? ''),
            'last_scan_error' => (string) ($model->last_scan_error ?? ''),
            'model' => $model,
        ];
    }

    private function accountNotFoundResponse(string $bank, string $token): array
    {
        $actualBank = $this->tokenBank($token);
        if ($actualBank && $actualBank !== $this->normalizeBank($bank)) {
            return $this->errorResponse(
                $bank,
                'Token này thuộc ' . $this->bankLabel($actualBank) . ', không phải ' . $this->bankLabel($bank) . '. Vui lòng chọn đúng ngân hàng hoặc dán lại token.',
                'TOKEN_BANK_MISMATCH',
                ['actual_bank' => $actualBank, 'requested_bank' => $this->normalizeBank($bank)]
            );
        }

        return $this->errorResponse(
            $bank,
            'Không tìm thấy tài khoản ' . $this->bankLabel($bank) . ' theo token',
            'TOKEN_NOT_FOUND'
        );
    }

    private function tokenBank(string $token): ?string
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        foreach (self::BANKS as $bank) {
            $query = match ($bank) {
                'acb' => AccountAcb::query(),
                'vcb' => AccountVietcombank::query(),
                'vpbank' => AccountVpbank::query(),
                'techcombank' => AccountTechcombank::query(),
                'mbbank' => AccountMbbank::query(),
            };

            if ($this->withoutArchived($query)->where('token', $token)->exists()) {
                return $bank;
            }
        }

        return null;
    }

    public function normalizeBank(?string $bank): ?string
    {
        $bank = strtolower(trim((string) $bank));

        return match ($bank) {
            'acb' => 'acb',
            'vcb', 'vietcombank' => 'vcb',
            'vpb', 'vpbank' => 'vpbank',
            'tcb', 'techcombank' => 'techcombank',
            'mbb', 'mbbank' => 'mbbank',
            default => null,
        };
    }

    public function tokenHash(string $token): string
    {
        return hash('sha256', $token);
    }

    private function expiredResponse(array $account): ?array
    {
        $userId = (int) ($account['user_id'] ?? 0);
        $user = $userId > 0 ? User::find($userId) : null;
        if (!$user) {
            return [
                'status' => 'false',
                'ok' => false,
                'msg' => 'Không tìm thấy user sở hữu token',
            ];
        }

        $fresh = ApiPackage::applyDueScheduledPlan($user) ?: $user;
        if (! ApiPackage::isExpired($fresh)) {
            return null;
        }

        return [
            'status' => 'false',
            'ok' => false,
            'code' => 'TOKEN_EXPIRED',
            'msg' => 'Token hết hạn, vui lòng gia hạn tài khoản để tiếp tục sử dụng API',
            'time_end' => (int) ($fresh->time_end ?? 0),
            'renew_url' => url('/client/upgrade'),
        ];
    }

    private function inactiveResponse(array $account): ?array
    {
        if ((bool) ($account['is_active'] ?? true)) {
            return null;
        }

        return $this->errorResponse(
            (string) $account['bank'],
            'Tài khoản ngân hàng đang tạm dừng. Vui lòng kích hoạt lại hoặc cập nhật phiên/token mới.',
            'ACCOUNT_INACTIVE',
            [
                'account_no' => (string) $account['account_no'],
                'stopped_at' => $account['stopped_at'] ?? null,
                'last_synced_at' => $account['last_synced_at'] ?? null,
            ]
        );
    }

    private function sqlTransactions(array $account, int $limit): array
    {
        $query = DB::table('bank_transactions')
            ->where(function ($query) use ($account) {
                $query->where('bank', $account['bank']);
                if (Schema::hasColumn('bank_transactions', 'bank_code')) {
                    $query->orWhere('bank_code', $account['bank']);
                }
            })
            ->where('account_no', (string) $account['account_no']);

        $rows = $query
            ->orderByDesc(DB::raw('COALESCE(happened_at, posted_at, created_at)'))
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($row) => $this->transactionOutput((array) $row))->values()->all();
    }

    private function transactionOutput(array $row): array
    {
        $raw = $row['raw'] ?? null;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            }
        }
        if (!is_array($raw)) {
            $raw = [];
        }

        $amount = (int) ($row['amount'] ?? 0);
        $direction = (string) ($row['direction'] ?? 'unknown');
        $isCredit = $direction === 'in' || $amount > 0;
        $happenedAt = (string) ($row['happened_at'] ?? $row['posted_at'] ?? $row['created_at'] ?? '');

        $raw['_reference'] = (string) ($raw['_reference'] ?? $row['ref_id'] ?? $row['transaction_id'] ?? $row['transaction_uid'] ?? '');
        $raw['_description'] = (string) ($raw['_description'] ?? $row['description'] ?? '');
        $raw['_amount'] = abs($amount);
        $raw['_is_credit'] = $isCredit;
        $raw['_date_key'] = $this->dateKey($happenedAt);
        $raw['_happened_at'] = $happenedAt !== '' ? $happenedAt : null;
        $raw['_transaction_uid'] = (string) ($row['transaction_uid'] ?? $row['transaction_hash'] ?? '');
        $raw['_party_info'] = [
            'name' => (string) ($row['counterparty_name'] ?? ''),
            'account' => (string) ($row['counterparty_account'] ?? ''),
            'bank' => (string) ($row['counterparty_bank'] ?? ''),
        ];

        return $raw;
    }

    private function historyEnvelope(string $bank, array $transactions, array $meta, string $source): array
    {
        $bank = $this->normalizeBank($bank) ?: $bank;
        $base = [
            'ok' => true,
            'status' => 200,
            'source' => $source,
            'last_synced_at' => $meta['last_synced_at'] ?? null,
            'stale_seconds' => (int) ($meta['stale_seconds'] ?? 0),
            'is_stale' => (bool) ($meta['is_stale'] ?? true),
            'transactions' => $transactions,
        ];

        if ($bank === 'acb') {
            $base['codeStatus'] = 200;
            $base['data'] = $transactions;
            return $base;
        }

        if ($bank === 'vcb') {
            $base['code'] = '00';
            return $base;
        }

        $base['code'] = 200;
        $base['success'] = true;
        $base['data'] = ['transactions' => $transactions];

        return $base;
    }

    private function metaForAccount(array $account): array
    {
        $last = $account['last_synced_at'] ?? null;
        $lastTs = $last ? strtotime((string) $last) : 0;
        $stale = $lastTs > 0 ? max(0, time() - $lastTs) : 999999999;

        return [
            'bank' => (string) $account['bank'],
            'account_no' => (string) $account['account_no'],
            'token_hash' => $this->tokenHash((string) $account['token']),
            'is_active' => (bool) ($account['is_active'] ?? true),
            'last_synced_at' => $last,
            'stale_seconds' => $stale,
            'is_stale' => $stale > (int) config('services.realtime_cache.stale_after_seconds', 90),
            'last_scan_status' => (string) ($account['last_scan_status'] ?? ''),
            'last_scan_error' => (string) ($account['last_scan_error'] ?? ''),
        ];
    }

    private function balanceSnapshotFromAccount(array $account): array
    {
        return [
            'balance' => (int) ($account['last_balance'] ?? 0),
            'account_no' => (string) $account['account_no'],
            'account_name' => (string) ($account['account_name'] ?? ''),
            'last_balance_at' => $account['last_balance_at'] ?? null,
        ];
    }

    private function cacheGet(string $key): mixed
    {
        try {
            return Cache::store((string) config('services.realtime_cache.store', 'redis'))->get($key);
        } catch (\Throwable $e) {
            return Cache::get($key);
        }
    }

    private function cachePut(string $key, mixed $value, int $seconds): void
    {
        try {
            Cache::store((string) config('services.realtime_cache.store', 'redis'))->put($key, $value, $seconds);
        } catch (\Throwable $e) {
            Cache::put($key, $value, $seconds);
        }
    }

    private function errorResponse(string $bank, string $message, string $code = 'BANK_ACCOUNT_ERROR', array $extra = []): array
    {
        return array_merge([
            'ok' => false,
            'status' => 'false',
            'code' => $code,
            'source' => 'none',
            'msg' => $message,
            'transactions' => [],
            'data' => $this->normalizeBank($bank) === 'acb' ? [] : ['transactions' => []],
        ], $extra);
    }

    private function withoutArchived($query)
    {
        $table = $query->getModel()->getTable();
        if (Schema::hasColumn($table, 'is_deleted')) {
            $query->where(function ($subQuery) use ($table) {
                $subQuery->whereNull($table . '.is_deleted')
                    ->orWhere($table . '.is_deleted', 0);
            });
        }

        return $query;
    }

    private function bankLabel(?string $bank): string
    {
        return match ($this->normalizeBank($bank)) {
            'acb' => 'ACB',
            'vcb' => 'Vietcombank',
            'vpbank' => 'VPBank',
            'techcombank' => 'Techcombank',
            'mbbank' => 'MBBank',
            default => strtoupper((string) $bank),
        };
    }

    private function modelDate(Model $model, string $field): ?string
    {
        $value = $model->{$field} ?? null;
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function modelInt(Model $model, string $field): int
    {
        $value = $model->{$field} ?? null;
        if ($value !== null && $value !== '') {
            return (int) $value;
        }

        return (int) ($model->balance ?? 0);
    }

    private function dateKey(string $value): string
    {
        if ($value === '') {
            return '';
        }

        try {
            return Carbon::parse($value)->format('YmdHis');
        } catch (\Throwable $e) {
            return preg_replace('/\D+/', '', $value) ?: '';
        }
    }
}
