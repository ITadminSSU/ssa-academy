<?php

namespace App\Support;

use App\Models\Course\UsExperienceAttempt;

class UsExperienceFastResubmit
{
    public const THRESHOLD_SECONDS = 180;

    /**
     * A review flag when this attempt was submitted under 3 minutes after the previous one.
     *
     * @return array{
     *     gap_seconds: int,
     *     previous_attempt_number: int,
     *     gap_label: string,
     *     gap_after_attempt: string
     * }|null
     */
    public static function describe(UsExperienceAttempt $current, ?UsExperienceAttempt $previous): ?array
    {
        if (! $previous || ! $current->submitted_at || ! $previous->submitted_at) {
            return null;
        }

        $seconds = $current->submitted_at->getTimestamp() - $previous->submitted_at->getTimestamp();

        if ($seconds < 0 || $seconds >= self::THRESHOLD_SECONDS) {
            return null;
        }

        $phrase = self::gapPhrase($seconds);
        $previousNumber = (int) $previous->attempt_number;

        return [
            'gap_seconds' => $seconds,
            'previous_attempt_number' => $previousNumber,
            'gap_label' => $phrase.' after the previous attempt',
            'gap_after_attempt' => $phrase.' after attempt '.$previousNumber,
        ];
    }

    public static function gapPhrase(int $seconds): string
    {
        $minutes = intdiv($seconds, 60);
        $remain = $seconds % 60;

        if ($minutes < 1) {
            return $remain.' sec';
        }

        if ($remain === 0) {
            return $minutes.' min';
        }

        return $minutes.' min '.$remain.' sec';
    }
}
