<?php

namespace App\Services\Course;

use App\Models\Course\Course;
use App\Models\Course\CourseLaunchNotification;
use App\Models\User;
use App\Notifications\CourseLaunchedNotification;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class CourseLaunchNotificationService
{
    public function __construct(
        private SettingsService $settingsService,
    ) {}

    public function subscribe(Course $course, string $email, ?User $user = null): CourseLaunchNotification
    {
        $normalizedEmail = Str::lower(trim($email));

        return CourseLaunchNotification::query()->updateOrCreate(
            [
                'course_id' => $course->id,
                'email' => $normalizedEmail,
            ],
            [
                'user_id' => $user?->id,
                'notified_at' => null,
            ],
        );
    }

    public function isSubscribed(Course $course, ?User $user = null, ?string $email = null): bool
    {
        if (!$user && !$email) {
            return false;
        }

        $normalizedEmail = $email ? Str::lower(trim($email)) : null;

        return CourseLaunchNotification::query()
            ->where('course_id', $course->id)
            ->where(function ($query) use ($user, $normalizedEmail) {
                if ($user) {
                    $query->where('user_id', $user->id);

                    if ($normalizedEmail) {
                        $query->orWhere('email', $normalizedEmail);
                    } else {
                        $query->orWhere('email', Str::lower(trim($user->email)));
                    }
                } elseif ($normalizedEmail) {
                    $query->where('email', $normalizedEmail);
                }
            })
            ->exists();
    }

    public function countForCourse(Course|int $course): int
    {
        $courseId = $course instanceof Course ? $course->id : $course;

        return CourseLaunchNotification::query()
            ->where('course_id', $courseId)
            ->count();
    }

    public function pendingCountForCourse(Course|int $course): int
    {
        $courseId = $course instanceof Course ? $course->id : $course;

        return CourseLaunchNotification::query()
            ->where('course_id', $courseId)
            ->whereNull('notified_at')
            ->count();
    }

    public function notifyWaitlist(Course $course): int
    {
        $this->applyMailConfig();

        $pending = CourseLaunchNotification::query()
            ->where('course_id', $course->id)
            ->whereNull('notified_at')
            ->get();

        $sent = 0;

        foreach ($pending as $subscription) {
            try {
                Notification::route('mail', $subscription->email)
                    ->notify(new CourseLaunchedNotification($course));

                $subscription->update(['notified_at' => now()]);
                $sent++;
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return $sent;
    }

    private function applyMailConfig(): void
    {
        $smtp = $this->settingsService->getSetting(['type' => 'smtp']);

        if (!$smtp || empty($smtp->fields)) {
            return;
        }

        setSmtpConfig($smtp->fields);
    }
}
