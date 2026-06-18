<?php

namespace App\Http\Controllers;

use App\Models\WebhookEndpoint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            'secret' => $this->secretForWebhookUrl($data['url']),
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
            'secret' => $this->secretForWebhookUrl($data['url'], $webhook->secret ?: null),
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

    private function secretForWebhookUrl(string $url, ?string $existingSecret = null): string
    {
        $quanlySecret = trim((string) config('services.webhook_integrations.quanly_secret', ''));
        $quanlyUrl = trim((string) config('services.webhook_integrations.quanly_url', 'https://quanly.3w.com.vn/webhooks/apibank/transactions'));

        if ($quanlySecret !== '' && $this->sameWebhookTarget($url, $quanlyUrl)) {
            return $quanlySecret;
        }

        return $existingSecret ?: $this->newSecret();
    }

    private function sameWebhookTarget(string $left, string $right): bool
    {
        $normalize = static function (string $value): ?string {
            $parts = parse_url(trim($value));
            $host = strtolower((string) ($parts['host'] ?? ''));
            $path = '/' . trim((string) ($parts['path'] ?? ''), '/');

            return $host !== '' ? ($host . $path) : null;
        };

        return $normalize($left) !== null && $normalize($left) === $normalize($right);
    }
}
