<?php

namespace App\Console\Commands;

use App\Enums\CourseStatusType;
use App\Models\Course\Course;
use App\Services\Course\CourseLaunchNotificationService;
use App\Services\SettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PublishScheduledCoursesCommand extends Command
{
    protected $signature = 'courses:publish-scheduled';

    protected $description = 'Publish upcoming courses whose launch date has passed';

    public function handle(
        CourseLaunchNotificationService $launchNotifications,
        SettingsService $settingsService,
    ): int {
        $this->applyMailConfig($settingsService);

        $published = 0;
        $opened = 0;
        $notified = 0;

        $dueUpcoming = Course::query()
            ->where('status', CourseStatusType::UPCOMING->value)
            ->whereNotNull('launch_at')
            ->where('launch_at', '<=', now())
            ->get();

        foreach ($dueUpcoming as $course) {
            $course->update([
                'status' => CourseStatusType::APPROVED->value,
                'launch_at' => null,
            ]);

            $notified += $launchNotifications->notifyWaitlist($course->fresh())['sent'];
            $published++;
        }

        $dueApproved = Course::query()
            ->where('status', CourseStatusType::APPROVED->value)
            ->whereNotNull('launch_at')
            ->where('launch_at', '<=', now())
            ->get();

        foreach ($dueApproved as $course) {
            $course->update(['launch_at' => null]);
            $notified += $launchNotifications->notifyWaitlist($course->fresh())['sent'];
            $opened++;
        }

        if ($published > 0 || $opened > 0 || $this->output->isVerbose()) {
            $message = "Published {$published} upcoming course(s), opened {$opened} scheduled approved course(s), sent {$notified} launch notification(s).";
            $this->info($message);
            Log::info($message);
        } else {
            $this->line('No courses due for launch right now.');
            Log::debug('courses:publish-scheduled ran — no courses due for launch.');
        }

        return self::SUCCESS;
    }

    private function applyMailConfig(SettingsService $settingsService): void
    {
        $smtp = $settingsService->getSetting(['type' => 'smtp']);

        if (!$smtp || empty($smtp->fields)) {
            return;
        }

        setSmtpConfig($smtp->fields);
    }
}
