<?php

namespace App\Support;

use App\Models\Course\Course;
use App\Models\Course\CourseCategory;
use App\Models\User;
use Illuminate\Support\Collection;

class LearningPathGuide
{
    /**
     * Catalog links for the Home "Don't know where to start?" popup.
     *
     * @return array{
     *     fundamentals: array{label: string, url: string},
     *     advanced: array{label: string, url: string},
     *     estimating: array{label: string, url: string},
     *     us_experience: array{label: string, url: string, note: string, clickable: bool}
     * }
     */
    public function payload(?User $user = null): array
    {
        $softwareSlug = (string) config('learning_path.software_category_slug', 'software-training');
        $estimatingSlug = (string) config('learning_path.estimating_category_slug', 'estimating');

        $softwareBrowse = $this->categoryBrowseUrl([$softwareSlug, 'software-training']);
        $estimatingBrowse = $this->categoryBrowseUrl([
            $estimatingSlug,
            'estimating',
            'estimating-course',
        ]);

        $courses = Course::query()
            ->listedInCatalog()
            ->visibleInCatalog($user)
            ->with('course_category:id,title,slug')
            ->get(['id', 'title', 'slug', 'course_category_id']);

        $fundamentals = $this->firstMatchingCourse($courses, ['planswift', 'fundamental'])
            ?? $this->firstMatchingCourse($courses, ['plan swift', 'fundamental']);
        $advanced = $this->firstMatchingCourse($courses, ['planswift', 'advanced'])
            ?? $this->firstMatchingCourse($courses, ['plan swift', 'advanced']);

        return [
            'fundamentals' => [
                'label' => $fundamentals?->title ?: 'PlanSwift Fundamentals',
                'url' => $fundamentals ? $this->courseUrl($fundamentals) : $softwareBrowse,
            ],
            'advanced' => [
                'label' => $advanced?->title ?: 'Advanced PlanSwift',
                'url' => $advanced ? $this->courseUrl($advanced) : $softwareBrowse,
            ],
            'estimating' => [
                'label' => 'Choice of Estimating Course',
                'url' => $estimatingBrowse,
            ],
            'us_experience' => [
                'label' => 'Build Your U.S. Experience',
                'url' => '',
                'clickable' => false,
                'note' => 'This is a tab on Estimating courses. Enroll in a course first. It unlocks after you finish the lessons and quizzes.',
            ],
        ];
    }

    /**
     * @param  Collection<int, Course>  $courses
     * @param  list<string>  $needles
     */
    public function firstMatchingCourse(Collection $courses, array $needles): ?Course
    {
        return $courses->first(fn (Course $course) => $this->titleContainsAll($course->title, $needles));
    }

    /**
     * @param  list<string>  $needles
     */
    public function titleContainsAll(string $title, array $needles): bool
    {
        $haystack = strtolower($title);

        foreach ($needles as $needle) {
            if (! str_contains($haystack, strtolower($needle))) {
                return false;
            }
        }

        return true;
    }

    private function courseUrl(Course $course): string
    {
        return route('course.details', ['slug' => $course->slug, 'id' => $course->id]);
    }

    /**
     * @param  list<string>  $candidateSlugs
     */
    private function categoryBrowseUrl(array $candidateSlugs): string
    {
        return route('student.category.courses', [
            'category' => $this->resolveCategorySlug($candidateSlugs) ?? 'all',
        ]);
    }

    /**
     * Prefer the first matching catalog slug so /dashboard/browse/estimating wins over estimating-course.
     *
     * @param  list<string>  $candidateSlugs
     */
    public function resolveCategorySlug(array $candidateSlugs, mixed $categories = null): ?string
    {
        $candidateSlugs = array_values(array_unique(array_filter($candidateSlugs)));

        if ($candidateSlugs === []) {
            return null;
        }

        $categories = $categories instanceof Collection
            ? $categories
            : CourseCategory::query()->whereIn('slug', $candidateSlugs)->get(['id', 'slug', 'title']);

        foreach ($candidateSlugs as $slug) {
            $match = $categories->first(fn ($category) => ($category->slug ?? null) === $slug);

            if ($match) {
                return $match->slug;
            }
        }

        $estimating = $categories->first(function ($category) {
            $slug = strtolower((string) ($category->slug ?? ''));
            $title = strtolower((string) ($category->title ?? ''));

            if (str_contains($slug, 'software') || str_contains($title, 'software')) {
                return false;
            }

            return str_contains($slug, 'estimating') || str_contains($title, 'estimating');
        });

        return $estimating?->slug;
    }
}
