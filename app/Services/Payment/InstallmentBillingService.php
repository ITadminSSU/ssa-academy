<?php

namespace App\Services\Payment;

use App\Enums\EnrollmentAccessStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Course\Course;
use App\Models\Course\CourseEnrollment;
use App\Models\StripeWebhookEvent;
use App\Models\Subscription;
use App\Support\InstallmentBillingRules;
use App\Support\StripeCheckoutIds;
use App\Support\StripeInvoiceIds;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Stripe\Invoice;
use Stripe\Subscription as StripeSubscription;

class InstallmentBillingService
{
    public function __construct(
        private SubscriptionService $subscriptions,
        private StripeCustomerService $stripeCustomer,
    ) {}

    public function recordCheckoutSession(object $session): void
    {
        $subscriptionId = StripeCheckoutIds::objectId($session->subscription ?? null);
        $invoiceId = StripeCheckoutIds::objectId($session->invoice ?? null);

        if ($subscriptionId === '' || $invoiceId === '') {
            return;
        }

        $subscription = Subscription::query()
            ->where('stripe_subscription_id', $subscriptionId)
            ->first();

        if (! $subscription) {
            return;
        }

        $course = $subscription->course;

        if (! $course?->usesInstallmentSubscription()) {
            return;
        }

        $this->countInvoiceOnce($subscription, $invoiceId, (string) $course->stripe_price_id);
        $subscription->refresh();
        $this->finishPaidInvoice($subscription, $course, null);
    }

    public function recordPaidInvoice(object $invoice): void
    {
        $subscriptionId = StripeInvoiceIds::subscriptionId($invoice);

        if ($subscriptionId === '') {
            return;
        }

        $subscription = Subscription::query()
            ->where('stripe_subscription_id', $subscriptionId)
            ->first();

        if (! $subscription) {
            return;
        }

        $course = $subscription->course;

        if (! $course?->usesInstallmentSubscription()) {
            return;
        }

        $priceId = StripeInvoiceIds::recurringPriceId($invoice);

        if (! InstallmentBillingRules::isInstallmentPrice(
            $priceId,
            (string) $course->stripe_price_id,
            (string) $subscription->stripe_price_id,
            (string) $course->stripe_ongoing_price_id,
        )) {
            return;
        }

        $this->countInvoiceOnce($subscription, StripeInvoiceIds::invoiceId($invoice), $priceId);
        $subscription->refresh();
        $this->finishPaidInvoice($subscription, $course, $invoice);
    }

    public function lockForFailedInvoice(object $stripeSubscription, object $invoice): void
    {
        $subscription = $this->subscriptions->syncFromStripeSubscription($stripeSubscription);
        $course = $subscription->course;

        if (! $course?->usesInstallmentSubscription()) {
            return;
        }

        $subscription->update([
            'status' => SubscriptionStatus::PAST_DUE,
            'grace_ends_at' => null,
            'open_invoice_id' => StripeInvoiceIds::invoiceId($invoice) ?: null,
            'open_invoice_url' => $this->invoiceUrl($invoice),
        ]);

        $this->suspendEnrollment($subscription);
    }

    private function countInvoiceOnce(Subscription $subscription, string $invoiceId, string $priceId): void
    {
        if ($invoiceId === '' || $priceId === '') {
            return;
        }

        $markerId = 'installment:'.$invoiceId;

        try {
            DB::transaction(function () use ($subscription, $markerId) {
                if (StripeWebhookEvent::query()->whereKey($markerId)->exists()) {
                    return;
                }

                StripeWebhookEvent::query()->create([
                    'id' => $markerId,
                    'type' => 'installment.invoice_counted',
                    'processed_at' => now(),
                ]);

                $subscription->increment('installment_paid_count');
            });
        } catch (UniqueConstraintViolationException) {
            // The invoice marker is unique. A second webhook for the same invoice does not count again.
        }
    }

