<?php

namespace Modules\PaymentGateways\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Services\Payment\StripeWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function __construct(private StripeWebhookService $webhooks) {}

    public function handle(Request $request): Response
    {
        try {
            $this->webhooks->handle(
                $request->getContent(),
                $request->header('Stripe-Signature'),
            );
        } catch (\Throwable $exception) {
            Log::warning('Stripe webhook rejected', [
                'message' => $exception->getMessage(),
            ]);

            return response($exception->getMessage(), 400);
        }

        return response('', 200);
    }
}
