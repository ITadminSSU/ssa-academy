<?php

namespace App\Services\UsExperience;

use App\Models\Course\UsExperienceAttempt;
use App\Models\User;
use App\Notifications\UsExperienceFastResubmitNotification;
use App\Support\UsExperienceFastResubmit;
use Illuminate\Support\Facades\Cache;

class UsExperienceFastResubmitAlertService
{
    public function afterAttempt(UsExperienceAttempt $attempt): void
    {
        $attempt->loadMissing(['user', 'plan.course.instructor.user']);

        $review = UsExperienceFastResubmit::describe($attempt, $attempt->earlierAttempt());

        if ($review === null || ! $attempt->user || ! $attempt->plan) {
            return;
        }

        $course = $attempt->plan->course;
        $instructor = $course?->instructor?->user;

        if (! $instructor || ! $instructor->isAccountActive() || ! $course) {
            return;
        }

        $dedupKey = 'attempt:'.$attempt->id;

        if ($this->alreadyNotified($instructor, $dedupKey)) {
            return;
        }

        if (! Cache::add($this->queuedKey($dedupKey), 1, now()->addDays(60))) {
            return;
        }

        $instructor->notify(new UsExperienceFastResubmitNotification(
            student: $attempt->user,
            planTitle: (string) $attempt->plan->title,
            planId: (int) $attempt->plan->id,
            attemptId: (int) $attempt->id,
            attemptNumber: (int) $attempt->attempt_number,
            gapAfterAttempt: $review['gap_after_attempt'],
            scoreSummary: $this->scoreSummary($attempt),
            url: route('courses.us-experience.attempts.show', [
                'course' => $course->id,
                'plan' => $attempt->plan->id,
                'attempt' => $attempt->id,
            ]),
            dedupKey: $dedupKey,
        ));
    }

    public function queuedKey(string $dedupKey): string
    {
        return 'us-experience-fast-resubmit:queued:'.$dedupKey;
    }

    private function alreadyNotified(User $instructor, string $dedupKey): bool
    {
        return $instructor->notifications()
            ->where('type', UsExperienceFastResubmitNotification::class)
            ->where('data->dedup_key', $dedupKey)
            ->exists();
    }

    private function scoreSummary(UsExperienceAttempt $attempt): string
    {
        if ($attempt->lines_percent === null) {
            return 'unavailable';
        }

        $summary = $this->formatNumber($attempt->lines_percent).'%';
        $passMark = $attempt->plan?->pass_mark;

        if ($passMark !== null && $passMark !== '') {
            $summary .= ' (pass mark '.$this->formatNumber($passMark).'%)';
        }

        return $summary;
    }

    private function formatNumber(mixed $value): string
    {
        $number = (float) $value;

        if (floor($number) == $number) {
            return (string) (int) $number;
        }

        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }
}
