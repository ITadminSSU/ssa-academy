<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LaunchOfferStudentMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<string>  $paragraphs
     * @param  list<string>  $bullets
     */
    public function __construct(
        public string $emailSubject,
        public string $greeting,
        public array $paragraphs,
        public array $bullets = [],
        public ?string $ctaLabel = null,
        public ?string $ctaUrl = null,
        public ?string $closingNote = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->emailSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.launch-offer-student',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
