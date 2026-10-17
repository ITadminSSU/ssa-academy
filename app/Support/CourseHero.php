<?php

namespace App\Support;

use App\Models\ChunkedUpload;
use App\Models\Course\Course;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CourseHero
{
    /**
     * Chunked uploads store size in kilobytes, matching ChunkInitiateRequest.
     * 5 GiB = 5 * 1024 * 1024 KB.
     */
    public const MAX_VIDEO_KILOBYTES = 5 * 1024 * 1024;

    /**
     * @return list<string>
     */
    public static function phrasesFromInput(mixed $value): array
    {
        if (is_array($value)) {
            $parts = $value;
        } else {
            $text = trim((string) $value);

            if ($text === '') {
                return [];
            }

            $parts = preg_match("/\R/u", $text) === 1
                ? preg_split("/\R/u", $text)
                : explode(',', $text);
        }

        $clean = [];

        foreach ($parts ?: [] as $part) {
            $part = trim((string) $part);

            if ($part !== '') {
                $clean[] = $part;
            }
        }

        return array_values(array_unique($clean));
    }

    public static function blankToNull(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    public static function headlineContains(string $headline, string $phrase): bool
    {
        $phrase = trim($phrase);

        return $phrase !== '' && mb_stripos($headline, $phrase) !== false;
    }

    /**
     * @param  list<string>  $phrases
     * @return list<string>
     */
    public static function matchingPhrases(string $headline, array $phrases): array
    {
        return array_values(array_filter(
            $phrases,
            fn (string $phrase) => self::headlineContains($headline, $phrase),
        ));
    }

    public static function stableVideo(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return S3CompatibleStorage::attributeSet($value);
    }

    /**
     * Decide the address to store. An empty upload keeps the current file.
     * A rejected file throws before the caller writes anything.
     */
    public static function resolveVideo(string $source, ?string $url, ?string $stored, ?string $previous): ?string
    {
        if ($source !== 'upload') {
            $url = trim((string) $url);

            if ($url === '') {
                return null;
            }

            if (! S3CompatibleStorage::isExternalEmbedUrl($url)) {
                throw ValidationException::withMessages([
                    'hero_video_url' => 'Paste a YouTube or Vimeo link.',
                ]);
            }

            return $url;
        }

        $stable = self::stableVideo($stored);

        if ($stable === null) {
            $previous = trim((string) $previous);

            return $previous === '' ? null : self::stableVideo($previous);
        }

        if (S3CompatibleStorage::isExternalEmbedUrl($stable)) {
            throw ValidationException::withMessages([
                'hero_video_stored' => 'Upload an MP4, WebM, or MOV video.',
            ]);
        }

        $key = S3CompatibleStorage::extractObjectKey($stable) ?? $stable;
        $extension = strtolower((string) pathinfo($key, PATHINFO_EXTENSION));

        if (! in_array($extension, ['mp4', 'webm', 'mov'], true)) {
            throw ValidationException::withMessages([
                'hero_video_stored' => 'Upload an MP4, WebM, or MOV video.',
            ]);
        }

        if (! S3CompatibleStorage::sameStoredObject($stable, $previous)) {
            self::assertVideoWithinLimit($stable, $key);
        }

        return $stable;
    }

    /**
     * @return array{eyebrow: string, headline: string, highlights: list<string>, paragraph: string, photo: ?string, video: ?string}
     */
    public static function present(Course $course): array
    {
        $course->loadMissing('course_category');

        $headline = self::blankToNull($course->hero_headline) ?? (string) $course->title;
        $phrases = self::phrasesFromInput($course->hero_highlights ?? []);
        $photo = $course->hero_photo ?: $course->banner ?: $course->thumbnail;
        $video = $course->hero_video ?: $course->preview;

        return [
            'eyebrow' => self::blankToNull($course->hero_eyebrow)
                ?? (string) ($course->course_category->title ?? ''),
            'headline' => $headline,
            'highlights' => self::matchingPhrases($headline, $phrases),
            'paragraph' => self::blankToNull($course->hero_paragraph) ?? (string) ($course->short_description ?? ''),
            'photo' => $photo !== null && $photo !== '' ? $photo : null,
            'video' => $video !== null && $video !== '' ? $video : null,
        ];
    }

    private static function assertVideoWithinLimit(string $stable, string $key): void
    {
        if (! Schema::hasTable('chunked_uploads')) {
            return;
        }

        $upload = ChunkedUpload::query()->where('key', $key)->first()
            ?? ChunkedUpload::query()->where('file_url', $stable)->first();

        if (! $upload) {
            return;
        }

        if ((int) $upload->size > self::MAX_VIDEO_KILOBYTES) {
            throw ValidationException::withMessages([
                'hero_video_stored' => 'The preview video must be 5 GB or smaller. The previous video was kept.',
            ]);
        }

        $mime = strtolower((string) $upload->mime_type);

        if (! in_array($mime, ['video/mp4', 'video/webm', 'video/quicktime', 'video/x-quicktime'], true)) {
            throw ValidationException::withMessages([
                'hero_video_stored' => 'Upload an MP4, WebM, or MOV video.',
            ]);
        }
    }
}
