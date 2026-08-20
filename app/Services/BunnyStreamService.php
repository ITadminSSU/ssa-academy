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
