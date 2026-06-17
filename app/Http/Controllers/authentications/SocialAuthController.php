<?php

namespace App\Http\Controllers\authentications;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserSocialAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use RuntimeException;
use Throwable;

class SocialAuthController extends Controller
{
    public function redirect(Request $request, string $provider): RedirectResponse
    {
        $provider = $this->normalizeProvider($provider);

        if (Auth::check()) {
            return redirect('/')->with('error', 'Bạn đang đăng nhập.');
        }

        try {
            $this->ensureProviderConfigured($provider);

            return Socialite::driver('google')
                ->setScopes(['openid', 'profile', 'email'])
                ->redirectUrl($this->callbackUrl())
                ->with(['include_granted_scopes' => 'false'])
                ->redirect();
        } catch (Throwable $e) {
            return redirect()
                ->route('auth-login-basic')
                ->with('error', 'Không thể chuyển hướng đăng nhập Google: ' . $e->getMessage());
        }
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $provider = $this->normalizeProvider($provider);

        try {
            $this->ensureProviderConfigured($provider);
            $googleUser = Socialite::driver('google')
                ->redirectUrl($this->callbackUrl())
                ->user();

            $payload = $this->googlePayload($googleUser);
            $user = $this->findOrCreateUserFromGoogle($request, $payload);

            Auth::login($user, true);
            $request->session()->regenerate();

            return redirect('/')
                ->with('success', 'Đăng nhập bằng Google thành công!');
        } catch (Throwable $e) {
            return redirect()
                ->route('auth-login-basic')
                ->with('error', 'Đăng nhập bằng Google thất bại: ' . $e->getMessage());
        }
    }

    private function googlePayload($googleUser): array
    {
        $rawProfile = is_array($googleUser->user ?? null) ? $googleUser->user : [];
        $providerId = (string) $googleUser->getId();
        if ($providerId === '') {
            throw new RuntimeException('Google không trả về mã định danh tài khoản.');
        }

        $emailVerified = Arr::get($rawProfile, 'email_verified', Arr::get($rawProfile, 'verified_email', true));
        $expiresIn = (int) ($googleUser->expiresIn ?? 0);

        return [
            'provider_id' => $providerId,
            'email' => $this->normalizeEmail($googleUser->getEmail()),
            'display_name' => trim((string) ($googleUser->getName() ?: $googleUser->getNickname())),
            'avatar' => $googleUser->getAvatar(),
            'access_token' => $googleUser->token ?? null,
            'refresh_token' => $googleUser->refreshToken ?? null,
            'token_expires_at' => $expiresIn > 0 ? now()->addSeconds($expiresIn) : null,
            'raw_profile' => $rawProfile,
            'email_verified' => filter_var($emailVerified, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== false,
        ];
    }

    private function findOrCreateUserFromGoogle(Request $request, array $payload): User
    {
        $providerId = trim((string) $payload['provider_id']);
        $email = $this->normalizeEmail($payload['email'] ?? null);
        $displayName = Str::limit(trim((string) ($payload['display_name'] ?? '')), 50, '');
        $avatar = trim((string) ($payload['avatar'] ?? '')) ?: null;
        $emailVerified = (bool) ($payload['email_verified'] ?? false);

        return DB::transaction(function () use ($request, $providerId, $payload, $email, $displayName, $avatar, $emailVerified) {
            $socialAccount = UserSocialAccount::with('user')
                ->where('provider', 'google')
                ->where('provider_id', $providerId)
                ->lockForUpdate()
                ->first();

            $user = $socialAccount?->user;

            if (!$user && $email) {
                $user = User::where('email', $email)->lockForUpdate()->first();
            }

            if (!$user) {
                $user = User::create([
                    'name' => $this->uniqueUsername($displayName ?: 'google', $providerId),
                    'email' => $this->uniqueEmail($email, $providerId),
                    'display_name' => $displayName ?: 'Google User',
                    'avatar' => $avatar,
                    'password' => Str::random(64),
                    'role' => $emailVerified ? 3 : 9,
                    'ip' => $request->ip(),
                    'device' => Str::limit((string) $request->userAgent(), 250, ''),
                    'level' => 0,
                    'amount' => 0,
                    'total_paid' => 0,
                    'banned' => 0,
                    'token' => md5(Str::random(32) . microtime(true)),
                    'email_verified_at' => $emailVerified ? now() : null,
                ]);
            } else {
                $updates = [];
                if ($displayName !== '' && empty($user->display_name)) {
                    $updates['display_name'] = $displayName;
                }
                if ($avatar && empty($user->avatar)) {
                    $updates['avatar'] = $avatar;
                }
                if ($emailVerified && !$user->hasVerifiedEmail()) {
                    $updates['email_verified_at'] = now();
                    if (in_array((int) ($user->role ?? 0), [0, 9], true)) {
                        $updates['role'] = 3;
                    }
                }
                if (!empty($updates)) {
                    $user->forceFill($updates)->save();
                }
            }

            if (empty($user->token)) {
                $user->forceFill(['token' => md5(Str::random(32) . microtime(true))])->save();
            }

            UserSocialAccount::updateOrCreate(
                [
                    'provider' => 'google',
                    'provider_id' => $providerId,
                ],
                [
                    'user_id' => $user->id,
                    'email' => $email,
                    'display_name' => $displayName ?: null,
                    'avatar' => $avatar,
                    'access_token' => $payload['access_token'] ?? null,
                    'refresh_token' => $payload['refresh_token'] ?? null,
                    'token_expires_at' => $payload['token_expires_at'] ?? null,
                    'raw_profile' => $payload['raw_profile'] ?? null,
                ]
            );

            return $user->fresh();
        });
    }

    private function uniqueUsername(string $source, string $providerId): string
    {
        $base = Str::lower(Str::ascii($source));
        $base = preg_replace('/[^a-z0-9]/', '', $base) ?: 'google' . substr(md5($providerId), 0, 10);
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
        if ($email) {
            return $email;
        }

        $safeId = Str::lower(preg_replace('/[^a-z0-9]/i', '', $providerId) ?: substr(md5($providerId), 0, 16));
        $base = substr('google_' . $safeId, 0, 70);
        $candidate = $base . '@social.local';
        $counter = 1;

        while (User::where('email', $candidate)->exists()) {
            $candidate = substr($base, 0, 64) . '_' . $counter . '@social.local';
            $counter++;
        }

        return $candidate;
    }

    private function normalizeEmail(?string $email): ?string
    {
        $email = Str::lower(trim((string) $email));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private function normalizeProvider(string $provider): string
    {
        $provider = Str::lower(trim($provider));
        if ($provider !== 'google') {
            abort(404);
        }

        return $provider;
    }

    private function ensureProviderConfigured(string $provider): void
    {
        if (config('services.google.client_id') === '' || config('services.google.client_secret') === '') {
            throw new RuntimeException('Chưa cấu hình GOOGLE_CLIENT_ID hoặc GOOGLE_CLIENT_SECRET trong .env.');
        }
    }

    private function callbackUrl(): string
    {
        $redirect = (string) config('services.google.redirect');
        if ($redirect === '') {
            return route('auth.social.callback', ['provider' => 'google']);
        }

        if (str_starts_with($redirect, '/')) {
            return url($redirect);
        }

        return $redirect;
    }
}
