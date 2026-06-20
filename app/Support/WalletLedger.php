<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class WalletLedger
{
    private static ?bool $available = null;

    public static function available(): bool
    {
        if (self::$available !== null) {
            return self::$available;
        }

        try {
            self::$available = Schema::hasTable('wallet_ledgers');
        } catch (\Throwable $e) {
            report($e);
            self::$available = false;
        }

        return self::$available;
    }

    public static function ensureOpeningBalance(int $userId, ?int $currentBalance = null): void
    {
        if ($userId <= 0 || !self::available()) {
            return;
        }

        $reference = self::openingReference($userId);
        $exists = DB::table('wallet_ledgers')->where('reference', $reference)->exists();
        if ($exists) {
            return;
        }

        $user = DB::table('users')->where('id', $userId)->first(['id', 'amount']);
        if (!$user) {
            return;
        }

        $balance = $currentBalance ?? (int) ($user->amount ?? 0);
        $existingLedger = (int) DB::table('wallet_ledgers')->where('user_id', $userId)->sum('amount');
        $openingAmount = $balance - $existingLedger;

        DB::table('wallet_ledgers')->insertOrIgnore([
            'user_id' => $userId,
            'actor_id' => null,
            'type' => 'opening_balance',
            'direction' => $openingAmount >= 0 ? 'credit' : 'debit',
            'amount' => $openingAmount,
            'balance_before' => 0,
            'balance_after' => $openingAmount,
            'reference' => $reference,
            'description' => 'Số dư khởi tạo khi bật ledger ví',
            'meta' => json_encode([
                'current_balance' => $balance,
                'existing_ledger' => $existingLedger,
                'baseline_at' => now()->toDateTimeString(),
            ], JSON_UNESCAPED_UNICODE),
            'ip' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function ensureOpeningBalances(int $limit = 1000): int
    {
        if (!self::available()) {
            return 0;
        }

        $inserted = 0;
        $users = DB::table('users')
            ->select(['id', 'amount'])
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('wallet_ledgers')
                    ->whereColumn('wallet_ledgers.user_id', 'users.id')
                    ->where('wallet_ledgers.type', 'opening_balance');
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($users as $user) {
            $before = DB::table('wallet_ledgers')->where('reference', self::openingReference((int) $user->id))->exists();
            self::ensureOpeningBalance((int) $user->id, (int) ($user->amount ?? 0));
            $after = DB::table('wallet_ledgers')->where('reference', self::openingReference((int) $user->id))->exists();
            if (!$before && $after) {
                $inserted++;
            }
        }

        return $inserted;
    }

    public static function record(
        int $userId,
        int $amount,
        string $type,
        string $reference,
        string $description,
        ?int $actorId = null,
        ?int $balanceBefore = null,
        ?int $balanceAfter = null,
        array $meta = []
    ): void {
        if ($userId <= 0 || $amount === 0 || !self::available()) {
            return;
        }

        self::ensureOpeningBalance($userId, $balanceBefore);

        DB::table('wallet_ledgers')->insertOrIgnore([
            'user_id' => $userId,
            'actor_id' => $actorId,
            'type' => mb_substr($type, 0, 64),
            'direction' => $amount >= 0 ? 'credit' : 'debit',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'reference' => mb_substr($reference ?: self::makeReference($type, $userId), 0, 191),
            'description' => $description,
            'meta' => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
            'ip' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function makeReference(string $type, int $userId, string $suffix = ''): string
    {
        $suffix = $suffix !== '' ? ':' . trim($suffix, ':') : '';

        return $type . ':' . $userId . ':' . now()->format('YmdHis') . ':' . Str::random(10) . $suffix;
    }

    private static function openingReference(int $userId): string
    {
        return 'opening_balance:' . $userId;
    }
}
