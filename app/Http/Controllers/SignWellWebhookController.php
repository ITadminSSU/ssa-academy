<?php

namespace App\Http\Controllers;

use App\Services\SignWellService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SignWellWebhookController extends Controller
{
    public function __construct(private SignWellService $signWell) {}

    public function handle(Request $request)
    {
        if (! $this->signWell->isEnabled()) {
            return response('SignWell disabled', 200);
        }

        $secret = (string) config('signwell.webhook_secret');
        if ($secret !== '') {
            $header = (string) $request->header('X-SignWell-Secret', $request->header('X-Webhook-Secret', ''));
            if (! hash_equals($secret, $header)) {
                Log::warning('SignWell webhook rejected: invalid secret');

                return response('Invalid secret', 401);
            }
        }

        $payload = $request->all();
        $event = strtolower((string) (
            $payload['event']
            ?? $payload['type']
            ?? $payload['event_type']
            ?? $payload['status']
            ?? ''
        ));

        $completedEvents = [
            'document_completed',
            'document.completed',
            'completed',
            'document_signed',
            'document.signed',
            'recipient_completed',
            'recipient.completed',
        ];

        $status = strtolower((string) ($payload['status'] ?? $payload['data']['status'] ?? ''));

        if (! in_array($event, $completedEvents, true) && $status !== 'completed') {
            return response('ignored', 200);
        }

        $user = $this->signWell->findUserFromWebhookPayload($payload);

        if (! $user) {
            Log::warning('SignWell webhook: user not found', ['payload' => $payload]);

            return response('user not found', 200);
        }

        $documentId = (string) (
            $payload['id']
            ?? $payload['document_id']
            ?? $payload['data']['id']
            ?? $user->signwell_document_id
            ?? ''
        );

        try {
            $this->signWell->markCompleted($user, $documentId ?: null, $request->ip());
        } catch (\Throwable $e) {
            Log::error('SignWell webhook processing failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response('error', 500);
        }

        return response('ok', 200);
    }
}
