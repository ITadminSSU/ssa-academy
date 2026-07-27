<?php

namespace App\Services;

use App\Mail\LegalAgreementAcceptedMail;
use App\Models\Page;
use App\Models\User;
use App\Models\UserLegalAcceptance;
use App\Support\ResendHttpClient;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class LegalAgreementService
{
    public function termsPageSlug(): string
    {
        return (string) config('legal.terms_page_slug', 'terms-and-conditions');
    }

    public function ndaPageSlug(): string
    {
        return (string) config('legal.nda_page_slug', 'non-disclosure-agreement');
    }

    public function getTermsPage(): ?Page
    {
        return Page::query()
            ->where('slug', $this->termsPageSlug())
            ->where('active', true)
            ->first()
            ?? Page::query()->where('slug', $this->termsPageSlug())->first();
    }

    public function getNdaPage(): ?Page
    {
        return Page::query()
            ->where('slug', $this->ndaPageSlug())
            ->where('active', true)
            ->first()
            ?? Page::query()->where('slug', $this->ndaPageSlug())->first();
    }

    public function currentVersion(): string
    {
        $terms = $this->getTermsPage();
        $nda = $this->getNdaPage();

        if (! $terms || ! $nda) {
            return (string) config('legal.agreement_version', 'fallback');
        }

        return substr(hash('sha256', implode('|', [
            $terms->id,
            $terms->updated_at?->timestamp ?? 0,
            $nda->id,
            $nda->updated_at?->timestamp ?? 0,
            $terms->description ?? '',
            $nda->description ?? '',
        ])), 0, 16);
    }

    public function requiresAcceptance(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->role === 'admin') {
            return false;
        }

        return ! $this->hasAcceptedCurrentAgreement($user);
    }

    public function hasAcceptedCurrentAgreement(User $user): bool
    {
        if (! $user->legal_agreement_accepted_at) {
            return false;
        }

        return $user->legal_agreement_version === $this->currentVersion();
    }

    public function documentPayload(): array
    {
        $terms = $this->getTermsPage();
        $nda = $this->getNdaPage();

        return [
            'version' => $this->currentVersion(),
            'terms' => $this->formatDocument($terms, 'terms'),
            'nda' => $this->formatDocument($nda, 'nda'),
        ];
    }

    public function recordAcceptance(User $user, Request $request, bool $sendEmail = true): User
    {
        $terms = $this->getTermsPage();
        $nda = $this->getNdaPage();
        $ip = $request->ip();
        $acceptedAt = now();
        $version = $this->currentVersion();

        foreach ([
            ['type' => 'terms', 'page' => $terms],
            ['type' => 'nda', 'page' => $nda],
        ] as $entry) {
            if (! $entry['page']) {
                continue;
            }

            UserLegalAcceptance::create([
                'user_id' => $user->id,
                'document_type' => $entry['type'],
                'document_slug' => $entry['page']->slug,
                'version' => $this->documentVersion($entry['page']),
                'ip' => $ip,
                'accepted_at' => $acceptedAt,
            ]);
        }

        $user->update([
            'legal_agreement_accepted_at' => $acceptedAt,
            'legal_agreement_version' => $version,
            'legal_agreement_ip' => $ip,
        ]);

        $user = $user->fresh();

        if ($sendEmail && $terms && $nda) {
            try {
                $this->deliverAcceptanceEmail($user);
            } catch (\Throwable $exception) {
                Log::warning('Failed to send legal agreement confirmation email', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $user;
    }

    public function sendAcceptanceEmail(User $user, ?Page $terms = null, ?Page $nda = null, $acceptedAt = null, ?string $ip = null): void
    {
        $this->deliverAcceptanceEmail($user, $terms, $nda, $acceptedAt, $ip);
    }

    public function deliverAcceptanceEmail(
        User $user,
        ?Page $terms = null,
        ?Page $nda = null,
        $acceptedAt = null,
        ?string $ip = null,
        ?string $resendApiKey = null,
    ): void {
        $terms ??= $this->getTermsPage();
        $nda ??= $this->getNdaPage();
        $acceptedAt = $acceptedAt ?? $user->legal_agreement_accepted_at ?? now();
        $ip ??= $user->legal_agreement_ip;
        $agreementVersion = $this->currentVersion();

        if (! $terms || ! $nda) {
            $message = 'Legal agreement email skipped because CMS pages are missing';

            Log::warning($message, [
                'user_id' => $user->id,
                'email' => $user->email,
                'terms_found' => (bool) $terms,
                'nda_found' => (bool) $nda,
            ]);

            $this->markLegalEmailFailed($user, $message);

            throw new \RuntimeException($message);
        }

        $errors = [];

        if ($resendApiKey && str_starts_with($resendApiKey, 're_')) {
            config(['services.resend.key' => $resendApiKey]);
        }

        if (ResendHttpClient::isAvailable()) {
            try {
                $this->sendAcceptanceEmailViaResendHttp($user, $terms, $nda, $acceptedAt, $ip, $agreementVersion);
                $this->markLegalEmailSent($user);

                return;
            } catch (\Throwable $exception) {
                $errors[] = 'Resend API: '.$exception->getMessage();
                Log::warning('Resend HTTP legal email failed, trying Laravel Mail', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        foreach ([true, false] as $includePdfAttachments) {
            try {
                Mail::to($user->email)->send(new LegalAgreementAcceptedMail(
                    user: $user,
                    terms: $terms,
                    nda: $nda,
                    acceptedAt: $acceptedAt,
                    ipAddress: $ip,
                    agreementVersion: $agreementVersion,
                    includePdfAttachments: $includePdfAttachments,
                ));

                $this->markLegalEmailSent($user);

                return;
            } catch (\Throwable $exception) {
                $label = $includePdfAttachments ? 'Laravel Mail with PDFs' : 'Laravel Mail without PDFs';
                $errors[] = $label.': '.$exception->getMessage();
            }
        }

        $message = implode(' | ', $errors);
        $this->markLegalEmailFailed($user, $message);

        throw new \RuntimeException($message !== '' ? $message : 'Legal agreement email could not be sent.');
    }

    private function sendAcceptanceEmailViaResendHttp(
        User $user,
        Page $terms,
        Page $nda,
        $acceptedAt,
        ?string $ip,
        string $agreementVersion,
    ): void {
        $html = view('mail.legal-agreement-accepted', [
            'user' => $user,
            'terms' => $terms,
            'nda' => $nda,
            'acceptedAt' => $acceptedAt,
            'ipAddress' => $ip,
            'agreementVersion' => $agreementVersion,
        ])->render();

        $attachments = [];

        try {
            $attachments[] = [
                'filename' => 'SSU-Academy-Terms-and-Conditions.pdf',
                'content' => base64_encode($this->renderPdf($terms->title, $terms->description ?? '')),
            ];
            $attachments[] = [
                'filename' => 'SSU-Academy-NDA.pdf',
                'content' => base64_encode($this->renderPdf($nda->title, $nda->description ?? '')),
            ];
        } catch (\Throwable $exception) {
            Log::warning('Legal email PDF generation failed for Resend HTTP; sending HTML only', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);
        }

        $payload = [
            'from' => config('mail.from.name').' <'.config('mail.from.address').'>',
            'to' => [$user->email],
            'subject' => config('app.name').' — Your Terms & NDA Acceptance Record',
            'html' => $html,
        ];

        if ($attachments !== []) {
            $payload['attachments'] = $attachments;
        }

        ResendHttpClient::send($payload);
    }

    private function renderPdf(string $title, string $html): string
    {
        return Pdf::loadView('mail.pdf.legal-document', [
            'title' => $title,
            'html' => $html,
        ])->output();
    }

    private function markLegalEmailSent(User $user): void
    {
        if (! Schema::hasColumn('users', 'legal_confirmation_email_sent_at')) {
            return;
        }

        $user->forceFill([
            'legal_confirmation_email_sent_at' => now(),
            'legal_confirmation_email_last_error' => null,
        ])->save();
    }

    private function markLegalEmailFailed(User $user, string $message): void
    {
        if (! Schema::hasColumn('users', 'legal_confirmation_email_last_error')) {
            return;
        }

        $user->forceFill([
            'legal_confirmation_email_last_error' => $message,
        ])->save();
    }

    private function formatDocument(?Page $page, string $type): array
    {
        if (! $page) {
            return [
                'title' => $type === 'terms' ? 'Terms & Conditions' : 'Non-Disclosure Agreement (NDA)',
                'html' => '<p>Document unavailable. Please contact support.</p>',
                'url' => url('/'),
                'version' => 'unavailable',
            ];
        }

        return [
            'title' => $page->title,
            'html' => $page->description ?? '',
            'url' => url('/'.$page->slug),
            'version' => $this->documentVersion($page),
        ];
    }

    private function documentVersion(Page $page): string
    {
        return (string) ($page->updated_at?->timestamp ?? $page->id);
    }
}
