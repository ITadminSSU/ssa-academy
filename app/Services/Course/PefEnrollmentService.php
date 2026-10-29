<?php

namespace App\Services\Course;

use App\Enums\EnrollmentAccessStatus;
use App\Enums\LearnerUserType;
use App\Mail\PefPackageMail;
use App\Models\Course\Course;
use App\Models\Course\CourseEnrollment;
use App\Models\User;
use App\Support\PefPackageSettings;
use App\Support\TransactionalMailSender;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PefEnrollmentService
{
    public function __construct(
        private PefPackageSettings $package,
        private CourseEnrollmentService $enrollments,
        private TransactionalMailSender $mail,
    ) {}

    public function syncTag(User $user, bool $tagged): void
    {
        if (! $tagged) {
            if ($user->pef_tagged) {
                $user->forceFill(['pef_tagged' => false])->save();
            }

            return;
        }

        if ($user->role !== 'student' || $user->user_type !== LearnerUserType::EXTERNAL) {
            throw ValidationException::withMessages([
                'pef_tagged' => 'Only an external student can be tagged PEF.',
            ]);
        }

        if (! $this->package->isReady()) {
            throw ValidationException::withMessages([
                'pef_tagged' => 'Turn on the PEF package and choose its courses before tagging a student.',
            ]);
        }

        $firstTag = $user->pef_tagged_at === null;
        $taggedAt = $firstTag ? now() : $user->pef_tagged_at;

        $user->forceFill([
            'pef_tagged' => true,
            'pef_tagged_at' => $taggedAt,
            'pef_access_ends_at' => $firstTag
                ? $this->package->endsAt($taggedAt)
                : $user->pef_access_ends_at,
        ])->save();

        $created = $this->enrollMissingCourses($user);

        if ($created === []) {
            return;
        }

        try {
            $this->mail->send(
                $user,
                new PefPackageMail($user, $created, $user->pef_access_ends_at),
                'PEF package email',
            );
        } catch (\Throwable $exception) {
            Log::warning('PEF package email failed', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return list<Course>
     */
    private function enrollMissingCourses(User $user): array
    {
        $created = [];

        foreach ($this->package->courseIds() as $courseId) {
            $exists = CourseEnrollment::query()
                ->where('user_id', $user->id)
                ->where('course_id', $courseId)
                ->exists();

            if ($exists || ! Course::query()->whereKey($courseId)->exists()) {
                continue;
            }

            $enrollment = $this->enrollments->createCourseEnroll([
                'user_id' => $user->id,
                'course_id' => $courseId,
                'enrollment_type' => 'free',
                'access_status' => EnrollmentAccessStatus::ACTIVE->value,
                'pef_granted' => true,
            ], allowBeforeLaunch: true, sendWelcome: false);

            $enrollment->loadMissing('course');

            if ($enrollment->course) {
                $created[] = $enrollment->course;
            }
        }

        return $created;
    }
}
