<?php

namespace App\Http\Controllers;

use App\Models\WebhookEndpoint;
use App\Models\WebhookDelivery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WebhookEndpointController extends Controller
{
    public function index()
    {
        $endpoints = WebhookEndpoint::query()
            ->where('user_id', Auth::id())
            ->orderByDesc('id')
            ->paginate(20);

        return view('webhooks.index', [
            'endpoints' => $endpoints,
            'events' => WebhookEndpoint::EVENTS,
            'defaultSecret' => $this->newSecret(),
            'quanlyIntegration' => $this->quanlyIntegrationStatus(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        WebhookEndpoint::query()->create([
            'user_id' => Auth::id(),
            'name' => $data['name'],
            'url' => $data['url'],
            'events' => array_values($data['events']),
            'secret' => trim((string) ($data['secret'] ?? '')) ?: $this->newSecret(),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('client.webhooks.index')->with('success', 'Đã thêm webhook endpoint.');
    }

    public function update(Request $request, WebhookEndpoint $webhook)
    {
        abort_unless((int) $webhook->user_id === (int) Auth::id(), 403);
        $data = $this->validated($request);

        $webhook->forceFill([
            'name' => $data['name'],
            'url' => $data['url'],
            'events' => array_values($data['events']),
            'secret' => trim((string) ($data['secret'] ?? '')) ?: $webhook->secret,
            'is_active' => $request->boolean('is_active'),
        ])->save();

        return redirect()->route('client.webhooks.index')->with('success', 'Đã cập nhật webhook endpoint.');
    }

    public function destroy(WebhookEndpoint $webhook)
    {
        abort_unless((int) $webhook->user_id === (int) Auth::id(), 403);
        $webhook->delete();

        return redirect()->route('client.webhooks.index')->with('success', 'Đã xoá webhook endpoint.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'url' => ['required', 'url', 'max:2048', 'regex:/^https?:\/\//i'],
            'secret' => ['nullable', 'string', 'min:16', 'max:191'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['required', Rule::in(WebhookEndpoint::EVENTS)],
        ], [
            'events.required' => 'Vui lòng chọn ít nhất một event.',
            'events.*.in' => 'Event webhook không hợp lệ.',
        ]);
    }

    private function newSecret(): string
    {
        return 'whsec_' . Str::random(48);
    }

    private function quanlyIntegrationStatus(): array
    {
        $userId = (int) Auth::id();
        $url = trim((string) config('services.quanly_webhook.url', ''));
        $secretConfigured = trim((string) config('services.quanly_webhook.secret', '')) !== '';
        $events = array_values(array_filter(array_map('trim', explode(',', (string) config(
            'services.quanly_webhook.events',
            'transaction.created,transaction.updated,balance.updated,account.session_expired'
        )))));

        $deliveryQuery = WebhookDelivery::query()
            ->whereNull('webhook_endpoint_id')
            ->where('user_id', $userId);

        $link = null;
        $linksCount = 0;
        if (Schema::hasTable('quanly_account_links')) {
            $linksCount = (int) DB::table('quanly_account_links')
                ->where('apibank_user_id', $userId)
                ->count();

            $link = DB::table('quanly_account_links')
                ->where('apibank_user_id', $userId)
                ->orderByDesc('id')
                ->first();
        }

        $lastDelivery = (clone $deliveryQuery)
            ->select([
                'event',
                'event_id',
                'target_url',
                'attempts',
                'response_status',
                'delivered_at',
                'failed_at',
                'last_error',
                'created_at',
                'updated_at',
            ])
            ->orderByDesc('id')
            ->first();

        return [
            'enabled' => $url !== '' && $secretConfigured,
            'url' => $url,
            'secret_configured' => $secretConfigured,
            'events' => $events,
            'links_count' => $linksCount,
            'link' => $link,
            'deliveries_total' => (clone $deliveryQuery)->count(),
            'deliveries_delivered' => (clone $deliveryQuery)->whereNotNull('delivered_at')->count(),
            'deliveries_failed' => (clone $deliveryQuery)->whereNotNull('failed_at')->count(),
            'deliveries_pending' => (clone $deliveryQuery)->whereNull('delivered_at')->whereNull('failed_at')->count(),
            'last_delivery' => $lastDelivery,
        ];
    }
}
