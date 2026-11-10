<?php

namespace App\Services\Payment;

use App\Enums\EnrollmentAccessStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Course\CourseEnrollment;
use App\Models\StripeWebhookEvent;
use App\Models\Subscription;
use App\Support\ChoiceBilling;
use App\Support\StripeInvoiceIds;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Subscription as StripeSubscription;

class ChoiceInstallmentService
{
    public function __construct(private StripeCustomerService $stripeCustomer) {}

    public function recordPaidInvoice(object $invoice): void
    {
        $subscriptionId = StripeInvoiceIds::subscriptionId($invoice);

        if ($subscriptionId === '') {
            return;
        }

        $subscription = Subscription::query()
            ->where('stripe_subscription_id', $subscriptionId)
            ->first();
        $course = $subscription?->course;

        if (! $subscription || ! $course?->usesOneTimeOrInstallment() || $subscription->choiceInstallmentFinished()) {
            return;
        }

        $invoiceId = StripeInvoiceIds::invoiceId($invoice);

        if ($invoiceId === '') {
            return;
        }

        $this->countInvoiceOnce($subscription, $invoiceId);
        $subscription->refresh();
        $this->finishIfComplete($subscription);
    }

    public function lockForFailedInvoice(object $invoice): void
    {
        $subscriptionId = StripeInvoiceIds::subscriptionId($invoice);

        if ($subscriptionId === '') {
            return;
        }

        $subscription = Subscription::query()
            ->where('stripe_subscription_id', $subscriptionId)
            ->first();

        if (! $subscription?->course?->usesOneTimeOrInstallment() || $subscription->choiceInstallmentFinished()) {
            return;
        }

        $subscription->update([
            'status' => SubscriptionStatus::PAST_DUE,
            'grace_ends_at' => null,
            'open_invoice_id' => StripeInvoiceIds::invoiceId($invoice) ?: null,
            'open_invoice_url' => is_string($invoice->hosted_invoice_url ?? null) ? $invoice->hosted_invoice_url : $subscription->open_invoice_url,
        ]);

        CourseEnrollment::query()
            ->where('subscription_id', $subscription->id)
            ->update([
                'access_status' => EnrollmentAccessStatus::SUSPENDED,
                'suspended_at' => now(),
            ]);
    }

    public function finishIfComplete(Subscription $subscription): bool
    {
        $subscription->loadMissing('course');
        $course = $subscription->course;

        if (! $course?->usesOneTimeOrInstallment() || $subscription->choiceInstallmentFinished()) {
            return false;
        }

        if (! ChoiceBilling::shouldFinishInstallment(
            (int) $subscription->installment_paid_count,
            (int) $course->installment_months,
        )) {
            return false;
        }

        $subscription->update([
            'choice_installment_completed_at' => now(),
        ]);

        CourseEnrollment::query()
            ->where('subscription_id', $subscription->id)
            ->update([
                'enrollment_type' => 'paid',
                'access_status' => EnrollmentAccessStatus::ACTIVE,
                'suspended_at' => null,
            ]);

        $this->cancelStripeSubscription((string) $subscription->stripe_subscription_id);

        return true;
    }

    private function countInvoiceOnce(Subscription $subscription, string $invoiceId): void
    {
        $markerId = 'choice-installment:'.$invoiceId;

        try {
            DB::transaction(function () use ($subscription, $markerId) {
                if (StripeWebhookEvent::query()->whereKey($markerId)->exists()) {
                    return;
                }

                StripeWebhookEvent::query()->create([
                    'id' => $markerId,
                    'type' => 'choice_installment.invoice_counted',
                    'processed_at' => now(),
                ]);

                $subscription->increment('installment_paid_count');
            });
        } catch (UniqueConstraintViolationException) {
            // A repeated webhook for the same invoice does not count twice.
        }
    }

    private function cancelStripeSubscription(string $subscriptionId): void
    {
        if ($subscriptionId === '') {
            return;
        }

        try {
            $this->stripeCustomer->configureStripe();
            $stripeSubscription = StripeSubscription::retrieve($subscriptionId);

            if (($stripeSubscription->status ?? '') !== 'canceled') {
                $stripeSubscription->cancel();
            }
        } catch (\Throwable $exception) {
            Log::warning('Choice installment could not be canceled in Stripe', [
                'stripe_subscription_id' => $subscriptionId,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
