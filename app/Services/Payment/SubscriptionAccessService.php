<?php

namespace App\Services\Payment;

use App\Enums\EnrollmentAccessStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Course\Course;
use App\Models\Course\CourseEnrollment;
use App\Models\Course\WatchHistory;
use App\Models\User;
use App\Support\InstallmentBillingRules;

class SubscriptionAccessService
{
    public function resolveEnrollment(User $user, Course $course): ?CourseEnrollment
    {
        return CourseEnrollment::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->with('subscription')
            ->first();
    }

    public function getAccessMode(User $user, Course $course, ?CourseEnrollment $enrollment = null): string
    {
        if ($course->isStaffPreviewer($user)) {
            return 'full';
        }

        if ($user->qualifiesForFreeCourseAccess()) {
            return 'full';
        }

        $enrollment ??= $this->resolveEnrollment($user, $course);

        if (! $enrollment) {
            return 'none';
        }

        if ($enrollment->isReservedSeat() || $enrollment->isCanceled()) {
            return 'none';
        }

        if ($enrollment->pef_granted) {
            if ($enrollment->access_status !== EnrollmentAccessStatus::ACTIVE) {
                return 'none';
            }

            return $this->pefWindowEnded($user) ? 'completed_only' : 'full';
        }

        $enrollment->loadMissing('subscription');

        if ($enrollment->subscription?->accessRetained()) {
            return 'full';
        }

        if ($course->usesOneTimeOrInstallment()) {
            if (! $enrollment->relationLoaded('subscription')) {
                $enrollment->load('subscription');
            }

            $subscription = $enrollment->subscription;

            if ($subscription?->choiceInstallmentFinished()) {
                return 'full';
            }

            if ($subscription) {
                return InstallmentBillingRules::accessMode($subscription->status) === 'full'
                    ? 'full'
                    : 'completed_only';
            }

            return $enrollment->access_status === EnrollmentAccessStatus::ACTIVE ? 'full' : 'none';
        }

        if ($course->usesOneTimeOrMonthly()) {
            if ($enrollment->hasFullAccess()) {
                return 'full';
            }

            if ($enrollment->isSuspended()) {
                return 'completed_only';
            }

            return 'none';
        }

        if ($course->usesInstallmentSubscription()) {
            if (! $enrollment->relationLoaded('subscription')) {
                $enrollment->load('subscription');
            }

            if (! $enrollment->subscription) {
                return 'installment_locked';
            }

            return InstallmentBillingRules::accessMode($enrollment->subscription->status) === 'full'
                ? 'full'
                : 'completed_only';
        }

        if (! $course->usesSubscriptionBilling()) {
            return $this->oneTimeAccessMode($enrollment);
        }

        if ($enrollment->hasFullAccess()) {
            return 'full';
        }

        if ($enrollment->isSuspended()) {
            return 'completed_only';
        }

        return 'none';
    }

    public function isItemCompleted(WatchHistory $watchHistory, int|string $itemId, string $itemType): bool
    {
        foreach ($watchHistory->getCompletedWatchingItems() as $item) {
            if ((string) $item['id'] === (string) $itemId && $item['type'] === $itemType) {
                return true;
            }
        }

        return false;
    }

    public function canAccessPlayer(User $user, Course $course, ?CourseEnrollment $enrollment = null): bool
    {
        $mode = $this->getAccessMode($user, $course, $enrollment);

        return $mode === 'full' || $mode === 'completed_only';
    }

    /**
     * Public course-page curriculum (module/lesson titles). Same bar as player
     * access: guests, reserved deposits, and canceled seats cannot see it.
     * Active and completed_only (lapsed) enrollments, plus admins and this
     * course's instructor, can.
     */
    public function canViewPublicCurriculum(?User $user, Course $course, ?CourseEnrollment $enrollment = null): bool
    {
        if (! $user) {
            return false;
        }

        return $this->canAccessPlayer($user, $course, $enrollment);
    }

    /**
     * Trainer-authored US Experience plan titles/descriptions on the public
     * course page. The tab itself stays visible for estimating courses.
     * Guests, reserved seats, and staff preview without an enrollment do not
     * see the plan list.
     */
    public function canViewPublicUsExperience(?User $user, Course $course, ?CourseEnrollment $enrollment = null): bool
    {
        if (! $user || ! $enrollment) {
            return false;
        }

        if ($enrollment->isReservedSeat() || $enrollment->isCanceled()) {
            return false;
        }

        if ($course->usesSubscriptionBilling()) {
            return $enrollment->hasFullAccess() || $enrollment->isSuspended();
        }

        return $enrollment->access_status === EnrollmentAccessStatus::ACTIVE;
    }

