<?php

return [
    /*
    | When enabled, new students must complete the SignWell Student Agreement
    | before accessing the learner dashboard.
    */
    'enabled' => (bool) env('SIGNWELL_ENABLED', false),

    'api_key' => env('SIGNWELL_API_KEY'),

    'api_base' => env('SIGNWELL_API_BASE', 'https://www.signwell.com/api/v1'),

    'template_id' => env('SIGNWELL_TEMPLATE_ID', 'dc1e7295-e1d8-4ed0-9988-2182837f37b8'),

    /*
    | Must match the placeholder name on the SignWell template exactly.
    */
    'recipient_placeholder' => env('SIGNWELL_RECIPIENT_PLACEHOLDER', 'Student'),

    'test_mode' => (bool) env('SIGNWELL_TEST_MODE', true),

    /*
    | Optional shared secret for verifying SignWell webhooks (if configured).
    */
    'webhook_secret' => env('SIGNWELL_WEBHOOK_SECRET'),
];
