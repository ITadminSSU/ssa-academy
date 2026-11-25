<?php

namespace App\Services\Payment;

use App\Enums\EnrollmentAccessStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Course\CourseEnrollment;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Stripe\Exception\InvalidRequestException;
use Stripe\Subscription as StripeSubscription;

class RetainedAccessService
{
    public function __construct(private StripeCustomerService $stripeCustomer) {}

    public function keepCourse(CourseEnrollment $enrollment): CourseEnrollment
    {
        $enrollment->loadMissing('subscription');
        $subscription = $enrollment->subscription;

        if (! $subscription) {
            throw new \RuntimeException('This enrollment has no Stripe subscription to stop.');
        }

        $this->stopStripeBilling($subscription);

        DB::transaction(function () use ($subscription, $enrollment) {
            if ($subscription->access_retained_at === null) {
                $subscription->update(['access_retained_at' => now()]);
            }

            $enrollment->update([
                'enrollment_type' => 'paid',
                'access_status' => EnrollmentAccessStatus::ACTIVE,
                'suspended_at' => null,
            ]);

            $subscription->update([
                'status' => SubscriptionStatus::CANCELED,
                'canceled_at' => $subscription->canceled_at ?? now(),
                'cancel_at_period_end' => false,
                'grace_ends_at' => null,
            ]);
        });

        return $enrollment->fresh(['subscription']);
    }

    private function stopStripeBilling(Subscription $subscription): void
    {
        if ($subscription->stripe_subscription_id === null || $subscription->stripe_subscription_id === '') {
            return;
        }

        if ($subscription->status === SubscriptionStatus::CANCELED) {
            return;
        }

        $this->stripeCustomer->configureStripe();

        try {
            $stripeSubscription = StripeSubscription::retrieve($subscription->stripe_subscription_id);

            if (($stripeSubscription->status ?? '') !== 'canceled') {
                $stripeSubscription->cancel();
            }
        } catch (InvalidRequestException $exception) {
            if ($this->subscriptionIsGone($exception)) {
                return;
            }

            throw $exception;
        }
    }

    private function subscriptionIsGone(InvalidRequestException $exception): bool
    {
        if ($exception->getStripeCode() === 'resource_missing' || $exception->getHttpStatus() === 404) {
            return true;
        }

        return str_contains(strtolower($exception->getMessage()), 'no such subscription');
    }
}
