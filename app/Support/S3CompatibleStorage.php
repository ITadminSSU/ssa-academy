<?php

namespace App\Support;

use Aws\S3\S3Client;
use DateTimeInterface;
use Illuminate\Support\Facades\Storage;

class S3CompatibleStorage
{
    public static function isR2Endpoint(?string $endpoint): bool
    {
        if (!$endpoint) {
            return false;
        }

        return str_contains(strtolower($endpoint), 'r2.cloudflarestorage.com');
    }

    /**
     * @return array<string, mixed>
     */
    public static function clientConfig(): array
    {
        $config = [
            'credentials' => [
                'key' => config('filesystems.disks.s3.key'),
                'secret' => config('filesystems.disks.s3.secret'),
            ],
            'region' => config('filesystems.disks.s3.region') ?: 'auto',
            'version' => 'latest',
        ];

        $endpoint = config('filesystems.disks.s3.endpoint');

        if ($endpoint) {
            $config['endpoint'] = $endpoint;
            $config['use_path_style_endpoint'] = (bool) config('filesystems.disks.s3.use_path_style_endpoint', false);
        }

        return $config;
    }

    public static function makeClient(): S3Client
    {
        return new S3Client(static::clientConfig());
    }

    public static function objectFileUrl(string $key): string
    {
        $bucket = (string) config('filesystems.disks.s3.bucket');
        $endpoint = config('filesystems.disks.s3.endpoint');

        if ($endpoint) {
            $base = rtrim((string) $endpoint, '/');

            if (config('filesystems.disks.s3.use_path_style_endpoint')) {
                return "{$base}/{$bucket}/{$key}";
            }

            return "{$base}/{$key}";
        }

        $region = config('filesystems.disks.s3.region');

        return "https://{$bucket}.s3.{$region}.amazonaws.com/{$key}";
    }

    public static function temporaryObjectUrl(string $key, ?DateTimeInterface $expiresAt = null): string
    {
        $expiresAt ??= now()->addHours(12);

        return Storage::disk('s3')->temporaryUrl($key, $expiresAt);
    }

    /**
     * Resolve a stored media URL for browser playback.
     * Private R2/S3 object URLs are converted to temporary signed URLs.
     */
    public static function resolvePlaybackUrl(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return $url;
        }

        $url = trim($url);

        if (static::isExternalEmbedUrl($url) || static::isLocalPublicUrl($url)) {
            return $url;
        }

        $key = static::extractObjectKey($url);

        if ($key === null) {
            return $url;
        }

        try {
            return static::temporaryObjectUrl($key);
        } catch (\Throwable) {
            return $url;
        }
    }

    /**
     * Persist a stable (unsigned) object URL / path instead of a short-lived signed URL.
     */
    public static function normalizeStoredUrl(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $url = trim($url);

        if (static::isExternalEmbedUrl($url) || static::isLocalPublicUrl($url)) {
            return $url;
        }

        $key = static::extractObjectKey($url);

        if ($key === null) {
            return $url;
        }

        return static::objectFileUrl($key);
    }

    public static function isExternalEmbedUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($host === '') {
            return false;
        }

        return str_contains($host, 'youtube.com')
            || str_contains($host, 'youtu.be')
            || str_contains($host, 'vimeo.com');
    }

    public static function isLocalPublicUrl(string $url): bool
    {
        if (str_starts_with($url, '/')) {
            return true;
        }

        $path = (string) parse_url($url, PHP_URL_PATH);

        return str_contains($path, '/storage/') || str_contains($path, '/assets/');
    }

    /**
     * Extract the object key from a raw key, private endpoint URL, public AWS_URL, or signed URL.
     */
    public static function extractObjectKey(string $urlOrKey): ?string
    {
        $value = trim($urlOrKey);

        if ($value === '') {
            return null;
        }

        if (! str_starts_with($value, 'http://') && ! str_starts_with($value, 'https://')) {
            return ltrim($value, '/');
        }

        $path = ltrim((string) parse_url($value, PHP_URL_PATH), '/');

        if ($path === '') {
            return null;
        }

        $bucket = (string) config('filesystems.disks.s3.bucket');
        $endpoint = rtrim((string) config('filesystems.disks.s3.endpoint'), '/');
        $publicBase = rtrim((string) config('filesystems.disks.s3.url'), '/');
        $host = strtolower((string) parse_url($value, PHP_URL_HOST));
        $endpointHost = strtolower((string) parse_url($endpoint, PHP_URL_HOST));

        $isOurHost = false;

        if ($endpointHost !== '' && ($host === $endpointHost || str_ends_with($host, '.'.$endpointHost))) {
            $isOurHost = true;
        }

        if ($publicBase !== '') {
            $publicHost = strtolower((string) parse_url($publicBase, PHP_URL_HOST));
            if ($publicHost !== '' && $host === $publicHost) {
                $isOurHost = true;
            }
        }

        if (! $isOurHost && ! str_contains($host, 'r2.cloudflarestorage.com') && ! str_contains($host, '.amazonaws.com')) {
            return null;
        }

        if ($bucket !== '' && str_starts_with($path, $bucket.'/')) {
            return substr($path, strlen($bucket) + 1) ?: null;
        }

        if ($publicBase !== '') {
            $publicPath = ltrim((string) parse_url($publicBase, PHP_URL_PATH), '/');
            if ($publicPath !== '' && str_starts_with($path, $publicPath.'/')) {
                return substr($path, strlen($publicPath) + 1) ?: null;
            }
        }

        return $path !== '' ? $path : null;
    }
}
