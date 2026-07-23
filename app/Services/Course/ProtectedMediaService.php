<?php

namespace App\Services\Course;

use App\Models\ChunkedUpload;
use App\Models\Course\Course;
use App\Models\Course\CourseEnrollment;
use App\Models\Course\LessonResource;
use App\Models\Course\SectionLesson;
use App\Models\Course\WatchHistory;
use App\Models\User;
use App\Services\BunnyStreamService;
use App\Services\Payment\SubscriptionAccessService;
use App\Support\S3CompatibleStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class ProtectedMediaService
{
    private const SIGNED_URL_TTL_HOURS = 2;

    private const VIDEO_SIGNED_URL_TTL_MINUTES = 30;

    /** Deliver via blob URL when file is at or below this size (bytes). */
    private const VIDEO_BLOB_MAX_BYTES = 52_428_800; // 50 MB

    public function __construct(
        private SubscriptionAccessService $subscriptionAccess,
        private BunnyStreamService $bunnyStream,
    ) {}

    public function protectLessonForPlayer(SectionLesson $lesson, User $user): SectionLesson
    {
        if (!$this->shouldSignLessonMedia($lesson)) {
            return $lesson;
        }

        if (in_array($lesson->lesson_type, ['video', 'video_url'], true)) {
            $lesson->setAttribute('stream_protected', true);
            $lesson->setAttribute('lesson_src', null);

            return $lesson;
        }

        $lesson->lesson_src = URL::temporarySignedRoute(
            'course.player.media',
            now()->addHours(self::SIGNED_URL_TTL_HOURS),
            ['lesson' => $lesson->id],
            absolute: false,
        );

        return $lesson;
    }

    /**
     * @return array{protected: bool, stream_url: string, delivery: string, expires_at: string, mime_type: string, embed_url?: string}
     */
    public function createVideoPlaybackPayload(SectionLesson $lesson, ?string $playbackToken = null): array
    {
        $expiresAt = now()->addMinutes(self::VIDEO_SIGNED_URL_TTL_MINUTES);

        if ($this->bunnyVideoIsDeliverable($lesson)) {
            $embedUrl = $this->bunnyStream->signedEmbedUrl($lesson->bunny_video_id, $expiresAt);

            return [
                'protected' => true,
                'stream_url' => $embedUrl,
                'embed_url' => $embedUrl,
                'delivery' => 'bunny_embed',
                'expires_at' => $expiresAt->toIso8601String(),
                'mime_type' => 'text/html',
            ];
        }

        $media = $this->resolveMediaForStreaming($lesson->getRawOriginal('lesson_src') ?: $lesson->lesson_src);

        if (!$media) {
            abort(404, 'Video file not found. Please re-upload this lesson video.');
        }

        $originalSrc = $lesson->getRawOriginal('lesson_src') ?: $lesson->lesson_src;
        $mimeType = $this->resolveMimeType($originalSrc, 'video/mp4');
        $delivery = 'signed';

        if ($media['type'] === 'local' && filesize($media['path']) <= self::VIDEO_BLOB_MAX_BYTES) {
            $delivery = 'blob';
        }

        $routeParams = ['lesson' => $lesson->id];

        // Embed the playback token in the signed URL so direct <video> playback
        // (used for larger files that bypass blob delivery) can authorize without
        // a custom request header, which the browser cannot attach to a media tag.
        if ($playbackToken !== null) {
            $routeParams['playback_token'] = $playbackToken;
        }

        return [
            'protected' => true,
            'stream_url' => URL::temporarySignedRoute(
                'course.player.video.stream',
                $expiresAt,
                $routeParams,
                absolute: false,
            ),
            'delivery' => $delivery,
            'expires_at' => $expiresAt->toIso8601String(),
            'mime_type' => $mimeType,
        ];
    }

    public function streamFileResponse(
        Request $request,
        string $filePath,
        string $mimeType,
    ): \Symfony\Component\HttpFoundation\Response {
        $fileSize = filesize($filePath);
        $start = 0;
        $end = $fileSize - 1;
        $status = 200;
        $headers = [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'no-referrer',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'Cache-Control' => 'private, no-store, max-age=0, no-transform',
            'Pragma' => 'no-cache',
            'Accept-Ranges' => 'bytes',
        ];

        if ($request->headers->has('Range')) {
            $range = $request->header('Range');

            if (preg_match('/bytes=(\d*)-(\d*)/', $range, $matches)) {
                $start = $matches[1] !== '' ? (int) $matches[1] : 0;
                $end = $matches[2] !== '' ? (int) $matches[2] : $fileSize - 1;
            }

            if ($start > $end || $start >= $fileSize) {
                return response('Requested range not satisfiable', 416, [
                    'Content-Range' => "bytes */{$fileSize}",
                ]);
            }

            $end = min($end, $fileSize - 1);
            $length = $end - $start + 1;
            $status = 206;
            $headers['Content-Length'] = (string) $length;
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$fileSize}";

            return response()->stream(function () use ($filePath, $start, $length) {
                $handle = fopen($filePath, 'rb');

                if ($handle === false) {
                    return;
                }

                fseek($handle, $start);

                $remaining = $length;

                while ($remaining > 0 && !feof($handle)) {
                    $chunk = fread($handle, min(8192, $remaining));

                    if ($chunk === false) {
                        break;
                    }

                    echo $chunk;
                    $remaining -= strlen($chunk);
                }

                fclose($handle);
            }, $status, $headers);
        }

        $headers['Content-Length'] = (string) $fileSize;

        return response()->file($filePath, $headers);
    }

    public function streamMediaResponse(Request $request, ?string $url, string $mimeType): Response
    {
        $media = $this->resolveMediaForStreaming($url);

        if (!$media) {
            abort(404, 'Media file not found.');
        }

        if ($media['type'] === 'local') {
            return $this->streamFileResponse($request, $media['path'], $mimeType);
        }

        return $this->streamObjectStorageResponse($request, $media['key'], $mimeType);
    }

    public function lessonVideoIsStreamable(SectionLesson $lesson): bool
    {
        if ($this->bunnyVideoIsDeliverable($lesson)) {
            return true;
        }

        $originalSrc = $lesson->getRawOriginal('lesson_src') ?: $lesson->lesson_src;

        return $this->resolveMediaForStreaming($originalSrc) !== null;
    }

    private function bunnyVideoIsDeliverable(SectionLesson $lesson): bool
    {
        return (bool) $lesson->bunny_video_id && $this->bunnyStream->isEnabled();
    }

    /**
     * @return array{type: 'local', path: string}|array{type: 's3', key: string}|null
     */
    public function resolveMediaForStreaming(?string $url): ?array
    {
        $localPath = $this->resolveLocalPath($url);

        if ($localPath && is_file($localPath)) {
            return ['type' => 'local', 'path' => $localPath];
        }

        $upload = $this->findChunkedUpload($url);

        if ($upload && $upload->disk === 's3' && $upload->key && $upload->status === 'completed') {
            return ['type' => 's3', 'key' => $upload->key];
        }

        return null;
    }

    public function findChunkedUpload(?string $url): ?ChunkedUpload
    {
        if (!$url) {
            return null;
        }

        return ChunkedUpload::where('file_url', $url)->first();
    }

    public function streamObjectStorageResponse(Request $request, string $key, string $mimeType): Response
    {
        $client = S3CompatibleStorage::makeClient();
        $bucket = (string) config('filesystems.disks.s3.bucket');
        $head = $client->headObject([
            'Bucket' => $bucket,
            'Key' => $key,
        ]);
        $fileSize = (int) ($head['ContentLength'] ?? 0);

        if ($fileSize <= 0) {
            abort(404, 'Media file not found.');
        }

        $start = 0;
        $end = $fileSize - 1;
        $status = 200;
        $headers = [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'no-referrer',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'Cache-Control' => 'private, no-store, max-age=0, no-transform',
            'Pragma' => 'no-cache',
            'Accept-Ranges' => 'bytes',
        ];

        $rangeHeader = null;

        if ($request->headers->has('Range')) {
            $range = $request->header('Range');

            if (preg_match('/bytes=(\d*)-(\d*)/', (string) $range, $matches)) {
                $start = $matches[1] !== '' ? (int) $matches[1] : 0;
                $end = $matches[2] !== '' ? (int) $matches[2] : $fileSize - 1;
            }

            if ($start > $end || $start >= $fileSize) {
                return response('Requested range not satisfiable', 416, [
                    'Content-Range' => "bytes */{$fileSize}",
                ]);
            }

            $end = min($end, $fileSize - 1);
            $length = $end - $start + 1;
            $status = 206;
            $headers['Content-Length'] = (string) $length;
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$fileSize}";
            $rangeHeader = "bytes={$start}-{$end}";
        } else {
            $headers['Content-Length'] = (string) $fileSize;
        }

        $getParams = [
            'Bucket' => $bucket,
            'Key' => $key,
        ];

        if ($rangeHeader !== null) {
            $getParams['Range'] = $rangeHeader;
        }

        $object = $client->getObject($getParams);
        $body = $object['Body'];

        return response()->stream(function () use ($body) {
            while (!$body->eof()) {
                echo $body->read(8192);
            }
        }, $status, $headers);
    }

    public function authorizeLessonAccess(User $user, SectionLesson $lesson): void
    {
        if ($user->role === 'admin') {
            return;
        }

        if ($user->role === 'instructor') {
            $course = Course::query()->find($lesson->course_id);

            if ($course && (int) $user->instructor_id === (int) $course->instructor_id) {
                return;
            }
        }

        $enrollment = CourseEnrollment::query()
            ->where('user_id', $user->id)
            ->where('course_id', $lesson->course_id)
            ->with('subscription')
            ->first();

        if (!$enrollment) {
            abort(403, 'You are not enrolled in this course.');
        }

        $course = Course::query()->findOrFail($lesson->course_id);
        $watchHistory = WatchHistory::query()
            ->where('user_id', $user->id)
            ->where('course_id', $lesson->course_id)
            ->first();

        if (!$this->subscriptionAccess->canAccessItem($user, $course, $lesson->id, 'lesson', $watchHistory, $enrollment)) {
            abort(403, 'Your subscription is inactive. Resubscribe to access new content.');
        }
    }

    public function authorizeResourceAccess(User $user, LessonResource $resource): void
    {
        $lesson = $resource->section_lesson()->firstOrFail();
        $this->authorizeLessonAccess($user, $lesson);
    }

    public function authorizeResourceDownload(User $user, LessonResource $resource): void
    {
        $this->authorizeResourceAccess($user, $resource);

        if (!$resource->is_downloadable) {
            abort(403, 'This resource is view-only and cannot be downloaded.');
        }
    }

    public function shouldSignLessonMedia(SectionLesson $lesson): bool
    {
        if (!in_array($lesson->lesson_type, ['video', 'document', 'image'], true)) {
            return false;
        }

        if ($lesson->bunny_video_id && $lesson->lesson_type === 'video') {
            return true;
        }

        if ($this->isLocalMediaUrl($lesson->lesson_src)) {
            return true;
        }

        return $this->isObjectStorageMedia($lesson->lesson_src);
    }

    public function isObjectStorageMedia(?string $url): bool
    {
        $upload = $this->findChunkedUpload($url);

        return $upload !== null
            && $upload->disk === 's3'
            && $upload->status === 'completed';
    }

    public function isLocalMediaUrl(?string $url): bool
    {
        if (!$url || trim($url) === '') {
            return false;
        }

        if ($this->isExternalVideoUrl($url)) {
            return false;
        }

        if (str_starts_with(trim($url), '<')) {
            return false;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
            $urlHost = parse_url($url, PHP_URL_HOST);

            if ($appHost && $urlHost && $appHost !== $urlHost) {
                return false;
            }
        }

        return str_contains($url, '/storage/') || str_starts_with(ltrim($url, '/'), 'storage/');
    }

    public function resolveLocalPath(?string $url): ?string
    {
        if (!$this->isLocalMediaUrl($url)) {
            return null;
        }

        $path = $url;

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $appUrl = rtrim((string) config('app.url'), '/');
            if (!str_starts_with($path, $appUrl)) {
                return null;
            }
            $path = substr($path, strlen($appUrl));
        }

        $path = ltrim($path, '/');

        if (!str_starts_with($path, 'storage/')) {
            return null;
        }

        $relative = substr($path, strlen('storage/'));

        // When media URLs are normalised with a public storage segment (e.g.
        // "app/public" on hosts whose public/storage symlink points at the whole
        // storage dir), strip it so the path maps back onto storage/app/public.
        $segment = trim((string) config('filesystems.public_storage_path', ''), '/');

        if ($segment !== '' && str_starts_with($relative, $segment . '/')) {
            $relative = substr($relative, strlen($segment . '/'));
        }

        return storage_path('app/public/' . $relative);
    }

    public function resolveLessonVideoPath(SectionLesson $lesson): ?string
    {
        $originalSrc = $lesson->getRawOriginal('lesson_src') ?: $lesson->lesson_src;
        $media = $this->resolveMediaForStreaming($originalSrc);

        if (!$media || $media['type'] !== 'local') {
            return null;
        }

        return $media['path'];
    }

    public function resolveMimeType(?string $url, ?string $fallback = 'application/octet-stream'): string
    {
        $chunkedUpload = $url ? ChunkedUpload::where('file_url', $url)->first() : null;

        if ($chunkedUpload?->mime_type) {
            return $chunkedUpload->mime_type;
        }

        $path = $this->resolveLocalPath($url);

        if ($path && file_exists($path)) {
            $detected = mime_content_type($path);

            if ($detected) {
                return $detected;
            }
        }

        return $fallback;
    }

    public function resolveResourcePath(LessonResource $resource): ?string
    {
        if ($resource->type === 'link') {
            return null;
        }

        $media = $this->resolveMediaForStreaming($resource->resource);

        if (!$media || $media['type'] !== 'local') {
            return null;
        }

        return $media['path'];
    }

    public function streamStoredFileDownload(?string $url, string $fallbackFilename): Response
    {
        $media = $this->resolveMediaForStreaming($url);

        if (!$media) {
            abort(404, 'File not found');
        }

        $chunkedUpload = $this->findChunkedUpload($url);
        $mimeType = $this->resolveMimeType($url);
        $filename = $chunkedUpload?->original_filename
            ?: ($chunkedUpload?->filename ?? $fallbackFilename);

        if ($media['type'] === 'local') {
            return response()->streamDownload(
                function () use ($media) {
                    $stream = fopen($media['path'], 'r');
                    fpassthru($stream);
                    fclose($stream);
                },
                $filename,
                [
                    'Content-Type' => $mimeType,
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                ],
            );
        }

        $client = S3CompatibleStorage::makeClient();
        $bucket = (string) config('filesystems.disks.s3.bucket');
        $object = $client->getObject([
            'Bucket' => $bucket,
            'Key' => $media['key'],
        ]);
        $body = $object['Body'];

        return response()->streamDownload(
            function () use ($body) {
                while (!$body->eof()) {
                    echo $body->read(8192);
                }
            },
            $filename,
            [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ],
        );
    }

    public function resourceIsStreamable(LessonResource $resource): bool
    {
        if ($resource->type === 'link') {
            return true;
        }

        return $this->resolveMediaForStreaming($resource->resource) !== null;
    }

    private function isExternalVideoUrl(string $url): bool
    {
        $externalHosts = ['youtube.com', 'youtu.be', 'vimeo.com', 'player.vimeo.com'];

        foreach ($externalHosts as $host) {
            if (str_contains($url, $host)) {
                return true;
            }
        }

        return false;
    }
}
