<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class CustomResetPassword extends BaseResetPassword
{
    public function toMail($notifiable)
    {
        $resetUrl = $this->resetUrl($notifiable);

        // Các đường dẫn/logo/link bạn muốn truyền sang template
        $logo = asset('img/logo.png');
        $loginUrl = url('/login');

        return (new MailMessage)
            ->subject('Yêu cầu kích hoạt tài khoản - API Bank')
            // Thay vì ->line(), ta dùng ->view() để render Blade
            ->view('emails.custom_reset_password', [
                // Truyền dữ liệu để Blade hiển thị
                'resetUrl' => $resetUrl,
                'logo'     => $logo,
                'loginUrl' => $loginUrl,
            ]);
    }

    /**
     * Tạo URL reset/kích hoạt
     */
    protected function resetUrl($notifiable)
    {
        return url(route('auth-reset-password-basic', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));
    }
}
