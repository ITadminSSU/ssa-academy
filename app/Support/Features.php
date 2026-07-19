<?php

namespace App\Support;

use Nwidart\Modules\Facades\Module;

class Features
{
    public static function enabled(string $key): bool
    {
        if ($key === 'blog') {
            return (bool) config('features.blog', false) && self::moduleEnabled('Blog');
        }

        return (bool) config("features.{$key}", false);
    }

    public static function moduleEnabled(string $name): bool
    {
        try {
            $module = Module::find($name);

            return $module ? $module->isEnabled() : false;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function payload(): array
    {
        return [
            'blog' => self::enabled('blog'),
            'job_circulars' => self::enabled('job_circulars'),
            'careers_page' => self::enabled('careers_page'),
            'newsletters' => self::enabled('newsletters'),
            'exams_public_nav' => self::enabled('exams_public_nav'),
        ];
    }

    public static function shouldHideNavbarItem(?string $title, ?string $url): bool
    {
        $title = trim((string) $title);
        $url = strtolower((string) $url);

        if (!self::enabled('blog') && (str_contains($url, '/blogs') || $title === 'Blogs')) {
            return true;
        }

        if (!self::enabled('careers_page') && (str_contains($url, '/careers') || $title === 'Careers')) {
            return true;
        }

        if (!self::enabled('job_circulars') && (str_contains($url, '/job-circulars') || str_contains($url, 'job-circular'))) {
            return true;
        }

        if (!self::enabled('exams_public_nav') && (str_contains($url, '/exams') || $title === 'Exams')) {
            return true;
        }

        if ($title === 'Our Team' || str_contains($url, '/our-team')) {
            return true;
        }

        return false;
    }
}
