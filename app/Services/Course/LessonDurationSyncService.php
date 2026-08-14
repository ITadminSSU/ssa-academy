<?php

namespace App\Services\Course;

use App\Models\Course\Course;
use App\Models\Course\SectionLesson;
use App\Services\BunnyStreamService;

class LessonDurationSyncService
{
    public function __construct(private BunnyStreamService $bunnyStream) {}

    /**
     * Fill missing lesson durations from Bunny Stream for videos still showing 00:00:00.
     */
    public function syncCourse(Course $course): Course
    {
        if (! $this->bunnyStream->isEnabled()) {
            return $course;
        }

        if (! $course->relationLoaded('sections')) {
            $course->load(['sections.section_lessons']);
        }

        foreach ($course->sections as $section) {
            foreach ($section->section_lessons as $lesson) {
                $this->syncLesson($lesson);
            }
        }

        return $course;
    }

    public function syncLesson(SectionLesson $lesson): void
    {
        if ($lesson->lesson_type !== 'video' || ! $lesson->bunny_video_id) {
            return;
        }

        if ($this->bunnyStream->durationToSeconds($lesson->duration) > 0) {
            return;
        }

        try {
            $duration = $this->bunnyStream->resolveDurationForVideoId($lesson->bunny_video_id);
        } catch (\Throwable) {
            return;
        }

        if (! $duration) {
            return;
        }

        $lesson->forceFill(['duration' => $duration])->save();
    }
}
