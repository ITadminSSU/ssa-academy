<?php

namespace App\Services;

use App\Jobs\SendRegistrationNotificationsJob;
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
        $signWell = app(SignWellService::class);

        if ($signWell->isEnabled()) {
            return $signWell->agreementVersion();
        }

        $terms = $this->getTermsPage();

        if (! $terms) {
            return (string) config('legal.agreement_version', 'fallback');
        }

        return substr(hash('sha256', implode('|', [
            $terms->id,
            $terms->updated_at?->timestamp ?? 0,
            $terms->description ?? '',
        ])), 0, 16);
    }

    public function requiresAcceptance(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if (in_array($user->role, ['admin', 'social_media'], true)) {
            return false;
        }

        return ! $this->hasAcceptedCurrentAgreement($user);
    }

    public function hasAcceptedCurrentAgreement(User $user): bool
    {
        if (! $user->legal_agreement_accepted_at) {
            return false;
        }

        $signWell = app(SignWellService::class);

        if ($signWell->isEnabled()) {
            // Completed SignWell for the current template.
            if ($user->signwell_completed_at && $user->legal_agreement_version === $signWell->agreementVersion()) {
                return true;
            }

            // Grandfather students who already accepted before SignWell was turned on.
            if (! $user->signwell_document_id && ! str_starts_with((string) $user->legal_agreement_version, 'signwell:')) {
                return true;
            }

            return false;
        }

        return $user->legal_agreement_version === $this->currentVersion();
    }

    public function documentPayload(?User $user = null): array
    {
        $terms = $this->getTermsPage();
        $signWell = app(SignWellService::class);

        return [
            'version' => $this->currentVersion(),
            'terms' => $this->formatDocument($terms, 'terms'),
            'signwell' => [
                'enabled' => $signWell->isEnabled(),
                'status' => $user?->signwell_status,
                'has_signing_url' => filled($user?->signwell_signing_url),
            ],
        ];
    }

    public function recordAcceptance(User $user, Request $request, bool $sendEmail = true): User
    {
        if (app(SignWellService::class)->isEnabled()) {
            throw new \RuntimeException('Please complete the SignWell student agreement to continue.');
        }

        $terms = $this->getTermsPage();
        $ip = $request->ip();
        $acceptedAt = now();
        $version = $this->currentVersion();

        if ($terms) {
            UserLegalAcceptance::create([
                'user_id' => $user->id,
                'document_type' => 'terms',
                'document_slug' => $terms->slug,
                'version' => $this->documentVersion($terms),
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

        if ($sendEmail && $terms) {
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

    /**
     * Persist legal acceptance after SignWell document completion.
     */
    public function recordSignWellAcceptance(User $user, ?string $ip = null): User
    {
        $terms = $this->getTermsPage();
        $acceptedAt = $user->legal_agreement_accepted_at ?? now();
        $version = app(SignWellService::class)->agreementVersion();
        $ip ??= $user->legal_agreement_ip;

        $alreadyLogged = UserLegalAcceptance::query()
            ->where('user_id', $user->id)
            ->where('document_type', 'signwell')
            ->where('version', $version)
            ->exists();

        if (! $alreadyLogged) {
            UserLegalAcceptance::create([
                'user_id' => $user->id,
                'document_type' => 'signwell',
                'document_slug' => 'student-agreement',
                'version' => $version,
                'ip' => $ip,
                'accepted_at' => $acceptedAt,
            ]);
        }

        if ($terms) {
            $termsLogged = UserLegalAcceptance::query()
                ->where('user_id', $user->id)
                ->where('document_type', 'terms')
                ->where('version', $this->documentVersion($terms))
                ->exists();

            if (! $termsLogged) {
                UserLegalAcceptance::create([
                    'user_id' => $user->id,
                    'document_type' => 'terms',
                    'document_slug' => $terms->slug,
                    'version' => $this->documentVersion($terms),
                    'ip' => $ip,
                    'accepted_at' => $acceptedAt,
                ]);
            }
        }

        $user->forceFill([
            'legal_agreement_accepted_at' => $acceptedAt,
            'legal_agreement_version' => $version,
            'legal_agreement_ip' => $ip,
        ])->save();

        $user = $user->fresh();

        if (! $user->legal_confirmation_email_sent_at) {
            SendRegistrationNotificationsJob::dispatch($user->id)
                ->onConnection('sync')
                ->afterResponse();
        }

        return $user;
    }

    public function sendAcceptanceEmail(User $user, ?Page $terms = null, $acceptedAt = null, ?string $ip = null): void
    {
        $this->deliverAcceptanceEmail($user, $terms, $acceptedAt, $ip);
    }

    public function deliverAcceptanceEmail(
        User $user,
        ?Page $terms = null,
        $acceptedAt = null,
        ?string $ip = null,
        ?string $resendApiKey = null,
    ): void {
        $terms ??= $this->getTermsPage();
        $acceptedAt = $acceptedAt ?? $user->legal_agreement_accepted_at ?? now();
        $ip ??= $user->legal_agreement_ip;
        $agreementVersion = $this->currentVersion();

        if (! $terms) {
            $message = 'Legal agreement email skipped because Terms & Conditions CMS page is missing';

            Log::warning($message, [
                'user_id' => $user->id,
                'email' => $user->email,
                'terms_found' => false,
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
                $this->sendAcceptanceEmailViaResendHttp($user, $terms, $acceptedAt, $ip, $agreementVersion);
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
        $acceptedAt,
        ?string $ip,
        string $agreementVersion,
    ): void {
        $html = view('mail.legal-agreement-accepted', [
            'user' => $user,
            'terms' => $terms,
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
        } catch (\Throwable $exception) {
            Log::warning('Legal email PDF generation failed for Resend HTTP; sending HTML only', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);
        }

        $payload = [
            'from' => config('mail.from.name').' <'.config('mail.from.address').'>',
            'to' => [$user->email],
            'subject' => config('app.name').' — Your Terms & Conditions Acceptance Record',
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
                'title' => 'Terms & Conditions',
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
