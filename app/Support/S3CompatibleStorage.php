<?php

namespace App\Support;

use Aws\S3\S3Client;
use DateTimeInterface;
use Illuminate\Support\Facades\Storage;

class S3CompatibleStorage
{
    /**
     * AWS SDK rejects an empty region and the Cloudflare placeholder "auto".
     * us-east-1 is valid for AWS S3 and for R2's S3-compatible API.
     */
    public static function normalizeRegion(?string $region): string
    {
        $region = strtolower(trim((string) $region));

        if ($region === '' || $region === 'auto') {
            return 'us-east-1';
        }

        return $region;
    }

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
            'region' => static::normalizeRegion(config('filesystems.disks.s3.region')),
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

        $region = static::normalizeRegion(config('filesystems.disks.s3.region'));

        return "https://{$bucket}.s3.{$region}.amazonaws.com/{$key}";
    }

    public static function temporaryObjectUrl(string $key, ?DateTimeInterface $expiresAt = null): string
    {
        $expiresAt ??= now()->addHours(12);

        try {
            return Storage::disk('s3')->temporaryUrl($key, $expiresAt);
        } catch (\Throwable) {
            return static::objectFileUrl($key);
        }
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

    /**
     * Eloquent Attribute get: sign private R2/S3 URLs for the browser; keep local paths app-absolute.
     */
    public static function attributeGet(?string $value): ?string
    {
        $resolved = static::resolvePlaybackUrl($value);

        if ($resolved === null || $resolved === '') {
            return $resolved;
        }

        if (static::isLocalPublicUrl($resolved) || ! str_starts_with($resolved, 'http')) {
            return public_asset_url($resolved) ?? $resolved;
        }

        return $resolved;
    }

    /**
     * Eloquent Attribute set: strip signatures / query strings before persisting.
     */
    public static function attributeSet(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        if (static::isLocalPublicUrl($value)) {
            return $value;
        }

        return static::normalizeStoredUrl($value) ?? $value;
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
            return static::decodeObjectKey(ltrim($value, '/'));
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

        $key = null;

        if ($bucket !== '' && str_starts_with($path, $bucket.'/')) {
            $key = substr($path, strlen($bucket) + 1) ?: null;
        } elseif ($publicBase !== '') {
            $publicPath = ltrim((string) parse_url($publicBase, PHP_URL_PATH), '/');
            if ($publicPath !== '' && str_starts_with($path, $publicPath.'/')) {
                $key = substr($path, strlen($publicPath) + 1) ?: null;
            }
        }

        if ($key === null && $path !== '') {
            $key = $path;
        }

        return $key !== null ? static::decodeObjectKey($key) : null;
    }

    /**
     * URL paths keep percent-encoding (e.g. %28 for "("). Signing re-encodes the key,
     * which turns %28 into %2528 and breaks R2/S3 lookups for filenames with spaces/parens.
     */
    public static function decodeObjectKey(string $key): string
    {
        $decoded = $key;

        for ($i = 0; $i < 3; $i++) {
            $next = rawurldecode($decoded);

            if ($next === $decoded) {
                break;
            }

            $decoded = $next;
        }

        return $decoded;
    }
}
