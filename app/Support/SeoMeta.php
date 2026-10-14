<?php

namespace App\Support;

use Illuminate\Http\Request;

class SeoMeta
{
    /**
     * Path prefixes that must not appear in search results.
     *
     * @var list<string>
     */
    private const NOINDEX_PREFIXES = [
        'dashboard',
        'login',
        'register',
        'forgot-password',
        'verify-email',
        'password-reset',
        'payments',
        'play-course',
        'student',
        'two-factor-challenge',
        'confirm-password',
        'settings',
        'email',
        'messages',
        'legal',
        'confirm-email-change',
        'auth',
    ];

    public const HOME_DESCRIPTION = 'Build practical skills in U.S. construction workflows, estimating, software, and professional development with SMARTSOURCING USA Academy.';

    public const ABOUT_DESCRIPTION = 'SMARTSOURCING USA Academy is a professional learning platform for structured training, hands-on practice, and verified certification.';

    public const FAQ_DESCRIPTION = 'Answers about SmartSourcing USA Academy courses, training, certification, and career opportunities.';

    public static function canonicalUrl(Request $request): string
    {
        $base = rtrim((string) config('app.url'), '/');
        $path = trim($request->path(), '/');

        return $path === '' ? $base.'/' : $base.'/'.$path;
    }

    public static function isIndexable(Request $request): bool
    {
        $path = trim($request->path(), '/');

        if ($path === '') {
            return true;
        }

        foreach (self::NOINDEX_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return false;
            }
        }

        return true;
    }

    public static function robotsContent(Request $request): string
    {
        return self::isIndexable($request) ? 'index, follow' : 'noindex, nofollow';
    }

    public static function descriptionOverride(Request $request): ?string
    {
        return match ($request->route()?->getName()) {
            'home' => self::HOME_DESCRIPTION,
            'about' => self::ABOUT_DESCRIPTION,
            'faqs' => self::FAQ_DESCRIPTION,
            default => null,
        };
    }
}
