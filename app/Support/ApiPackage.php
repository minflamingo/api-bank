<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApiPackage
{
    public static function plans(): array
    {
        return [
            'standard' => [
                'name' => 'Standard',
                'price' => 20000,
                'limit' => 1,
                'summary' => '1 tài khoản ngân hàng',
                'features' => ['Thông tin số dư', 'Lịch sử giao dịch', 'API', 'Webhook'],
                'durations' => self::discountDurations(20000),
            ],
            'plus' => [
                'name' => 'Plus',
                'price' => 40000,
                'limit' => 2,
                'summary' => '2 tài khoản ngân hàng',
                'features' => ['Thông tin số dư', 'Lịch sử giao dịch', 'API', 'Webhook'],
                'durations' => self::discountDurations(40000),
            ],
            'pro' => [
                'name' => 'Pro',
                'price' => 90000,
                'limit' => 5,
                'summary' => '5 tài khoản ngân hàng',
                'features' => ['Thông tin số dư', 'Lịch sử giao dịch', 'API', 'Webhook'],
                'durations' => self::discountDurations(90000),
            ],
            'business' => [
                'name' => 'Business',
                'price' => 1000000,
                'limit' => 200,
                'summary' => '200 tài khoản ngân hàng',
                'features' => ['Thông tin số dư', 'Lịch sử giao dịch', 'API', 'Webhook', 'Website riêng'],
                'durations' => self::discountDurations(1000000),
            ],
        ];
    }

    public static function plan(?string $key): ?array
    {
        $plans = self::plans();
        return $plans[$key ?? ''] ?? null;
    }

    public static function packagePrice(string $planKey, int $months): ?int
    {
        $plan = self::plan($planKey);
        if (!$plan) {
            return null;
        }

        return $plan['durations'][$months] ?? null;
    }

    public static function isCustomPlan(?User $user): bool
    {
        if (!$user || (int) ($user->time_end ?? 0) <= time()) {
            return false;
        }

        if ((int) ($user->api_account_limit ?? 0) <= 0) {
            return false;
        }

        return self::plan((string) ($user->api_plan ?? '')) === null;
    }

    public static function currentPlanName(?User $user): string
    {
        $plan = self::plan((string) ($user->api_plan ?? ''));
        if ($plan) {
            return (string) $plan['name'];
        }

        return self::isCustomPlan($user) ? 'Gói tùy chỉnh' : 'Chưa chọn gói';
    }

    public static function userBaseLimit(?User $user): int
    {
        $user = self::applyDueScheduledPlan($user) ?: $user;
        $limit = (int) ($user->api_account_limit ?? 0);
        if ($limit > 0) {
            return $limit;
        }

        return (int) config('services.bank_api.account_limit', 3);
    }

    public static function userExtraSlots(?User $user): int
    {
        return max(0, (int) ($user->api_extra_slots ?? 0));
    }

    public static function userLimit(?User $user): int
    {
        return self::userBaseLimit($user) + self::userExtraSlots($user);
    }

    public static function applyDueScheduledPlan(?User $user): ?User
    {
        if (!$user) {
            return null;
        }

        $now = time();
        if ((int) ($user->time_end ?? 0) > $now || trim((string) ($user->api_next_plan ?? '')) === '') {
            return $user;
        }

        $freshUser = DB::transaction(function () use ($user, $now) {
            $lockedUser = User::whereKey($user->id)->lockForUpdate()->first();
            if (!$lockedUser) {
                return null;
            }

            if ((int) ($lockedUser->time_end ?? 0) > $now) {
                return $lockedUser->fresh();
            }

            $nextPlanKey = trim((string) ($lockedUser->api_next_plan ?? ''));
            if ($nextPlanKey === '') {
                return $lockedUser->fresh();
            }

            $nextPlan = self::plan($nextPlanKey);
            $nextMonths = max(0, (int) ($lockedUser->api_next_plan_months ?? 0));
            if (!$nextPlan || $nextMonths <= 0) {
                $lockedUser->forceFill([
                    'api_next_plan' => null,
                    'api_next_plan_months' => 0,
                    'api_next_plan_price' => 0,
                    'api_next_plan_scheduled_at' => 0,
                ])->save();

                return $lockedUser->fresh();
            }

            $price = (int) ($lockedUser->api_next_plan_price ?? 0);
            if ($price <= 0) {
                $price = (int) (self::packagePrice($nextPlanKey, $nextMonths) ?? 0);
            }

            if ($price <= 0 || (int) ($lockedUser->amount ?? 0) < $price) {
                return $lockedUser->fresh();
            }

            $newTimeEnd = $now + (86400 * 30 * $nextMonths);
            $walletBefore = (int) ($lockedUser->amount ?? 0);
            $walletAfter = $walletBefore - $price;
            WalletLedger::ensureOpeningBalance((int) $lockedUser->id, $walletBefore);

            $lockedUser->forceFill([
                'amount' => $walletAfter,
                'time_end' => $newTimeEnd,
                'api_plan' => $nextPlanKey,
                'api_account_limit' => (int) $nextPlan['limit'],
                'api_plan_started_at' => $now,
                'api_plan_months' => $nextMonths,
                'api_plan_paid_amount' => $price,
                'api_next_plan' => null,
                'api_next_plan_months' => 0,
                'api_next_plan_price' => 0,
                'api_next_plan_scheduled_at' => 0,
            ])->save();

            WalletLedger::record(
                (int) $lockedUser->id,
                -abs((int) $price),
                'api_package_payment',
                WalletLedger::makeReference('api_package_scheduled', (int) $lockedUser->id),
                'Kích hoạt gói API kỳ sau: ' . $nextPlan['name'] . ' - ' . $nextMonths . ' tháng',
                (int) $lockedUser->id,
                $walletBefore,
                $walletAfter,
                [
                    'action' => 'scheduled_plan',
                    'plan' => $nextPlanKey,
                    'months' => $nextMonths,
                    'price' => $price,
                    'time_end' => $newTimeEnd,
                ]
            );

            DB::table('xlogs')->insert([
                'ip' => request()->ip(),
                'user' => $lockedUser->id,
                'log' => 'Kích hoạt gói API kỳ sau',
                'notes' => $nextPlan['name']
                    . ' - ' . $nextMonths . ' tháng, phí ' . $price
                    . ', hạn mới ' . date('H:i d/m/Y', $newTimeEnd)
                    . ', limit ' . (int) $nextPlan['limit'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $lockedUser->fresh();
        });

        return $freshUser ?: $user;
    }

    public static function durationLabel(int $months): string
    {
        if ($months % 12 === 0) {
            return ($months / 12) . ' năm';
        }

        return $months . ' tháng';
    }

    private static function discountDurations(int $monthlyPrice): array
    {
        return [
            1 => $monthlyPrice,
            2 => $monthlyPrice * 2,
            3 => $monthlyPrice * 3,
            6 => $monthlyPrice * 6,
            12 => (int) round($monthlyPrice * 12 * 0.9),
            24 => (int) round($monthlyPrice * 24 * 0.8),
        ];
    }
}
