<?php

namespace App\Notifications;

use App\Support\EmailVerificationUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyEmailNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $verificationUrl = EmailVerificationUrl::forUser($notifiable);

        return (new MailMessage)
            ->subject('Verify your email — '.config('branding.short_name', config('app.name')))
            ->view('mail.email-verification', [
                'user' => $notifiable,
                'url' => $verificationUrl,
                'expireMinutes' => EmailVerificationUrl::expireMinutes(),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
