<?php

namespace App\Services\Course;

use App\Enums\CourseStatusType;
use App\Mail\CourseLaunchedMail;
use App\Models\Course\Course;
use App\Models\Course\CourseLaunchNotification;
use App\Models\User;
use App\Services\SettingsService;
use App\Support\MailConfigurator;
use App\Support\ResendHttpClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
        if (! $user && ! $email) {
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

    /**
     * @return array{sent: int, failed: int, last_error: string|null}
     */
    public function notifyWaitlist(Course $course): array
    {
        $smtpSetting = $this->settingsService->getSetting(['type' => 'smtp']);
        $fields = $smtpSetting?->fields ?? [];
        $apiKey = is_array($fields) ? ($fields['mail_password'] ?? null) : null;

        if (is_string($apiKey) && str_starts_with($apiKey, 're_')) {
            config(['services.resend.key' => $apiKey]);
        }

        MailConfigurator::applyFromSetting($smtpSetting);

        $pending = CourseLaunchNotification::query()
            ->where('course_id', $course->id)
            ->whereNull('notified_at')
            ->get();

        $sent = 0;
        $failed = 0;
        $lastError = null;

        foreach ($pending as $subscription) {
            $email = (string) $subscription->email;

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $failed++;
                $lastError = "Invalid waitlist email: {$email}";
                Log::warning('Course launch notification skipped: invalid email', [
                    'course_id' => $course->id,
                    'email' => $email,
                ]);

                continue;
            }

            try {
                $this->sendLaunchMail($course, $email);
                $subscription->update(['notified_at' => now()]);
                $sent++;
            } catch (\Throwable $exception) {
                $failed++;
                $lastError = $exception->getMessage();
                report($exception);
                Log::error('Course launch notification email failed', [
                    'course_id' => $course->id,
                    'email' => $email,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return [
            'sent' => $sent,
            'failed' => $failed,
            'last_error' => $lastError,
        ];
    }

    public function resetNotifiedForCourse(Course|int $course): int
    {
        $courseId = $course instanceof Course ? $course->id : $course;

        return CourseLaunchNotification::query()
            ->where('course_id', $courseId)
            ->whereNotNull('notified_at')
            ->update(['notified_at' => null]);
    }

    /**
     * Open the course (if still Coming Soon) and email the waitlist.
     *
     * @return array{opened: bool, sent: int, pending_before: int, failed: int, last_error: string|null}
     */
    public function openAndNotify(Course $course): array
    {
        $pendingBefore = $this->pendingCountForCourse($course);
        $opened = false;

        if ($course->isComingSoon()) {
            $course->update([
                'status' => CourseStatusType::APPROVED->value,
                'launch_at' => null,
            ]);
            $opened = true;
            $course = $course->fresh() ?? $course;
        }

        $result = $this->notifyWaitlist($course);

        return [
            'opened' => $opened,
            'sent' => $result['sent'],
            'pending_before' => $pendingBefore,
            'failed' => $result['failed'],
            'last_error' => $result['last_error'],
        ];
    }

    private function sendLaunchMail(Course $course, string $email): void
    {
        $mailable = new CourseLaunchedMail($course);
        $subject = $mailable->envelope()->subject;

        if (ResendHttpClient::isAvailable()) {
            ResendHttpClient::send([
                'from' => config('mail.from.name').' <'.config('mail.from.address').'>',
                'to' => [$email],
                'subject' => $subject,
                'html' => $mailable->render(),
            ]);

            return;
        }

        if (! MailConfigurator::isConfigured()) {
            throw new \RuntimeException(
                'Mail is not configured. Set SMTP or Resend in Website → SMTP settings.',
            );
        }

        Mail::to($email)->send($mailable);
    }
}