    public function canAccessItem(
        User $user,
        Course $course,
        int|string $itemId,
        string $itemType,
        ?WatchHistory $watchHistory = null,
        ?CourseEnrollment $enrollment = null,
    ): bool {
        $mode = $this->getAccessMode($user, $course, $enrollment);

        if ($mode === 'none' || $mode === 'installment_locked') {
            return false;
        }

        if ($mode === 'full') {
            return true;
        }

        if (! $watchHistory) {
            return false;
        }

        return $this->isItemCompleted($watchHistory, $itemId, $itemType);
    }

    public function canMarkProgress(User $user, Course $course, ?CourseEnrollment $enrollment = null): bool
    {
        return $this->getAccessMode($user, $course, $enrollment) === 'full';
    }

    public function canSubmitAssignments(User $user, Course $course, ?CourseEnrollment $enrollment = null): bool
    {
        return $this->getAccessMode($user, $course, $enrollment) === 'full';
    }

    public function canFinishCourse(User $user, Course $course, ?CourseEnrollment $enrollment = null): bool
    {
        return $this->getAccessMode($user, $course, $enrollment) === 'full';
    }

    public function hasActiveSubscriptionForLinkedExam(User $user, int $examId): bool
    {
        $course = Course::query()
            ->where('final_exam_id', $examId)
            ->first();

        if (! $course || ! $course->usesSubscriptionBilling()) {
            return true;
        }

        if ($user->qualifiesForFreeCourseAccess()) {
            return true;
        }

        $enrollment = $this->resolveEnrollment($user, $course);

        if ($enrollment?->pef_granted && $this->pefWindowEnded($user)) {
            return false;
        }

        return $enrollment !== null && $enrollment->hasFullAccess();
    }

    public function progressDeniedMessage(User $user, Course $course, string $otherwise): string
    {
        $enrollment = $this->resolveEnrollment($user, $course);

        if ($enrollment?->pef_granted && $this->pefWindowEnded($user)) {
            return 'Your PEF access period has ended. Finished lessons, quizzes, and assignments stay available. Unfinished items stay locked.';
        }

        return $otherwise;
    }

    public function toFrontendPayload(User $user, Course $course, ?CourseEnrollment $enrollment = null): array
    {
        if ($enrollment === null && ! $course->isStaffPreviewer($user)) {
            $enrollment = $this->resolveEnrollment($user, $course);
        }

        if ($enrollment && ! $enrollment->relationLoaded('subscription')) {
            $enrollment->load('subscription');
        }

        $mode = $this->getAccessMode($user, $course, $enrollment);
        $pefLimited = $mode === 'completed_only' && (bool) $enrollment?->pef_granted;
        $invoiceUrl = $this->openInvoiceUrl($enrollment);

        return [
            'mode' => $mode,
            'can_mark_progress' => $mode === 'full',
            'can_submit_assignments' => $mode === 'full',
            'can_finish_course' => $mode === 'full',
            'can_resubscribe' => $mode === 'completed_only' && $course->usesSubscriptionBilling() && ! $pefLimited && $invoiceUrl === null,
            'pef_limited' => $pefLimited,
            'can_pay_invoice' => $invoiceUrl !== null,
            'invoice_url' => $invoiceUrl,
            'is_subscription_course' => $course->usesSubscriptionBilling(),
            'staff_preview' => $course->isStaffPreviewer($user),
            'access_status' => $enrollment?->access_status?->value,
            'subscription_status' => $enrollment?->subscription?->status?->value,
        ];
    }

    private function oneTimeAccessMode(CourseEnrollment $enrollment): string
    {
        $subscription = $enrollment->subscription;

        if ($subscription?->choiceInstallmentFinished() || $subscription?->accessRetained()) {
            return 'full';
        }

        if ($subscription?->status === SubscriptionStatus::ACTIVE || $subscription?->status === SubscriptionStatus::TRIALING) {
            return 'full';
        }

        if ($subscription) {
            return 'completed_only';
        }

        return $enrollment->access_status === EnrollmentAccessStatus::ACTIVE ? 'full' : 'none';
    }

    private function openInvoiceUrl(?CourseEnrollment $enrollment): ?string
    {
        $subscription = $enrollment?->subscription;

        if (
            ! $subscription
            || $subscription->accessRetained()
            || $subscription->choiceInstallmentFinished()
            || $subscription->status === SubscriptionStatus::ACTIVE
            || $subscription->status === SubscriptionStatus::TRIALING
        ) {
            return null;
        }

        $url = $subscription->open_invoice_url;

        return is_string($url) && $url !== '' ? $url : null;
    }

    private function pefWindowEnded(User $user): bool
    {
        $endsAt = $user->pef_access_ends_at;

        return $endsAt !== null && now()->greaterThanOrEqualTo($endsAt);
    }
}
