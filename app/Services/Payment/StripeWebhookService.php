<?php

namespace App\Services\Payment;

use App\Models\Course\Course;
use App\Models\StripeWebhookEvent;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookService
{
    public function __construct(
        private StripeCustomerService $stripeCustomer,
        private SubscriptionService $subscriptions,
        private LaunchOfferEnrollmentService $launchOfferEnrollment,
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

        $this->recordLaunchBalanceFromSession($session);

        $this->subscriptions->activateFromCheckoutSession($session);
    }

    protected function recordLaunchBalanceFromSession(object $session): void
    {
        if ((string) data_get($session, 'metadata.launch_offer_mode') !== 'balance') {
            return;
        }

        $userId = (int) (data_get($session, 'metadata.user_id') ?: ($session->client_reference_id ?? 0));
        $courseId = (int) data_get($session, 'metadata.item_id');
        $user = User::query()->find($userId);
        $course = Course::query()->find($courseId);

        if (! $user || ! $course) {
            Log::warning('Stripe launch balance webhook is missing user or course', [
                'session_id' => $session->id ?? null,
                'user_id' => $userId,
                'course_id' => $courseId,
            ]);

            return;
        }

        $couponCode = data_get($session, 'metadata.coupon_code')
            ?: data_get($session, 'subscription_details.metadata.coupon_code');
        $couponDiscount = (float) data_get($session, 'metadata.coupon_discount', 0);
        $chargedAmount = (float) data_get($session, 'metadata.charged_amount', 0);

        if ($chargedAmount <= 0) {
            $chargedAmount = ($session->amount_total ?? 0) / 100;
        }

        try {
            $this->launchOfferEnrollment->recordBalancePayment(
                $user,
                $course,
                'stripe',
                (string) ($session->payment_intent ?: $session->subscription ?: $session->id),
                $chargedAmount,
                $couponCode ? (string) $couponCode : null,
                $couponDiscount,
            );
        } catch (\Throwable $exception) {
            Log::error('Stripe launch balance webhook failed', [
                'session_id' => $session->id ?? null,
                'message' => $exception->getMessage(),
            ]);
        }
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
