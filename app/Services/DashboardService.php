<?php

namespace App\Services;

use App\Models\Course\AssignmentSubmission;
use App\Models\Course\Course;
use App\Models\Course\CourseEnrollment;
use App\Models\Course\QuizSubmission;
use App\Models\Course\SectionLesson;
use App\Models\Course\WatchHistory;
use App\Models\User;
use App\Services\Course\CommunityDiscussionService;
use App\Services\Course\CoursePlayerService;
use App\Services\Course\TopPerformerService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\PaymentGateways\Models\PaymentHistory;

class DashboardService extends MediaService
{
    public function __construct(
        private CoursePlayerService $coursePlayerService,
        private TopPerformerService $topPerformerService,
        private CommunityDiscussionService $communityDiscussion,
    ) {}

    public function getDashboard(User $user, $currentYear)
    {
        $isInstructor = $user->role === 'instructor';

        $courses_ids = Course::query()
            ->when($isInstructor && $user->instructor_id, function ($query) use ($user) {
                return $query->where('instructor_id', $user->instructor_id);
            })
            ->get()
            ->pluck('id')
            ->toArray();

        // Basic statistics
        $statistics = [
            'courses' => Course::whereIn('id', $courses_ids)->count(),
            'lessons' => SectionLesson::whereIn('course_id', $courses_ids)->count(),
            'enrollments' => CourseEnrollment::whereIn('course_id', $courses_ids)->count(),
            'students' => CourseEnrollment::whereIn('course_id', $courses_ids)->distinct('user_id')->count('user_id'),
            'instructors' => User::where('role', 'instructor')->count(),
        ];

        // Revenue for current year (monthly breakdown) — kept for optional use elsewhere
        $yearlyRevenue = PaymentHistory::query()
            ->selectRaw('MONTH(created_at) as month, SUM(' . $user->role . '_revenue) as revenue')
            ->whereYear('created_at', $currentYear)
            ->where('purchase_type', Course::class)
            ->whereIn('purchase_id', $courses_ids)
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month')
            ->map(function ($item) {
                return $item->revenue;
            })
            ->toArray();

        $revenueData = [];
        for ($month = 1; $month <= 12; $month++) {
            $monthName = Carbon::create($currentYear, $month, 1)->format('F');
            $revenueData[$monthName] = $yearlyRevenue[$month] ?? 0;
        }

        $courseStatusDistribution = $this->getCourseStatusDistribution($courses_ids);

        $topPerformers = $this->getTopPerformersPreview($user);
        $studentProgressOverview = $this->getStudentProgressOverview($courses_ids);
        $recentStudentActivity = $this->getRecentStudentActivity($courses_ids);

        $forumSummary = $this->communityDiscussion->trainerDashboardSummary($user);

        return [
            'statistics' => $statistics,
            'revenueData' => $revenueData,
            'courseStatusDistribution' => $courseStatusDistribution,
            'topPerformers' => $topPerformers,
            'studentProgressOverview' => $studentProgressOverview,
            'recentStudentActivity' => $recentStudentActivity,
            'openForumQuestions' => $forumSummary['openForumQuestions'],
            'forumPreview' => $forumSummary['forumPreview'],
            'forumQueueUrl' => $forumSummary['forumQueueUrl'],
        ];
    }

    public function getTopPerformersPreview(User $user): array
    {
        $leaderboard = $this->topPerformerService->getLeaderboard($user, ['per_page' => 8]);

        return collect($leaderboard->items())->map(function (array $row) {
            return [
                'rank' => $row['rank'],
                'name' => $row['user']['name'],
                'photo' => $row['user']['photo'],
                'score' => $row['average_score_percent'],
                'is_top_performer' => $row['is_top_performer'],
            ];
        })->values()->all();
    }

