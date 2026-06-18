<?php

namespace App\Services;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Str;

class WebhookEventService
{
    public function dispatch(int $userId, string $event, array $payload): int
    {
        if ($userId <= 0 || !in_array($event, WebhookEndpoint::EVENTS, true)) {
            return 0;
        }

        $count = 0;
        $basePayload = [
            'event' => $event,
            'created_at' => now()->toISOString(),
            'data' => $payload,
        ];

        $endpoints = WebhookEndpoint::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->get()
            ->filter(fn (WebhookEndpoint $endpoint) => $endpoint->accepts($event));

        foreach ($endpoints as $endpoint) {
            $this->createDelivery(
                endpointId: (int) $endpoint->id,
                userId: $userId,
                event: $event,
                url: (string) $endpoint->url,
                secret: (string) $endpoint->secret,
                payload: $basePayload
            );
            $count++;
        }

        $internalUrl = trim((string) config('services.quanly_webhook.url', ''));
        $internalSecret = trim((string) config('services.quanly_webhook.secret', ''));
        $internalEvents = array_filter(array_map('trim', explode(',', (string) config('services.quanly_webhook.events', 'transaction.created,transaction.updated,balance.updated,account.session_expired'))));
        if ($internalUrl !== '' && $internalSecret !== '' && in_array($event, $internalEvents, true)) {
            $this->createDelivery(
                endpointId: null,
                userId: $userId,
                event: $event,
                url: $internalUrl,
                secret: $internalSecret,
                payload: $basePayload
            );
            $count++;
        }

        return $count;
    }

    private function createDelivery(?int $endpointId, int $userId, string $event, string $url, string $secret, array $payload): void
    {
        $eventId = 'evt_' . (string) Str::uuid();
        $payload['event_id'] = $eventId;

        WebhookDelivery::query()->create([
            'webhook_endpoint_id' => $endpointId,
            'user_id' => $userId,
            'event_id' => $eventId,
            'event' => $event,
            'target_url' => $url,
            'secret' => $secret,
            'payload' => $payload,
            'attempts' => 0,
            'max_attempts' => 8,
            'next_attempt_at' => now(),
        ]);
    }
}
