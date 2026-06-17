<?php

namespace App\Http\Controllers\authentications;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;

class ForgotPasswordBasic extends Controller
{
    // GET: Trang quên mật khẩu
    public function index()
    {
        return view('content.authentications.auth-forgot-password-basic');
    }

    // POST: Gửi link reset
    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        // Gửi mail reset password
        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with(['status' => __($status)]);
        }

        return back()->withErrors(['email' => __($status)]);
    }

    // GET: Hiển thị form reset password khi có token
    public function showResetForm($token)
    {
        return view('content.authentications.auth-reset-password-basic', [
            'token' => $token
        ]);
    }

    // POST: Xử lý thay đổi password
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'                 => 'required',
            'email'                 => 'required|email',
            'password'              => 'required|min:6|confirmed',
            'password_confirmation' => 'required|min:6',
        ]);

        // Tiến hành reset
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                // Đổi mật khẩu
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));
                $user->save();

                // Gửi event
                event(new PasswordReset($user));
            }
        );

        // Kiểm tra kết quả
        if ($status === Password::PASSWORD_RESET) {
            // Reset thành công
            return redirect()->route('auth-login-basic')->with('status', __($status));
        }

        return back()->withErrors(['email' => __($status)]);
    }
}
