<?php

namespace App\Services\Course;

use App\Models\Course\AssignmentSubmission;
use App\Models\Course\Course;
use App\Models\Course\CourseEnrollment;
use App\Models\Course\LessonActivitySubmission;
use App\Models\Course\QuizSubmission;
use App\Models\Course\SectionLesson;
use App\Models\Course\SectionQuiz;
use App\Models\Course\UsExperienceAttempt;
use App\Models\Course\UsExperiencePlan;
use App\Models\Course\WatchHistory;
use App\Models\User;
use App\Support\CourseWelcomeEmailCopy;
use App\Support\CurriculumSequence;
use DateTimeInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Modules\Exam\Models\Exam;
use Modules\Exam\Models\ExamAttempt;

class CourseStudentProgressService
{
    public function __construct(
        private CoursePlayerService $coursePlayerService,
        private CourseCompletionGateService $courseCompletionGateService,
    ) {}

    public function authorizeCourseAccess(Course $course, User $user): void
    {
        if (isAdmin()) {
            return;
        }

        if ($user->role === 'instructor' && $user->instructor_id === $course->instructor_id) {
            return;
        }

        abort(403);
    }

    public function getCoursesForProgress(User $user, array $data = []): LengthAwarePaginator
    {
        $perPage = (int) ($data['per_page'] ?? 10);

        $query = Course::query()
            ->with(['instructor.user'])
            ->withCount('enrollments')
            ->when(! isAdmin() && $user->role === 'instructor', function ($query) use ($user) {
                $query->where('instructor_id', $user->instructor_id);
            })
            ->when(! empty($data['search']), function ($query) use ($data) {
                $query->where('title', 'LIKE', '%'.$data['search'].'%');
            })
            ->orderByDesc('created_at');

        $paginator = $query->paginate($perPage);
        $paginator->getCollection()->transform(function (Course $course) {
            $course->setAttribute('tracking_summary', $this->buildCourseTrackingSummary($course));

            return $course;
        });

        return $paginator;
    }

    public function getTrackingDashboardSummary(User $user): array
    {
        $courseQuery = Course::query()
            ->when(! isAdmin() && $user->role === 'instructor', function ($query) use ($user) {
                $query->where('instructor_id', $user->instructor_id);
            });

        $courseIds = (clone $courseQuery)->pluck('id');

        return [
            'total_courses' => $courseIds->count(),
            'total_enrollments' => CourseEnrollment::whereIn('course_id', $courseIds)->count(),
            'pending_review' => (clone $courseQuery)->where('status', 'pending')->count(),
            'approved_courses' => (clone $courseQuery)->where('status', 'approved')->count(),
        ];
    }

    private function buildCourseTrackingSummary(Course $course): array
    {
        $course->loadMissing(['assignments', 'sections.section_quizzes']);
        $enrollmentCount = (int) ($course->enrollments_count ?? 0);
        $userIds = CourseEnrollment::where('course_id', $course->id)->pluck('user_id');

        $completionSamples = WatchHistory::where('course_id', $course->id)
            ->whereIn('user_id', $userIds)
            ->get()
            ->map(fn ($history) => $this->coursePlayerService->calculateCompletion(
                $course->load(['sections.section_lessons', 'sections.section_quizzes']),
                $history
            )['completion'])
            ->all();

        $assignmentIds = $course->assignments->pluck('id');
        $quizIds = SectionQuiz::where('course_id', $course->id)->pluck('id');

        $submittedCount = $assignmentIds->isEmpty() || $userIds->isEmpty()
            ? 0
            : AssignmentSubmission::whereIn('course_assignment_id', $assignmentIds)
                ->whereIn('user_id', $userIds)
                ->distinct()
                ->count('user_id');

        $quizPassCount = $quizIds->isEmpty() || $userIds->isEmpty()
            ? 0
            : QuizSubmission::whereIn('section_quiz_id', $quizIds)
                ->whereIn('user_id', $userIds)
                ->where('is_passed', true)
                ->count();

        return [
            'avg_completion_percent' => ! empty($completionSamples)
                ? round(array_sum($completionSamples) / count($completionSamples), 1)
                : 0,
            'assignment_slots' => $assignmentIds->count() * max(1, $enrollmentCount),
            'assignments_submitted' => $submittedCount,
            'quiz_slots' => $quizIds->count() * max(1, $enrollmentCount),
            'quizzes_passed' => $quizPassCount,
        ];
    }

