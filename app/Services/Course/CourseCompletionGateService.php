<?php

namespace App\Services\Course;

use App\Models\Course\AssignmentSampleDownload;
use App\Models\Course\AssignmentSubmission;
use App\Models\Course\Course;
use App\Models\Course\CourseAssignment;
use App\Models\Course\QuizSubmission;
use App\Models\Course\SectionQuiz;
use App\Models\Course\WatchHistory;
use App\Models\User;
use App\Services\Payment\SubscriptionAccessService;

class CourseCompletionGateService
{
    public function __construct(
        private LessonWatchProgressService $lessonWatchProgress,
        private SubscriptionAccessService $subscriptionAccess,
    ) {}

    /**
     * Linear workflow gates:
     * 1. Video phase — all lessons watched (videos at 100%).
     * 2. Assignment phase — sample downloaded, submitted, trainer Passed/Approved.
     * 3. Quiz phase — all quizzes passed.
     * 4. Certification — auto-issued when quizzes complete.
     */
    public function getGateStatus(Course $course, int $userId, ?array $completion = null, ?WatchHistory $watchHistory = null): array
    {
        $course->loadMissing(['assignments', 'sections.section_lessons']);

        $watchHistory ??= WatchHistory::query()
            ->where('course_id', $course->id)
            ->where('user_id', $userId)
            ->first();

        $quizzes = SectionQuiz::where('course_id', $course->id)->get();
        $hasAssignments = $course->assignments->isNotEmpty();
        $hasQuizzes = $quizzes->isNotEmpty();
        $hasVideoLessons = $this->lessonWatchProgress->getVideoLessons($course)->isNotEmpty();

        $videosCompleted = $watchHistory
            ? $this->lessonWatchProgress->allVideoLessonsWatched($course, $watchHistory)
            : !$hasVideoLessons;

        $allLessonsCompleted = $watchHistory
            ? $this->lessonWatchProgress->allLessonsCompleted($course, $watchHistory)
            : false;

        $assignmentsUnlocked = $videosCompleted;
        $assignmentsApproved = $this->hasApprovedAllAssignments($course, $userId);
        $assignmentsSubmitted = $this->hasSubmittedAllAssignments($course, $userId);

        $quizzesUnlocked = !$hasAssignments || $assignmentsApproved;
        $allQuizzesPassed = $this->hasPassedAllQuizzes($quizzes, $userId);

        $completionPercent = (float) ($completion['completion'] ?? 0);
        $certificateUnlocked = $hasQuizzes
            ? $allQuizzesPassed
            : ($allLessonsCompleted && (!$hasAssignments || $assignmentsApproved));

        $pendingAssignments = $hasAssignments && !$assignmentsApproved
            ? $this->countPendingAssignments($course, $userId)
            : 0;

        $currentPhase = $this->resolveCurrentPhase(
            $videosCompleted,
            $hasAssignments,
            $assignmentsApproved,
            $hasQuizzes,
            $allQuizzesPassed,
            $certificateUnlocked,
        );

        return [
            'current_phase' => $currentPhase,
            'has_video_lessons' => $hasVideoLessons,
            'has_assignments' => $hasAssignments,
            'has_quizzes' => $hasQuizzes,
            'videos_completed' => $videosCompleted,
            'assignments_unlocked' => $assignmentsUnlocked,
            'assignments_submitted' => $assignmentsSubmitted,
            'assignments_approved' => $assignmentsApproved,
            'quizzes_unlocked' => $quizzesUnlocked,
            'all_quizzes_passed' => $allQuizzesPassed,
            'certificate_unlocked' => $certificateUnlocked,
            'pending_assignments_count' => $pendingAssignments,
        ];
    }

    public function canAccessAssignmentTab(Course $course, int $userId, ?WatchHistory $watchHistory = null): bool
    {
        return $this->getGateStatus($course, $userId, null, $watchHistory)['assignments_unlocked'];
    }

