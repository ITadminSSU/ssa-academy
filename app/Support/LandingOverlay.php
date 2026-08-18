<?php

namespace App\Support;

class LandingOverlay
{
    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'overlay_enabled' => false,
            'overlay_headline' => 'WE HEARD YOU!',
            'overlay_pains_title' => 'USER PAINS:',
            'overlay_pains' => [
                'Add a pain point visitors will recognize.',
            ],
            'overlay_solution_title' => 'OUR SOLUTION:',
            'overlay_highlight' => 'INTRODUCING SMARTSOURCING USA ACADEMY',
            'overlay_solution_html' => '<p>Describe what you are offering and why it is different. Visitors can close this overlay and continue to the site.</p>',
            'overlay_cta_label' => 'APPLY TO JOIN',
            'overlay_cta_url' => '/register',
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

        $pains = $fields['overlay_pains'] ?? $defaults['overlay_pains'];
        if (! is_array($pains)) {
            $pains = [];
        }

        $overlay = [
            'overlay_enabled' => filter_var($fields['overlay_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'overlay_headline' => (string) ($fields['overlay_headline'] ?? $defaults['overlay_headline']),
            'overlay_pains_title' => (string) ($fields['overlay_pains_title'] ?? $defaults['overlay_pains_title']),
            'overlay_pains' => array_values(array_filter(
                array_map(static fn ($pain) => trim((string) $pain), $pains),
                static fn (string $pain) => $pain !== ''
            )),
            'overlay_solution_title' => (string) ($fields['overlay_solution_title'] ?? $defaults['overlay_solution_title']),
            'overlay_highlight' => (string) ($fields['overlay_highlight'] ?? $defaults['overlay_highlight']),
            'overlay_solution_html' => self::sanitizeHtml((string) ($fields['overlay_solution_html'] ?? $defaults['overlay_solution_html'])),
            'overlay_cta_label' => (string) ($fields['overlay_cta_label'] ?? $defaults['overlay_cta_label']),
            'overlay_cta_url' => (string) ($fields['overlay_cta_url'] ?? $defaults['overlay_cta_url']),
        ];

        $overlay['overlay_version'] = self::version($overlay);

        return $overlay;
    }

    /**
     * @param  array<string, mixed>  $overlay
     */
    public static function version(array $overlay): string
    {
        $content = [
            'headline' => $overlay['overlay_headline'] ?? '',
            'pains_title' => $overlay['overlay_pains_title'] ?? '',
            'pains' => $overlay['overlay_pains'] ?? [],
            'solution_title' => $overlay['overlay_solution_title'] ?? '',
            'highlight' => $overlay['overlay_highlight'] ?? '',
            'solution_html' => $overlay['overlay_solution_html'] ?? '',
            'cta_label' => $overlay['overlay_cta_label'] ?? '',
            'cta_url' => $overlay['overlay_cta_url'] ?? '',
        ];

        return hash('sha256', json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param  array<string, mixed>|null  $fields
     * @return array<string, mixed>|null
     */
    public static function publicPayload(?array $fields, bool $force = false): ?array
    {
        $overlay = self::fromFields($fields);

        if (! $force && ! $overlay['overlay_enabled']) {
            return null;
        }

        $hasContent = trim($overlay['overlay_headline']) !== ''
            || $overlay['overlay_pains'] !== []
            || trim($overlay['overlay_highlight']) !== ''
            || trim(strip_tags($overlay['overlay_solution_html'])) !== ''
            || trim($overlay['overlay_cta_label']) !== '';

        if (! $hasContent) {
            return null;
        }

        return [
            'version' => $overlay['overlay_version'],
            'headline' => $overlay['overlay_headline'],
            'pains_title' => $overlay['overlay_pains_title'],
            'pains' => $overlay['overlay_pains'],
            'solution_title' => $overlay['overlay_solution_title'],
            'highlight' => $overlay['overlay_highlight'],
            'solution_html' => $overlay['overlay_solution_html'],
            'cta_label' => $overlay['overlay_cta_label'],
            'cta_url' => $overlay['overlay_cta_url'],
        ];
    }

    public static function sanitizeHtml(string $html): string
    {
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html) ?? '';
        $html = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $html) ?? '';
        $html = strip_tags($html, '<p><br><strong><b><em><i><u><a><ul><ol><li><h2><h3><h4><span><div>');

        return $html;
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
}
