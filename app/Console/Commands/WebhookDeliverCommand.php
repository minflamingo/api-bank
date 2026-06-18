<?php

namespace App\Console\Commands;

use App\Services\WebhookDeliveryService;
use Illuminate\Console\Command;

class WebhookDeliverCommand extends Command
{
    protected $signature = 'webhooks:deliver {--loop} {--sleep=5} {--limit=100}';

    protected $description = 'Deliver APIBank webhook events with retry/backoff';

    public function handle(WebhookDeliveryService $deliveries): int
    {
        do {
            $summary = $deliveries->deliverDue((int) $this->option('limit'));
            $this->line(json_encode(['time' => now()->toDateTimeString()] + $summary, JSON_UNESCAPED_UNICODE));

            if (!$this->option('loop')) {
                break;
            }

            sleep(max(1, min(60, (int) $this->option('sleep'))));
        } while (true);

        return self::SUCCESS;
    }
}
