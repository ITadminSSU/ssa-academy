<?php

namespace App\Services\Payment;

use App\Models\StripeWebhookEvent;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookService
{
    public function __construct(
        private StripeCustomerService $stripeCustomer,
        private SubscriptionService $subscriptions,
    ) {}

    public function handle(string $payload, ?string $signatureHeader): void
    {
        $secret = config('payment.stripe.webhook_secret');

        if (empty($secret)) {
            throw new \RuntimeException('Stripe webhook secret is not configured.');
        }

        if (empty($signatureHeader)) {
            throw new \RuntimeException('Missing Stripe-Signature header.');
        }

        try {
            $event = Webhook::constructEvent($payload, $signatureHeader, $secret);
        } catch (SignatureVerificationException $exception) {
            throw new \RuntimeException('Invalid Stripe webhook signature.', 0, $exception);
        }

        if (StripeWebhookEvent::query()->whereKey($event->id)->exists()) {
            return;
        }

        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutSessionCompleted($event->data->object),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($event->data->object),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event->data->object),
            'invoice.payment_succeeded' => $this->handleInvoicePaymentSucceeded($event->data->object),
            'invoice.payment_failed' => $this->handleInvoicePaymentFailed($event->data->object),
            default => null,
        };

        StripeWebhookEvent::query()->create([
            'id' => $event->id,
            'type' => $event->type,
            'processed_at' => now(),
        ]);
    }

    protected function handleCheckoutSessionCompleted(object $session): void
    {
        if (($session->mode ?? null) !== 'subscription' || empty($session->subscription)) {
            return;
        }

        $this->stripeCustomer->configureStripe();

        $this->subscriptions->activateFromCheckoutSession($session);
    }

    protected function handleSubscriptionUpdated(object $stripeSubscription): void
    {
        $this->stripeCustomer->configureStripe();

        $subscription = $this->subscriptions->syncFromStripeSubscription($stripeSubscription);

        if ($subscription->status === \App\Enums\SubscriptionStatus::CANCELED) {
            $this->subscriptions->suspend($subscription);
        }
    }

    protected function handleSubscriptionDeleted(object $stripeSubscription): void
    {
        $subscription = \App\Models\Subscription::query()
            ->where('stripe_subscription_id', $stripeSubscription->id)
            ->first();

        if ($subscription) {
            $this->subscriptions->suspend($subscription);
        }
    }

    protected function handleInvoicePaymentSucceeded(object $invoice): void
    {
        try {
            $this->subscriptions->handleInvoicePaymentSucceeded($invoice);
        } catch (\Throwable $exception) {
            Log::error('Stripe subscription invoice payment handling failed', [
                'invoice_id' => $invoice->id ?? null,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    protected function handleInvoicePaymentFailed(object $invoice): void
    {
        if (empty($invoice->subscription)) {
            return;
        }

        $this->stripeCustomer->configureStripe();

        $stripeSubscription = \Stripe\Subscription::retrieve($invoice->subscription);
        $this->subscriptions->handlePaymentFailed($stripeSubscription);
    }
}
