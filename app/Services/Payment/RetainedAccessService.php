<?php

namespace App\Services\Payment;

use App\Enums\EnrollmentAccessStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Course\CourseEnrollment;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
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

        DB::transaction(function () use ($subscription, $enrollment) {
            if ($subscription->access_retained_at === null) {
                $subscription->update(['access_retained_at' => now()]);
            }

            $enrollment->update([
                'enrollment_type' => 'paid',
                'access_status' => EnrollmentAccessStatus::ACTIVE,
                'suspended_at' => null,
            ]);
        });

        $this->stopStripeBilling($subscription->fresh());

        $subscription->update([
            'status' => SubscriptionStatus::CANCELED,
            'canceled_at' => $subscription->canceled_at ?? now(),
            'cancel_at_period_end' => false,
            'grace_ends_at' => null,
        ]);

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
        $stripeSubscription = StripeSubscription::retrieve($subscription->stripe_subscription_id);

        if (($stripeSubscription->status ?? '') !== 'canceled') {
            $stripeSubscription->cancel();
        }
    }
}
