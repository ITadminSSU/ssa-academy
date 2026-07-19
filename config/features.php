<?php

/**
 * SSU Academy feature flags — disable unused Mentor LMS modules and pages.
 * Blog is also controlled via modules_statuses.json (nwidart module activator).
 */
return [
    'blog' => env('FEATURE_BLOG', false),
    'job_circulars' => env('FEATURE_JOB_CIRCULARS', false),
    'careers_page' => env('FEATURE_CAREERS_PAGE', false),
    'newsletters' => env('FEATURE_NEWSLETTERS', false),
    'exams_public_nav' => env('FEATURE_EXAMS_PUBLIC_NAV', false),
];
