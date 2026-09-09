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
use App\Support\CurriculumSequence;

class CourseCompletionGateService
{
    public function __construct(
        private LessonWatchProgressService $lessonWatchProgress,
        private SubscriptionAccessService $subscriptionAccess,
    ) {}

    /**
     * Linear workflow gates:
     * 1. Video phase — all lessons watched (videos at 100%).
     * 2. Quiz phase — all quizzes passed. Classic course assignments are not a gate.
     * 3. Certification — auto-issued when quizzes complete (or all lessons if there are no quizzes).
     * Practice plans (Build Your US Experience) never block the certificate.
     * The US Experience tab stays locked until the same lesson/quiz bar is met.
     */
    public function getGateStatus(Course $course, int $userId, ?array $completion = null, ?WatchHistory $watchHistory = null): array
    {
        $course->loadMissing(['sections.section_lessons']);

        $watchHistory ??= WatchHistory::query()
            ->where('course_id', $course->id)
            ->where('user_id', $userId)
            ->first();

        $quizzes = SectionQuiz::where('course_id', $course->id)->get();
        $hasAssignments = false;
        $hasQuizzes = $quizzes->isNotEmpty();
        $hasVideoLessons = $this->lessonWatchProgress->getVideoLessons($course)->isNotEmpty();

        $videosCompleted = $watchHistory
            ? $this->lessonWatchProgress->allVideoLessonsWatched($course, $watchHistory)
            : !$hasVideoLessons;

        $allLessonsCompleted = $watchHistory
            ? $this->lessonWatchProgress->allLessonsCompleted($course, $watchHistory)
            : false;

        $assignmentsUnlocked = true;
        $assignmentsApproved = true;
        $assignmentsSubmitted = true;

        $quizzesUnlocked = $videosCompleted;
        $allQuizzesPassed = $this->hasPassedAllQuizzes($quizzes, $userId);

        $certificateUnlocked = $hasQuizzes
            ? $allQuizzesPassed
            : $allLessonsCompleted;

        $pendingAssignments = 0;

        $currentPhase = $this->resolveCurrentPhase(
            $videosCompleted,
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
            'us_experience_unlocked' => $certificateUnlocked,
            'pending_assignments_count' => $pendingAssignments,
        ];
    }

    public function canAccessUsExperience(Course $course, int $userId, ?WatchHistory $watchHistory = null): bool
    {
        return $this->getGateStatus($course, $userId, null, $watchHistory)['us_experience_unlocked'];
    }

    /**
     * @param  array<string, mixed>  $gates
     */
    public function usExperienceLockMessage(array $gates): ?string
    {
        if ($gates['us_experience_unlocked'] ?? $gates['certificate_unlocked'] ?? false) {
            return null;
        }

        if ($gates['has_quizzes'] ?? false) {
            if (! ($gates['videos_completed'] ?? false)) {
                return 'Finish all video lessons and pass all quizzes before you can access Build Your US Experience.';
            }

            return 'Pass all course quizzes before you can access Build Your US Experience.';
        }

        return 'Finish all course lessons before you can access Build Your US Experience.';
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

        if (!$quizId) {
            return $this->getGateStatus($course, $userId, null, $watchHistory)['quizzes_unlocked'];
        }

        $course->loadMissing(['sections.section_lessons', 'sections.section_quizzes']);

        if (!$watchHistory) {
            return $this->isFirstCurriculumItem($course, $quizId, 'quiz');
        }

        return $this->canAccessOrderedCurriculumItem($course, $userId, $quizId, 'quiz', $watchHistory);
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
            return $this->isFirstCurriculumItem($course, $lessonId, 'lesson');
        }

        return $this->canAccessOrderedCurriculumItem($course, $userId, $lessonId, 'lesson', $watchHistory);
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
        bool $hasQuizzes,
        bool $allQuizzesPassed,
        bool $certificateUnlocked,
    ): string {
        if ($certificateUnlocked) {
            return 'completed';
        }

        if (!$videosCompleted) {
            return 'video';
        }

        if ($hasQuizzes && !$allQuizzesPassed) {
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
        return CurriculumSequence::flattenCourse($course)
            ->map(fn (array $item) => [
                'id' => $item['id'],
                'type' => $item['type'],
            ])
            ->values();
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
        foreach ($watchHistory->getCompletedWatchingItems() as $item) {
            if ((string) $item['id'] === (string) $itemId && $item['type'] === $itemType) {
                return true;
            }
        }

        return false;
    }

    private function canAccessOrderedCurriculumItem(
        Course $course,
        int $userId,
        int|string $itemId,
        string $type,
        WatchHistory $watchHistory,
    ): bool {
        $allItems = $this->getOrderedCurriculumItems($course);
        $targetIndex = $allItems->search(
            fn ($item) => $item['type'] === $type && (string) $item['id'] === (string) $itemId
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

    private function isFirstCurriculumItem(Course $course, int|string $itemId, string $type): bool
    {
        $first = CurriculumSequence::flattenCourse($course)->first();

        return $first
            && (string) $first['id'] === (string) $itemId
            && $first['type'] === $type;
    }

    private function isLessonComplete(WatchHistory $watchHistory, $lesson): bool
    {
        return $this->isItemInCompletedWatching($watchHistory, $lesson->id, 'lesson');
    }
}
