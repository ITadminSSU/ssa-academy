<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ChangeEmailVerification extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $app;
    public $verificationUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, $app, $verificationUrl)
    {
        $this->user = $user;
        $this->app = $app;
        $this->verificationUrl = $verificationUrl;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $appName = config('branding.short_name', config('app.name'));

        return new Envelope(
            subject: "Confirm your new email for {$appName}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            html: 'mail.email-change-verification',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
