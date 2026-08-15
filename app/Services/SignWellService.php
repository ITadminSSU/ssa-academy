<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SignWellService
{
    public function isEnabled(): bool
    {
        return (bool) config('signwell.enabled')
            && filled(config('signwell.api_key'))
            && filled(config('signwell.template_id'));
    }

    public function agreementVersion(): string
    {
        return 'signwell:'.(string) config('signwell.template_id');
    }

    /**
     * Create (or reuse) a SignWell document and return the signing URL.
     */
    public function startSigning(User $user): string
    {
        if (! $this->isEnabled()) {
            throw new RuntimeException('SignWell is not configured.');
        }

        if ($user->signwell_completed_at || $user->signwell_status === 'completed') {
            throw new RuntimeException('Student agreement already completed.');
        }

        if ($user->signwell_signing_url && $user->signwell_document_id && $user->signwell_status !== 'declined') {
            return $user->signwell_signing_url;
        }

        $payload = [
            'test_mode' => (bool) config('signwell.test_mode', true),
            'template_id' => (string) config('signwell.template_id'),
            'name' => 'SMARTSOURCING USA Academy Student Agreement — '.$user->name,
            'embedded_signing' => true,
            'embedded_signing_notifications' => true,
            'redirect_url' => route('signwell.complete'),
            'recipients' => [
                [
                    'id' => '1',
                    'placeholder_name' => (string) config('signwell.recipient_placeholder', 'Student'),
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ],
            'metadata' => [
                'user_id' => (string) $user->id,
                'email' => $user->email,
            ],
        ];

        try {
            $response = Http::withHeaders([
                'X-Api-Key' => (string) config('signwell.api_key'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
                ->timeout(30)
                ->post(rtrim((string) config('signwell.api_base'), '/').'/document_templates/documents', $payload)
                ->throw()
                ->json();
        } catch (RequestException $e) {
            Log::error('SignWell create document failed', [
                'user_id' => $user->id,
                'status' => $e->response?->status(),
                'body' => $e->response?->json() ?? $e->response?->body(),
            ]);

            throw new RuntimeException('Unable to start the student agreement signing session. Please try again or contact support.');
        }

        $documentId = (string) ($response['id'] ?? '');
        $recipients = $response['recipients'] ?? [];
        $recipient = is_array($recipients) ? ($recipients[0] ?? []) : [];
        $signingUrl = (string) ($recipient['embedded_signing_url'] ?? $recipient['signing_url'] ?? '');

        if ($documentId === '' || $signingUrl === '') {
            Log::error('SignWell response missing signing URL', [
                'user_id' => $user->id,
                'response' => $response,
            ]);

            throw new RuntimeException('SignWell did not return a signing link.');
        }

        $user->forceFill([
            'signwell_document_id' => $documentId,
            'signwell_recipient_id' => (string) ($recipient['id'] ?? '1'),
            'signwell_signing_url' => $signingUrl,
            'signwell_status' => 'pending',
            'signwell_completed_at' => null,
        ])->save();

        return $signingUrl;
    }

    public function getDocument(string $documentId): ?array
    {
        try {
            return Http::withHeaders([
                'X-Api-Key' => (string) config('signwell.api_key'),
                'Accept' => 'application/json',
            ])
                ->timeout(20)
                ->get(rtrim((string) config('signwell.api_base'), '/').'/documents/'.$documentId)
                ->throw()
                ->json();
        } catch (\Throwable $e) {
            Log::warning('SignWell get document failed', [
                'document_id' => $documentId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function documentIsCompleted(?array $document): bool
    {
        if (! $document) {
            return false;
        }

        $status = strtolower((string) ($document['status'] ?? ''));

        return in_array($status, ['completed', 'signed'], true);
    }

    public function markCompleted(User $user, ?string $documentId = null, ?string $ip = null): void
    {
        if ($user->legal_agreement_accepted_at && $user->signwell_completed_at) {
            return;
        }

        $now = now();

        $user->forceFill([
            'signwell_document_id' => $documentId ?: $user->signwell_document_id,
            'signwell_status' => 'completed',
            'signwell_completed_at' => $user->signwell_completed_at ?? $now,
            'signwell_signing_url' => null,
            'legal_agreement_accepted_at' => $user->legal_agreement_accepted_at ?? $now,
            'legal_agreement_version' => $this->agreementVersion(),
            'legal_agreement_ip' => $ip ?: $user->legal_agreement_ip,
        ])->save();

        app(LegalAgreementService::class)->recordSignWellAcceptance($user, $ip);
    }

    public function findUserByDocumentId(string $documentId): ?User
    {
        return User::query()->where('signwell_document_id', $documentId)->first();
    }

    public function findUserFromWebhookPayload(array $payload): ?User
    {
        $documentId = (string) ($payload['id'] ?? $payload['document_id'] ?? $payload['data']['id'] ?? '');

        if ($documentId !== '') {
            $user = $this->findUserByDocumentId($documentId);
            if ($user) {
                return $user;
            }
        }

        $metadata = $payload['metadata'] ?? $payload['data']['metadata'] ?? [];
        $userId = $metadata['user_id'] ?? null;

        if ($userId) {
            return User::query()->find($userId);
        }

        $email = $payload['recipients'][0]['email']
            ?? $payload['data']['recipients'][0]['email']
            ?? null;

        if ($email) {
            return User::query()->where('email', $email)->first();
        }

        return null;
    }
}
