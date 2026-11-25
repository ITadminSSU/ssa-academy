<?php

namespace App\Services\Payment;

use App\Models\Subscription;
use Stripe\Subscription as StripeSubscription;

class StripeSubscriptionCourseAssigner
{
    public function __construct(private StripeCustomerService $stripeCustomer) {}

    /**
     * Point the existing Stripe subscription at another course.
     * The subscription id, customer, and price stay as they are.
     */
    public function assignCourse(Subscription $subscription, int $courseId): void
    {
        if (! filled($subscription->stripe_subscription_id)) {
            return;
        }

        $this->stripeCustomer->configureStripe();

        StripeSubscription::update($subscription->stripe_subscription_id, [
            'metadata' => [
                'course_id' => (string) $courseId,
            ],
        ]);
    }
}
