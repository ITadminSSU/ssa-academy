<?php

namespace App\Support;

class LegalPageCopy
{
    /** @var list<string> */
    public const SLUGS = [
        'cookie-policy',
        'terms-and-conditions',
        'privacy-policy',
        'refund-policy',
        'non-disclosure-agreement',
    ];

    /**
     * Remove leading headings that only repeat the page name.
     * The rest of the saved page, including legal sentences, stays as written.
     */
    public static function stripLeadingPageTitle(string $html, string $pageName): string
    {
        $rest = ltrim($html);

        while ($rest !== '' && preg_match('/^<h([1-6])\b[^>]*>([\s\S]*?)<\/h\1>/i', $rest, $match) === 1) {
            if (! self::isRepeatedPageTitle($match[2], $pageName)) {
                break;
            }

            $rest = ltrim(substr($rest, strlen($match[0])));
        }

        return $rest;
    }

    private static function isRepeatedPageTitle(string $headingHtml, string $pageName): bool
    {
        $heading = self::normalizeTitle($headingHtml);
        $name = self::normalizeTitle($pageName);

        if ($heading === '' || $name === '') {
            return false;
        }

        if ($heading === $name) {
            return true;
        }

        $withoutNda = trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/\bnda\b/', ' ', $heading)));

        return $withoutNda === $name;
    }

    private static function normalizeTitle(string $value): string
    {
        $text = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = mb_strtolower($text);
        $text = (string) preg_replace('/[^a-z0-9]+/u', ' ', $text);

        return trim($text);
    }
}
