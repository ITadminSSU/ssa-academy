<?php

namespace App\Services\Course;

use App\Enums\EnrollmentAccessStatus;
use App\Models\Course\Course;
use App\Models\Course\CourseEnrollment;
use App\Services\SettingsService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;

class CompanionCourseEnrollmentService
{
    private bool $granting = false;

    public function __construct(
        private CourseEnrollmentService $courseEnrollment,
        private SettingsService $settings,
    ) {}

    public function isEnabled(): bool
    {
        return $this->companionCourseId() !== null;
    }

    public function companionCourseId(): ?int
    {
        $fields = $this->systemFields();

        if (! filter_var($fields['companion_auto_enroll_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        $courseId = $fields['companion_course_id'] ?? null;

        if ($courseId === null || $courseId === '' || (int) $courseId <= 0) {
            return null;
        }

        return (int) $courseId;
    }

    /**
     * Companion course to mention on a source-course welcome email.
     */
    public function welcomeCompanionCourse(?Course $sourceCourse): ?Course
    {
        $companionCourseId = $this->companionCourseId();

        if (! $companionCourseId) {
            return null;
        }

        if ($sourceCourse && (int) $sourceCourse->getKey() === $companionCourseId) {
            return null;
        }

        return Course::query()->find($companionCourseId);
    }

    /**
     * Grant the companion course when the learner has real access to another course.
     */
    public function grantForEnrollment(CourseEnrollment $enrollment): ?CourseEnrollment
    {
        if ($this->granting) {
            return null;
        }

        $companionCourseId = $this->companionCourseId();

        if (! $companionCourseId) {
            return null;
        }

        if ((int) $enrollment->course_id === $companionCourseId) {
            return null;
        }

        if (! $this->sourceGrantsCompanion($enrollment)) {
            return null;
        }

        return $this->grantToUser((int) $enrollment->user_id, $companionCourseId);
    }

    /**
     * Enroll every learner who already has ACTIVE access to another course.
     *
     * @return array{granted: int, skipped: int, dry_run: bool}
     */
    public function grantForExistingLearners(bool $dryRun = false): array
    {
        $companionCourseId = $this->companionCourseId();
        $granted = 0;
        $skipped = 0;

        if (! $companionCourseId) {
            return ['granted' => 0, 'skipped' => 0, 'dry_run' => $dryRun];
        }

        $userIds = CourseEnrollment::query()
            ->where('access_status', EnrollmentAccessStatus::ACTIVE->value)
            ->where('course_id', '!=', $companionCourseId)
            ->distinct()
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $alreadyActive = CourseEnrollment::query()
                ->where('user_id', $userId)
                ->where('course_id', $companionCourseId)
                ->where('access_status', EnrollmentAccessStatus::ACTIVE->value)
                ->exists();

            if ($alreadyActive) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $granted++;
                continue;
            }

            $result = $this->grantToUser((int) $userId, $companionCourseId);

            if ($result) {
                $granted++;
            } else {
                $skipped++;
            }
        }

        return ['granted' => $granted, 'skipped' => $skipped, 'dry_run' => $dryRun];
    }

    public function grantToUser(int $userId, ?int $companionCourseId = null): ?CourseEnrollment
    {
        $companionCourseId ??= $this->companionCourseId();

        if (! $companionCourseId || $this->granting) {
            return null;
        }

        $existing = CourseEnrollment::query()
            ->where('user_id', $userId)
            ->where('course_id', $companionCourseId)
            ->first();

        if ($existing) {
            if ($existing->access_status === EnrollmentAccessStatus::ACTIVE) {
                return $existing;
            }

            $existing->update([
                'access_status' => EnrollmentAccessStatus::ACTIVE->value,
                'enrollment_type' => 'free',
                'forfeited_at' => null,
                'suspended_at' => null,
            ]);

            return $existing->fresh();
        }

        if (! Course::query()->whereKey($companionCourseId)->exists()) {
            Log::warning('Companion auto-enroll skipped: course not found', [
                'companion_course_id' => $companionCourseId,
                'user_id' => $userId,
            ]);

            return null;
        }

        $this->granting = true;

        try {
            return $this->courseEnrollment->createCourseEnroll([
                'user_id' => $userId,
                'course_id' => $companionCourseId,
                'enrollment_type' => 'free',
                'access_status' => EnrollmentAccessStatus::ACTIVE->value,
            ], allowBeforeLaunch: true, sendWelcome: false);
        } catch (UniqueConstraintViolationException $exception) {
            return CourseEnrollment::query()
                ->where('user_id', $userId)
                ->where('course_id', $companionCourseId)
                ->first();
        } catch (\Throwable $exception) {
            Log::warning('Companion auto-enroll failed', [
                'companion_course_id' => $companionCourseId,
                'user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);

            return null;
        } finally {
            $this->granting = false;
        }
    }

    private function sourceGrantsCompanion(CourseEnrollment $enrollment): bool
    {
        $status = $enrollment->access_status instanceof EnrollmentAccessStatus
            ? $enrollment->access_status
            : EnrollmentAccessStatus::tryFrom((string) $enrollment->access_status);

        return $status === EnrollmentAccessStatus::ACTIVE;
    }

    /**
     * @return array<string, mixed>
     */
    private function systemFields(): array
    {
        $setting = $this->settings->getSetting(['type' => 'system']);
        $fields = $setting?->fields;

        return is_array($fields) ? $fields : [];
    }
}
