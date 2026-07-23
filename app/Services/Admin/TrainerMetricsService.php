<?php

namespace App\Services\Admin;

use App\Models\Course\AssignmentSubmission;
use App\Models\Course\Course;
use App\Models\Course\CourseEnrollment;
use App\Models\Course\QuizSubmission;
use App\Models\Course\SectionQuiz;
use App\Models\Course\WatchHistory;
use App\Models\Instructor;
use App\Services\Course\CourseCompletionGateService;
use App\Services\Course\CoursePlayerService;
use Illuminate\Support\Collection;

class TrainerMetricsService
{
    public function __construct(
        private CoursePlayerService $coursePlayerService,
        private CourseCompletionGateService $courseCompletionGateService,
    ) {}

    public function getTrainerMetrics(array $data = []): Collection
    {
        $instructors = Instructor::with('user')
            ->when(!empty($data['search']), function ($query) use ($data) {
                $query->whereHas('user', function ($userQuery) use ($data) {
                    $userQuery->where('name', 'LIKE', '%' . $data['search'] . '%')
                        ->orWhere('email', 'LIKE', '%' . $data['search'] . '%');
                });
            })
            ->get();

        $metrics = $instructors->map(function (Instructor $instructor) {
            $courses = Course::withCount('enrollments')
                ->where('instructor_id', $instructor->id)
                ->get();

            $courseIds = $courses->pluck('id');
            $enrollmentCount = CourseEnrollment::whereIn('course_id', $courseIds)->count();
            $pendingReview = $courses->where('status', 'pending')->count();
            $approvedCourses = $courses->where('status', 'approved')->count();

            $completionSamples = [];
            $assignmentSubmitted = 0;
            $assignmentTotal = 0;
            $quizPassed = 0;
            $quizTotal = 0;

            foreach ($courses as $course) {
                $course->load(['assignments', 'sections.section_lessons', 'sections.section_quizzes']);
                $assignmentTotal += $course->assignments->count() * max(1, $course->enrollments_count);

                $quizIds = SectionQuiz::where('course_id', $course->id)->pluck('id');
                $quizTotal += $quizIds->count() * max(1, $course->enrollments_count);

                $enrollments = CourseEnrollment::where('course_id', $course->id)->pluck('user_id');
                $watchHistories = WatchHistory::where('course_id', $course->id)
                    ->whereIn('user_id', $enrollments)
                    ->get();

                foreach ($watchHistories as $watchHistory) {
                    $completion = $this->coursePlayerService->calculateCompletion($course, $watchHistory);
                    $completionSamples[] = (float) $completion['completion'];
                }

                if ($course->assignments->isNotEmpty() && $enrollments->isNotEmpty()) {
                    $assignmentSubmitted += AssignmentSubmission::whereIn('course_assignment_id', $course->assignments->pluck('id'))
                        ->whereIn('user_id', $enrollments)
                        ->distinct('user_id', 'course_assignment_id')
                        ->count('id');
                }

                if ($quizIds->isNotEmpty() && $enrollments->isNotEmpty()) {
                    $quizPassed += QuizSubmission::whereIn('section_quiz_id', $quizIds)
                        ->whereIn('user_id', $enrollments)
                        ->where('is_passed', true)
                        ->count();
                }
            }

            $avgCompletion = !empty($completionSamples)
                ? round(array_sum($completionSamples) / count($completionSamples), 1)
                : null;

            return [
                'instructor' => $instructor,
                'courses_count' => $courses->count(),
                'approved_courses' => $approvedCourses,
                'pending_review' => $pendingReview,
                'enrollment_count' => $enrollmentCount,
                'avg_completion_percent' => $avgCompletion,
                'assignment_submission_rate' => $assignmentTotal > 0
                    ? round(($assignmentSubmitted / $assignmentTotal) * 100, 1)
                    : null,
                'quiz_pass_rate' => $quizTotal > 0
                    ? round(($quizPassed / $quizTotal) * 100, 1)
                    : null,
            ];
        });

        $sortBy = $data['sort_by'] ?? 'enrollments';

        return (match ($sortBy) {
            'completion' => $metrics->sortByDesc(fn ($row) => $row['avg_completion_percent'] ?? -1),
            'courses' => $metrics->sortByDesc('courses_count'),
            'name' => $metrics->sortBy(fn ($row) => strtolower($row['instructor']->user->name ?? '')),
            default => $metrics->sortByDesc('enrollment_count'),
        })->values();
    }

    public function getPlatformSummary(): array
    {
        return [
            'total_trainers' => Instructor::count(),
            'total_courses' => Course::count(),
            'pending_review_courses' => Course::where('status', 'pending')->count(),
            'total_enrollments' => CourseEnrollment::count(),
            'approved_courses' => Course::where('status', 'approved')->count(),
        ];
    }
}