    private function finishPaidInvoice(Subscription $subscription, Course $course, ?object $invoice): void
    {
        $openInvoiceLookup = $this->firstOpenInvoice((string) $subscription->stripe_subscription_id);

        if (! $openInvoiceLookup['ok']) {
            return;
        }

        $openInvoice = $openInvoiceLookup['invoice'];
        $active = $subscription->status === SubscriptionStatus::ACTIVE
            || $subscription->status === SubscriptionStatus::TRIALING;
        $hasOpenInvoice = $openInvoice !== null;

        if (! InstallmentBillingRules::shouldSwitchToOngoing(
            (int) $subscription->installment_paid_count,
            (int) $course->installment_months,
            $active && ! $hasOpenInvoice,
            $hasOpenInvoice,
            (string) $subscription->stripe_price_id,
            (string) $course->stripe_ongoing_price_id,
        )) {
            if (! $active || $hasOpenInvoice) {
                $this->remainLocked($subscription, $openInvoice ?? $invoice);
            } else {
                $subscription->update([
                    'open_invoice_id' => null,
                    'open_invoice_url' => null,
                ]);
            }

            return;
        }

        $this->switchToOngoingPrice($subscription, (string) $course->stripe_ongoing_price_id);
        $subscription->update([
            'stripe_price_id' => $course->stripe_ongoing_price_id,
            'open_invoice_id' => null,
            'open_invoice_url' => null,
            'grace_ends_at' => null,
        ]);
    }

    private function remainLocked(Subscription $subscription, ?object $invoice): void
    {
        $subscription->update([
            'status' => SubscriptionStatus::PAST_DUE,
            'grace_ends_at' => null,
            'open_invoice_id' => $invoice ? (StripeInvoiceIds::invoiceId($invoice) ?: $subscription->open_invoice_id) : $subscription->open_invoice_id,
            'open_invoice_url' => $this->invoiceUrl($invoice) ?: $subscription->open_invoice_url,
        ]);

        $this->suspendEnrollment($subscription);
    }

    private function suspendEnrollment(Subscription $subscription): void
    {
        CourseEnrollment::query()
            ->where('subscription_id', $subscription->id)
            ->update([
                'access_status' => EnrollmentAccessStatus::SUSPENDED,
                'suspended_at' => now(),
            ]);
    }

    private function switchToOngoingPrice(Subscription $subscription, string $ongoingPriceId): void
    {
        if ($ongoingPriceId === '') {
            return;
        }

        $this->stripeCustomer->configureStripe();

        $stripeSubscription = StripeSubscription::retrieve($subscription->stripe_subscription_id);
        $item = $stripeSubscription->items->data[0] ?? null;
        $itemId = $item->id ?? null;
        $currentPriceId = $item->price->id ?? null;

        if (! $itemId || $currentPriceId === $ongoingPriceId) {
            return;
        }

        StripeSubscription::update($subscription->stripe_subscription_id, [
            'items' => [
                [
                    'id' => $itemId,
                    'price' => $ongoingPriceId,
                ],
            ],
            'proration_behavior' => 'none',
        ]);
    }

    /**
     * @return array{ok: bool, invoice: object|null}
     */
    private function firstOpenInvoice(string $stripeSubscriptionId): array
    {
        if ($stripeSubscriptionId === '') {
            return ['ok' => false, 'invoice' => null];
        }

        try {
            $this->stripeCustomer->configureStripe();
            $invoices = Invoice::all([
                'subscription' => $stripeSubscriptionId,
                'status' => 'open',
                'limit' => 1,
            ]);
        } catch (\Throwable) {
            return ['ok' => false, 'invoice' => null];
        }

        return ['ok' => true, 'invoice' => $invoices->data[0] ?? null];
    }

    private function invoiceUrl(?object $invoice): ?string
    {
        $url = trim((string) ($invoice->hosted_invoice_url ?? ''));

        return $url !== '' ? $url : null;
    }
}
