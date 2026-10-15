<?php

namespace App\Services\Seo;

use App\Enums\CourseAudience;
use App\Enums\CourseStatusType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SitemapBuilder
{
    /**
     * Inner-page slugs that already have a dedicated public route or only redirect.
     *
     * @var list<string>
     */
    private const SKIP_PAGE_SLUGS = [
        'our-team',
        'contact-us',
        'about-us',
        'faqs',
        'connect-with-us',
    ];

    /**
     * @return list<array{loc: string, lastmod: ?string}>
     */
    public function urls(): array
    {
        $urls = [];

        foreach ($this->staticPaths() as $path) {
            $this->remember($urls, $path, null, allowBlank: true);
        }

        $this->addSection($urls, 'courses', 'courses', ['id', 'slug', 'updated_at', 'status', 'audience'], function () {
            return DB::table('courses')
                ->whereIn('status', [
                    CourseStatusType::APPROVED->value,
                    CourseStatusType::UPCOMING->value,
                    CourseStatusType::COMING_SOON->value,
                ])
                ->whereIn('audience', [
                    CourseAudience::PUBLIC->value,
                    CourseAudience::BOTH->value,
                ])
                ->get(['id', 'slug', 'updated_at'])
                ->map(fn ($row) => $this->entry(
                    filled($row->slug) && filled($row->id) ? 'courses/details/'.$row->slug.'/'.$row->id : null,
                    $row->updated_at ?? null,
                ))
                ->all();
        });

        $this->addSection($urls, 'blogs', 'blogs', ['id', 'updated_at', 'status'], function () {
            return DB::table('blogs')
                ->where('status', 'published')
                ->get(['id', 'updated_at'])
                ->map(fn ($row) => $this->entry(
                    filled($row->id) ? 'read/blogs/'.$row->id : null,
                    $row->updated_at ?? null,
                ))
                ->all();
        });

        $this->addSection($urls, 'blog categories', 'blog_categories', ['slug', 'updated_at', 'status'], function () {
            return DB::table('blog_categories')
                ->where('status', 'active')
                ->where('slug', '!=', 'all')
                ->get(['slug', 'updated_at'])
                ->map(fn ($row) => $this->entry(
                    filled($row->slug) ? 'blogs/'.$row->slug : null,
                    $row->updated_at ?? null,
                ))
                ->all();
        });

        $this->addSection($urls, 'exams', 'exams', ['id', 'slug', 'updated_at', 'status'], function () {
            return DB::table('exams')
                ->where('status', 'published')
                ->get(['id', 'slug', 'updated_at'])
                ->map(fn ($row) => $this->entry(
                    filled($row->slug) && filled($row->id) ? 'exams/details/'.$row->slug.'/'.$row->id : null,
                    $row->updated_at ?? null,
                ))
                ->all();
        });

        $this->addSection($urls, 'instructors', 'instructors', ['id', 'updated_at', 'status'], function () {
            return DB::table('instructors')
                ->where('status', 'approved')
                ->get(['id', 'updated_at'])
                ->map(fn ($row) => $this->entry(
                    filled($row->id) ? 'instructors/'.$row->id : null,
                    $row->updated_at ?? null,
                ))
                ->all();
        });

        $this->addSection($urls, 'pages', 'pages', ['slug', 'updated_at', 'type', 'active'], function () {
            return DB::table('pages')
                ->where('type', 'inner_page')
                ->where('active', true)
                ->whereNotIn('slug', self::SKIP_PAGE_SLUGS)
                ->get(['slug', 'updated_at'])
                ->map(fn ($row) => $this->entry(
                    filled($row->slug) ? (string) $row->slug : null,
                    $row->updated_at ?? null,
                ))
                ->all();
        });

        return array_values($urls);
    }

    public function toXml(): string
    {
        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];

        foreach ($this->urls() as $url) {
            $loc = htmlspecialchars($url['loc'], ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');

            if ($loc === '') {
                continue;
            }

            $lines[] = '  <url>';
            $lines[] = '    <loc>'.$loc.'</loc>';
            if ($url['lastmod']) {
                $lines[] = '    <lastmod>'.$url['lastmod'].'</lastmod>';
            }
            $lines[] = '  </url>';
        }

        $lines[] = '</urlset>';

        return implode("\n", $lines)."\n";
    }

    /**
     * @return list<string>
     */
    private function staticPaths(): array
    {
        return [
            '/',
            'about-us',
            'faqs',
            'connect-with-us',
            'courses/all',
            'blogs/all',
        ];
    }

    /**
     * @param  array<string, array{loc: string, lastmod: ?string}>  $urls
     * @param  list<string>  $columns
     * @param  callable(): list<array{path: ?string, updated_at: mixed}>  $rows
     */
    private function addSection(array &$urls, string $section, string $table, array $columns, callable $rows): void
    {
        try {
            if (! $this->tableReady($table, $columns)) {
                $this->reportSkipped($section, 'The '.$table.' table or one of its sitemap columns is missing.');

                return;
            }

            foreach ($rows() as $row) {
                $this->remember($urls, $row['path'] ?? null, $row['updated_at'] ?? null);
            }
        } catch (\Throwable $exception) {
            $this->reportSkipped($section, $exception->getMessage());
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function tableReady(string $table, array $columns): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, array{loc: string, lastmod: ?string}>  $urls
     */
    private function remember(array &$urls, ?string $path, mixed $updatedAt, bool $allowBlank = false): void
    {
        $path = trim((string) $path);

        if ($path === '' && ! $allowBlank) {
            return;
        }

        $loc = $this->absolute($allowBlank && $path === '' ? '/' : $path);

        if ($loc === '') {
            return;
        }

        $urls[$loc] = [
            'loc' => $loc,
            'lastmod' => $this->lastmod($updatedAt),
        ];
    }

    /**
     * @return array{path: ?string, updated_at: mixed}
     */
    private function entry(?string $path, mixed $updatedAt): array
    {
        return [
            'path' => $path,
            'updated_at' => $updatedAt,
        ];
    }

    private function absolute(string $path): string
    {
        $base = rtrim((string) config('app.url'), '/');
        $path = trim($path, '/');

        return $path === '' ? $base.'/' : $base.'/'.$path;
    }

    private function lastmod(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function reportSkipped(string $section, string $reason): void
    {
        try {
            Log::warning('Sitemap section skipped', [
                'section' => $section,
                'reason' => $reason,
            ]);
        } catch (\Throwable) {
            // A logging failure must not turn the sitemap into an error page.
        }
    }
}
