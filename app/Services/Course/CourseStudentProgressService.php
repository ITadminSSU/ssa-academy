<?php

namespace App\Services\Course;

use App\Models\Course\AssignmentSubmission;
use App\Models\Course\Course;
use App\Models\Course\CourseEnrollment;
use App\Models\Course\LessonActivitySubmission;
use App\Models\Course\QuizSubmission;
use App\Models\Course\SectionLesson;
use App\Models\Course\SectionQuiz;
use App\Models\Course\WatchHistory;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
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
            ->when(!isAdmin() && $user->role === 'instructor', function ($query) use ($user) {
                $query->where('instructor_id', $user->instructor_id);
            })
            ->when(!empty($data['search']), function ($query) use ($data) {
                $query->where('title', 'LIKE', '%' . $data['search'] . '%');
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
            ->when(!isAdmin() && $user->role === 'instructor', function ($query) use ($user) {
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
            'avg_completion_percent' => !empty($completionSamples)
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
            ->when(!empty($data['search']), function ($query) use ($data) {
                $query->whereHas('user', function ($userQuery) use ($data) {
                    $userQuery->where('name', 'LIKE', '%' . $data['search'] . '%')
                        ->orWhere('email', 'LIKE', '%' . $data['search'] . '%');
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

        $students = $allEnrollments->map(function (CourseEnrollment $enrollment) use (
            $course,
            $watchHistories,
            $quizSubmissions,
            $assignmentSubmissions,
            $examAttempts,
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

            return [
                'enrollment' => $enrollment,
                'completion' => $completion,
                'course_gates' => $courseGates,
                'assignments' => $assignmentProgress,
                'quizzes' => $quizProgress,
                'exams' => $examAttempts->get($userId, collect())->values()->all(),
                'best_quiz_percent' => $scores['best_quiz_percent'],
                'overall_score_percent' => $scores['overall_score_percent'],
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
            'overall_score_percent' => !empty($percentages)
                ? round(array_sum($percentages) / count($percentages), 1)
                : null,
        ];
    }

    private function bestQuizPercent(array $quizzes): ?float
    {
        $best = null;

        foreach ($quizzes as $quiz) {
            if (!$quiz['attempted'] || $quiz['total_mark'] <= 0 || $quiz['score'] === null) {
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

    private function getStandaloneExamAttempts(Course $course, array $userIds)
    {
        if (empty($userIds)) {
            return collect();
        }

        $examQuery = Exam::query()->where('status', 'approved');

        if (!isAdmin()) {
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
