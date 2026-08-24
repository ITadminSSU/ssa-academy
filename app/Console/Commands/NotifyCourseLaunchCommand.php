<?php

namespace App\Console\Commands;

use App\Enums\CourseStatusType;
use App\Models\Course\Course;
use App\Services\Course\CourseLaunchNotificationService;
use Illuminate\Console\Command;

class NotifyCourseLaunchCommand extends Command
{
    protected $signature = 'ssu:notify-course-launch
                            {course : Course ID}
                            {--open : Clear the launch date and set status to approved before emailing}
                            {--force : Re-send even to people already marked as notified}';

    protected $description = 'Open a course (optional) and send Coming Soon waitlist launch emails';

    public function handle(CourseLaunchNotificationService $launchNotifications): int
    {
        $course = Course::query()->find($this->argument('course'));

        if (! $course) {
            $this->error('Course not found.');

            return self::FAILURE;
        }

        if ($this->option('open')) {
            $course->update([
                'status' => CourseStatusType::APPROVED->value,
                'launch_at' => null,
            ]);
            $course = $course->fresh();
            $this->info("Opened “{$course->title}” (approved, launch date cleared).");
        }

        if ($this->option('force')) {
            $reset = $launchNotifications->resetNotifiedForCourse($course);
            $this->info("Reset notified flag for {$reset} waitlist row(s).");
        }

        $pending = $launchNotifications->pendingCountForCourse($course);
        $this->info("Pending waitlist for “{$course->title}”: {$pending}");

        if ($pending === 0) {
            $this->warn('No pending waitlist emails to send.');

            return self::SUCCESS;
        }

        $sent = $launchNotifications->notifyWaitlist($course->fresh() ?? $course);

        $this->info("Sent {$sent} launch notification email(s).");

        if ($sent < $pending) {
            $this->warn('Some emails failed. Check storage/logs/laravel.log and SMTP settings.');
        }

        return self::SUCCESS;
    }
}
