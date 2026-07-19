<?php

namespace App\Services;

use App\Enums\UserType;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use Illuminate\Database\Eloquent\Builder;

class AnnouncementService
{
    public function __construct(private AuthService $authService) {}

    public function notifyLearners(Announcement $announcement, User $author, ?int $excludeUserId = null): void
    {
        $this->recipientStudentsQuery($author)
            ->when($excludeUserId, fn (Builder $query) => $query->where('id', '!=', $excludeUserId))
            ->select(['id', 'role', 'user_type'])
            ->chunkById(100, function ($users) use ($announcement) {
                foreach ($users as $user) {
                    $user->notify(new AnnouncementNotification(
                        $announcement,
                        $this->announcementsUrlFor($user),
                    ));
                }
            });
    }

    public function publishedForStudent(User $student): Builder
    {
        return Announcement::query()
            ->where('is_published', true)
            ->where(function (Builder $query) use ($student) {
                $query->whereHas('author', fn (Builder $authorQuery) => $authorQuery->where('role', UserType::ADMIN->value))
                    ->orWhere(function (Builder $trainerQuery) use ($student) {
                        $trainerQuery
                            ->whereHas('author', fn (Builder $authorQuery) => $authorQuery->where('role', UserType::INSTRUCTOR->value))
                            ->whereExists(function ($subQuery) use ($student) {
                                $subQuery->selectRaw('1')
                                    ->from('course_enrollments')
                                    ->join('courses', 'courses.id', '=', 'course_enrollments.course_id')
                                    ->join('instructors', 'instructors.id', '=', 'courses.instructor_id')
                                    ->whereColumn('instructors.user_id', 'announcements.user_id')
                                    ->where('course_enrollments.user_id', $student->id);
                            });
                    });
            })
            ->with('author:id,name')
            ->orderByDesc('created_at');
    }

    public function forManagement(User $viewer): Builder
    {
        $query = Announcement::query()
            ->with('author:id,name')
            ->orderByDesc('created_at');

        if ($viewer->role === UserType::INSTRUCTOR->value) {
            $query->where('user_id', $viewer->id);
        }

        return $query;
    }

    public function authorCanManage(User $viewer, Announcement $announcement): bool
    {
        if ($viewer->role === UserType::ADMIN->value) {
            return true;
        }

        if ($viewer->role === UserType::INSTRUCTOR->value) {
            return (int) $announcement->user_id === (int) $viewer->id;
        }

        return false;
    }

    public function shouldNotifyOnCreate(array $data): bool
    {
        return (bool) ($data['is_published'] ?? false);
    }

    public function shouldNotifyOnUpdate(Announcement $announcement, array $data): bool
    {
        $willPublish = (bool) ($data['is_published'] ?? false);

        return $willPublish && !$announcement->is_published;
    }

    private function recipientStudentsQuery(User $author): Builder
    {
        $query = User::query()->where('role', UserType::STUDENT->value);

        if ($author->role === UserType::ADMIN->value) {
            return $query;
        }

        if ($author->role === UserType::INSTRUCTOR->value && $author->instructor_id) {
            return $query->whereIn('id', $this->enrolledStudentIdsSubquery((int) $author->instructor_id));
        }

        return $query->whereRaw('1 = 0');
    }

    private function enrolledStudentIdsSubquery(int $instructorId): \Closure
    {
        return function ($subQuery) use ($instructorId) {
            $subQuery->select('course_enrollments.user_id')
                ->from('course_enrollments')
                ->join('courses', 'courses.id', '=', 'course_enrollments.course_id')
                ->where('courses.instructor_id', $instructorId)
                ->distinct();
        };
    }

    private function announcementsUrlFor(User $user): string
    {
        return $this->authService->homeUrlFor($user, ['tab' => 'announcements']);
    }
}
