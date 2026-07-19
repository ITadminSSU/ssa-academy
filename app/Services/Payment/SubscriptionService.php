<?php

namespace App\Services\Payment;

use App\Enums\EnrollmentAccessStatus;
use App\Enums\PaymentBillingType;
use App\Enums\SubscriptionStatus;
use App\Models\Course\CourseEnrollment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Course\CourseEnrollmentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\PaymentGateways\Services\PaymentService;
use Stripe\Subscription as StripeSubscription;

class SubscriptionService
{
    public function __construct(
        private CourseEnrollmentService $courseEnrollment,
        private PaymentService $paymentService,
        private StripeCustomerService $stripeCustomer,
    ) {}

    public function listForUser(User $user): array
    {
        return Subscription::query()
            ->where('user_id', $user->id)
            ->with([
                'course:id,title,slug,thumbnail,subscription_price,billing_model',
                'enrollment:id,course_id,user_id,access_status,subscription_id',
            ])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Subscription $subscription) => [
                'id' => $subscription->id,
                'status' => $subscription->status->value,
                'current_period_start' => $subscription->current_period_start?->toIso8601String(),
                'current_period_end' => $subscription->current_period_end?->toIso8601String(),
                'cancel_at_period_end' => $subscription->cancel_at_period_end,
                'canceled_at' => $subscription->canceled_at?->toIso8601String(),
                'grace_ends_at' => $subscription->grace_ends_at?->toIso8601String(),
                'grants_full_access' => $subscription->grantsFullAccess(),
                'access_status' => $subscription->enrollment?->access_status?->value,
                'course' => $subscription->course,
            ])
            ->values()
            ->all();
    }

    public function userCanManageBilling(User $user): bool
    {
        if (!empty($user->stripe_customer_id)) {
            return true;
        }

        return Subscription::query()->where('user_id', $user->id)->exists();
    }

    public function activateFromCheckoutSession(object $session): Subscription
    {
        if ($session->mode !== 'subscription' || empty($session->subscription)) {
            throw new \InvalidArgumentException('Checkout session is not a subscription.');
        }

        $this->stripeCustomer->configureStripe();

        $stripeSubscription = StripeSubscription::retrieve($session->subscription);

        return $this->syncFromStripeSubscription($stripeSubscription, [
            'user_id' => (int) ($session->metadata['user_id'] ?? $session->client_reference_id),
            'course_id' => (int) ($session->metadata['item_id'] ?? $stripeSubscription->metadata['course_id'] ?? 0),
        ]);
    }

    public function syncFromStripeSubscription(object $stripeSubscription, ?array $context = null): Subscription
    {
        return DB::transaction(function () use ($stripeSubscription, $context) {
            $userId = (int) ($context['user_id'] ?? $stripeSubscription->metadata['user_id'] ?? 0);
            $courseId = (int) ($context['course_id'] ?? $stripeSubscription->metadata['course_id'] ?? 0);

            if (!$userId || !$courseId) {
                throw new \RuntimeException('Subscription metadata is missing user_id or course_id.');
            }

            $status = $this->mapStripeStatus($stripeSubscription->status);
            $attributes = $this->buildSubscriptionAttributes($stripeSubscription, $status);

            $subscription = Subscription::query()->updateOrCreate(
                ['stripe_subscription_id' => $stripeSubscription->id],
                array_merge($attributes, [
                    'user_id' => $userId,
                    'course_id' => $courseId,
                ])
            );

            $this->syncEnrollment($subscription->fresh());

            return $subscription->fresh(['enrollment', 'course', 'user']);
        });
    }

    public function suspend(Subscription $subscription): void
    {
        DB::transaction(function () use ($subscription) {
            $subscription->update([
                'status' => SubscriptionStatus::CANCELED,
                'canceled_at' => $subscription->canceled_at ?? now(),
                'grace_ends_at' => null,
            ]);

            $this->setEnrollmentAccess($subscription, EnrollmentAccessStatus::SUSPENDED);
        });
    }

    public function handlePaymentFailed(object $stripeSubscription): void
    {
        $subscription = Subscription::query()
            ->where('stripe_subscription_id', $stripeSubscription->id)
            ->first();

        if (!$subscription) {
            return;
        }

        $graceDays = (int) config('payment.subscription.grace_days', 3);

        $subscription->update([
            'status' => SubscriptionStatus::PAST_DUE,
            'grace_ends_at' => $subscription->grace_ends_at ?? now()->addDays($graceDays),
        ]);

        $this->syncEnrollment($subscription->fresh());
    }

    public function handleInvoicePaymentSucceeded(object $invoice): void
    {
        if (empty($invoice->subscription)) {
            return;
        }

        $this->stripeCustomer->configureStripe();

        $stripeSubscription = StripeSubscription::retrieve($invoice->subscription);
        $subscription = $this->syncFromStripeSubscription($stripeSubscription);

        $billingType = ($invoice->billing_reason ?? '') === 'subscription_create'
            ? PaymentBillingType::SUBSCRIPTION
            : PaymentBillingType::SUBSCRIPTION_RENEWAL;

        $this->paymentService->recordSubscriptionPayment(
            $subscription,
            (string) ($invoice->payment_intent ?: $invoice->id),
            round(($invoice->amount_paid ?? 0) / 100, 2),
            $this->extractTaxAmount($invoice),
            $billingType,
        );
    }

    protected function syncEnrollment(Subscription $subscription): CourseEnrollment
    {
        $accessStatus = $subscription->grantsFullAccess()
            ? EnrollmentAccessStatus::ACTIVE
            : EnrollmentAccessStatus::SUSPENDED;

        $enrollment = CourseEnrollment::query()
            ->where('user_id', $subscription->user_id)
            ->where('course_id', $subscription->course_id)
            ->first();

        if ($enrollment) {
            $enrollment->update([
                'enrollment_type' => 'subscription',
                'access_status' => $accessStatus,
                'subscription_id' => $subscription->id,
                'suspended_at' => $accessStatus === EnrollmentAccessStatus::SUSPENDED ? now() : null,
            ]);

            return $enrollment->fresh();
        }

        return $this->courseEnrollment->createCourseEnroll([
            'user_id' => $subscription->user_id,
            'course_id' => $subscription->course_id,
            'enrollment_type' => 'subscription',
            'access_status' => $accessStatus,
            'subscription_id' => $subscription->id,
        ]);
    }

    protected function setEnrollmentAccess(Subscription $subscription, EnrollmentAccessStatus $status): void
    {
        CourseEnrollment::query()
            ->where('subscription_id', $subscription->id)
            ->update([
                'access_status' => $status,
                'suspended_at' => $status === EnrollmentAccessStatus::SUSPENDED ? now() : null,
            ]);
    }

    protected function buildSubscriptionAttributes(object $stripeSubscription, SubscriptionStatus $status): array
    {
        $priceId = $stripeSubscription->items->data[0]->price->id ?? null;

        $attributes = [
            'stripe_customer_id' => (string) $stripeSubscription->customer,
            'stripe_price_id' => $priceId ?: '',
            'status' => $status,
            'current_period_start' => $this->timestampToCarbon($stripeSubscription->current_period_start ?? null),
            'current_period_end' => $this->timestampToCarbon($stripeSubscription->current_period_end ?? null),
            'cancel_at_period_end' => (bool) ($stripeSubscription->cancel_at_period_end ?? false),
            'canceled_at' => $this->timestampToCarbon($stripeSubscription->canceled_at ?? null),
        ];

        if ($status === SubscriptionStatus::ACTIVE || $status === SubscriptionStatus::TRIALING) {
            $attributes['grace_ends_at'] = null;
        }

        return $attributes;
    }

    protected function mapStripeStatus(string $status): SubscriptionStatus
    {
        return match ($status) {
            'trialing' => SubscriptionStatus::TRIALING,
            'active' => SubscriptionStatus::ACTIVE,
            'past_due' => SubscriptionStatus::PAST_DUE,
            'canceled' => SubscriptionStatus::CANCELED,
            'unpaid' => SubscriptionStatus::UNPAID,
            'paused' => SubscriptionStatus::PAUSED,
            default => SubscriptionStatus::ACTIVE,
        };
    }

    protected function timestampToCarbon(mixed $timestamp): ?Carbon
    {
        if (empty($timestamp)) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $timestamp);
    }

    protected function extractTaxAmount(object $invoice): float
    {
        $tax = 0;

        foreach ($invoice->total_tax_amounts ?? [] as $taxAmount) {
            $tax += ($taxAmount->amount ?? 0) / 100;
        }

        return round($tax, 2);
    }
}
