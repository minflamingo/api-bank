<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WebhookEndpoint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class QuanlyAccountLinkController extends Controller
{
    private const BANKS = ['acb', 'vcb', 'vpbank', 'techcombank', 'mbbank'];

    public function connect(Request $request): RedirectResponse
    {
        try {
            $payload = $this->verifiedPayload($request);
            $user = $this->findOrCreateLinkedUser($request, $payload);
            $webhookReady = $this->ensureQuanlyWebhookEndpoint($user);

            Auth::login($user, true);
            $request->session()->regenerate();

            return redirect()
                ->to(route('bank.accounts.create', ['bank' => $this->payloadBank($payload)]))
                ->with('success', $webhookReady
                    ? 'Đã liên kết tài khoản Quanly với APIBank và tạo webhook Quanly.'
                    : 'Đã liên kết tài khoản Quanly với APIBank.');
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('auth-login-basic')
                ->with('error', 'Không thể liên kết từ Quanly: ' . $e->getMessage());
        }
    }

    private function verifiedPayload(Request $request): array
    {
        $secret = trim((string) config('services.quanly_account_link.secret', ''));
        if ($secret === '') {
            throw new RuntimeException('Chưa cấu hình QUANLY_ACCOUNT_LINK_SECRET.');
        }

        $encoded = trim((string) $request->query('payload', ''));
        $signature = trim((string) $request->query('signature', ''));
        if ($encoded === '' || $signature === '') {
            throw new RuntimeException('Thiếu payload hoặc chữ ký liên kết.');
        }

        $expected = hash_hmac('sha256', $encoded, $secret);
        if (!hash_equals($expected, $signature)) {
            throw new RuntimeException('Chữ ký liên kết không hợp lệ.');
        }

        $json = $this->base64UrlDecode($encoded);
        $payload = json_decode($json, true);
        if (!is_array($payload)) {
            throw new RuntimeException('Payload liên kết không hợp lệ.');
        }

        $now = time();
        if ((int) ($payload['exp'] ?? 0) < $now) {
            throw new RuntimeException('Link liên kết đã hết hạn.');
        }

        if ((int) ($payload['iat'] ?? 0) > $now + 60) {
            throw new RuntimeException('Thời gian link liên kết không hợp lệ.');
        }

        if ((string) ($payload['iss'] ?? '') !== (string) config('services.quanly_account_link.issuer', 'quanly.3w.com.vn')) {
            throw new RuntimeException('Nguồn liên kết không hợp lệ.');
        }

        if ((string) ($payload['aud'] ?? '') !== (string) config('services.quanly_account_link.audience', 'apibank.com.vn')) {
            throw new RuntimeException('Đích liên kết không hợp lệ.');
        }

        $quanlyUserId = (int) data_get($payload, 'user.id');
        if ($quanlyUserId <= 0) {
            throw new RuntimeException('Payload thiếu mã user Quanly.');
        }

        return $payload;
    }

    private function findOrCreateLinkedUser(Request $request, array $payload): User
    {
        $quanlyUserId = (int) data_get($payload, 'user.id');
        $quanlyTenantId = (int) data_get($payload, 'user.tenant_id', 0);
        $email = $this->normalizeEmail(data_get($payload, 'user.email'));
        $phone = trim((string) data_get($payload, 'user.phone', ''));
        $displayName = Str::limit(trim((string) data_get($payload, 'user.display_name', '')), 80, '');
        $avatar = trim((string) data_get($payload, 'user.avatar', '')) ?: null;

        return DB::transaction(function () use ($request, $payload, $quanlyUserId, $quanlyTenantId, $email, $phone, $displayName, $avatar) {
            $link = DB::table('quanly_account_links')
                ->where('quanly_user_id', $quanlyUserId)
                ->lockForUpdate()
                ->first();

            $user = $link ? User::whereKey($link->apibank_user_id)->lockForUpdate()->first() : null;

            if (!$user && $email) {
                $user = User::where('email', $email)->lockForUpdate()->first();
            }

            if (!$user) {
                $providerId = (string) $quanlyUserId;
                $user = User::create([
                    'name' => $this->uniqueUsername((string) (data_get($payload, 'user.name') ?: $displayName ?: 'quanly'), $providerId),
                    'email' => $this->uniqueEmail($email, $providerId),
                    'display_name' => $displayName ?: 'Quanly User #' . $quanlyUserId,
                    'phone' => $phone ?: null,
                    'avatar' => $avatar,
                    'password' => Str::random(64),
                    'role' => 3,
                    'ip' => $request->ip(),
                    'device' => Str::limit((string) $request->userAgent(), 250, ''),
                    'level' => 0,
                    'amount' => 0,
                    'total_paid' => 0,
                    'banned' => 0,
                    'token' => md5(Str::random(32) . microtime(true)),
                ]);
            }

            $updates = [];
            if ($displayName !== '' && empty($user->display_name)) {
                $updates['display_name'] = $displayName;
            }
            if ($phone !== '' && empty($user->phone)) {
                $updates['phone'] = $phone;
            }
            if ($avatar && empty($user->avatar)) {
                $updates['avatar'] = $avatar;
            }
            if (!$user->hasVerifiedEmail()) {
                $updates['email_verified_at'] = now();
            }
            if (in_array((int) ($user->role ?? 0), [0, 9], true)) {
                $updates['role'] = 3;
            }
            if (empty($user->token)) {
                $updates['token'] = md5(Str::random(32) . microtime(true));
            }
            if (!empty($updates)) {
                $user->forceFill($updates)->save();
            }

            DB::table('quanly_account_links')->updateOrInsert(
                ['quanly_user_id' => $quanlyUserId],
                [
                    'quanly_tenant_id' => $quanlyTenantId > 0 ? $quanlyTenantId : null,
                    'apibank_user_id' => $user->id,
                    'email' => $email,
                    'phone' => $phone !== '' ? $phone : null,
                    'linked_at' => now(),
                    'updated_at' => now(),
                    'created_at' => $link?->created_at ?? now(),
                ]
            );

            return $user->fresh();
        });
    }

    private function ensureQuanlyWebhookEndpoint(User $user): bool
    {
        $url = trim((string) config('services.webhook_integrations.quanly_url', 'https://quanly.3w.com.vn/webhooks/apibank/transactions'));
        $secret = trim((string) config('services.webhook_integrations.quanly_secret', ''));
        if ((int) $user->id <= 0 || $url === '' || $secret === '') {
            return false;
        }

        $requiredEvents = [
            'transaction.created',
            'transaction.updated',
        ];
        $endpoint = WebhookEndpoint::query()
            ->where('user_id', (int) $user->id)
            ->orderBy('id')
            ->get()
            ->first(fn (WebhookEndpoint $endpoint) => $this->sameWebhookTarget((string) $endpoint->url, $url));

        if (!$endpoint) {
            $endpoint = new WebhookEndpoint([
                'user_id' => (int) $user->id,
                'url' => $url,
            ]);
        }

        $endpoint->forceFill([
            'name' => $endpoint->name ?: 'Quanly.3W',
            'url' => $url,
            'events' => $requiredEvents,
            'secret' => $secret,
            'is_active' => true,
            'last_error' => null,
        ])->save();

        return true;
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

    private function payloadBank(array $payload): string
    {
        $bank = Str::lower(trim((string) ($payload['bank'] ?? 'acb')));

        return in_array($bank, self::BANKS, true) ? $bank : 'acb';
    }

    private function normalizeEmail(mixed $email): ?string
    {
        $email = Str::lower(trim((string) $email));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private function uniqueUsername(string $source, string $providerId): string
    {
        $base = Str::lower(Str::ascii($source));
        $base = preg_replace('/[^a-z0-9]/', '', $base) ?: 'quanly' . substr(md5($providerId), 0, 10);
        $base = substr($base, 0, 42);
        $candidate = $base;
        $counter = 1;

        while (User::where('name', $candidate)->exists()) {
            $suffix = (string) $counter;
            $candidate = substr($base, 0, max(3, 42 - strlen($suffix))) . $suffix;
            $counter++;
        }

        return $candidate;
    }

    private function uniqueEmail(?string $email, string $providerId): string
    {
        if ($email && !User::where('email', $email)->exists()) {
            return $email;
        }

        if ($email) {
            return $email;
        }

        $safeId = preg_replace('/[^0-9a-z]/i', '', $providerId) ?: substr(md5($providerId), 0, 16);
        $base = substr('quanly_' . $safeId, 0, 70);
        $candidate = $base . '@users.quanly.3w.com.vn';
        $counter = 1;

        while (User::where('email', $candidate)->exists()) {
            $candidate = substr($base, 0, 64) . '_' . $counter . '@users.quanly.3w.com.vn';
            $counter++;
        }

        return $candidate;
    }

    private function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;
        if ($remainder) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new RuntimeException('Không đọc được payload liên kết.');
        }

        return $decoded;
    }
}
