<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
            throw new RuntimeException('SignWell is not configured. Set SIGNWELL_ENABLED, SIGNWELL_API_KEY, and SIGNWELL_TEMPLATE_ID on the server.');
        }

        if ($user->signwell_completed_at || $user->signwell_status === 'completed') {
            throw new RuntimeException('Student agreement already completed.');
        }

        if ($user->signwell_signing_url && $user->signwell_document_id && $user->signwell_status !== 'declined') {
            return $user->signwell_signing_url;
        }

        $placeholderName = $this->resolveRecipientPlaceholder();

        $payload = [
            'test_mode' => (bool) config('signwell.test_mode', true),
            'template_id' => (string) config('signwell.template_id'),
            'name' => 'SMARTSOURCING USA Academy Student Agreement — '.$user->name,
            'draft' => false,
            'embedded_signing' => true,
            'embedded_signing_notifications' => false,
            'redirect_url' => route('signwell.complete', absolute: true),
            'recipients' => [
                [
                    'id' => '1',
                    'placeholder_name' => $placeholderName,
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
            $response = $this->client()
                ->post($this->apiUrl('document_templates/documents/'), $payload)
                ->throw()
                ->json();
        } catch (RequestException $e) {
            $status = $e->response?->status();
            $body = $e->response?->json() ?? $e->response?->body();

            Log::error('SignWell create document failed', [
                'user_id' => $user->id,
                'status' => $status,
                'placeholder' => $placeholderName,
                'template_id' => config('signwell.template_id'),
                'body' => $body,
            ]);

            throw new RuntimeException($this->friendlyCreateError($status, $body, $placeholderName));
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

            throw new RuntimeException('SignWell created the document but did not return a signing link. Check that the template allows embedded signing.');
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

    /**
     * @return list<string>
     */
    public function templatePlaceholderNames(): array
    {
        $template = $this->getTemplate((string) config('signwell.template_id'));

        if (! $template) {
            return [];
        }

        $placeholders = $template['placeholders'] ?? [];

        if (! is_array($placeholders)) {
            return [];
        }

        return collect($placeholders)
            ->map(fn ($placeholder) => is_array($placeholder) ? (string) ($placeholder['name'] ?? '') : '')
            ->filter()
            ->values()
            ->all();
    }

    public function getTemplate(string $templateId): ?array
    {
        if ($templateId === '') {
            return null;
        }

        try {
            return $this->client()
                ->get($this->apiUrl('document_templates/'.$templateId))
                ->throw()
                ->json();
        } catch (\Throwable $e) {
            Log::warning('SignWell get template failed', [
                'template_id' => $templateId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function getDocument(string $documentId): ?array
    {
        try {
            return $this->client()
                ->get($this->apiUrl('documents/'.$documentId))
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

    /**
     * Prefer the configured placeholder when it exists on the template;
     * otherwise use the first template placeholder (common misconfiguration).
     */
    private function resolveRecipientPlaceholder(): string
    {
        $configured = trim((string) config('signwell.recipient_placeholder', 'Student'));
        $available = $this->templatePlaceholderNames();

        if ($available === []) {
            return $configured !== '' ? $configured : 'Student';
        }

        foreach ($available as $name) {
            if (strcasecmp($name, $configured) === 0) {
                return $name;
            }
        }

        Log::warning('SignWell placeholder mismatch; using first template placeholder', [
            'configured' => $configured,
            'available' => $available,
        ]);

        return $available[0];
    }

    private function client()
    {
        return Http::withHeaders([
            'X-Api-Key' => (string) config('signwell.api_key'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->timeout(30);
    }

    private function apiUrl(string $path): string
    {
        return rtrim((string) config('signwell.api_base'), '/').'/'.ltrim($path, '/');
    }

    private function friendlyCreateError(?int $status, mixed $body, string $placeholderName): string
    {
        $detail = $this->extractErrorDetail($body);

        if ($status === 401 || $status === 403) {
            return 'SignWell rejected the API key. Check SIGNWELL_API_KEY in Forge and run php artisan config:clear.';
        }

        if ($status === 404) {
            return 'SignWell template was not found. Check SIGNWELL_TEMPLATE_ID matches the template URL in SignWell.';
        }

        if ($status === 422) {
            $hint = $detail !== '' ? ' SignWell said: '.$detail : '';

            return 'SignWell could not create the agreement (often a recipient placeholder mismatch).'
                .' Template placeholder must match SIGNWELL_RECIPIENT_PLACEHOLDER (tried “'.$placeholderName.'”).'
                .$hint;
        }

        if (is_string($detail) && $detail !== '') {
            return 'Unable to start SignWell signing: '.$detail;
        }

        return 'Unable to start the student agreement signing session. Please try again or contact support.';
    }

    private function extractErrorDetail(mixed $body): string
    {
        if (is_string($body) && $body !== '') {
            return Str::limit($body, 240);
        }

        if (! is_array($body)) {
            return '';
        }

        if (isset($body['error']) && is_string($body['error'])) {
            return $body['error'];
        }

        if (isset($body['message']) && is_string($body['message'])) {
            return $body['message'];
        }

        $errors = $body['errors'] ?? null;

        if (is_string($errors)) {
            return $errors;
        }

        if (is_array($errors)) {
            $parts = [];

            foreach ($errors as $key => $value) {
                if (is_string($value)) {
                    $parts[] = $key.': '.$value;
                } elseif (is_array($value)) {
                    $flat = collect($value)->flatten()->filter(fn ($item) => is_string($item))->implode('; ');
                    if ($flat !== '') {
                        $parts[] = is_string($key) ? $key.': '.$flat : $flat;
                    }
                }
            }

            return implode(' | ', $parts);
        }

        return '';
    }
}
