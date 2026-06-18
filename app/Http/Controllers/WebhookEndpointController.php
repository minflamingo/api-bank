<?php

namespace App\Http\Controllers;

use App\Models\WebhookEndpoint;
use App\Models\WebhookDelivery;
use App\Models\QuanlyWebhookSetting;
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
            'defaultQuanlySecret' => $this->newSecret(),
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

    public function updateQuanly(Request $request)
    {
        $userId = (int) Auth::id();
        $existing = QuanlyWebhookSetting::query()->where('user_id', $userId)->first();
        $active = $request->boolean('quanly_is_active');

        $data = $request->validate([
            'quanly_url' => [$active ? 'required' : 'nullable', 'url', 'max:2048', 'regex:/^https?:\/\//i'],
            'quanly_secret' => [$active ? 'required' : 'nullable', 'string', 'min:16', 'max:191'],
            'quanly_events' => [$active ? 'required' : 'nullable', 'array', 'min:1'],
            'quanly_events.*' => ['required', Rule::in(WebhookEndpoint::EVENTS)],
        ], [
            'quanly_url.required' => 'Vui lòng nhập URL receiver Quanly.',
            'quanly_secret.required' => 'Vui lòng nhập secret HMAC cho Quanly.',
            'quanly_events.required' => 'Vui lòng chọn ít nhất một event gửi sang Quanly.',
            'quanly_events.*.in' => 'Event Quanly webhook không hợp lệ.',
        ]);

        $events = array_values($data['quanly_events'] ?? ($existing?->events ?: [
            'transaction.created',
            'transaction.updated',
            'balance.updated',
            'account.session_expired',
        ]));

        QuanlyWebhookSetting::query()->updateOrCreate(
            ['user_id' => $userId],
            [
                'url' => trim((string) ($data['quanly_url'] ?? '')) ?: null,
                'secret' => trim((string) ($data['quanly_secret'] ?? '')) ?: ($existing?->secret ?: $this->newSecret()),
                'events' => $events,
                'is_active' => $active,
            ]
        );

        return redirect()->route('client.webhooks.index')->with('success', 'Đã lưu cấu hình liên kết Quanly cho tài khoản này.');
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
        $setting = QuanlyWebhookSetting::query()
            ->where('user_id', $userId)
            ->first();
        $url = trim((string) ($setting?->url ?? ''));
        $secretConfigured = trim((string) ($setting?->secret ?? '')) !== '';
        $events = array_values($setting?->events ?: []);

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
            'setting' => $setting,
            'enabled' => $setting?->isUsable() ?? false,
            'url' => $url,
            'secret' => $setting?->secret,
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
