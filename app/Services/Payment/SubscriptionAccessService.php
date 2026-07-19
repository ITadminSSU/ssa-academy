<?php

namespace App\Services\Payment;

use App\Models\Course\Course;
use App\Models\Course\CourseEnrollment;
use App\Models\Course\WatchHistory;
use App\Models\User;

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
        if ($user->role === 'admin') {
            return 'full';
        }

        if ($user->role === 'instructor' && (int) $user->instructor_id === (int) $course->instructor_id) {
            return 'full';
        }

        if ($user->qualifiesForFreeCourseAccess()) {
            return 'full';
        }

        $enrollment ??= $this->resolveEnrollment($user, $course);

        if (!$enrollment) {
            return 'none';
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
        $completedItems = json_decode($watchHistory->completed_watching, true) ?: [];

        foreach ($completedItems as $item) {
            if ((string) $item['id'] === (string) $itemId && $item['type'] === $itemType) {
                return true;
            }
        }

        return false;
    }

    public function canAccessPlayer(User $user, Course $course, ?CourseEnrollment $enrollment = null): bool
    {
        return $this->getAccessMode($user, $course, $enrollment) !== 'none';
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

        if ($mode === 'none') {
            return false;
        }

        if ($mode === 'full') {
            return true;
        }

        if (!$watchHistory) {
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

        if (!$course || !$course->usesSubscriptionBilling()) {
            return true;
        }

        if ($user->qualifiesForFreeCourseAccess()) {
            return true;
        }

        $enrollment = $this->resolveEnrollment($user, $course);

        return $enrollment !== null && $enrollment->hasFullAccess();
    }

    public function toFrontendPayload(User $user, Course $course, ?CourseEnrollment $enrollment = null): array
    {
        $enrollment ??= $this->resolveEnrollment($user, $course);

        if ($enrollment && !$enrollment->relationLoaded('subscription')) {
            $enrollment->load('subscription');
        }

        $mode = $this->getAccessMode($user, $course, $enrollment);

        return [
            'mode' => $mode,
            'can_mark_progress' => $mode === 'full',
            'can_submit_assignments' => $mode === 'full',
            'can_finish_course' => $mode === 'full',
            'can_resubscribe' => $mode === 'completed_only' && $course->usesSubscriptionBilling(),
            'is_subscription_course' => $course->usesSubscriptionBilling(),
            'access_status' => $enrollment?->access_status?->value,
            'subscription_status' => $enrollment?->subscription?->status?->value,
        ];
    }
}
