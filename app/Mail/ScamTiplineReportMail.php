<?php

namespace App\Mail;

use App\Models\ScamTiplineReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ScamTiplineReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ScamTiplineReport $report) {}

    public function envelope(): Envelope
    {
        $siteName = (string) config('app.name');

        return new Envelope(
            subject: "New Fraud Training Tipline report — {$siteName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.scam-tipline-report',
            with: [
                'report' => $this->report,
                'inboxUrl' => route('scam-tipline.index'),
                'siteName' => (string) config('app.name'),
            ],
        );
    }
}
