<?php

namespace App\Support;

use App\Models\Course\Course;
use App\Models\Course\CourseLandingPage;
use App\Services\Course\CourseService;
use App\Services\Payment\SubscriptionAccessService;
use App\Support\Database\SsuAcademyTableRegistry;
use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class DrywallLanding
{
    public const COURSE_SLUG = 'drywall-estimating';

    public const FALLBACK_COUNTDOWN = '2026-10-31 23:59:00';

    public const DEFAULT_EMBED = <<<'HTML'
<iframe src="https://api.leadconnectorhq.com/widget/form/fyFpuL3CCfqs4YWlCe1V" style="width:100%;height:100%;border:none;border-radius:8px" id="inline-fyFpuL3CCfqs4YWlCe1V" data-layout="{'id':'INLINE'}" data-trigger-type="alwaysShow" data-trigger-value="" data-activation-type="alwaysActivated" data-activation-value="" data-deactivation-type="neverDeactivate" data-deactivation-value="" data-form-name="Drywall Estimating" data-height="569" data-layout-iframe-id="inline-fyFpuL3CCfqs4YWlCe1V" data-form-id="fyFpuL3CCfqs4YWlCe1V" data-cookie-consent="true" data-cookie-consent-provider="auto" title="Drywall Estimating"></iframe>
<script src="https://link.msgsndr.com/js/form_embed.js"></script>
HTML;

    /**
     * @return list<string>
     */
    public static function overview(): array
    {
        return [
            'This course provides a practical introduction to drywall estimating, starting with the fundamentals and building toward real-world takeoff and estimating workflows.',
            'You\'ll learn the basics of drywall and metal framing, including materials, types, components, stud sizes, gauges, spacing, ceiling framing, openings, and common framing conditions.',
            'The course also covers reading construction drawings, identifying wall and ceiling conditions, measuring drywall areas, calculating quantities, handling openings and deductions, and understanding specialty drywall applications.',
            'You\'ll also learn important estimating practices such as assumptions, clarifications, exclusions, quality control, and final takeoff deliverables to help you develop a complete and organized estimating process.',
            'By the end of the course, you\'ll have a structured understanding of how drywall estimators review project documents, identify scope, perform takeoffs, calculate material quantities, and prepare estimating deliverables.',
        ];
    }

    /**
     * @return list<array{type: string, quote: string, name: string, image: null}>
     */
    public static function defaultReviews(): array
    {
        return [
            [
                'type' => 'written',
                'quote' => 'The lessons walk through a real takeoff. I knew what to do on the first practice set.',
                'name' => 'Student review',
                'image' => null,
            ],
            [
                'type' => 'written',
                'quote' => 'Clear on framing, openings, and how estimators write assumptions.',
                'name' => 'Student review',
                'image' => null,
            ],
            [
                'type' => 'written',
                'quote' => 'Short lessons I could finish after work, with files I can reuse.',
                'name' => 'Student review',
                'image' => null,
            ],
        ];
    }

    /**
     * Accept only an http(s) URL or a site-relative path, and persist the stable object address.
     * A signed private-file link is stored without its signature so the next save still finds the file.
     */
    public static function storedReviewImage(string $value): ?string
    {
        $url = trim($value);

        if ($url === '' || strlen($url) > 8192) {
            return null;
        }

        $allowed = preg_match('#^https?://#i', $url) === 1
            || preg_match('#^/(?!/)#', $url) === 1;

        if (! $allowed) {
            return null;
        }

        $stable = trim((string) (S3CompatibleStorage::attributeSet($url) ?? $url));

        if ($stable === '' || strlen($stable) > 2048) {
            return null;
        }

        if (preg_match('#^https?://#i', $stable) === 1 || preg_match('#^/(?!/)#', $stable) === 1) {
            return $stable;
        }

        return null;
    }

    /**
     * Older saved reviews have quote and name only. Those stay written cards.
     *
     * @param  array<int, mixed>  $reviews
     * @return list<array{type: string, quote: string, name: string, image: string|null}>
     */
    public static function normalizeReviews(array $reviews): array
    {
        $normalized = [];

        foreach ($reviews as $review) {
            if (! is_array($review)) {
                continue;
            }

            $type = ($review['type'] ?? '') === 'image' ? 'image' : 'written';
            $name = trim((string) ($review['name'] ?? ''));
            $quote = trim((string) ($review['quote'] ?? ''));
            $image = is_string($review['image'] ?? null)
                ? self::storedReviewImage($review['image'])
                : null;

            if ($type === 'image') {
                if ($image === null) {
                    continue;
                }

                $normalized[] = [
                    'type' => 'image',
                    'quote' => '',
                    'name' => $name,
                    'image' => $image,
                ];

                continue;
            }

            if ($quote === '' && $name === '') {
                continue;
            }

            $normalized[] = [
                'type' => 'written',
                'quote' => $quote,
                'name' => $name,
                'image' => null,
            ];
        }

        return $normalized;
    }

    public static function isDrywallCourse(?Course $course): bool
    {
        return $course !== null && $course->slug === self::COURSE_SLUG;
    }

    /**
     * Keep a GoHighLevel form iframe and replace any script with the official embed script.
     */
    public static function sanitizeEmbed(string $html): ?string
    {
        $html = trim($html);

        if ($html === '') {
            return null;
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<?xml encoding="utf-8" ?><div>'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return null;
        }

        $iframes = $document->getElementsByTagName('iframe');

        if ($iframes->length !== 1) {
            return null;
        }

        $iframe = $iframes->item(0);

        if (! $iframe instanceof DOMElement) {
            return null;
        }

        $src = trim($iframe->getAttribute('src'));

        if (! preg_match('#^https://api\.leadconnectorhq\.com/widget/form/[A-Za-z0-9]+$#', $src)) {
            return null;
        }

        $attributes = [];

        foreach ($iframe->attributes ?? [] as $attribute) {
            $name = strtolower($attribute->name);

            if (str_starts_with($name, 'on')) {
                return null;
            }

            $allowed = in_array($name, ['src', 'id', 'style', 'title'], true) || str_starts_with($name, 'data-');

            if (! $allowed) {
                continue;
            }

            if (preg_match('/javascript\s*:/i', $attribute->value)) {
                return null;
            }

            $attributes[$name] = $attribute->value;
        }

        $attributes['src'] = $src;

        $serialized = '';

        foreach ($attributes as $name => $value) {
            $serialized .= ' '.$name.'="'.htmlspecialchars($value, ENT_COMPAT, 'UTF-8').'"';
        }

        return '<iframe'.$serialized.'></iframe>'."\n".'<script src="https://link.msgsndr.com/js/form_embed.js"></script>';
    }

    /**
     * @return array<string, mixed>
     */
    public function publicPage(): array
    {
        $course = $this->course();
        $landing = $this->recordFor($course);
        $canViewCurriculum = $this->viewerCanSeeCurriculum($course);

        return [
            'landing' => $this->present($landing),
            'course' => $course && $course->isPubliclyViewable() ? $this->courseTabs($course, $canViewCurriculum) : null,
            'canViewCurriculum' => $canViewCurriculum,
            'enrollUrl' => $this->enrollUrl($course),
        ];
    }

    /**
     * Same rule as the course details page: guests and people without access
     * do not receive the curriculum.
     */
    private function viewerCanSeeCurriculum(?Course $course): bool
    {
        $user = Auth::user();

        if (! $course || ! $user || ! $course->isPubliclyViewable($user)) {
            return false;
        }

        $enrollment = app(CourseService::class)->getCourseEnroll((string) $course->id);

        return app(SubscriptionAccessService::class)->canViewPublicCurriculum($user, $course, $enrollment);
    }

    /**
     * @return array<string, mixed>
     */
    public function adminPayload(Course $course): array
    {
        $presented = $this->present($this->recordFor($course));

        return [
            'countdown_ends_at' => $presented['countdown_ends_at'],
            'hero_photo' => $presented['hero_photo'],
            'video_source' => $presented['video_source'] ?? 'link',
            'video_url' => $presented['video_url'] ?? '',
            'video_stored_url' => $presented['video_stored_url'] ?? '',
            'video_duration' => $presented['video_duration'],
            'coach_line' => $presented['coach_line'],
            'embed_html' => $presented['embed_html'],
            'reviews' => $presented['reviews'],
            'thanks_url' => route('drywall-estimating.thanks'),
            'using_defaults' => $this->recordFor($course) === null,
        ];
    }

    public function course(): ?Course
    {
        if (! Schema::hasTable('courses')) {
            return null;
        }

        return Course::query()->where('slug', self::COURSE_SLUG)->first();
    }

    public function recordFor(?Course $course): ?CourseLandingPage
    {
        if (! $course || ! Schema::hasTable('course_landing_pages')) {
            return null;
        }

        return CourseLandingPage::query()->where('course_id', $course->id)->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function present(?CourseLandingPage $landing): array
    {
        $endsAt = $landing?->countdown_ends_at
            ?? Carbon::parse(self::FALLBACK_COUNTDOWN, (string) config('app.timezone'));

        $reviews = $landing ? array_values($landing->reviews ?? []) : self::defaultReviews();
        $video = self::presentVideo($landing);

        return [
            'countdown_ends_at' => $endsAt->toIso8601String(),
            'hero_photo' => self::browserPhoto($landing?->hero_photo) ?: asset('assets/images/drywall-estimating-hero.jpg'),
            'video_source' => $video['source'],
            'video_url' => $video['url'],
            'video_stored_url' => $video['stored_url'],
            'video_duration' => $landing?->video_duration ?: '2:36',
            'coach_line' => $landing?->coach_line ?: 'Your coach · Professional engineer.',
            'embed_html' => $landing?->embed_html ?: self::DEFAULT_EMBED,
            'reviews' => self::presentReviews($reviews),
            'overview' => self::overview(),
        ];
    }

    /**
     * Sign private review pictures for the browser and keep the stable address for the next save.
     *
     * @param  array<int, mixed>  $reviews
     * @return list<array{type: string, quote: string, name: string, image: string|null, stored_image: string|null}>
     */
    public static function presentReviews(array $reviews): array
    {
        return array_map(function (array $review): array {
            $stored = is_string($review['image'] ?? null) && $review['image'] !== ''
                ? $review['image']
                : null;

            $review['stored_image'] = $stored;
            $review['image'] = self::browserPhoto($stored);

            return $review;
        }, self::normalizeReviews($reviews));
    }

    /**
     * A link stays as entered. An upload is shown with a readable link and remembered as a stable address.
     *
     * @return array{source: string, url: string|null, stored_url: string|null}
     */
    public static function presentVideo(?CourseLandingPage $landing): array
    {
        $stored = is_string($landing?->video_url) && trim($landing->video_url) !== ''
            ? trim($landing->video_url)
            : null;
        $source = $landing?->video_source === 'upload' ? 'upload' : 'link';

        if ($source === 'upload') {
            return [
                'source' => 'upload',
                'url' => $stored !== null ? (self::browserPhoto($stored) ?: $stored) : null,
                'stored_url' => $stored,
            ];
        }

        return [
            'source' => 'link',
            'url' => $stored,
            'stored_url' => null,
        ];
    }

    private static function browserPhoto(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        return S3CompatibleStorage::attributeGet($url) ?: $url;
    }

    private function enrollUrl(?Course $course): string
    {
        if ($course && $course->isPubliclyViewable()) {
            return route('course.details', ['slug' => $course->slug, 'id' => $course->id]);
        }

        return route('category.courses', ['category' => 'all']);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function courseTabs(Course $course, bool $includeCurriculum): ?array
    {
        if (! Schema::hasTable('course_faqs') || ! Schema::hasTable('course_sections')) {
            return null;
        }

        $with = [
            'faqs',
            'requirements',
            'outcomes',
            'instructor' => function ($query) {
                $instructorTable = SsuAcademyTableRegistry::table('instructors');
                $courses = SsuAcademyTableRegistry::table('courses');
                $query->select($query->getModel()->getTable().'.*');

                if (Schema::hasTable('course_reviews')) {
                    $courseReviews = SsuAcademyTableRegistry::table('course_reviews');
                    $query->selectRaw("(SELECT COUNT(*) FROM {$courseReviews} INNER JOIN {$courses} ON {$courseReviews}.course_id = {$courses}.id WHERE {$courses}.instructor_id = {$instructorTable}.id) as total_reviews_count")
                        ->selectRaw("(SELECT AVG({$courseReviews}.rating) FROM {$courseReviews} INNER JOIN {$courses} ON {$courseReviews}.course_id = {$courses}.id WHERE {$courses}.instructor_id = {$instructorTable}.id) as total_average_rating");
                }

                $query->with([
                    'user',
                    'courses' => fn ($coursesQuery) => $coursesQuery
                        ->select('id', 'instructor_id')
                        ->withCount('enrollments'),
                ]);
            },
        ];

        if ($includeCurriculum) {
            $with['sections'] = function ($query) {
                $query->orderBy('sort')->orderBy('id')->with([
                    'section_lessons' => fn ($lessons) => $lessons
                        ->select('id', 'title', 'sort', 'lesson_type', 'duration', 'course_section_id')
                        ->orderBy('sort'),
                    'section_quizzes' => fn ($quizzes) => $quizzes
                        ->select('id', 'title', 'sort', 'duration', 'course_section_id')
                        ->orderBy('sort'),
                ]);
            };
        }

        $course->load($with);

        return [
            'id' => $course->id,
            'slug' => $course->slug,
            'title' => $course->title,
            'faqs' => $course->faqs->map(fn ($faq) => [
                'id' => $faq->id,
                'question' => $faq->question,
                'answer' => $faq->answer,
            ])->values(),
            'requirements' => $course->requirements->map(fn ($requirement) => [
                'id' => $requirement->id,
                'requirement' => $requirement->requirement,
            ])->values(),
            'outcomes' => $course->outcomes->map(fn ($outcome) => [
                'id' => $outcome->id,
                'outcome' => $outcome->outcome,
            ])->values(),
            'sections' => $includeCurriculum ? $course->sections->map(fn ($section) => [
                'id' => $section->id,
                'title' => $section->title,
                'sort' => $section->sort,
                'section_lessons' => $section->section_lessons->map(fn ($lesson) => [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'sort' => $lesson->sort,
                    'lesson_type' => $lesson->lesson_type,
                    'duration' => $lesson->duration,
                ])->values(),
                'section_quizzes' => $section->section_quizzes->map(fn ($quiz) => [
                    'id' => $quiz->id,
                    'title' => $quiz->title,
                    'sort' => $quiz->sort,
                    'duration' => $quiz->duration,
                ])->values(),
            ])->values() : [],
            'instructor' => $this->instructorPayload($course),
            'instructor_card_name' => $course->instructor_card_name,
            'instructor_card_biography' => $course->instructor_card_biography,
            'instructor_card_photo' => $course->instructor_card_photo,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function instructorPayload(Course $course): ?array
    {
        $instructor = $course->instructor;
        $user = $instructor?->user;

        if (! $instructor || ! $user) {
            return null;
        }

        return [
            'id' => $instructor->id,
            'biography' => $instructor->biography,
            'total_average_rating' => $instructor->total_average_rating,
            'total_reviews_count' => (int) ($instructor->total_reviews_count ?? 0),
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'photo' => $user->photo ?? '',
            ],
            'courses' => $instructor->courses->map(fn ($item) => [
                'id' => $item->id,
                'enrollments_count' => $item->enrollments_count ?? 0,
            ])->values(),
        ];
    }
}
