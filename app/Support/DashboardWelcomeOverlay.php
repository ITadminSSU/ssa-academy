<?php

namespace App\Support;

use App\Services\BunnyStreamService;

class DashboardWelcomeOverlay
{
    public const VIDEO_NONE = 'none';

    public const VIDEO_FILE = 'file';

    public const VIDEO_EMBED = 'embed';

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'enabled' => false,
            'headline' => 'Welcome to SSU Academy!',
            'body' => 'Browse our courses and start building skills that move your career forward.',
            'cta_label' => 'Browse Courses',
            'cta_url' => '/dashboard/browse/all',
            'poster_url' => '',
            'video_type' => self::VIDEO_NONE,
            'video_url' => '',
            'autoplay_muted' => true,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $fields
     * @return array<string, mixed>
     */
    public static function fromFields(?array $fields): array
    {
        $defaults = self::defaults();
        $fields = is_array($fields) ? $fields : [];

        $videoType = (string) ($fields['video_type'] ?? $defaults['video_type']);
        if (! in_array($videoType, [self::VIDEO_NONE, self::VIDEO_FILE, self::VIDEO_EMBED], true)) {
            $videoType = self::VIDEO_NONE;
        }

        $overlay = [
            'enabled' => filter_var($fields['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'headline' => trim((string) ($fields['headline'] ?? $defaults['headline'])),
            'body' => trim((string) ($fields['body'] ?? $defaults['body'])),
            'cta_label' => trim((string) ($fields['cta_label'] ?? $defaults['cta_label'])),
            'cta_url' => trim((string) ($fields['cta_url'] ?? $defaults['cta_url'])),
            'poster_url' => trim((string) ($fields['poster_url'] ?? $defaults['poster_url'])),
            'video_type' => $videoType,
            'video_url' => trim((string) ($fields['video_url'] ?? $defaults['video_url'])),
            'autoplay_muted' => filter_var($fields['autoplay_muted'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ];

        if ($overlay['video_url'] === '') {
            $overlay['video_type'] = self::VIDEO_NONE;
        }

        $overlay['version'] = self::version($overlay);

        return $overlay;
    }

    /**
     * @param  array<string, mixed>  $overlay
     */
    public static function version(array $overlay): string
    {
        $content = [
            'headline' => $overlay['headline'] ?? '',
            'body' => $overlay['body'] ?? '',
            'cta_label' => $overlay['cta_label'] ?? '',
            'cta_url' => $overlay['cta_url'] ?? '',
            'poster_url' => $overlay['poster_url'] ?? '',
            'video_type' => $overlay['video_type'] ?? self::VIDEO_NONE,
            'video_url' => $overlay['video_url'] ?? '',
            'autoplay_muted' => (bool) ($overlay['autoplay_muted'] ?? true),
        ];

        return hash('sha256', json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param  array<string, mixed>|null  $fields
     * @return array<string, mixed>|null
     */
    public static function publicPayload(?array $fields, ?string $dismissedVersion = null): ?array
    {
        $overlay = self::fromFields($fields);

        if (! $overlay['enabled']) {
            return null;
        }

        $hasContent = $overlay['headline'] !== ''
            || $overlay['body'] !== ''
            || $overlay['poster_url'] !== ''
            || $overlay['cta_label'] !== ''
            || ($overlay['video_type'] !== self::VIDEO_NONE && $overlay['video_url'] !== '');

        if (! $hasContent) {
            return null;
        }

        if ($dismissedVersion !== null && hash_equals($overlay['version'], $dismissedVersion)) {
            return null;
        }

        $videoType = $overlay['video_type'];
        $videoUrl = $overlay['video_url'];

        if ($videoType === self::VIDEO_EMBED && $videoUrl !== '') {
            $videoUrl = self::resolvePlayableEmbedUrl($videoUrl, (bool) $overlay['autoplay_muted']);
        }

        return [
            'version' => $overlay['version'],
            'headline' => $overlay['headline'],
            'body' => $overlay['body'],
            'cta_label' => $overlay['cta_label'],
            'cta_url' => $overlay['cta_url'] !== '' ? $overlay['cta_url'] : '/dashboard/browse/all',
            'poster_url' => self::resolvePosterUrl($overlay['poster_url']),
            'video_type' => $videoType,
            'video_url' => $videoUrl,
            'autoplay_muted' => (bool) $overlay['autoplay_muted'],
        ];
    }

    /**
     * Fresh Bunny signed embed (when token auth is on) + muted autoplay query params.
     */
    public static function resolvePlayableEmbedUrl(string $url, bool $mutedAutoplay = true): string
    {
        $url = trim($url);
        $videoId = self::extractBunnyVideoId($url);

        if ($videoId !== null) {
            try {
                $bunny = app(BunnyStreamService::class);
                if ($bunny->isEnabled()) {
                    $url = $bunny->signedEmbedUrl($videoId, now()->addHours(12));
                }
            } catch (\Throwable) {
                // Fall through to the stored URL.
            }
        }

        return self::withMutedAutoplayEmbedParams($url, $mutedAutoplay);
    }

    public static function extractBunnyVideoId(string $url): ?string
    {
        if (preg_match('#player\.mediadelivery\.net/embed/[^/]+/([a-f0-9\-]+)#i', $url, $matches)) {
            return $matches[1];
        }

        if (preg_match('#iframe\.mediadelivery\.net/embed/[^/]+/([a-f0-9\-]+)#i', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public static function withMutedAutoplayEmbedParams(string $url, bool $mutedAutoplay = true): string
    {
        $parts = parse_url($url);

        if ($parts === false || empty($parts['host'])) {
            return $url;
        }

        $query = [];
        if (! empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        if ($mutedAutoplay) {
            $query['autoplay'] = 'true';
            $query['muted'] = 'true';
            // YouTube
            $query['mute'] = '1';
            $query['playsinline'] = '1';
        }

        $query['preload'] = $query['preload'] ?? 'true';
        $query['playerjs'] = 'true';

        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'];
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return $scheme.'://'.$host.$port.$path.'?'.http_build_query($query).$fragment;
    }

    public static function resolvePosterUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://') || str_starts_with($url, '//')) {
            return $url;
        }

        if (function_exists('public_asset_url')) {
            return (string) public_asset_url($url);
        }

        return $url;
    }

    public static function isAllowedUrl(string $url): bool
    {
        $url = trim($url);

        if ($url === '') {
            return true;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return true;
        }

        return (bool) preg_match('#^(https?:|mailto:|tel:)#i', $url);
    }

    public static function isAllowedVideoUrl(string $url): bool
    {
        $url = trim($url);

        if ($url === '') {
            return true;
        }

        if (preg_match('#^https?://#i', $url)) {
            return true;
        }

        // Allow stored media/object paths (no scheme).
        return ! str_contains($url, '://') && ! str_starts_with($url, '//');
    }
}
