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
        $baseUrl = 'https://player.mediadelivery.net/embed/' . $this->libraryId() . '/' . $videoId;
        $query = 'autoplay=false&preload=true&playerjs=true';

        if ($this->tokenAuthKey() === '') {
            return $baseUrl . '?' . $query;
        }

        $expiresAt ??= now()->addHour();
        $expires = $expiresAt->getTimestamp();
        $token = $this->signToken($videoId, $expires);

        return $baseUrl . '?token=' . $token . '&expires=' . $expires . '&' . $query;
    }

    public function formatDuration(int $seconds): string
    {
        return gmdate('H:i:s', max(0, $seconds));
    }

    /**
     * @return array{bunny_video_id: string, duration: string, thumbnail: string|null, status: int}
     */
    public function completeUpload(string $videoId): array
    {
        $video = $this->getVideo($videoId);

        if (!$video) {
            throw new RuntimeException('Uploaded Bunny Stream video was not found.');
        }

        $status = (int) ($video['status'] ?? 0);

        if ($status === 5) {
            throw new RuntimeException('Bunny Stream reported a processing error for this video.');
        }

        return [
            'bunny_video_id' => $videoId,
            'duration' => $this->formatDuration((int) ($video['length'] ?? 0)),
            'thumbnail' => $video['thumbnailUrl'] ?? $video['thumbnailFileName'] ?? null,
            'status' => $status,
        ];
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
