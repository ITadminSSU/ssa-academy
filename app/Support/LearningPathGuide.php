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
     *     us_experience: array{label: string, url: string, note: string}
     * }
     */
    public function payload(?User $user = null): array
    {
        $softwareSlug = (string) config('learning_path.software_category_slug', 'software-training');
        $estimatingSlug = (string) config('learning_path.estimating_category_slug', 'estimating-course');

        $softwareBrowse = $this->browseUrl($softwareSlug);
        $estimatingBrowse = $this->browseUrl($estimatingSlug);

        $courses = Course::query()
            ->listedInCatalog()
            ->visibleInCatalog($user)
            ->with('course_category:id,title,slug')
            ->get(['id', 'title', 'slug', 'course_category_id']);

        $fundamentals = $this->firstMatchingCourse($courses, ['planswift', 'fundamental'])
            ?? $this->firstMatchingCourse($courses, ['plan swift', 'fundamental']);
        $advanced = $this->firstMatchingCourse($courses, ['planswift', 'advanced'])
            ?? $this->firstMatchingCourse($courses, ['plan swift', 'advanced']);

        $estimatingCourse = $courses->first(function (Course $course) {
            return CourseWelcomeEmailCopy::showsUsExperience($course);
        });

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
                'label' => 'Build Your US Experience',
                'url' => $estimatingCourse ? $this->courseUrl($estimatingCourse) : $estimatingBrowse,
                'note' => 'This is a tab on Estimating courses. It unlocks after you finish the lessons and quizzes.',
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

    private function browseUrl(string $categorySlug): string
    {
        $exists = CourseCategory::query()->where('slug', $categorySlug)->exists();

        return route('student.category.courses', [
            'category' => $exists ? $categorySlug : 'all',
        ]);
    }
}
