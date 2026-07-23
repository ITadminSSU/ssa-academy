<?php

return [
    'name' => env('BRAND_NAME', 'Smart Sourcing Academy'),
    'short_name' => env('BRAND_SHORT_NAME', 'SSU Academy'),
    'author' => env('BRAND_AUTHOR', 'Smart Sourcing USA'),
    'tagline' => env('BRAND_TAGLINE', 'Enterprise training for teams and professionals within the construction industry.'),
    'keywords' => env('BRAND_KEYWORDS', 'SSU Academy, Smart Sourcing USA, corporate training, professional development, online courses'),
    'description' => env(
        'BRAND_DESCRIPTION',
        'Enterprise training for teams and professionals within the construction industry.'
    ),
    'support_email' => env('BRAND_SUPPORT_EMAIL', 'support@smartsourcingusa.com'),
    'contact_email' => env('BRAND_CONTACT_EMAIL', 'info@smartsourcingusa.com'),

    'logos' => [
        'icon' => '/assets/branding/favicon-ssa.png',
        'dark' => '/assets/branding/ssa-academy-logo.png',
        'light' => '/assets/branding/ssa-academy-logo.png',
        'favicon' => '/favicon.png',
    ],

    'colors' => [
        'red' => '#E94448',
        'blue' => '#2D537C',
        'navy' => '#1A1B1B',
    ],

    'fonts' => [
        'display' => 'Plus Jakarta Sans',
        'body' => 'Source Sans 3',
    ],

    'legacy_names' => [
        'LMS Academy',
        'Mentor LMS',
        'Mentor',
        'UI Lib',
        'UiLib',
    ],
];
