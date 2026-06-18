<?php

namespace App\Console\Commands;

use App\Services\BankRealtimeScannerService;
use Illuminate\Console\Command;

class BankScanCommand extends Command
{
    protected $signature = 'bank:scan {--bank=} {--loop} {--sleep=5} {--batch=10} {--limit=100}';

    protected $description = 'Scan real bank accounts into SQL/Redis cache and enqueue webhook events';

    public function handle(BankRealtimeScannerService $scanner): int
    {
        do {
            $summary = $scanner->runOnce(
                onlyBank: $this->option('bank') ?: null,
                batch: (int) $this->option('batch'),
                limit: (int) $this->option('limit')
            );

            $this->line(json_encode(['time' => now()->toDateTimeString()] + $summary, JSON_UNESCAPED_UNICODE));

            if (!$this->option('loop')) {
                break;
            }

            sleep(max(1, min(60, (int) $this->option('sleep'))));
        } while (true);

        return self::SUCCESS;
    }
}
