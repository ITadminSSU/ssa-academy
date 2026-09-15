<?php

namespace App\Console\Commands;

use App\Services\Course\CompanionCourseEnrollmentService;
use Illuminate\Console\Command;

class GrantCompanionCourseCommand extends Command
{
    protected $signature = 'ssu:grant-companion-course
                            {--dry-run : Count who would be enrolled without writing}';

    protected $description = 'Enroll learners who already have active course access into the companion (gift) course.';

    public function handle(CompanionCourseEnrollmentService $companionCourse): int
    {
        if (! $companionCourse->isEnabled()) {
            $this->warn('Companion auto-enroll is off, or no companion course is selected in System settings.');

            return self::SUCCESS;
        }

        $result = $companionCourse->grantForExistingLearners((bool) $this->option('dry-run'));

        $verb = $result['dry_run'] ? 'Would enroll' : 'Enrolled';
        $this->info("{$verb} {$result['granted']} learner(s). Skipped {$result['skipped']} already enrolled.");

        return self::SUCCESS;
    }
}