    public function getCourseStudentProgress(Course $course, array $data = []): array
    {
        $course->load([
            'assignments',
            'sections.section_lessons',
            'sections.section_quizzes',
        ]);

        $perPage = (int) ($data['per_page'] ?? 15);
        $quizIds = SectionQuiz::where('course_id', $course->id)->pluck('id');
        $assignmentIds = $course->assignments->pluck('id');
        $quizzes = SectionQuiz::where('course_id', $course->id)->orderBy('id')->get();
        $assignments = $course->assignments;

        $enrollmentsQuery = CourseEnrollment::with('user')
            ->where('course_id', $course->id)
            ->when(! empty($data['search']), function ($query) use ($data) {
                $query->whereHas('user', function ($userQuery) use ($data) {
                    $userQuery->where('name', 'LIKE', '%'.$data['search'].'%')
                        ->orWhere('email', 'LIKE', '%'.$data['search'].'%');
                });
            });

        $allEnrollments = $enrollmentsQuery->get();
        $userIds = $allEnrollments->pluck('user_id')->all();

        $watchHistories = WatchHistory::where('course_id', $course->id)
            ->whereIn('user_id', $userIds)
            ->get()
            ->keyBy('user_id');

        $quizSubmissions = QuizSubmission::whereIn('section_quiz_id', $quizIds)
            ->whereIn('user_id', $userIds)
            ->get()
            ->groupBy('user_id');

        $assignmentSubmissions = AssignmentSubmission::whereIn('course_assignment_id', $assignmentIds)
            ->whereIn('user_id', $userIds)
            ->orderByDesc('attempt_number')
            ->get()
            ->groupBy('user_id');

        $examAttempts = $this->getStandaloneExamAttempts($course, $userIds);
        $usExperience = $this->getUsExperienceProgress($course, $userIds);

        $students = $allEnrollments->map(function (CourseEnrollment $enrollment) use (
            $course,
            $watchHistories,
            $quizSubmissions,
            $assignmentSubmissions,
            $examAttempts,
            $usExperience,
            $quizzes,
            $assignments,
        ) {
            $userId = $enrollment->user_id;
            $watchHistory = $watchHistories->get($userId);

            $completion = $watchHistory
                ? $this->coursePlayerService->calculateCompletion($course, $watchHistory)
                : $this->emptyCompletion($course);

            $courseGates = $this->courseCompletionGateService->getGateStatus($course, $userId, $completion);
            $assignmentProgress = $this->mapAssignmentProgress($assignments, $assignmentSubmissions->get($userId, collect()));
            $quizProgress = $this->mapQuizProgress($quizzes, $quizSubmissions->get($userId, collect()));
            $scores = $this->calculateStudentScores($assignmentProgress, $quizProgress);
            $userUsExperienceAttempts = $usExperience['attemptsByUserPlan']->get($userId)
                ?? $usExperience['attemptsByUserPlan']->get((string) $userId)
                ?? collect();

            return [
                'enrollment' => $enrollment,
                'completion' => $completion,
                'course_gates' => $courseGates,
                'assignments' => $assignmentProgress,
                'quizzes' => $quizProgress,
                'exams' => $examAttempts->get($userId, collect())->values()->all(),
                'us_experience' => $this->mapUsExperienceProgress(
                    $usExperience['plans'],
                    $userUsExperienceAttempts,
                ),
                'best_quiz_percent' => $scores['best_quiz_percent'],
                'overall_score_percent' => $scores['overall_score_percent'],
                'position' => $this->buildCoursePosition($course, $watchHistory, $completion),
            ];
        });

        $sortBy = $data['sort_by'] ?? 'overall_score';
        $students = $this->sortStudents($students, $sortBy)->values();

        $page = max(1, (int) ($data['page'] ?? Paginator::resolveCurrentPage()));
        $enrollments = new LengthAwarePaginator(
            $students->forPage($page, $perPage)->values(),
            $students->count(),
            $perPage,
            $page,
            [
                'path' => Paginator::resolveCurrentPath(),
                'query' => request()->query(),
            ],
        );

        return [
            'course' => $course,
            'students' => $enrollments->getCollection()->values()->all(),
            'enrollments' => $enrollments,
            'summary' => [
                'total_enrollments' => $students->count(),
                'quiz_count' => $quizzes->count(),
                'assignment_count' => $assignments->count(),
            ],
            'sort_by' => $sortBy,
        ];
    }

