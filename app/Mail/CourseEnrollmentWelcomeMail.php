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

    /**
     * @param  list<string>  $introParagraphs
     * @param  list<string>  $paymentBullets
     * @param  list<string>  $bodyParagraphs
     * @param  list<array{label: string, url: string, description?: string|null}>  $ctas
     */
    public function __construct(
        public string $emailSubject,
        public string $greeting,
        public string $courseTitle,
        public array $introParagraphs,
        public array $paymentBullets,
        public array $bodyParagraphs,
        public ?string $instructorName,
        public ?string $instructorBio,
        public array $ctas,
        public ?string $closingNote = null,
        public string $farewell = 'Best regards,',
        public ?string $signatureName = null,
        public string $paymentHeading = 'Payment Breakdown:',
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
            with: [
                'signatureName' => $this->signatureName
                    ?? (config('branding.name', config('app.name')).' Team'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
