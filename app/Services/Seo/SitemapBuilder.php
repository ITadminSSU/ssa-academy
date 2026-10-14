<?php

namespace App\Services\Seo;

use App\Enums\CourseAudience;
use App\Models\Course\Course;
use App\Models\Instructor;
use App\Models\Page;
use Illuminate\Support\Carbon;
use Modules\Blog\Models\Blog;
use Modules\Blog\Models\BlogCategory;
use Modules\Exam\Models\Exam;

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
            $urls[$this->absolute($path)] = ['loc' => $this->absolute($path), 'lastmod' => null];
        }

        foreach ($this->courses() as $course) {
            $loc = $this->absolute('courses/details/'.$course->slug.'/'.$course->id);
            $urls[$loc] = ['loc' => $loc, 'lastmod' => $this->lastmod($course->updated_at)];
        }

        foreach ($this->blogs() as $blog) {
            $loc = $this->absolute('read/blogs/'.$blog->id);
            $urls[$loc] = ['loc' => $loc, 'lastmod' => $this->lastmod($blog->updated_at)];
        }

        foreach ($this->blogCategories() as $category) {
            $loc = $this->absolute('blogs/'.$category->slug);
            $urls[$loc] = ['loc' => $loc, 'lastmod' => $this->lastmod($category->updated_at)];
        }

        foreach ($this->exams() as $exam) {
            $loc = $this->absolute('exams/details/'.$exam->slug.'/'.$exam->id);
            $urls[$loc] = ['loc' => $loc, 'lastmod' => $this->lastmod($exam->updated_at)];
        }

        foreach ($this->instructors() as $instructor) {
            $loc = $this->absolute('instructors/'.$instructor->id);
            $urls[$loc] = ['loc' => $loc, 'lastmod' => $this->lastmod($instructor->updated_at)];
        }

        foreach ($this->pages() as $page) {
            $loc = $this->absolute($page->slug);
            $urls[$loc] = ['loc' => $loc, 'lastmod' => $this->lastmod($page->updated_at)];
        }

        return array_values($urls);
    }

    public function toXml(): string
    {
        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];

        foreach ($this->urls() as $url) {
            $lines[] = '  <url>';
            $lines[] = '    <loc>'.htmlspecialchars($url['loc'], ENT_XML1).'</loc>';
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

    private function courses()
    {
        return Course::query()
            ->listedInCatalog()
            ->whereIn('audience', [
                CourseAudience::PUBLIC->value,
                CourseAudience::BOTH->value,
            ])
            ->get(['id', 'slug', 'updated_at']);
    }

    private function blogs()
    {
        return Blog::query()
            ->published()
            ->get(['id', 'updated_at']);
    }

    private function blogCategories()
    {
        return BlogCategory::query()
            ->where('status', 'active')
            ->where('slug', '!=', 'all')
            ->get(['slug', 'updated_at']);
    }

    private function exams()
    {
        return Exam::query()
            ->where('status', 'published')
            ->get(['id', 'slug', 'updated_at']);
    }

    private function instructors()
    {
        return Instructor::query()
            ->where('status', 'approved')
            ->get(['id', 'updated_at']);
    }

    private function pages()
    {
        return Page::query()
            ->where('type', 'inner_page')
            ->where('active', true)
            ->whereNotIn('slug', self::SKIP_PAGE_SLUGS)
            ->get(['slug', 'updated_at']);
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

        return Carbon::parse($value)->toDateString();
    }
}
