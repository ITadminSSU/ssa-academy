<?php

namespace App\Observers;

use App\Models\Course\CourseEnrollment;
use App\Services\Chat\ChatService;

class CourseEnrollmentObserver
{
    public function __construct(private ChatService $chat) {}

    public function created(CourseEnrollment $enrollment): void
    {
        $enrollment->loadMissing(['user', 'course']);
        if ($enrollment->user && $enrollment->course) {
            $this->chat->syncEnrollmentAccess($enrollment->user, $enrollment->course, $enrollment);
        }
    }

    public function updated(CourseEnrollment $enrollment): void
    {
        if (! $enrollment->wasChanged(['access_status', 'suspended_at', 'expiry_date'])) {
            return;
        }

        $enrollment->loadMissing(['user', 'course']);
        if ($enrollment->user && $enrollment->course) {
            $this->chat->syncEnrollmentAccess($enrollment->user, $enrollment->course, $enrollment);
        }
    }

    public function deleted(CourseEnrollment $enrollment): void
    {
        $enrollment->loadMissing(['user', 'course']);
        if ($enrollment->user && $enrollment->course) {
            $this->chat->syncEnrollmentAccess($enrollment->user, $enrollment->course, null);
        }
    }
}
