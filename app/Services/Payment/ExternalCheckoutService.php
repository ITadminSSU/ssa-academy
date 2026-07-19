<?php

namespace App\Services\Payment;

use App\Enums\EnrollmentAccessStatus;
use App\Enums\CourseAudience;
use App\Models\Course\Course;
use App\Models\Course\CourseEnrollment;
use App\Models\User;
use Illuminate\Support\Collection;

class ExternalCheckoutService
{
    public function requiresPaidCheckout(User $user, Course $course): bool
    {
        if ($user->qualifiesForFreeCourseAccess()) {
            return false;
        }

        if ($course->pricing_type !== 'paid') {
            return false;
        }

        if ($course->audience === CourseAudience::INTERNAL) {
            return false;
        }

        return true;
    }

    public function userCanAccessCheckoutCourse(User $user, Course $course): bool
    {
        if ($course->audience === CourseAudience::INTERNAL && !$user->isEmployeeLearner()) {
            return false;
        }

        return $course->isVisibleToUser($user);
    }

    public function isAlreadyEnrolled(User $user, Course $course): bool
    {
        return CourseEnrollment::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->exists();
    }

    public function hasActiveCourseAccess(User $user, Course $course): bool
    {
        $enrollment = CourseEnrollment::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->with('subscription')
            ->first();

        if (!$enrollment) {
            return false;
        }

        if ($course->usesSubscriptionBilling()) {
            if ($enrollment->subscription) {
                return $enrollment->subscription->grantsFullAccess();
            }

            return $enrollment->access_status === EnrollmentAccessStatus::ACTIVE;
        }

        return $enrollment->access_status !== EnrollmentAccessStatus::SUSPENDED;
    }

    public function requiresSubscriptionCheckout(User $user, Course $course): bool
    {
        return $this->requiresPaidCheckout($user, $course) && $course->usesSubscriptionBilling();
    }

    public function canPurchaseCourse(User $user, Course $course): bool
    {
        if (!$this->requiresPaidCheckout($user, $course)) {
            return false;
        }

        return !$this->hasActiveCourseAccess($user, $course);
    }

    /**
     * External learners see Stripe (sandbox) first; admins/instructors keep all gateways.
     */
    public function filterGatewaysForCheckout(Collection $payments, ?User $user): Collection
    {
        if (!$user || $user->qualifiesForFreeCourseAccess()) {
            return $payments;
        }

        $primaryGateways = ['stripe', 'bank_transfer', 'wire_transfer', config('payment.external_primary_gateway', 'stripe')];

        $filtered = $payments->filter(function ($payment) use ($primaryGateways) {
            return in_array($payment->sub_type, array_unique($primaryGateways), true)
                && ($payment->fields['active'] ?? false);
        });

        if ($filtered->isNotEmpty()) {
            return $filtered->values();
        }

        return $payments->filter(fn ($payment) => $payment->fields['active'] ?? false)->values();
    }

    public function hasActiveGateway(Collection $payments): bool
    {
        return $payments->contains(fn ($payment) => $payment->fields['active'] ?? false);
    }
}
