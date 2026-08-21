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
     * @param  list<string>  $postParagraphs
     * @param  list<array{label: string, url: string, description?: string|null}>  $ctas
     */
    public function __construct(
        public string $emailSubject,
        public string $greeting,
        public array $paragraphs,
        public array $bullets = [],
        public ?string $ctaLabel = null,
        public ?string $ctaUrl = null,
        public ?string $closingNote = null,
        public ?string $bulletsHeading = null,
        public array $ctas = [],
        public string $farewell = 'Thanks,',
        public ?string $signatureName = null,
        public array $postParagraphs = [],
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
            with: [
                'resolvedCtas' => $this->resolvedCtas(),
                'signatureName' => $this->signatureName
                    ?? (config('mail.from.name', config('app.name')).' Team'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }

    /**
     * @return list<array{label: string, url: string, description: string|null}>
     */
    public function resolvedCtas(): array
    {
        if ($this->ctas !== []) {
            return array_values(array_map(
                fn (array $cta): array => [
                    'label' => (string) ($cta['label'] ?? ''),
                    'url' => (string) ($cta['url'] ?? ''),
                    'description' => isset($cta['description']) ? (string) $cta['description'] : null,
                ],
                $this->ctas,
            ));
        }

        if ($this->ctaUrl && $this->ctaLabel) {
            return [[
                'label' => $this->ctaLabel,
                'url' => $this->ctaUrl,
                'description' => null,
            ]];
        }

        return [];
    }
}
