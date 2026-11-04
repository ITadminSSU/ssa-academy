<?php

namespace App\Services\Course;

use App\Models\ChunkedUpload;
use App\Models\Course\LessonResource;
use App\Models\Course\SectionLesson;
use App\Services\BunnyStreamService;
use App\Services\LocalFileUploadService;
use App\Services\S3MultipartUploadService;

/**
 * Deletes a Bunny video or uploaded lesson file only when this lesson is the last one using it.
 * A copied course can never wipe the original course's media by sharing an id or file URL.
 */
class LessonMediaGuard
{
    public function release(?string $bunnyVideoId, ?string $fileUrl, int $exceptLessonId): void
    {
        $bunnyVideoId = trim((string) $bunnyVideoId);
        $fileUrl = trim((string) $fileUrl);

        if ($bunnyVideoId !== '' && ! $this->bunnyVideoIsShared($bunnyVideoId, $exceptLessonId)) {
            app(BunnyStreamService::class)->deleteVideo($bunnyVideoId);
        }

        if ($fileUrl !== '' && ! $this->fileUrlIsShared($fileUrl, $exceptLessonId)) {
            $this->deleteChunkedFile($fileUrl);
        }
    }

    public function bunnyVideoIsShared(string $videoId, int $exceptLessonId): bool
    {
        return SectionLesson::query()
            ->where('bunny_video_id', $videoId)
            ->where('id', '!=', $exceptLessonId)
            ->exists();
    }

    public function fileUrlIsShared(string $fileUrl, int $exceptLessonId): bool
    {
        $usedByAnotherLesson = SectionLesson::query()
            ->where('lesson_src', $fileUrl)
            ->where('id', '!=', $exceptLessonId)
            ->exists();

        if ($usedByAnotherLesson) {
            return true;
        }

        return LessonResource::query()
            ->where('resource', $fileUrl)
            ->where('section_lesson_id', '!=', $exceptLessonId)
            ->exists();
    }

    private function deleteChunkedFile(string $fileUrl): void
    {
        $upload = ChunkedUpload::query()->where('file_url', $fileUrl)->first();

        if (! $upload) {
            return;
        }

        $disk = (string) ($upload->disk ?: '');
        $useS3 = $disk === 's3' || ($disk === '' && config('filesystems.default') === 's3');
        $uploader = $useS3
            ? app(S3MultipartUploadService::class)
            : app(LocalFileUploadService::class);

        $uploader->deleteFile($upload);
    }
}
