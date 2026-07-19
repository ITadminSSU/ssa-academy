<?php

namespace App\Support;

class Branding
{
    public static function name(): string
    {
        return (string) config('branding.name', 'Smart Sourcing Academy');
    }

    public static function shortName(): string
    {
        return (string) config('branding.short_name', 'SSU Academy');
    }

    public static function author(): string
    {
        return (string) config('branding.author', 'Smart Sourcing USA');
    }

    public static function tagline(): string
    {
        return (string) config('branding.tagline', 'Enterprise training for teams and professionals within the construction industry.');
    }

    public static function keywords(): string
    {
        return (string) config('branding.keywords', 'SSU Academy, corporate training');
    }

    public static function description(): string
    {
        return (string) config('branding.description', 'Enterprise training for teams and professionals within the construction industry.');
    }

    public static function logos(): array
    {
        return config('branding.logos', []);
    }

    public static function logo(string $variant = 'dark'): ?string
    {
        return config("branding.logos.{$variant}");
    }

    public static function isLegacyLogo(?string $path): bool
    {
        if (!$path) {
            return true;
        }

        $normalized = strtolower($path);

        return str_contains($normalized, '/assets/icons/logo')
            || str_contains($normalized, '/favicon.ico')
            || str_contains($normalized, 'mentor')
            || str_contains($normalized, 'uilib');
    }

    public static function resolveLogo(?string $configured, string $variant = 'dark'): string
    {
        if (self::isLegacyLogo($configured)) {
            $path = (string) (self::logo($variant) ?? self::logo('dark'));
        } else {
            $path = $configured ?: (string) (self::logo($variant) ?? self::logo('dark'));
        }

        return self::versionPublicPath($path);
    }

    public static function versionPublicPath(string $path): string
    {
        if ($path === '' || str_contains($path, '://') || str_contains($path, '?v=')) {
            return $path;
        }

        $relative = ltrim((string) (parse_url($path, PHP_URL_PATH) ?? $path), '/');
        $fullPath = public_path($relative);

        if (!is_file($fullPath)) {
            return $path;
        }

        $separator = str_contains($path, '?') ? '&' : '?';

        return $path . $separator . 'v=' . filemtime($fullPath);
    }

    public static function payload(): array
    {
        $logos = self::logos();

        return [
            'name' => self::name(),
            'short_name' => self::shortName(),
            'author' => self::author(),
            'tagline' => self::tagline(),
            'keywords' => self::keywords(),
            'description' => self::description(),
            'logos' => [
                'icon' => self::versionPublicPath((string) ($logos['icon'] ?? '')),
                'dark' => self::versionPublicPath((string) ($logos['dark'] ?? '')),
                'light' => self::versionPublicPath((string) ($logos['light'] ?? '')),
                'favicon' => self::versionPublicPath((string) ($logos['favicon'] ?? '')),
            ],
        ];
    }

    public static function isLegacyName(?string $value): bool
    {
        if (!$value) {
            return false;
        }

        $normalized = strtolower(trim($value));

        foreach (config('branding.legacy_names', []) as $legacy) {
            if (strtolower(trim((string) $legacy)) === $normalized) {
                return true;
            }
        }

        return str_contains($normalized, 'mentor lms')
            || str_contains($normalized, 'lms academy')
            || str_contains($normalized, 'uilib');
    }

    public static function resolveSiteName(?string $configured = null): string
    {
        return self::isLegacyName($configured) || !$configured
            ? self::name()
            : $configured;
    }

    public static function resolveAuthor(?string $configured = null): string
    {
        return self::isLegacyName($configured) || !$configured
            ? self::author()
            : $configured;
    }
}
