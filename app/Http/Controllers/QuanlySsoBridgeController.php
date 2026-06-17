<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class QuanlySsoBridgeController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        if (!Auth::check()) {
            $request->session()->put('quanly_sso_bridge_intent', $request->fullUrl());

            return redirect()->route('auth-login-basic')
                ->with('info', 'Đăng nhập APIBank để tiếp tục sang quanly.3w.com.vn.');
        }

        $secret = trim((string) config('services.quanly_sso.secret', ''));
        if ($secret === '') {
            return redirect('/')->with('error', 'Chưa cấu hình APIBANK_SSO_SECRET.');
        }

        $callbackUrl = trim((string) config('services.quanly_sso.callback_url', ''));
        if ($callbackUrl === '') {
            return redirect('/')->with('error', 'Chưa cấu hình QUANLY_SSO_CALLBACK_URL.');
        }

        /** @var User $user */
        $user = Auth::user();
        $now = time();
        $payload = [
            'iss' => (string) config('services.quanly_sso.issuer', 'apibank.com.vn'),
            'aud' => (string) config('services.quanly_sso.audience', 'quanly.3w.com.vn'),
            'iat' => $now,
            'exp' => $now + 180,
            'nonce' => Str::random(32),
            'return_url' => $this->safeReturnUrl($request, $request->query('return_url')),
            'user' => [
                'id' => (int) $user->id,
                'email' => (string) ($user->email ?? ''),
                'name' => (string) ($user->name ?? ''),
                'display_name' => (string) (($user->display_name ?? '') ?: ($user->name ?? '')),
                'avatar' => $this->avatarUrl($user),
                'phone' => (string) ($user->phone ?? ''),
            ],
        ];

        $encoded = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
        $signature = hash_hmac('sha256', $encoded, $secret);
        $separator = str_contains($callbackUrl, '?') ? '&' : '?';

        return redirect()->away($callbackUrl . $separator . http_build_query([
            'payload' => $encoded,
            'signature' => $signature,
        ]));
    }

    private function safeReturnUrl(Request $request, mixed $returnUrl): string
    {
        $returnUrl = trim((string) $returnUrl);
        $origin = rtrim((string) config('services.quanly_sso.return_origin', 'https://quanly.3w.com.vn'), '/');

        if ($origin !== '' && str_starts_with($returnUrl, $origin . '/')) {
            return $returnUrl;
        }

        if (str_starts_with($returnUrl, '/') && !str_starts_with($returnUrl, '//')) {
            return $origin . $returnUrl;
        }

        return $origin !== '' ? $origin . '/' : 'https://quanly.3w.com.vn/';
    }

    private function avatarUrl(User $user): string
    {
        $avatar = trim((string) ($user->avatar ?? ''));
        if ($avatar === '') {
            return '';
        }

        if (str_starts_with($avatar, 'http://') || str_starts_with($avatar, 'https://')) {
            return $avatar;
        }

        if (str_starts_with($avatar, '/storage/')) {
            return asset(ltrim($avatar, '/'));
        }

        if (str_starts_with($avatar, 'storage/')) {
            return asset($avatar);
        }

        return asset('storage/' . ltrim($avatar, '/'));
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
