<?php

namespace App\Services\Course;

use App\Models\Course\CourseEnrollment;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

class TopPerformerService
{
    public const TOP_PERFORMER_RANK_LIMIT = 10;

    public function __construct(private CourseStudentProgressService $progressService) {}

    public function getLeaderboard(User $viewer, array $data = []): LengthAwarePaginator
    {
        $perPage = (int) ($data['per_page'] ?? 20);
        $search = $data['search'] ?? null;

        $enrollments = $this->scopedEnrollments($viewer, $search)->get();

        $courseCache = [];

        $rows = $enrollments
            ->groupBy('user_id')
            ->map(function (Collection $userEnrollments) use (&$courseCache) {
                $user = $userEnrollments->first()->user;
                $courseScores = [];
                $weightedSum = 0.0;
                $totalWeight = 0;

                foreach ($userEnrollments as $enrollment) {
                    $courseId = $enrollment->course_id;

                    if (!isset($courseCache[$courseId])) {
                        $courseCache[$courseId] = $enrollment->course;
                    }

                    $detail = $this->progressService->computeLearnerCourseScoreDetail(
                        (int) $user->id,
                        $courseCache[$courseId],
                    );

                    if ($detail === null) {
                        continue;
                    }

                    $courseSize = max(1, $detail['course_size']);

                    $courseScores[] = [
                        'course_id' => $courseId,
                        'course_title' => $courseCache[$courseId]->title,
                        'score_percent' => $detail['score_percent'],
                        'graded_assessments' => $detail['graded_assessments'],
                        'course_size' => $detail['course_size'],
                        'assessments' => $detail['assessments'],
                    ];

                    $weightedSum += $detail['score_percent'] * $courseSize;
                    $totalWeight += $courseSize;
                }

                if ($totalWeight === 0) {
                    return null;
                }

                return [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'photo' => $user->photo,
                    ],
                    'courses_enrolled' => $userEnrollments->count(),
                    'courses_graded' => count($courseScores),
                    'total_course_size' => $totalWeight,
                    'average_score_percent' => round($weightedSum / $totalWeight, 1),
                    'course_scores' => $courseScores,
                ];
            })
            ->filter()
            ->sortByDesc('average_score_percent')
            ->values()
            ->map(function (array $row, int $index) {
                $rank = $index + 1;
                $row['rank'] = $rank;
                $row['is_top_performer'] = $rank <= self::TOP_PERFORMER_RANK_LIMIT;

                return $row;
            });

        $page = max(1, (int) ($data['page'] ?? Paginator::resolveCurrentPage()));

        return new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => Paginator::resolveCurrentPath(),
                'query' => request()->query(),
            ],
        );
    }

    private function scopedEnrollments(User $viewer, ?string $search)
    {
        return CourseEnrollment::query()
            ->with(['user:id,name,email,photo', 'course:id,title,instructor_id'])
            ->when(!isAdmin() && $viewer->role === 'instructor', function ($query) use ($viewer) {
                $query->whereHas('course', function ($courseQuery) use ($viewer) {
                    $courseQuery->where('instructor_id', $viewer->instructor_id);
                });
            })
            ->when($search, function ($query) use ($search) {
                $query->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('email', 'LIKE', "%{$search}%");
                });
            });
    }
}
