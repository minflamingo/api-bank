<?php

namespace App\Http\Controllers\authentications;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class LoginBasic extends Controller
{
    public function index()
    {
        return view('content.authentications.auth', [
            'mode' => 'login',
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email_username' => 'required',
            'password'       => 'required|min:6',
        ], [
            'email_username.required' => 'Vui lòng nhập email, tên đăng nhập hoặc số điện thoại.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
        ]);

        $emailUsername = trim((string) $request->email_username);
        $user = $this->findUserByLoginIdentifier($emailUsername);

        if (!$user) {
            return back()
                ->withErrors(['email_username' => 'Tài khoản không tồn tại.'])
                ->withInput(['email_username' => $emailUsername]);
        }

        if (!Hash::check($request->password, $user->password)) {
            return back()
                ->withErrors(['password' => 'Mật khẩu không chính xác.'])
                ->withInput(['email_username' => $emailUsername]);
        }

        $remember = $request->boolean('remember');
        Auth::login($user, $remember);

        $request->session()->regenerate();
        $this->writeAuthLog($request, $user, 'Đăng nhập');

        if ($redirect = $this->bridgeIntentRedirect($request)) {
            return $redirect;
        }

        return redirect('/')
               ->with('success', 'Đăng nhập thành công!');
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        if ($user) {
            $this->writeAuthLog($request, $user, 'Đăng xuất');
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
               ->route('auth-login-basic')
               ->with('success', 'Đã đăng xuất!');
    }

    private function findUserByLoginIdentifier(string $identifier): ?User
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return User::where('email', strtolower($identifier))->first();
        }

        $digits = preg_replace('/\D+/', '', $identifier);
        if ($digits !== '') {
            $phoneCandidates = array_values(array_unique(array_filter([
                $identifier,
                $digits,
                str_starts_with($digits, '84') ? '0' . substr($digits, 2) : null,
                str_starts_with($digits, '0') ? '84' . substr($digits, 1) : null,
            ])));

            $phoneUser = User::whereIn('phone', $phoneCandidates)->first();
            if ($phoneUser) {
                return $phoneUser;
            }
        }

        return User::where('name', $identifier)->first();
    }

    private function writeAuthLog(Request $request, User $user, string $action): void
    {
        try {
            if ($action === 'Đăng nhập') {
                $user->forceFill([
                    'ip' => $request->ip(),
                    'last_activity' => now()->toDateString(),
                ])->save();
            }

        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function bridgeIntentRedirect(Request $request)
    {
        $intent = trim((string) $request->session()->pull('quanly_sso_bridge_intent', ''));
        if ($intent === '') {
            return null;
        }

        $bridgeUrl = url('/auth/bridge/quanly');
        if (str_starts_with($intent, $bridgeUrl)) {
            return redirect($intent);
        }

        return null;
    }
}
