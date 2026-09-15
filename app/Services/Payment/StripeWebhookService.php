<?php

namespace App\Services\Payment;

use App\Enums\PaymentBillingType;
use App\Models\Course\Course;
use App\Models\StripeWebhookEvent;
use App\Models\Subscription;
use App\Models\User;
use App\Support\StripeCheckoutIds;
use App\Support\StripeInvoiceIds;
use Illuminate\Support\Facades\Log;
use Modules\PaymentGateways\Services\PaymentService;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookService
{
    public function __construct(
        private StripeCustomerService $stripeCustomer,
        private SubscriptionService $subscriptions,
        private LaunchOfferEnrollmentService $launchOfferEnrollment,
        private PaymentService $payment,
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
            'invoice.paid' => $this->handleInvoicePaymentSucceeded($event->data->object),
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
        $mode = $session->mode ?? null;

        if ($mode === 'payment') {
            $this->enrollOneTimeFromCheckoutSession($session);

            return;
        }

        if ($mode !== 'subscription' || StripeCheckoutIds::objectId($session->subscription ?? null) === '') {
            return;
        }

        $this->stripeCustomer->configureStripe();

        $this->recordLaunchBalanceFromSession($session);

        $subscription = $this->subscriptions->activateFromCheckoutSession($session);
        $this->recordInitialSubscriptionPayment($session, $subscription);
    }

    public function enrollOneTimeFromCheckoutSession(object $session): bool
    {
        if (($session->mode ?? null) !== 'payment') {
            return false;
        }

        if (($session->payment_status ?? null) !== 'paid') {
            return false;
        }

        $offerMode = (string) data_get($session, 'metadata.launch_offer_mode', 'legacy_one_time');
        if ($offerMode !== '' && $offerMode !== 'legacy_one_time') {
            return false;
        }

        $transactionId = StripeCheckoutIds::transactionId($session);
        $itemType = (string) (data_get($session, 'metadata.item_type') ?: 'course');
        $itemId = (string) data_get($session, 'metadata.item_id', '');
        $userId = (string) (data_get($session, 'metadata.user_id') ?: ($session->client_reference_id ?? ''));
        $couponCode = data_get($session, 'metadata.coupon_code');

        if ($transactionId === '' || $itemId === '' || $userId === '' || ! in_array($itemType, ['course', 'exam'], true)) {
            Log::warning('Stripe one-time webhook is missing enrollment data', [
                'session_id' => $session->id ?? null,
                'transaction_id' => $transactionId !== '' ? $transactionId : null,
                'user_id' => $userId !== '' ? $userId : null,
                'item_type' => $itemType,
                'item_id' => $itemId !== '' ? $itemId : null,
            ]);

            return false;
        }

        try {
            $this->payment->coursesBuy(
                'stripe',
                $itemType,
                $itemId,
                $transactionId,
                0.0,
                ($session->amount_total ?? 0) / 100,
                $couponCode ? (string) $couponCode : null,
                $userId,
            );
        } catch (\Throwable $exception) {
            Log::error('Stripe one-time webhook failed', [
                'session_id' => $session->id ?? null,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        return true;
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
                StripeCheckoutIds::transactionId($session),
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

    protected function recordInitialSubscriptionPayment(object $session, Subscription $subscription): void
    {
        if ((string) data_get($session, 'metadata.launch_offer_mode') === 'balance') {
            return;
        }

        $amount = round(($session->amount_total ?? 0) / 100, 2);

        if ($amount <= 0) {
            return;
        }

        $transactionId = StripeCheckoutIds::transactionId($session);

        if ($transactionId === '') {
            return;
        }

        $couponCode = data_get($session, 'metadata.coupon_code')
            ?: data_get($session, 'subscription_details.metadata.coupon_code');
        $couponDiscount = (float) data_get($session, 'metadata.coupon_discount', 0);

        try {
            $this->payment->recordSubscriptionPayment(
                $subscription,
                $transactionId,
                $amount,
                0.0,
                PaymentBillingType::SUBSCRIPTION,
                $couponCode ? (string) $couponCode : null,
                $couponDiscount > 0 ? $couponDiscount : null,
                $amount,
                array_filter([
                    StripeCheckoutIds::objectId($session->invoice ?? null),
                    StripeCheckoutIds::objectId($session->id ?? null),
                ]),
            );
        } catch (\Throwable $exception) {
            Log::error('Stripe subscription checkout payment could not be recorded', [
                'session_id' => $session->id ?? null,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    protected function handleInvoicePaymentFailed(object $invoice): void
    {
        $subscriptionId = StripeInvoiceIds::subscriptionId($invoice);

        if ($subscriptionId === '') {
            return;
        }

        $this->stripeCustomer->configureStripe();

        $stripeSubscription = \Stripe\Subscription::retrieve($subscriptionId);
        $this->subscriptions->handlePaymentFailed($stripeSubscription);
    }
}
