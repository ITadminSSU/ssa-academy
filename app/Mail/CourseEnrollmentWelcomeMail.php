<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CourseEnrollmentWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $emailSubject,
        public string $greeting,
        public string $courseTitle,
        public ?string $instructorName,
        public ?string $instructorBio,
        public string $facebookUrl,
        public string $ctaUrl,
        public string $ctaLabel = 'Open your course',
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
            html: 'mail.course-enrollment-welcome',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
