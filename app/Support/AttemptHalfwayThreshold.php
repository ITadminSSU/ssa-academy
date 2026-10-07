<?php

namespace App\Support;

class AttemptHalfwayThreshold
{
    /**
     * Halfway attempt count for a failing learner, or null when a warning is not useful.
     */
    public static function threshold(int $maxAttempts): ?int
    {
        if ($maxAttempts <= 1) {
            return null;
        }

        return max(1, (int) ceil($maxAttempts / 2));
    }

    public static function shouldNotify(int $maxAttempts, int $attemptsUsed, bool $passed): bool
    {
        if ($passed) {
            return false;
        }

        $threshold = self::threshold($maxAttempts);

        if ($threshold === null) {
            return false;
        }

        return $attemptsUsed === $threshold;
    }
}
