<?php

namespace App\Services;

use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Http;

class WebhookDeliveryService
{
    public function deliverDue(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $deliveries = WebhookDelivery::query()
            ->whereNull('delivered_at')
            ->whereNull('failed_at')
            ->where(function ($query) {
                $query->whereNull('next_attempt_at')
                    ->orWhere('next_attempt_at', '<=', now());
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $ok = 0;
        $failed = 0;
        $retry = 0;

        foreach ($deliveries as $delivery) {
            $result = $this->deliver($delivery);
            if ($result === 'ok') {
                $ok++;
            } elseif ($result === 'retry') {
                $retry++;
            } else {
                $failed++;
            }
        }

        return ['ok' => $ok, 'retry' => $retry, 'failed' => $failed, 'total' => $deliveries->count()];
    }

    public function deliver(WebhookDelivery $delivery): string
    {
        $payload = $delivery->payload ?: [];
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}';
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, (string) $delivery->secret);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-APIBank-Timestamp' => $timestamp,
                'X-APIBank-Signature' => $signature,
                'X-APIBank-Event-Id' => (string) $delivery->event_id,
            ])
                ->timeout(12)
                ->send('POST', (string) $delivery->target_url, ['body' => $body]);

            $delivery->attempts = (int) $delivery->attempts + 1;
            $delivery->response_status = $response->status();
            $delivery->response_body = mb_substr((string) $response->body(), 0, 5000);

            if ($response->successful()) {
                $delivery->delivered_at = now();
                $delivery->last_error = null;
                $delivery->save();
                $delivery->endpoint?->forceFill(['last_success_at' => now(), 'last_error' => null])->save();

                return 'ok';
            }

            $delivery->last_error = 'HTTP ' . $response->status();
        } catch (\Throwable $e) {
            $delivery->attempts = (int) $delivery->attempts + 1;
            $delivery->last_error = $e->getMessage();
        }

        if ((int) $delivery->attempts >= (int) $delivery->max_attempts) {
            $delivery->failed_at = now();
            $delivery->next_attempt_at = null;
            $delivery->save();
            $delivery->endpoint?->forceFill(['last_failure_at' => now(), 'last_error' => $delivery->last_error])->save();

            return 'failed';
        }

        $delaySeconds = min(3600, 2 ** max(1, (int) $delivery->attempts) * 30);
        $delivery->next_attempt_at = now()->addSeconds($delaySeconds);
        $delivery->save();
        $delivery->endpoint?->forceFill(['last_failure_at' => now(), 'last_error' => $delivery->last_error])->save();

        return 'retry';
    }
}
