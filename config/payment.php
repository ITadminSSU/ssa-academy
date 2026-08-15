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
        // Test keys (STRIPE_TEST_MODE=true)
        'test_public_key' => env('STRIPE_KEY', env('STRIPE_TEST_PUBLIC_KEY')),
        'test_secret_key' => env('STRIPE_SECRET', env('STRIPE_TEST_SECRET_KEY')),
        // Live keys (STRIPE_TEST_MODE=false)
        'live_public_key' => env('STRIPE_LIVE_KEY', env('STRIPE_LIVE_PUBLIC_KEY')),
        'live_secret_key' => env('STRIPE_LIVE_SECRET', env('STRIPE_LIVE_SECRET_KEY')),
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

    /*
    |--------------------------------------------------------------------------
    | Launch offer defaults (per-course fields override these when set)
    |--------------------------------------------------------------------------
    */
    'launch_offer' => [
        'window_start' => env('LAUNCH_OFFER_START', '2026-08-15'),
        'window_end' => env('LAUNCH_OFFER_END', '2026-09-14'),
        'list_price' => (float) env('LAUNCH_OFFER_LIST_PRICE', 75),
        'offer_price' => (float) env('LAUNCH_OFFER_PRICE', 70),
        'deposit_amount' => (float) env('LAUNCH_OFFER_DEPOSIT', 20),
        'balance_amount' => (float) env('LAUNCH_OFFER_BALANCE', 50),
        'balance_grace_days' => (int) env('LAUNCH_OFFER_GRACE_DAYS', 5),
        'subscription_price' => (float) env('LAUNCH_OFFER_SUBSCRIPTION', 6),
        'subscription_trial_ends_at' => env('LAUNCH_OFFER_TRIAL_ENDS', '2026-10-15'),
        'full_upfront_price' => (float) env('LAUNCH_OFFER_FULL_UPFRONT', 75),
        'deposit_non_refundable' => true,
    ],
];

