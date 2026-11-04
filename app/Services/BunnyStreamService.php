<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class BunnyStreamService
{
    private const API_BASE = 'https://video.bunnycdn.com';

    public function isEnabled(): bool
    {
        return (bool) config('bunny.enabled')
            && $this->libraryId() !== ''
            && $this->apiKey() !== '';
    }

    public function libraryId(): string
    {
        return (string) config('bunny.library_id');
    }

    public function apiKey(): string
    {
        return (string) config('bunny.api_key');
    }

    public function cdnHostname(): string
    {
        return trim((string) config('bunny.cdn_hostname'));
    }

    public function tokenAuthKey(): string
    {
        return (string) config('bunny.token_auth_key');
    }

    /**
     * @return array<string, mixed>
     */
    public function createVideo(string $title): array
    {
        $response = Http::withHeaders($this->headers())
            ->post(self::API_BASE . '/library/' . $this->libraryId() . '/videos', [
                'title' => $title,
            ]);

        if (!$response->successful()) {
            Log::error('Bunny Stream create video failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('Unable to create Bunny Stream video.');
        }

        return $response->json();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getVideo(string $videoId): ?array
    {
        $response = Http::withHeaders($this->headers())
            ->get(self::API_BASE . '/library/' . $this->libraryId() . '/videos/' . $videoId);

        if ($response->status() === 404) {
            return null;
        }

        if (!$response->successful()) {
            Log::error('Bunny Stream get video failed', [
                'video_id' => $videoId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('Unable to load Bunny Stream video.');
        }

        return $response->json();
    }

    public function deleteVideo(string $videoId): bool
    {
        $response = Http::withHeaders($this->headers())
            ->delete(self::API_BASE . '/library/' . $this->libraryId() . '/videos/' . $videoId);

        return $response->successful() || $response->status() === 404;
    }

    /**
     * Download an existing library video and upload it as a new video id.
     * The source video is only read. A failed copy deletes the empty new video.
     */
    public function copyVideo(string $sourceVideoId, string $title): string
    {
        $sourceVideoId = trim($sourceVideoId);

        if (! $this->isEnabled()) {
            throw new RuntimeException('Bunny Stream is not enabled, so this course\'s videos cannot be copied.');
        }

        if ($sourceVideoId === '' || ! $this->getVideo($sourceVideoId)) {
            throw new RuntimeException('A lesson video could not be found in Bunny Stream, so the course was not copied.');
        }

        $created = $this->createVideo($title !== '' ? $title : 'Copied lesson video');
        $newId = (string) ($created['guid'] ?? $created['id'] ?? '');

        if ($newId === '' || $newId === $sourceVideoId) {
            throw new RuntimeException('Bunny Stream did not create a separate video, so the course was not copied.');
        }

        $tempFile = null;
        $stream = null;

        try {
            $tempFile = $this->downloadSourceVideo($sourceVideoId);
            $size = filesize($tempFile);

            if ($size === false || $size < 1) {
                throw new RuntimeException('A lesson video could not be read for copying, so the course was not copied.');
            }

            $stream = fopen($tempFile, 'rb');

            if ($stream === false) {
                throw new RuntimeException('A lesson video could not be read for copying, so the course was not copied.');
            }

            $upload = Http::withHeaders([
                'AccessKey' => $this->apiKey(),
                'Accept' => 'application/json',
                'Content-Length' => (string) $size,
            ])->timeout(600)
                ->withBody($stream, 'application/octet-stream')
                ->put(self::API_BASE.'/library/'.$this->libraryId().'/videos/'.$newId);

            if (! $upload->successful()) {
                throw new RuntimeException('Bunny Stream rejected the copied video, so the course was not copied.');
            }
        } catch (\Throwable $exception) {
            $this->deleteVideo($newId);

            if ($exception instanceof RuntimeException) {
                throw $exception;
            }

            throw new RuntimeException('A lesson video could not be copied, so the course was not copied.', 0, $exception);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }

            if ($tempFile && is_file($tempFile)) {
                @unlink($tempFile);
            }
        }

        return $newId;
    }

    private function downloadSourceVideo(string $videoId): string
    {
        $host = preg_replace('#^https?://#i', '', $this->cdnHostname());
        $host = rtrim((string) $host, '/');

        if ($host === '') {
            throw new RuntimeException('Bunny Stream has no CDN hostname, so this course\'s videos cannot be copied.');
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'bunny-copy-');

        if ($tempFile === false) {
            throw new RuntimeException('A lesson video could not be copied, so the course was not copied.');
        }

        $paths = ['original', 'play_720p.mp4', 'play_480p.mp4', 'play_360p.mp4'];

        foreach ($paths as $path) {
            $urls = ['https://'.$host.'/'.$videoId.'/'.$path];

            if ($this->tokenAuthKey() !== '') {
                $urls[] = $this->signCdnUrl($urls[0]);
            }

            foreach ($urls as $url) {
                $response = Http::timeout(600)->sink($tempFile)->get($url);

                if ($response->successful() && is_file($tempFile) && filesize($tempFile) > 0) {
                    return $tempFile;
                }
            }
        }

        @unlink($tempFile);

        throw new RuntimeException('Bunny Stream could not read a lesson video, so the course was not copied.');
    }

    private function signCdnUrl(string $url): string
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');
        $expires = time() + 3600;
        $hash = hash('sha256', $this->tokenAuthKey().$path.$expires, true);
        $token = rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.'token='.$token.'&expires='.$expires;
    }

    /**
     * @return array{video_id: string, library_id: string, authorization_signature: string, authorization_expire: int, tus_endpoint: string}
     */
    public function tusUploadCredentials(string $videoId, int $ttlSeconds = 3600): array
    {
        $expiration = time() + $ttlSeconds;
        $signature = hash('sha256', $this->libraryId() . $this->apiKey() . $expiration . $videoId);

        return [
            'video_id' => $videoId,
            'library_id' => $this->libraryId(),
            'authorization_signature' => $signature,
            'authorization_expire' => $expiration,
            'tus_endpoint' => 'https://video.bunnycdn.com/tusupload',
        ];
    }

    public function videoIsPlayable(string $videoId): bool
    {
        $video = $this->getVideo($videoId);

        if (!$video) {
            return false;
        }

        return in_array((int) ($video['status'] ?? 0), [2, 3, 4], true);
    }

    public function signedEmbedUrl(string $videoId, ?\DateTimeInterface $expiresAt = null): string
    {
        $baseUrl = 'https://iframe.mediadelivery.net/embed/' . $this->libraryId() . '/' . $videoId;
        // Do not start muted — welcome overlay starts the iframe only after a user tap.
        $query = 'autoplay=true&muted=false&preload=true&responsive=true&playerjs=true';

        if ($this->tokenAuthKey() === '') {
            return $baseUrl . '?' . $query;
        }

        $expiresAt ??= now()->addHours(12);
        $expires = $expiresAt->getTimestamp();
        $token = $this->signToken($videoId, $expires);

        return $baseUrl . '?token=' . $token . '&expires=' . $expires . '&' . $query;
    }

    /**
     * Direct MP4 for native <video> playback (reliable tap-to-unmute).
     * Requires Stream CDN hostname (e.g. vz-xxxxx.b-cdn.net).
     */
    public function directPlayMp4Url(string $videoId, string $quality = '720p'): ?string
    {
        $host = preg_replace('#^https?://#i', '', $this->cdnHostname());
        $host = rtrim((string) $host, '/');

        if ($host === '' || $videoId === '') {
            return null;
        }

        $quality = preg_replace('/[^0-9p]/', '', $quality) ?: '720p';

        return 'https://'.$host.'/'.$videoId.'/play_'.$quality.'.mp4';
    }

    public function formatDuration(int $seconds): string
    {
        return gmdate('H:i:s', max(0, $seconds));
    }

    public function durationToSeconds(?string $duration): int
    {
        $duration = trim((string) $duration);

        if ($duration === '' || $duration === '00:00:00') {
            return 0;
        }

        $parts = array_map('intval', explode(':', $duration));

        if (count($parts) === 3) {
            return ($parts[0] * 3600) + ($parts[1] * 60) + $parts[2];
        }

        if (count($parts) === 2) {
            return ($parts[0] * 60) + $parts[1];
        }

        return max(0, (int) $duration);
    }

    /**
     * Wait briefly for Bunny to finish probing length after upload.
     *
     * @return array{bunny_video_id: string, duration: string, thumbnail: string|null, status: int, length: int}
     */
    public function completeUpload(string $videoId, int $maxAttempts = 8, int $delayMs = 1000): array
    {
        $video = null;
        $length = 0;
        $status = 0;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $video = $this->getVideo($videoId);

            if (! $video) {
                throw new RuntimeException('Uploaded Bunny Stream video was not found.');
            }

            $status = (int) ($video['status'] ?? 0);

            if ($status === 5) {
                throw new RuntimeException('Bunny Stream reported a processing error for this video.');
            }

            $length = (int) ($video['length'] ?? 0);

            if ($length > 0) {
                break;
            }

            // Status 4 = finished encoding on Bunny Stream; still retry if length is missing.
            if ($attempt < $maxAttempts - 1) {
                usleep($delayMs * 1000);
            }
        }

        return [
            'bunny_video_id' => $videoId,
            'duration' => $this->formatDuration($length),
            'thumbnail' => $video['thumbnailUrl'] ?? $video['thumbnailFileName'] ?? null,
            'status' => $status,
            'length' => $length,
        ];
    }

    public function resolveDurationForVideoId(string $videoId): ?string
    {
        $video = $this->getVideo($videoId);

        if (! $video) {
            return null;
        }

        $length = (int) ($video['length'] ?? 0);

        return $length > 0 ? $this->formatDuration($length) : null;
    }

    private function signToken(string $videoId, int $expires): string
    {
        $key = $this->tokenAuthKey();

        if ($key === '') {
            return '';
        }

        return hash('sha256', $key . $videoId . $expires);
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'AccessKey' => $this->apiKey(),
            'Accept' => 'application/json',
        ];
    }
}