    /**
     * Read-only curriculum location for one learner. Uses the course relations
     * already loaded for the roster and does not write watch history.
     *
     * @param  array{total_items?: int, completed_items?: int, completion?: float|int}  $completion
     * @return array{
     *     status: 'not_started'|'in_progress'|'finished',
     *     now_on: array{section_title: ?string, item_title: string, item_type: ?string, video_percent: ?float, unavailable: bool}|null,
     *     last_finished: array{section_title: string, item_title: string, item_type: string}|null,
     *     last_active_at: ?string,
     *     outline: list<array{section_id: string, section_title: string, items: list<array{id: string, type: string, title: string, status: string, current: bool, video_percent: ?float}>}>
     * }
     */
    public function buildCoursePosition(Course $course, ?WatchHistory $watchHistory, array $completion): array
    {
        $items = $this->curriculumItems($course);
        $completed = $watchHistory ? $this->completedItemKeys($watchHistory) : [];
        $currentKey = $watchHistory ? $this->currentItemKey($watchHistory) : null;
        $outline = [];
        $nowOn = null;
        $lastFinished = null;

        foreach ($items as $item) {
            $key = $item['id'].'|'.$item['type'];
            $isCompleted = isset($completed[$key]);
            $isCurrent = $currentKey !== null && $currentKey === $key;
            $videoPercent = null;

            if ($isCurrent && ! $isCompleted && $this->isVideoLessonType($item['lesson_type'])) {
                $videoPercent = $this->storedVideoPercent($watchHistory, $item['id']);
            }

            $status = $isCompleted ? 'finished' : ($isCurrent ? 'watching' : 'not_started');

            if (! isset($outline[$item['section_id']])) {
                $outline[$item['section_id']] = [
                    'section_id' => $item['section_id'],
                    'section_title' => $item['section_title'],
                    'items' => [],
                ];
            }

            $outline[$item['section_id']]['items'][] = [
                'id' => $item['id'],
                'type' => $item['type'],
                'title' => $item['title'],
                'status' => $status,
                'current' => $isCurrent,
                'video_percent' => $videoPercent,
            ];

            if ($isCompleted) {
                $lastFinished = [
                    'section_title' => $item['section_title'],
                    'item_title' => $item['title'],
                    'item_type' => $item['type'],
                ];
            }

            if ($isCurrent) {
                $nowOn = [
                    'section_title' => $item['section_title'],
                    'item_title' => $item['title'],
                    'item_type' => $item['type'],
                    'video_percent' => $videoPercent,
                    'unavailable' => false,
                ];
            }
        }

        if (! $watchHistory) {
            return [
                'status' => 'not_started',
                'now_on' => null,
                'last_finished' => null,
                'last_active_at' => null,
                'outline' => array_values($outline),
            ];
        }

        if ($currentKey !== null && $nowOn === null) {
            $nowOn = [
                'section_title' => null,
                'item_title' => 'Current item unavailable',
                'item_type' => null,
                'video_percent' => null,
                'unavailable' => true,
            ];
        }

        $finished = (float) ($completion['completion'] ?? 0) >= 100 || filled($watchHistory->completion_date);

        return [
            'status' => $finished ? 'finished' : 'in_progress',
            'now_on' => $nowOn,
            'last_finished' => $lastFinished,
            'last_active_at' => $this->lastActiveAt($watchHistory),
            'outline' => array_values($outline),
        ];
    }

