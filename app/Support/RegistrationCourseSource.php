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

        if (preg_match('#/courses/details/[^/]+/(\d+)/?$#', $path, $matches) !== 1) {
            return null;
        }

        $id = (int) $matches[1];

        return $id > 0 ? $id : null;
    }
}