    public function getStudentProgressOverview(array $courseIds): array
    {
        if (empty($courseIds)) {
            return [
                'completed' => 0,
                'in_progress' => 0,
                'not_started' => 0,
                'total' => 0,
            ];
        }

        $enrollments = CourseEnrollment::whereIn('course_id', $courseIds)->get(['user_id', 'course_id']);
        $courses = Course::whereIn('id', $courseIds)
            ->with(['sections.section_lessons', 'sections.section_quizzes'])
            ->get()
            ->keyBy('id');

        $histories = WatchHistory::whereIn('course_id', $courseIds)
            ->get()
            ->keyBy(fn (WatchHistory $history) => $history->user_id . ':' . $history->course_id);

        $completed = 0;
        $inProgress = 0;
        $notStarted = 0;

        foreach ($enrollments as $enrollment) {
            $course = $courses->get($enrollment->course_id);

            if (!$course) {
                $notStarted++;

                continue;
            }

            $history = $histories->get($enrollment->user_id . ':' . $enrollment->course_id);

            if (!$history) {
                $notStarted++;

                continue;
            }

            $completion = $this->coursePlayerService->calculateCompletion($course, $history);
            $percentage = (float) ($completion['percentage'] ?? 0);

            if ($history->completion_date || $percentage >= 100) {
                $completed++;
            } elseif ($percentage > 0) {
                $inProgress++;
            } else {
                $notStarted++;
            }
        }

        return [
            'completed' => $completed,
            'in_progress' => $inProgress,
            'not_started' => $notStarted,
            'total' => $enrollments->count(),
        ];
    }

    public function getRecentStudentActivity(array $courseIds, int $limit = 8): array
    {
        if (empty($courseIds)) {
            return [];
        }

        $activities = collect();

        QuizSubmission::query()
            ->with(['user:id,name,photo', 'section_quiz:id,title,course_id'])
            ->whereHas('section_quiz', fn ($query) => $query->whereIn('course_id', $courseIds))
            ->latest('updated_at')
            ->limit(12)
            ->get()
            ->each(function (QuizSubmission $submission) use ($activities) {
                $activities->push([
                    'user_name' => $submission->user?->name ?? 'Learner',
                    'user_photo' => $submission->user?->photo,
                    'action' => $submission->is_passed ? 'Passed quiz' : 'Submitted quiz',
                    'detail' => $submission->section_quiz?->title,
                    'occurred_at' => $submission->updated_at?->toIso8601String(),
                ]);
            });

        AssignmentSubmission::query()
            ->with(['student:id,name,photo', 'assignment:id,title,course_id'])
            ->whereHas('assignment', fn ($query) => $query->whereIn('course_id', $courseIds))
            ->latest('submitted_at')
            ->limit(12)
            ->get()
            ->each(function (AssignmentSubmission $submission) use ($activities) {
                $activities->push([
                    'user_name' => $submission->student?->name ?? 'Learner',
                    'user_photo' => $submission->student?->photo,
                    'action' => $submission->status === 'graded' ? 'Assignment graded' : 'Submitted assignment',
                    'detail' => $submission->assignment?->title,
                    'occurred_at' => ($submission->submitted_at ?? $submission->updated_at)?->toIso8601String(),
                ]);
            });

        CourseEnrollment::query()
            ->with(['user:id,name,photo', 'course:id,title'])
            ->whereIn('course_id', $courseIds)
            ->latest('created_at')
            ->limit(8)
            ->get()
            ->each(function (CourseEnrollment $enrollment) use ($activities) {
                $activities->push([
                    'user_name' => $enrollment->user?->name ?? 'Learner',
                    'user_photo' => $enrollment->user?->photo,
                    'action' => 'Enrolled in course',
                    'detail' => $enrollment->course?->title,
                    'occurred_at' => $enrollment->created_at?->toIso8601String(),
                ]);
            });

        return $activities
            ->filter(fn (array $item) => !empty($item['occurred_at']))
            ->sortByDesc('occurred_at')
            ->take($limit)
            ->values()
            ->all();
    }

    public function getCourseStatusDistribution($courses_ids)
    {
        $distribution = Course::select('status', DB::raw('count(*) as count'))
            ->whereIn('id', $courses_ids)
            ->groupBy('status')
            ->get()
            ->mapWithKeys(function ($item) {
                $statusLabels = [
                    'approved' => 'Approved',
                    'upcoming' => 'Upcoming',
                    'pending' => 'Pending',
                    'private' => 'Private',
                    'draft' => 'Draft',
                ];

                if (is_numeric($item->status)) {
                    $numericLabels = [
                        1 => 'Active',
                        2 => 'Upcoming',
                        3 => 'Pending',
                        4 => 'Private',
                        5 => 'Draft',
                    ];
                    $status = $numericLabels[$item->status] ?? 'Unknown';
                } else {
                    $status = $statusLabels[$item->status] ?? ucfirst($item->status);
                }

                return [$status => $item->count];
            })
            ->toArray();

        $allStatuses = ['Approved', 'Upcoming', 'Pending', 'Private', 'Draft'];
        foreach ($allStatuses as $status) {
            if (!isset($distribution[$status])) {
                $distribution[$status] = 0;
            }
        }

        return $distribution;
    }
}
