<?php

return [
    'name' => env('BRAND_NAME', 'SMARTSOURCING USA ACADEMY'),
    'short_name' => env('BRAND_SHORT_NAME', 'SSU Academy'),
    'author' => env('BRAND_AUTHOR', 'Smart Sourcing USA'),
    'tagline' => env('BRAND_TAGLINE', 'Enterprise training for teams and professionals within the construction industry.'),
    'keywords' => env('BRAND_KEYWORDS', 'SMARTSOURCING USA ACADEMY, Smart Sourcing USA, corporate training, professional development, online courses'),
    'description' => env(
        'BRAND_DESCRIPTION',
        'SMARTSOURCING USA ACADEMY is a professional training and development platform of Smart Sourcing USA and is not a CHED-accredited higher education institution.'
    ),
    'support_email' => env('BRAND_SUPPORT_EMAIL', 'support@smartsourcingusa.com'),
    'contact_email' => env('BRAND_CONTACT_EMAIL', 'info@smartsourcingusa.com'),
    'facebook_group_url' => env('BRAND_FACEBOOK_GROUP_URL', 'https://www.facebook.com/share/g/14ttXqLttek/'),

    'logos' => [
        'icon' => '/assets/branding/favicon-ssa.png',
        'dark' => '/assets/branding/ssa-academy-logo-black.png',
        'light' => '/assets/branding/ssa-academy-logo-white.png',
        'footer' => '/assets/branding/ssa-academy-logo-black.png',
        'certificate' => '/assets/branding/ssa-academy-logo.png',
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
        'Smart Sourcing Academy',
        'SMART SOURCING ACADEMY',
        'Smart Sourcing USA Academy',
        'SSU Academy',
    ],
];