    /**
     * @return list<array{id: string, type: string, title: string, section_id: string, section_title: string, lesson_type: ?string}>
     */
    private function curriculumItems(Course $course): array
    {
        $items = [];

        foreach (CurriculumSequence::flattenCourse($course) as $item) {
            $section = $item['section'];
            $id = (string) $item['id'];
            $model = $item['type'] === 'lesson'
                ? $section->section_lessons->first(fn ($lesson) => (string) $lesson->id === $id)
                : $section->section_quizzes->first(fn ($quiz) => (string) $quiz->id === $id);

            if (! $model) {
                continue;
            }

            $items[] = [
                'id' => $id,
                'type' => $item['type'],
                'title' => (string) $model->title,
                'section_id' => (string) $section->id,
                'section_title' => (string) $section->title,
                'lesson_type' => $item['type'] === 'lesson' ? (string) ($model->lesson_type ?? '') : null,
            ];
        }

        return $items;
    }

    /**
     * @return array<string, true>
     */
    private function completedItemKeys(WatchHistory $watchHistory): array
    {
        $keys = [];

        foreach ($watchHistory->getCompletedWatchingItems() as $item) {
            $keys[(string) $item['id'].'|'.$item['type']] = true;
        }

        return $keys;
    }

    private function currentItemKey(WatchHistory $watchHistory): ?string
    {
        $id = $watchHistory->current_watching_id;
        $type = $watchHistory->current_watching_type;

        if ($id === null || $id === '' || ! in_array($type, ['lesson', 'quiz'], true)) {
            return null;
        }

        return (string) $id.'|'.$type;
    }

    private function isVideoLessonType(?string $lessonType): bool
    {
        return in_array($lessonType, ['video', 'video_url'], true);
    }

    private function storedVideoPercent(?WatchHistory $watchHistory, string $lessonId): ?float
    {
        if (! $watchHistory) {
            return null;
        }

        $raw = $watchHistory->lesson_watch_progress;

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : null;
        }

        if (! is_array($raw)) {
            return null;
        }

        $entry = $raw[$lessonId] ?? $raw[(int) $lessonId] ?? null;

        if (! is_array($entry) || ! array_key_exists('percent', $entry) || $entry['percent'] === null || $entry['percent'] === '') {
            return null;
        }

