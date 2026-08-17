<?php

namespace App\Notifications;

use App\Services\Auth\EmailVerificationCodeService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyEmailNotification extends Notification
{
    use Queueable;

    public function __construct(public string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $expireMinutes = app(EmailVerificationCodeService::class)->expireMinutes();

        return (new MailMessage)
            ->subject('Your verification code — '.config('branding.short_name', config('app.name')))
            ->view('mail.email-verification', [
                'user' => $notifiable,
                'code' => $this->code,
                'expireMinutes' => $expireMinutes,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