    public function canAccessQuiz(
        Course $course,
        int $userId,
        int|string|null $quizId = null,
        ?WatchHistory $watchHistory = null,
    ): bool {
        if ($quizId && $this->hasPassedQuiz($userId, $quizId)) {
            return true;
        }

        $watchHistory ??= WatchHistory::query()
            ->where('course_id', $course->id)
            ->where('user_id', $userId)
            ->first();

        $user = User::query()->find($userId);

        if ($user) {
            $mode = $this->subscriptionAccess->getAccessMode($user, $course);

            if ($mode === 'none') {
                return false;
            }

            if ($mode === 'completed_only') {
                if (!$watchHistory || !$quizId) {
                    return false;
                }

                return $this->subscriptionAccess->isItemCompleted($watchHistory, $quizId, 'quiz');
            }
        }

        return $this->getGateStatus($course, $userId, null, $watchHistory)['quizzes_unlocked'];
    }

    public function canAccessCertificate(Course $course, int $userId, ?array $completion = null): bool
    {
        return $this->getGateStatus($course, $userId, $completion)['certificate_unlocked'];
    }

    public function canAccessLesson(Course $course, int $userId, int|string $lessonId, ?WatchHistory $watchHistory = null): bool
    {
        $course->loadMissing(['sections.section_lessons', 'sections.section_quizzes']);
        $watchHistory ??= WatchHistory::query()
            ->where('course_id', $course->id)
            ->where('user_id', $userId)
            ->first();

        $user = User::query()->find($userId);

        if ($user) {
            $mode = $this->subscriptionAccess->getAccessMode($user, $course);

            if ($mode === 'none') {
                return false;
            }

            if ($mode === 'completed_only') {
                if (!$watchHistory) {
                    return false;
                }

                return $this->subscriptionAccess->isItemCompleted($watchHistory, $lessonId, 'lesson');
            }
        }

        if (!$watchHistory) {
            return $this->isFirstLesson($course, $lessonId);
        }

        $allItems = $this->getOrderedCurriculumItems($course);
        $targetIndex = $allItems->search(
            fn ($item) => $item['type'] === 'lesson' && (string) $item['id'] === (string) $lessonId
        );

        if ($targetIndex === false) {
            return false;
        }

        if ($targetIndex === 0) {
            return true;
        }

        for ($i = 0; $i < $targetIndex; $i++) {
            $item = $allItems[$i];
            if (!$this->isCurriculumItemComplete($watchHistory, $item, $userId)) {
                return false;
            }
        }

        return true;
    }

    public function isAssignmentSubmissionAllowed(CourseAssignment $assignment, int $userId): bool
    {
        $course = $assignment->course()->with(['assignments', 'sections.section_lessons'])->firstOrFail();
        $user = User::query()->find($userId);

        if ($user && !$this->subscriptionAccess->canSubmitAssignments($user, $course)) {
            return false;
        }

        if (!$this->canAccessAssignmentTab($course, $userId)) {
            return false;
        }

        if (empty($assignment->sample_project_path)) {
            return true;
        }

        return AssignmentSampleDownload::query()
            ->where('user_id', $userId)
            ->where('course_assignment_id', $assignment->id)
            ->exists();
    }

    public function isSubmissionApproved(AssignmentSubmission $submission, CourseAssignment $assignment): bool
    {
        if (in_array($submission->status, ['passed', 'approved'], true)) {
            return true;
        }

        if ($submission->status === 'graded' && $submission->marks_obtained !== null) {
            return (float) $submission->marks_obtained >= (float) $assignment->pass_mark;
        }

        return false;
    }

    private function resolveCurrentPhase(
        bool $videosCompleted,
        bool $hasAssignments,
        bool $assignmentsApproved,
        bool $hasQuizzes,
        bool $allQuizzesPassed,
        bool $certificateUnlocked,
    ): string {
        if ($certificateUnlocked) {
            return 'completed';
        }

        if ($hasQuizzes && $assignmentsApproved) {
            return 'quiz';
        }

        if ($hasAssignments && $videosCompleted) {
            return 'assignment';
        }

        if (!$videosCompleted) {
            return 'video';
        }

        if ($hasQuizzes) {
            return 'quiz';
        }

        return 'certification';
    }

    private function hasSubmittedAllAssignments(Course $course, int $userId): bool
    {
        if ($course->assignments->isEmpty()) {
            return true;
        }

        foreach ($course->assignments as $assignment) {
            if (!$this->getLatestSubmission($assignment->id, $userId)) {
                return false;
            }
        }

        return true;
    }

