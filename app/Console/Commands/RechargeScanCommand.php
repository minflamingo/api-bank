<?php

namespace App\Console\Commands;

use App\Http\Controllers\CronController;
use App\Models\Bank;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RechargeScanCommand extends Command
{
    protected $signature = 'recharge:scan {--loop} {--sleep=2}';

    protected $description = 'Scan the active recharge receiver bank and credit matched customer deposits';

    public function handle(): int
    {
        do {
            $message = $this->withLock(fn () => $this->scanActiveReceiver());
            $this->line(json_encode([
                'time' => now()->toDateTimeString(),
                'message' => $message,
            ], JSON_UNESCAPED_UNICODE));

            if (!$this->option('loop')) {
                break;
            }

            sleep(max(1, min(60, (int) $this->option('sleep'))));
        } while (true);

        return self::SUCCESS;
    }

    private function scanActiveReceiver(): string
    {
        $bank = Bank::whereIn('receiver_bank_type', ['ACB', 'VCB', 'VPBANK', 'TECHCOMBANK', 'MBBANK'])
            ->whereNotNull('receiver_account_id')
            ->orderBy('id')
            ->first();

        if (!$bank) {
            return 'Chưa chọn tài khoản nhận nạp active.';
        }

        $request = Request::create('/internal/recharge-scan', 'GET');
        $cron = app(CronController::class);

        return (string) match ((string) $bank->receiver_bank_type) {
            'VCB' => $cron->cronNapVCB($request),
            'VPBANK' => $cron->cronNapVPBANK($request),
            'TECHCOMBANK' => $cron->cronNapTECHCOMBANK($request),
            'MBBANK' => $cron->cronNapMBBANK($request),
            default => $cron->cronNapACB($request),
        };
    }

    private function withLock(callable $callback): string
    {
        $lockName = 'apibank_recharge_scan';
        $locked = false;

        try {
            $row = DB::selectOne('SELECT GET_LOCK(?, 0) as locked', [$lockName]);
            $locked = (int) ($row->locked ?? 0) === 1;
            if (!$locked) {
                return 'Đang có tiến trình đối soát khác chạy, bỏ qua lượt này.';
            }

            return (string) $callback();
        } catch (\Throwable $e) {
            report($e);

            return 'Lỗi đối soát: ' . $e->getMessage();
        } finally {
            if ($locked) {
                DB::select('SELECT RELEASE_LOCK(?)', [$lockName]);
            }
        }
    }
}
