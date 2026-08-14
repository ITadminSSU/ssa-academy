<?php

namespace App\Mail;

use App\Models\Page;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class LegalAgreementAcceptedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Page $terms,
        public Carbon $acceptedAt,
        public ?string $ipAddress,
        public string $agreementVersion,
        public bool $includePdfAttachments = true,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: config('app.name') . ' — Your Terms & Conditions Acceptance Record',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.legal-agreement-accepted',
        );
    }

    public function attachments(): array
    {
        if (! $this->includePdfAttachments) {
            return [];
        }

        return [
            Attachment::fromData(
                fn () => $this->renderPdf($this->terms->title, $this->terms->description ?? ''),
                'SSU-Academy-Terms-and-Conditions.pdf'
            )->withMime('application/pdf'),
        ];
    }

    private function renderPdf(string $title, string $html): string
    {
        return Pdf::loadView('mail.pdf.legal-document', [
            'title' => $title,
            'html' => $html,
        ])->output();
    }
}
