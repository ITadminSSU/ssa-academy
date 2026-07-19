<?php

namespace App\Services\Course;

use App\Models\Course\Course;
use App\Models\Course\WatchHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Exam\Models\Exam;
use Modules\Exam\Models\ExamEnrollment;
use Modules\Exam\Services\ExamEnrollmentService;

class CourseFinalExamService
{
    public function __construct(
        private CoursePlayerService $coursePlayer,
        private ExamEnrollmentService $examEnrollment,
    ) {}

    public function getSelectableExams(User $user, ?Course $course = null): Collection
    {
        $query = Exam::query()
            ->where('status', 'published')
            ->orderBy('title');

        if (!isAdmin()) {
            $instructorId = $course?->instructor_id ?? $user->instructor_id;
            $query->where('instructor_id', $instructorId);
        }

        return $query->get(['id', 'title', 'slug', 'instructor_id']);
    }

    public function assertValidFinalExamLink(?int $examId, Course $course): void
    {
        if (!$examId) {
            return;
        }

        $exam = Exam::find($examId);

        if (!$exam) {
            throw ValidationException::withMessages([
                'final_exam_id' => 'The selected exam does not exist.',
            ]);
        }

        if (!isAdmin() && (int) $exam->instructor_id !== (int) $course->instructor_id) {
            throw ValidationException::withMessages([
                'final_exam_id' => 'The final exam must belong to the same instructor as this course.',
            ]);
        }
    }

    public function isCourseComplete(Course $course, int $userId): bool
    {
        $watchHistory = WatchHistory::query()
            ->where('course_id', $course->id)
            ->where('user_id', $userId)
            ->first();

        if (!$watchHistory) {
            return false;
        }

        $completion = $this->coursePlayer->calculateCompletion($course, $watchHistory);

        return (float) ($completion['completion'] ?? 0) >= 100;
    }

    public function userCompletedLinkedCourse(int $examId, int $userId): bool
    {
        $course = Course::query()
            ->where('final_exam_id', $examId)
            ->first();

        if (!$course) {
            return false;
        }

        return $this->isCourseComplete($course, $userId);
    }

    public function ensureFinalExamEnrollment(Course $course, User $user): ?ExamEnrollment
    {
        if (!$course->final_exam_id) {
            return null;
        }

        if (!$this->isCourseComplete($course, $user->id)) {
            return null;
        }

        $existing = ExamEnrollment::query()
            ->where('user_id', $user->id)
            ->where('exam_id', $course->final_exam_id)
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->examEnrollment->createExamEnroll([
            'user_id' => $user->id,
            'exam_id' => $course->final_exam_id,
            'enrollment_type' => 'free',
        ]);
    }

    public function ensureEnrollmentForExam(int $examId, User $user): ?ExamEnrollment
    {
        $course = Course::query()
            ->where('final_exam_id', $examId)
            ->first();

        if (!$course) {
            return null;
        }

        return $this->ensureFinalExamEnrollment($course, $user);
    }
}
