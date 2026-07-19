<?php

namespace App\Services\Course;

use App\Models\Course\Course;
use App\Models\Course\SectionLesson;
use App\Models\Course\WatchHistory;

class LessonWatchProgressService
{
    public const COMPLETION_THRESHOLD = 95;

    public function isVideoLesson(SectionLesson $lesson): bool
    {
        return in_array($lesson->lesson_type, ['video', 'video_url'], true);
    }

    public function isExternalVideoLesson(SectionLesson $lesson): bool
    {
        if (in_array($lesson->lesson_provider, ['youtube', 'vimeo'], true)) {
            return true;
        }

        $src = (string) ($lesson->lesson_src ?? '');

        return str_contains($src, 'youtube.com')
            || str_contains($src, 'youtu.be')
            || str_contains($src, 'vimeo.com');
    }

    public function recordFullProgress(WatchHistory $watchHistory, int|string $lessonId): WatchHistory
    {
        return $this->recordProgress($watchHistory, $lessonId, 999999, 999999);
    }

    public function getProgressMap(WatchHistory $watchHistory): array
    {
        $raw = $watchHistory->lesson_watch_progress;

        if (is_array($raw)) {
            return $raw;
        }

        return json_decode($raw, true) ?: [];
    }

    public function getLessonProgress(WatchHistory $watchHistory, int|string $lessonId): array
    {
        $progress = $this->getProgressMap($watchHistory);
        $key = (string) $lessonId;

        return $progress[$key] ?? [
            'percent' => 0,
            'max_seconds' => 0,
            'duration_seconds' => 0,
        ];
    }

    public function recordProgress(
        WatchHistory $watchHistory,
        int|string $lessonId,
        float $currentTime,
        float $duration,
    ): WatchHistory {
        $progress = $this->getProgressMap($watchHistory);
        $key = (string) $lessonId;
        $existing = $progress[$key] ?? ['percent' => 0, 'max_seconds' => 0, 'duration_seconds' => 0];

        $maxSeconds = max((float) ($existing['max_seconds'] ?? 0), $currentTime);
        $durationSeconds = $duration > 0 ? $duration : (float) ($existing['duration_seconds'] ?? 0);
        $percent = $durationSeconds > 0
            ? min(100, round(($maxSeconds / $durationSeconds) * 100, 2))
            : 0;

        $progress[$key] = [
            'percent' => $percent,
            'max_seconds' => $maxSeconds,
            'duration_seconds' => $durationSeconds,
        ];

        $watchHistory->lesson_watch_progress = $progress;
        $watchHistory->save();

        return $watchHistory;
    }

    public function hasWatchedFully(WatchHistory $watchHistory, SectionLesson $lesson): bool
    {
        if (!$this->isVideoLesson($lesson)) {
            return true;
        }

        $progress = $this->getLessonProgress($watchHistory, $lesson->id);

        return (float) ($progress['percent'] ?? 0) >= self::COMPLETION_THRESHOLD;
    }

    public function allVideoLessonsWatched(Course $course, WatchHistory $watchHistory): bool
    {
        $videoLessons = $this->getVideoLessons($course);

        if ($videoLessons->isEmpty()) {
            return true;
        }

        foreach ($videoLessons as $lesson) {
            if (!$this->isLessonMarkedComplete($watchHistory, $lesson->id)) {
                return false;
            }

            if (!$this->hasWatchedFully($watchHistory, $lesson)) {
                return false;
            }
        }

        return true;
    }

    public function allLessonsCompleted(Course $course, WatchHistory $watchHistory): bool
    {
        foreach ($course->sections as $section) {
            foreach ($section->section_lessons as $lesson) {
                if (!$this->isLessonMarkedComplete($watchHistory, $lesson->id)) {
                    return false;
                }
            }
        }

        return true;
    }

    public function getVideoLessons(Course $course)
    {
        $course->loadMissing(['sections.section_lessons']);

        return $course->sections
            ->flatMap(fn ($section) => $section->section_lessons)
            ->filter(fn (SectionLesson $lesson) => $this->isVideoLesson($lesson))
            ->values();
    }

    private function isLessonMarkedComplete(WatchHistory $watchHistory, int|string $lessonId): bool
    {
        $completedItems = json_decode($watchHistory->completed_watching, true) ?: [];

        foreach ($completedItems as $item) {
            if ((string) $item['id'] === (string) $lessonId && $item['type'] === 'lesson') {
                return true;
            }
        }

        return false;
    }
}