    private function hasApprovedAllAssignments(Course $course, int $userId): bool
    {
        if ($course->assignments->isEmpty()) {
            return true;
        }

        foreach ($course->assignments as $assignment) {
            $submission = $this->getLatestSubmission($assignment->id, $userId);

            if (!$submission) {
                return false;
            }

            if (!empty($assignment->sample_project_path)) {
                $downloaded = AssignmentSampleDownload::query()
                    ->where('user_id', $userId)
                    ->where('course_assignment_id', $assignment->id)
                    ->exists();

                if (!$downloaded) {
                    return false;
                }
            }

            if (!$this->isSubmissionApproved($submission, $assignment)) {
                return false;
            }
        }

        return true;
    }

    private function countPendingAssignments(Course $course, int $userId): int
    {
        $pending = 0;

        foreach ($course->assignments as $assignment) {
            $submission = $this->getLatestSubmission($assignment->id, $userId);

            if (!$submission || !$this->isSubmissionApproved($submission, $assignment)) {
                $pending++;
            }
        }

        return $pending;
    }

    private function hasPassedAllQuizzes($quizzes, int $userId): bool
    {
        if ($quizzes->isEmpty()) {
            return true;
        }

        foreach ($quizzes as $quiz) {
            $passed = QuizSubmission::where('section_quiz_id', $quiz->id)
                ->where('user_id', $userId)
                ->where('is_passed', true)
                ->exists();

            if (!$passed) {
                return false;
            }
        }

        return true;
    }

    private function getLatestSubmission(int $assignmentId, int $userId): ?AssignmentSubmission
    {
        return AssignmentSubmission::query()
            ->where('course_assignment_id', $assignmentId)
            ->where('user_id', $userId)
            ->orderByDesc('attempt_number')
            ->first();
    }

    private function hasPassedQuiz(int $userId, int|string $quizId): bool
    {
        return QuizSubmission::where('section_quiz_id', $quizId)
            ->where('user_id', $userId)
            ->where('is_passed', true)
            ->exists();
    }

    private function getOrderedCurriculumItems(Course $course)
    {
        $items = collect();

        foreach ($course->sections as $section) {
            foreach ($section->section_lessons->sortBy('lesson_number') as $lesson) {
                $items->push([
                    'id' => $lesson->id,
                    'type' => 'lesson',
                ]);
            }

            foreach ($section->section_quizzes as $quiz) {
                $items->push([
                    'id' => $quiz->id,
                    'type' => 'quiz',
                ]);
            }
        }

        return $items->values();
    }

    private function isCurriculumItemComplete(WatchHistory $watchHistory, array $item, int $userId): bool
    {
        if ($item['type'] === 'quiz') {
            return $this->isQuizComplete($watchHistory, $item['id'], $userId);
        }

        return $this->isLessonComplete($watchHistory, (object) ['id' => $item['id']]);
    }

    private function isQuizComplete(WatchHistory $watchHistory, int|string $quizId, int $userId): bool
    {
        if ($this->isItemInCompletedWatching($watchHistory, $quizId, 'quiz')) {
            return true;
        }

        return $this->hasPassedQuiz($userId, $quizId);
    }

    private function isItemInCompletedWatching(WatchHistory $watchHistory, int|string $itemId, string $itemType): bool
    {
        $completedItems = json_decode($watchHistory->completed_watching, true) ?: [];

        foreach ($completedItems as $item) {
            if ((string) $item['id'] === (string) $itemId && $item['type'] === $itemType) {
                return true;
            }
        }

        return false;
    }

    private function getOrderedLessons(Course $course)
    {
        return $course->sections
            ->flatMap(fn ($section) => $section->section_lessons->sortBy('lesson_number'))
            ->values();
    }

    private function isFirstLesson(Course $course, int|string $lessonId): bool
    {
        $first = $this->getOrderedLessons($course)->first();

        return $first && (string) $first->id === (string) $lessonId;
    }

    private function isLessonComplete(WatchHistory $watchHistory, $lesson): bool
    {
        return $this->isItemInCompletedWatching($watchHistory, $lesson->id, 'lesson');
    }
}
