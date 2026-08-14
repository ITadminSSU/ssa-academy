<?php

return [
    /*
    |--------------------------------------------------------------------------
    | External learner checkout
    |--------------------------------------------------------------------------
    |
    | External learners checking out paid courses see only the primary gateway
    | (Stripe sandbox first). Employees use free enrollment instead of checkout.
    |
    */
    'external_primary_gateway' => env('PAYMENT_EXTERNAL_PRIMARY_GATEWAY', 'stripe'),

    'stripe' => [
        'test_public_key' => env('STRIPE_KEY', env('STRIPE_TEST_PUBLIC_KEY')),
        'test_secret_key' => env('STRIPE_SECRET', env('STRIPE_TEST_SECRET_KEY')),
        'force_test_mode' => filter_var(env('STRIPE_TEST_MODE', true), FILTER_VALIDATE_BOOLEAN),
        'sync_from_env' => filter_var(env('STRIPE_SYNC_FROM_ENV', true), FILTER_VALIDATE_BOOLEAN),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'subscription' => [
        'grace_days' => (int) env('SUBSCRIPTION_GRACE_DAYS', 3),
        'portal_return_url' => env('SUBSCRIPTION_PORTAL_RETURN_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Course selling tax at checkout
    |--------------------------------------------------------------------------
    |
    | When false, one-time checkout charges the exact course/coupon price and
    | hides the Tax line. Set true (or PAYMENT_APPLY_SELLING_TAX=true) later to
    | apply Website → Course Selling Tax (%) again.
    |
    */
    'apply_selling_tax' => filter_var(env('PAYMENT_APPLY_SELLING_TAX', false), FILTER_VALIDATE_BOOLEAN),
];

