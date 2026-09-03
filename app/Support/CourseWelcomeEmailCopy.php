<?php

namespace App\Support;

use App\Models\Course\Course;

class CourseWelcomeEmailCopy
{
    public const VARIANT_ESTIMATING = 'estimating';

    public const VARIANT_SOFTWARE = 'software';

    public const VARIANT_PROFESSIONAL = 'professional';

    /**
     * @return list<string>
     */
    public static function bodyParagraphs(string $variant): array
    {
        return match ($variant) {
            self::VARIANT_SOFTWARE => [
                'Throughout this course, you’ll develop the knowledge and practical skills needed to use the software with greater confidence and efficiency. Once you complete all the modules, you can practice your new skills using sample project plans.',
                'After completing this course, we recommend taking an estimating course to help you identify a specialty where you can apply your software skills. Once you complete an estimating course, you can continue building your skills through our Project Plans Subscription, which provides access to construction plans for takeoff. This gives you the opportunity to apply what you’ve learned to real-world estimating scenarios, gain hands-on experience, and build a portfolio based on U.S. construction projects.',
            ],
            self::VARIANT_PROFESSIONAL => [
                'Throughout this course, you’ll gain practical knowledge, industry-relevant skills, and proven strategies to approach your career journey with greater confidence and clarity. Each module is designed to strengthen how you present your experience, communicate your value, navigate the hiring process, and prepare for professional opportunities.',
                'While completing the program does not guarantee employment or engagement, it will help you become better prepared, more confident, and more competitive as you pursue opportunities aligned with your skills and career goals.',
            ],
            default => [
                'Throughout this course, you’ll develop the knowledge and practical skills needed to approach construction estimating with greater confidence and accuracy. Once you complete all the modules, you can continue building your skills through the Project Plans Subscription, which provides access to construction plans for takeoff. You can use these plans to apply what you’ve learned to real-world estimating scenarios, gain hands-on experience, and build a portfolio based on U.S. construction projects.',
            ],
        };
    }

    public static function resolveVariant(?Course $course): string
    {
        $slug = strtolower((string) ($course?->course_category?->slug ?? ''));
        $title = strtolower((string) ($course?->course_category?->title ?? ''));
        $haystack = $slug.' '.$title;

        if (str_contains($haystack, 'software')) {
            return self::VARIANT_SOFTWARE;
        }

        if (
            str_contains($haystack, 'professional')
            || str_contains($haystack, 'career')
            || (str_contains($haystack, 'development') && ! str_contains($haystack, 'estimating'))
        ) {
            return self::VARIANT_PROFESSIONAL;
        }

        if (str_contains($haystack, 'estimating')) {
            return self::VARIANT_ESTIMATING;
        }

        return self::VARIANT_PROFESSIONAL;
    }

    public static function showsUsExperience(?Course $course): bool
    {
        $course?->loadMissing('course_category');

        return self::resolveVariant($course) === self::VARIANT_ESTIMATING;
    }
}
