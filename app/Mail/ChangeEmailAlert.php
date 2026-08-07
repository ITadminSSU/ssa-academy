<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ChangeEmailAlert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $newEmail,
    ) {}

    public function envelope(): Envelope
    {
        $appName = config('branding.short_name', config('app.name'));

        return new Envelope(
            subject: "Email change requested for your {$appName} account",
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.email-change-alert',
            text: 'mail.email-change-alert-text',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