        return (float) $entry['percent'];
    }

    private function lastActiveAt(WatchHistory $watchHistory): ?string
    {
        $updatedAt = $watchHistory->updated_at;

        if ($updatedAt instanceof DateTimeInterface) {
            return $updatedAt->format(DateTimeInterface::ATOM);
        }

        if (is_string($updatedAt) && $updatedAt !== '') {
            return $updatedAt;
        }

        return null;
    }

    /**
     * Average assessment score (as %) for one learner in one course.
     * Includes quizzes, assignments, and graded practical activities.
     * Returns null when no graded work exists yet.
     */
    public function computeLearnerCourseScorePercent(int $userId, Course $course): ?float
    {
        $detail = $this->computeLearnerCourseScoreDetail($userId, $course);

        return $detail['score_percent'] ?? null;
    }

    /**
     * @return array{
     *     score_percent: float|null,
     *     graded_assessments: int,
     *     course_size: int,
     *     assessments: list<array{type: string, title: string, score_percent: float}>
     * }|null
     */
    public function computeLearnerCourseScoreDetail(int $userId, Course $course): ?array
    {
        $course->loadMissing(['assignments', 'sections.section_quizzes']);

        $quizzes = SectionQuiz::where('course_id', $course->id)->orderBy('id')->get();
        $assignments = $course->assignments;
        $practicalLessons = SectionLesson::query()
            ->where('course_id', $course->id)
            ->where('requires_submission', true)
            ->orderBy('id')
            ->get();

        $quizSubmissions = QuizSubmission::whereIn('section_quiz_id', $quizzes->pluck('id'))
            ->where('user_id', $userId)
            ->get();

        $assignmentSubmissions = AssignmentSubmission::whereIn('course_assignment_id', $assignments->pluck('id'))
            ->where('user_id', $userId)
            ->orderByDesc('attempt_number')
            ->get();

        $activitySubmissions = $practicalLessons->isEmpty()
            ? collect()
            : LessonActivitySubmission::whereIn('section_lesson_id', $practicalLessons->pluck('id'))
                ->where('user_id', $userId)
                ->orderByDesc('attempt_number')
                ->get();

        $quizProgress = $this->mapQuizProgress($quizzes, $quizSubmissions);
        $assignmentProgress = $this->mapAssignmentProgress($assignments, $assignmentSubmissions);
        $activityProgress = $this->mapPracticalActivityProgress($practicalLessons, $activitySubmissions);

        $scores = $this->calculateStudentScores($assignmentProgress, $quizProgress, $activityProgress);

        if ($scores['graded_assessments'] === 0) {
            return null;
        }

        $assessments = [];

        foreach ($quizProgress as $quiz) {
            if ($quiz['attempted'] && $quiz['total_mark'] > 0 && $quiz['score'] !== null) {
                $assessments[] = [
                    'type' => 'quiz',
                    'title' => $quiz['title'],
                    'score_percent' => round(($quiz['score'] / $quiz['total_mark']) * 100, 1),
                ];
            }
        }

        foreach ($assignmentProgress as $assignment) {
            if ($assignment['marks_obtained'] !== null && $assignment['total_mark'] > 0) {
                $assessments[] = [
                    'type' => 'assignment',
                    'title' => $assignment['title'],
                    'score_percent' => round(($assignment['marks_obtained'] / $assignment['total_mark']) * 100, 1),
                ];
            }
        }

        foreach ($activityProgress as $activity) {
            if ($activity['marks_obtained'] !== null && $activity['total_mark'] > 0) {
                $assessments[] = [
                    'type' => 'practical_activity',
                    'title' => $activity['title'],
                    'score_percent' => round(($activity['marks_obtained'] / $activity['total_mark']) * 100, 1),
                ];
            }
        }

        return [
            'score_percent' => $scores['overall_score_percent'],
            'graded_assessments' => $scores['graded_assessments'],
            'course_size' => $scores['course_size'],
            'assessments' => $assessments,
        ];
    }

    private function calculateStudentScores(array $assignments, array $quizzes, array $practicalActivities = []): array
    {
        $percentages = [];
        $courseSize = count($quizzes) + count($assignments) + count($practicalActivities);

        foreach ($quizzes as $quiz) {
            if ($quiz['attempted'] && $quiz['total_mark'] > 0 && $quiz['score'] !== null) {
                $percentages[] = ($quiz['score'] / $quiz['total_mark']) * 100;
            }
        }

        foreach ($assignments as $assignment) {
            if ($assignment['marks_obtained'] !== null && $assignment['total_mark'] > 0) {
                $percentages[] = ($assignment['marks_obtained'] / $assignment['total_mark']) * 100;
            }
        }

        foreach ($practicalActivities as $activity) {
            if ($activity['marks_obtained'] !== null && $activity['total_mark'] > 0) {
                $percentages[] = ($activity['marks_obtained'] / $activity['total_mark']) * 100;
            }
        }

        return [
            'best_quiz_percent' => $this->bestQuizPercent($quizzes),
            'graded_assessments' => count($percentages),
            'course_size' => $courseSize,
            'overall_score_percent' => ! empty($percentages)
                ? round(array_sum($percentages) / count($percentages), 1)
                : null,
        ];
    }

    private function bestQuizPercent(array $quizzes): ?float
    {
        $best = null;

        foreach ($quizzes as $quiz) {
            if (! $quiz['attempted'] || $quiz['total_mark'] <= 0 || $quiz['score'] === null) {
                continue;
            }

            $percent = ($quiz['score'] / $quiz['total_mark']) * 100;
            $best = $best === null ? $percent : max($best, $percent);
        }

        return $best !== null ? round($best, 1) : null;
    }

    private function sortStudents($students, string $sortBy)
    {
        return match ($sortBy) {
            'completion' => $students->sortByDesc(fn ($row) => $row['completion']['completion']),
            'overall_score' => $students->sortByDesc(fn ($row) => $row['overall_score_percent'] ?? -1),
            'best_quiz' => $students->sortByDesc(fn ($row) => $row['best_quiz_percent'] ?? -1),
            default => $students->sortBy(fn ($row) => strtolower($row['enrollment']->user->name ?? '')),
        };
    }

    private function emptyCompletion(Course $course): array
    {
        $totalItems = 0;

        foreach ($course->sections as $section) {
            $totalItems += $section->section_lessons->count() + $section->section_quizzes->count();
        }

        return [
            'total_items' => $totalItems,
            'completed_items' => 0,
            'completion' => 0,
        ];
    }

    private function mapAssignmentProgress($assignments, $submissions): array
    {
        return $assignments->map(function ($assignment) use ($submissions) {
            $latest = $submissions
                ->where('course_assignment_id', $assignment->id)
                ->sortByDesc('attempt_number')
                ->first();

            return [
                'assignment_id' => $assignment->id,
                'title' => $assignment->title,
                'total_mark' => (float) $assignment->total_mark,
                'pass_mark' => (float) $assignment->pass_mark,
                'submitted' => $latest !== null,
                'status' => $latest?->status ?? 'not_submitted',
                'marks_obtained' => $latest?->marks_obtained !== null ? (float) $latest->marks_obtained : null,
                'is_passed' => $latest
                    ? $this->courseCompletionGateService->isSubmissionApproved($latest, $assignment)
                    : null,
                'is_approved' => $latest
                    ? $this->courseCompletionGateService->isSubmissionApproved($latest, $assignment)
                    : false,
            ];
        })->values()->all();
    }

    private function mapQuizProgress($quizzes, $submissions): array
    {
        return $quizzes->map(function ($quiz) use ($submissions) {
            $submission = $submissions->firstWhere('section_quiz_id', $quiz->id);

            return [
                'quiz_id' => $quiz->id,
                'title' => $quiz->title,
                'total_mark' => (float) $quiz->total_mark,
                'pass_mark' => (float) $quiz->pass_mark,
                'attempted' => $submission !== null,
                'attempts' => $submission?->attempts ?? 0,
                'score' => $submission ? (float) $submission->total_marks : null,
                'is_passed' => $submission?->is_passed,
            ];
        })->values()->all();
    }

    private function mapPracticalActivityProgress($lessons, $submissions): array
    {
        return $lessons->map(function ($lesson) use ($submissions) {
            $latest = $submissions
                ->where('section_lesson_id', $lesson->id)
                ->sortByDesc('attempt_number')
                ->first();

            $isGraded = $latest && in_array($latest->status, ['graded', 'passed', 'approved'], true);

            return [
                'lesson_id' => $lesson->id,
                'title' => $lesson->title,
                'total_mark' => (float) ($lesson->activity_total_mark ?? 0),
                'pass_mark' => (float) ($lesson->activity_pass_mark ?? 0),
                'submitted' => $latest !== null,
                'status' => $latest?->status ?? 'not_submitted',
                'marks_obtained' => $isGraded && $latest->marks_obtained !== null
                    ? (float) $latest->marks_obtained
                    : null,
            ];
        })->values()->all();
    }

    /**
     * Ready US Experience plans plus one attempt query for the current roster page.
     * Practice scores stay off overall_score_percent and certificate gates.
     *
     * @return array{plans: Collection<int, UsExperiencePlan>, attemptsByUserPlan: Collection<int|string, Collection<int|string, Collection<int, UsExperienceAttempt>>>}
     */
    private function getUsExperienceProgress(Course $course, array $userIds): array
    {
        if (! CourseWelcomeEmailCopy::showsUsExperience($course)) {
            return [
                'plans' => collect(),
                'attemptsByUserPlan' => collect(),
            ];
        }

        $plans = UsExperiencePlan::query()
            ->where('course_id', $course->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (UsExperiencePlan $plan) => $plan->isReady())
            ->values();

        if ($plans->isEmpty() || $userIds === []) {
            return [
                'plans' => $plans,
                'attemptsByUserPlan' => collect(),
            ];
        }

        $attemptsByUserPlan = UsExperienceAttempt::query()
            ->whereIn('us_experience_plan_id', $plans->pluck('id'))
            ->whereIn('user_id', $userIds)
            ->orderByDesc('attempt_number')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->groupBy('us_experience_plan_id'));

        return [
            'plans' => $plans,
            'attemptsByUserPlan' => $attemptsByUserPlan,
        ];
    }

    /**
     * @param  Collection<int, UsExperiencePlan>  $plans
     * @param  Collection<int|string, Collection<int, UsExperienceAttempt>>  $attemptsByPlan
     * @return list<array<string, mixed>>
     */
    private function mapUsExperienceProgress($plans, $attemptsByPlan): array
    {
        return $plans->map(function (UsExperiencePlan $plan) use ($attemptsByPlan) {
            $planAttempts = collect(
                $attemptsByPlan->get($plan->id)
                    ?? $attemptsByPlan->get((string) $plan->id)
                    ?? []
            );
            $passed = $planAttempts->contains(fn (UsExperienceAttempt $attempt) => $attempt->isPassed());
            $attemptsUsed = $planAttempts->count();
            $exhausted = ! $passed && $attemptsUsed >= (int) $plan->max_attempts;
            $bestPercent = $planAttempts->max('lines_percent');
            $latest = $planAttempts->sortByDesc('id')->first();

            if ($passed) {
                $status = 'passed';
            } elseif ($attemptsUsed === 0) {
                $status = 'not_started';
            } elseif ($exhausted) {
                $status = 'failed';
            } else {
                $status = 'ongoing';
            }

            return [
                'plan_id' => $plan->id,
                'title' => $plan->title,
                'group_name' => $plan->group_name,
                'attempts_used' => $attemptsUsed,
                'max_attempts' => (int) $plan->max_attempts,
                'best_percent' => $bestPercent !== null ? round((float) $bestPercent, 1) : null,
                'is_passed' => $passed,
                'status' => $status,
                'latest_attempt_id' => $latest?->id,
            ];
        })->values()->all();
    }

    private function getStandaloneExamAttempts(Course $course, array $userIds)
    {
        if (empty($userIds)) {
            return collect();
        }

        $examQuery = Exam::query()->where('status', 'approved');

        if (! isAdmin()) {
            $examQuery->where('instructor_id', $course->instructor_id);
        }

        $examIds = $examQuery->pluck('id');

        if ($examIds->isEmpty()) {
            return collect();
        }

        $attempts = ExamAttempt::query()
            ->with('exam:id,title,pass_mark,total_marks')
            ->whereIn('user_id', $userIds)
            ->whereIn('exam_id', $examIds)
            ->where('status', 'completed')
            ->orderByDesc('obtained_marks')
            ->get();

        return $attempts
            ->groupBy('user_id')
            ->map(function ($userAttempts) {
                return $userAttempts
                    ->groupBy('exam_id')
                    ->map(function ($examAttempts) {
                        $best = $examAttempts->first();

                        return [
                            'exam_id' => $best->exam_id,
                            'title' => $best->exam->title,
                            'score' => (float) $best->obtained_marks,
                            'total_marks' => (float) $best->total_marks,
                            'pass_mark' => (float) $best->exam->pass_mark,
                            'is_passed' => (bool) $best->is_passed,
                            'attempts' => $examAttempts->count(),
                        ];
                    })
                    ->values();
            });
    }
}
