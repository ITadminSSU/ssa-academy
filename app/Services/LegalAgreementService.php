<?php

namespace App\Services;

use App\Models\Page;
use App\Models\User;
use App\Models\UserLegalAcceptance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\LegalAgreementAcceptedMail;

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
            ->first();
    }

    public function getNdaPage(): ?Page
    {
        return Page::query()
            ->where('slug', $this->ndaPageSlug())
            ->where('active', true)
            ->first();
    }

    public function currentVersion(): string
    {
        $terms = $this->getTermsPage();
        $nda = $this->getNdaPage();

        if (!$terms || !$nda) {
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
        if (!$user) {
            return false;
        }

        if ($user->role === 'admin') {
            return false;
        }

        return !$this->hasAcceptedCurrentAgreement($user);
    }

    public function hasAcceptedCurrentAgreement(User $user): bool
    {
        if (!$user->legal_agreement_accepted_at) {
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
            if (!$entry['page']) {
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
                $this->sendAcceptanceEmail($user, $terms, $nda, $acceptedAt, $ip);
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
        $terms ??= $this->getTermsPage();
        $nda ??= $this->getNdaPage();

        if (!$terms || !$nda) {
            return;
        }

        Mail::to($user->email)->send(new LegalAgreementAcceptedMail(
            user: $user,
            terms: $terms,
            nda: $nda,
            acceptedAt: $acceptedAt ?? $user->legal_agreement_accepted_at ?? now(),
            ipAddress: $ip ?? $user->legal_agreement_ip,
            agreementVersion: $this->currentVersion(),
        ));
    }

    private function formatDocument(?Page $page, string $type): array
    {
        if (!$page) {
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
            'url' => url('/' . $page->slug),
            'version' => $this->documentVersion($page),
        ];
    }

    private function documentVersion(Page $page): string
    {
        return (string) ($page->updated_at?->timestamp ?? $page->id);
    }
}
