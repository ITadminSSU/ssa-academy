<?php

namespace App\Observers;

use App\Models\Course\CourseEnrollment;
use App\Services\Chat\ChatService;
use App\Services\Course\CompanionCourseEnrollmentService;
use Illuminate\Support\Facades\Log;

class CourseEnrollmentObserver
{
    public function __construct(
        private ChatService $chat,
        private CompanionCourseEnrollmentService $companionCourse,
    ) {}

    public function created(CourseEnrollment $enrollment): void
    {
        $enrollment->loadMissing(['user', 'course']);
        if ($enrollment->user && $enrollment->course) {
            $this->chat->syncEnrollmentAccess($enrollment->user, $enrollment->course, $enrollment);
        }

        $this->grantCompanionCourse($enrollment);
    }

    public function updated(CourseEnrollment $enrollment): void
    {
        if ($enrollment->wasChanged(['access_status', 'suspended_at', 'expiry_date'])) {
            $enrollment->loadMissing(['user', 'course']);
            if ($enrollment->user && $enrollment->course) {
                $this->chat->syncEnrollmentAccess($enrollment->user, $enrollment->course, $enrollment);
            }
        }

        if ($enrollment->wasChanged('access_status')) {
            $this->grantCompanionCourse($enrollment);
        }
    }

    public function deleted(CourseEnrollment $enrollment): void
    {
        $enrollment->loadMissing(['user', 'course']);
        if ($enrollment->user && $enrollment->course) {
            $this->chat->syncEnrollmentAccess($enrollment->user, $enrollment->course, null);
        }
    }

    private function grantCompanionCourse(CourseEnrollment $enrollment): void
    {
        try {
            $this->companionCourse->grantForEnrollment($enrollment);
        } catch (\Throwable $exception) {
            Log::warning('Companion auto-enroll observer failed', [
                'enrollment_id' => $enrollment->id,
                'user_id' => $enrollment->user_id,
                'course_id' => $enrollment->course_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
