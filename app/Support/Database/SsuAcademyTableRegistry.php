<?php

namespace App\Support\Database;

/**
 * Canonical list of application tables managed by SSU Academy.
 * Logical names are used in migrations/models; the connection prefix produces physical names.
 */
class SsuAcademyTableRegistry
{
    public const LEGACY_PREFIX = 'mentor_lms_';

    public const TARGET_PREFIX = 'ssu_academy_';

    /**
     * @return list<string>
     */
    public static function tables(): array
    {
        return [
            'assignment_sample_downloads',
            'assignment_submissions',
            'backups',
            'blog_categories',
            'blog_comments',
            'blog_like_dislikes',
            'blogs',
            'cache',
            'cache_locks',
            'certificate_templates',
            'chunked_upload_parts',
            'chunked_uploads',
            'course_assignments',
            'course_carts',
            'course_categories',
            'course_category_children',
            'course_certificates',
            'course_coupons',
            'course_enrollments',
            'course_faqs',
            'course_forum_replies',
            'course_forums',
            'course_live_classes',
            'course_outcomes',
            'course_progress',
            'course_requirements',
            'course_reviews',
            'course_sections',
            'course_wishlists',
            'courses',
            'exam_attempt_answers',
            'exam_attempts',
            'exam_coupons',
            'exam_enrollments',
            'exam_faqs',
            'exam_outcomes',
            'exam_question_options',
            'exam_questions',
            'exam_requirements',
            'exam_resources',
            'exam_reviews',
            'exam_wishlists',
            'exams',
            'failed_jobs',
            'footer_items',
            'footers',
            'instructors',
            'job_batches',
            'job_circulars',
            'jobs',
            'language_properties',
            'languages',
            'lesson_resources',
            'marksheet_templates',
            'media',
            'migrations',
            'navbar_items',
            'navbars',
            'newsletters',
            'notifications',
            'page_sections',
            'pages',
            'password_reset_tokens',
            'payment_gateway_refund_attempts',
            'payment_histories',
            'payment_refund_audit_logs',
            'payment_settings',
            'payout_histories',
            'personal_access_tokens',
            'professional_types',
            'question_answers',
            'quiz_questions',
            'quiz_submissions',
            'section_lessons',
            'section_quizzes',
            'sessions',
            'settings',
            'subscribes',
            'temp_stores',
            'users',
            'watch_histories',
        ];
    }

    public static function toLogicalName(string $physicalTableName, ?string $prefix = null): string
    {
        $prefix ??= config('database.connections.' . config('database.default') . '.prefix', '');

        foreach ([self::TARGET_PREFIX, self::LEGACY_PREFIX, $prefix] as $candidate) {
            if ($candidate !== '' && str_starts_with($physicalTableName, $candidate)) {
                return substr($physicalTableName, strlen($candidate));
            }
        }

        return $physicalTableName;
    }

    public static function toPhysicalName(string $logicalTableName, ?string $prefix = null): string
    {
        $prefix ??= config('database.connections.' . config('database.default') . '.prefix', '');

        if ($prefix === '') {
            return $logicalTableName;
        }

        return $prefix . $logicalTableName;
    }

    /** Logical table name with the active connection prefix applied (for raw SQL). */
    public static function table(string $logicalTableName): string
    {
        return self::toPhysicalName($logicalTableName);
    }
}
