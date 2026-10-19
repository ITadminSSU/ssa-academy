<?php

namespace App\Support;

class CourseReviewCarousel
{
    public const MAX_SLIDES = 24;

    /**
     * Stable object URLs, in display order.
     *
     * @return list<string>
     */
    public static function storedUrls(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($raw)) {
            return [];
        }

        $urls = [];

        foreach ($raw as $url) {
            if (! is_string($url)) {
                continue;
            }

            $stable = DrywallLanding::storedReviewImage($url);

            if ($stable !== null && $stable !== '') {
                $urls[] = $stable;
            }
        }

        return $urls;
    }

    /**
     * Browser-ready URLs. Signed private files stay loadable, and the stored value stays unsigned.
     *
     * @return list<string>
     */
    public static function browserUrls(mixed $raw): array
    {
        $urls = [];

        foreach (self::storedUrls($raw) as $url) {
            $browser = S3CompatibleStorage::attributeGet($url);

            if (is_string($browser) && $browser !== '') {
                $urls[] = $browser;
            }
        }

        return $urls;
    }

    /**
     * @param  list<string>  $known
     */
    public static function matchingIndex(array $known, string $url): ?int
    {
        foreach ($known as $index => $knownUrl) {
            if (S3CompatibleStorage::sameStoredObject($knownUrl, $url)) {
                return $index;
            }
        }

        return null;
    }
}
