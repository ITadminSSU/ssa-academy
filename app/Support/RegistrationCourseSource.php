<?php

namespace App\Support;

class RegistrationCourseSource
{
    public static function idFromUrl(?string $url): ?int
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            $path = $url;
        }

        $patterns = [
            '#/courses/details/[^/]+/(\d+)/?$#',
            '#/payments/[^/]+/course/(\d+)/?$#',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $path, $matches) !== 1) {
                continue;
            }

            $id = (int) $matches[1];

            return $id > 0 ? $id : null;
        }

        return null;
    }
}
