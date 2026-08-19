<?php

namespace App\Console\Commands;

use App\Models\Course\CourseEnrollment;
use App\Services\Course\CourseEnrollmentWelcomeMailService;
use Illuminate\Console\Command;

class ResendWelcomeEmailCommand extends Command
{
    protected $signature = 'ssu:resend-welcome-email
                            {--email= : Student email address}
                            {--course= : Course title (partial match)}
                            {--course-id= : Course ID}
                            {--force : Send even if welcome_email_sent_at is already set}';

    protected $description = 'Resend the course enrollment welcome email by student email (no enrollment ID needed)';

    public function handle(CourseEnrollmentWelcomeMailService $welcomeMail): int
    {
        $email = trim((string) $this->option('email'));
        $courseTitle = trim((string) $this->option('course'));
        $courseId = $this->option('course-id');

        if ($email === '') {
            $this->error('Provide --email=student@example.com');

            return self::FAILURE;
        }

        $query = CourseEnrollment::query()
            ->with(['user', 'course.instructor.user'])
            ->whereHas('user', fn ($q) => $q->where('email', $email));

        if ($courseId) {
            $query->where('course_id', $courseId);
        } elseif ($courseTitle !== '') {
            $query->whereHas('course', fn ($q) => $q->where('title', 'like', '%'.$courseTitle.'%'));
        }

        $enrollments = $query->orderByDesc('id')->get();

        if ($enrollments->isEmpty()) {
            $this->error('No enrollment found for that email'.($courseTitle !== '' || $courseId ? ' and course' : '').'.');

            return self::FAILURE;
        }

        if ($enrollments->count() > 1 && ! $courseId && $courseTitle === '') {
            $this->warn('Multiple enrollments found — pick one with --course="Course Name" or --course-id=ID:');
            foreach ($enrollments as $enrollment) {
                $this->line(sprintf(
                    '  ID %d — %s (status: %s, welcome sent: %s)',
                    $enrollment->id,
                    $enrollment->course?->title ?? 'Unknown course',
                    $enrollment->access_status?->value ?? 'unknown',
                    $enrollment->welcome_email_sent_at?->toDateTimeString() ?? 'never',
                ));
            }

            return self::FAILURE;
        }

        $enrollment = $enrollments->first();
        $force = (bool) $this->option('force');

        $this->line('Enrollment ID: '.$enrollment->id);
        $this->line('Course: '.($enrollment->course?->title ?? 'unknown'));
        $this->line('Access: '.($enrollment->access_status?->value ?? 'unknown'));
        $this->line('Welcome sent at: '.($enrollment->welcome_email_sent_at?->toDateTimeString() ?? 'never'));
        $this->line('Recipient: '.($enrollment->user?->email ?? $email));
        $this->newLine();

        if ($welcomeMail->sendForEnrollment($enrollment, $force)) {
            $this->info('Welcome email sent successfully.');

            return self::SUCCESS;
        }

        $this->error('Welcome email was not sent. Run: php artisan ssu:verify-integrations --email='.$email.' --smtp-only');
        $this->line('Then check storage/logs/laravel.log for "Course enrollment welcome email".');

        return self::FAILURE;
    }
}
