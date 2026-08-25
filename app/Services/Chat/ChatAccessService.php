<?php

namespace App\Services\Chat;

use App\Models\Course\Course;
use App\Models\Course\CourseEnrollment;
use App\Models\User;
use App\Services\Payment\SubscriptionAccessService;

class ChatAccessService
{
    public function __construct(private SubscriptionAccessService $subscriptionAccess) {}

    public function canAccessCourseMessaging(User $user, ?Course $course, ?CourseEnrollment $enrollment = null): bool
    {
        if (! $course) {
            return false;
        }

        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role === 'instructor' && (int) $user->instructor_id === (int) $course->instructor_id) {
            return true;
        }

        $enrollment ??= $this->subscriptionAccess->resolveEnrollment($user, $course);

        return $enrollment && $this->subscriptionAccess->canAccessPlayer($user, $course, $enrollment);
    }

    public function isCourseInstructor(User $user, ?Course $course): bool
    {
        if (! $course) {
            return false;
        }

        return $user->role === 'instructor' && (int) $user->instructor_id === (int) $course->instructor_id;
    }

    public function isAdmin(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function canModerate(User $user, ?Course $course): bool
    {
        if (! $course) {
            return $this->isAdmin($user);
        }

        return $this->isAdmin($user) || $this->isCourseInstructor($user, $course);
    }
}
