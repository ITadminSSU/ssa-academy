<?php

namespace App\Services\Course;

use App\Models\Course\Course;
use App\Models\Course\CourseEnrollment;
use App\Models\Course\CourseForum;
use App\Models\Course\CourseForumReply;
use App\Models\Course\WatchHistory;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CommunityDiscussionService
{
    public function listForUser(User $user, array $filters = []): array
    {
        $courseIds = $this->accessibleCourseIds($user);
        $filter = $filters['filter'] ?? 'all';
        $courseId = !empty($filters['course_id']) ? (int) $filters['course_id'] : null;
        $isTrainerView = $this->isTrainerView($user);

        $courses = $courseIds->isEmpty()
            ? collect()
            : Course::query()
                ->whereIn('id', $courseIds)
                ->select('id', 'title')
                ->orderBy('title')
                ->get();

        if ($courseIds->isEmpty()) {
            return $this->buildListResponse([], $courses, $isTrainerView, $filter, $courseId);
        }

        $query = $this->baseForumQuery($courseIds);

        if ($courseId) {
            $query->where('course_id', $courseId);
        }

        if ($filter === 'mine') {
            $query->where('user_id', $user->id);
        }

        if ($filter === 'resolved') {
            $query->whereNotNull('resolved_at');
        } elseif ($filter === 'unanswered') {
            $this->applyUnansweredScope($query);
        } elseif ($filter === 'all') {
            $query->whereNull('resolved_at');
        }

        $forums = $query->limit(50)->get();
        $watchHistories = $this->watchHistoriesForCourses($user, $forums->pluck('course_id')->unique());

        $discussions = $forums->map(
            fn (CourseForum $forum) => $this->formatDiscussion($forum, $user, $watchHistories, $isTrainerView)
        )->values()->all();

        return $this->buildListResponse($discussions, $courses, $isTrainerView, $filter, $courseId);
    }

    public function trainerDashboardSummary(User $user): array
    {
        if (!$this->isTrainerView($user)) {
            return [
                'openForumQuestions' => 0,
                'forumPreview' => [],
                'forumQueueUrl' => null,
            ];
        }

        $courseIds = $this->trainerCourseIds($user);

        if ($courseIds->isEmpty()) {
            return [
                'openForumQuestions' => 0,
                'forumPreview' => [],
                'forumQueueUrl' => isAdmin()
                ? route('admin.forum-questions.index', ['filter' => 'unanswered'])
                : route('trainer.forum-questions.index', ['filter' => 'unanswered']),
            ];
        }

        $openCount = $this->baseForumQuery($courseIds)
            ->tap(fn ($query) => $this->applyUnansweredScope($query))
            ->count();

        $previewForums = $this->baseForumQuery($courseIds)
            ->tap(fn ($query) => $this->applyUnansweredScope($query))
            ->limit(5)
            ->get();

        $watchHistories = $this->watchHistoriesForCourses($user, $previewForums->pluck('course_id')->unique());

        return [
            'openForumQuestions' => $openCount,
            'forumPreview' => $previewForums
                ->map(fn (CourseForum $forum) => $this->formatDiscussion($forum, $user, $watchHistories, true))
                ->values()
                ->all(),
            'forumQueueUrl' => isAdmin()
                ? route('admin.forum-questions.index', ['filter' => 'unanswered'])
                : route('trainer.forum-questions.index', ['filter' => 'unanswered']),
        ];
    }

    public function canModerateForum(User $user, CourseForum $forum): bool
    {
        if (isAdmin()) {
            return true;
        }

        $forum->loadMissing('course:id,instructor_id');

        return !empty($user->instructor_id)
            && (int) $forum->course?->instructor_id === (int) $user->instructor_id;
    }

    public function resolveForum(CourseForum $forum, User $user): CourseForum
    {
        $forum->update([
            'resolved_at' => now(),
            'resolved_by' => $user->id,
        ]);

        return $forum->fresh();
    }

    public function reopenForum(CourseForum $forum): CourseForum
    {
        $forum->update([
            'resolved_at' => null,
            'resolved_by' => null,
        ]);

        return $forum->fresh();
    }

    public function pinReply(CourseForum $forum, CourseForumReply $reply): CourseForum
    {
        if ((int) $reply->course_forum_id !== (int) $forum->id) {
            throw new \InvalidArgumentException('Reply does not belong to this forum thread.');
        }

        $forum->update(['pinned_reply_id' => $reply->id]);

        return $forum->fresh(['pinnedReply.user:id,name,photo']);
    }

    public function unpinReply(CourseForum $forum): CourseForum
    {
        $forum->update(['pinned_reply_id' => null]);

        return $forum->fresh();
    }

    public function autoResolveIfInstructorReplied(CourseForum $forum, User $replier): void
    {
        $forum->loadMissing('course.instructor:id,user_id');
        $instructorUserId = $forum->course?->instructor?->user_id;

        if ($instructorUserId && (int) $replier->id === (int) $instructorUserId) {
            $this->resolveForum($forum, $replier);
        }
    }

    public function reopenIfStudentFollowUp(CourseForum $forum, User $replier): void
    {
        if (!$forum->resolved_at) {
            return;
        }

        $forum->loadMissing('course.instructor:id,user_id');
        $instructorUserId = $forum->course?->instructor?->user_id;

        if (!$instructorUserId || (int) $replier->id !== (int) $instructorUserId) {
            $this->reopenForum($forum);
        }
    }

    protected function baseForumQuery(Collection $courseIds)
    {
        return CourseForum::query()
            ->with([
                'user:id,name,photo',
                'course:id,title,slug,instructor_id',
                'course.instructor:id,user_id',
                'section_lesson:id,title',
                'replies:id,course_forum_id,user_id',
                'pinnedReply.user:id,name,photo',
                'resolvedBy:id,name',
            ])
            ->whereIn('course_id', $courseIds)
            ->withCount('replies')
            ->orderByDesc('created_at');
    }

    protected function applyUnansweredScope($query): void
    {
        $query
            ->whereNull('resolved_at')
            ->whereNotExists(function ($sub) {
                $sub->from('course_forum_replies')
                    ->join('courses', 'courses.id', '=', 'course_forums.course_id')
                    ->join('instructors', 'instructors.id', '=', 'courses.instructor_id')
                    ->whereColumn('course_forum_replies.course_forum_id', 'course_forums.id')
                    ->whereColumn('course_forum_replies.user_id', 'instructors.user_id');
            });
    }

    protected function formatDiscussion(
        CourseForum $forum,
        User $user,
        Collection $watchHistories,
        bool $isTrainerView,
    ): array {
        $instructorUserId = $forum->course?->instructor?->user_id;
        $hasInstructorReply = $forum->replies->contains(
            fn ($reply) => $instructorUserId && (int) $reply->user_id === (int) $instructorUserId
        );
        $isStudentQuestion = $instructorUserId && (int) $forum->user_id !== (int) $instructorUserId;
        $watchHistory = $watchHistories->get($forum->course_id);
        $isResolved = !empty($forum->resolved_at);

        return [
            'id' => $forum->id,
            'title' => $forum->title,
            'excerpt' => Str::limit(trim(strip_tags($forum->description ?? '')), 180),
            'created_at' => $forum->created_at?->toIso8601String(),
            'resolved_at' => $forum->resolved_at?->toIso8601String(),
            'is_resolved' => $isResolved,
            'replies_count' => $forum->replies_count,
            'is_mine' => (int) $forum->user_id === (int) $user->id,
            'needs_reply' => $isTrainerView && $isStudentQuestion && !$hasInstructorReply && !$isResolved,
            'has_instructor_reply' => $hasInstructorReply,
            'can_moderate' => $this->canModerateForum($user, $forum),
            'author' => $forum->user ? [
                'id' => $forum->user->id,
                'name' => $forum->user->name,
                'photo' => $forum->user->photo,
            ] : null,
            'course' => $forum->course ? [
                'id' => $forum->course->id,
                'title' => $forum->course->title,
                'slug' => $forum->course->slug,
            ] : null,
            'lesson' => $forum->section_lesson ? [
                'id' => $forum->section_lesson->id,
                'title' => $forum->section_lesson->title,
            ] : null,
            'player' => $watchHistory ? [
                'watch_history_id' => $watchHistory->id,
                'lesson_id' => $forum->section_lesson_id,
            ] : null,
            'pinned_reply' => $forum->pinnedReply ? [
                'id' => $forum->pinnedReply->id,
                'description' => $forum->pinnedReply->description,
                'author' => $forum->pinnedReply->user ? [
                    'id' => $forum->pinnedReply->user->id,
                    'name' => $forum->pinnedReply->user->name,
                    'photo' => $forum->pinnedReply->user->photo,
                ] : null,
            ] : null,
        ];
    }

    protected function buildListResponse(
        array $discussions,
        Collection $courses,
        bool $isTrainerView,
        string $filter,
        ?int $courseId,
    ): array {
        return [
            'discussions' => $discussions,
            'communityCourses' => $courses,
            'isTrainerView' => $isTrainerView,
            'communityFilter' => $filter,
            'communityCourseId' => $courseId,
        ];
    }

    protected function accessibleCourseIds(User $user): Collection
    {
        if (isAdmin()) {
            return Course::query()->pluck('id');
        }

        return $this->trainerCourseIds($user)
            ->merge(
                CourseEnrollment::query()
                    ->where('user_id', $user->id)
                    ->pluck('course_id')
            )
            ->unique()
            ->values();
    }

    protected function trainerCourseIds(User $user): Collection
    {
        if (empty($user->instructor_id)) {
            return collect();
        }

        return Course::query()
            ->where('instructor_id', $user->instructor_id)
            ->pluck('id');
    }

    protected function watchHistoriesForCourses(User $user, Collection $courseIds): Collection
    {
        if ($courseIds->isEmpty()) {
            return collect();
        }

        return WatchHistory::query()
            ->where('user_id', $user->id)
            ->whereIn('course_id', $courseIds)
            ->get()
            ->keyBy('course_id');
    }

    protected function isTrainerView(User $user): bool
    {
        return isAdmin() || !empty($user->instructor_id);
    }
}
