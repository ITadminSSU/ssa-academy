<?php

namespace App\Services\Course;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class VideoPlaybackTokenService
{
    private const TTL_MINUTES = 30;

    public function issue(int $userId, int $lessonId): string
    {
        $token = Str::random(48);

        Cache::put($this->cacheKey($token), [
            'user_id' => $userId,
            'lesson_id' => $lessonId,
        ], now()->addMinutes(self::TTL_MINUTES));

        return $token;
    }

    public function validate(string $token, int $userId, int $lessonId): bool
    {
        $payload = Cache::get($this->cacheKey($token));

        if (!is_array($payload)) {
            return false;
        }

        return (int) ($payload['user_id'] ?? 0) === $userId
            && (int) ($payload['lesson_id'] ?? 0) === $lessonId;
    }

    private function cacheKey(string $token): string
    {
        return 'video_playback_token:' . $token;
    }
}
