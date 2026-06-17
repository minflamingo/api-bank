<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;
use Carbon\Carbon;

class CustomVerifyEmail extends Notification
{
    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        // Tạo URL xác thực (chữ ký tạm thời, sống 60 phút)
        $verificationUrl = $this->verificationUrl($notifiable);

        // Logo & link login
        // Hoặc bạn có thể gán thẳng: $logo = 'https://apibank.com.vn/img/logo.png';
        $logo = asset('img/logo.png');
        $loginUrl = url('/login');

        return (new MailMessage)
            ->subject('Xác thực email')
            // Gọi view Blade 'emails.custom_verify_email'
            ->view('emails.custom_verify_email', [
                'verificationUrl' => $verificationUrl,
                'logo'            => $logo,
                'loginUrl'        => $loginUrl,
            ]);
    }

    /**
     * Tạo URL xác thực, sống 60 phút
     */
    protected function verificationUrl($notifiable)
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(60),
            [
                'id'   => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    }
}
