<?php

return [
    /*
    | When enabled, new students must complete the SignWell Student Agreement
    | before accessing the learner dashboard.
    */
    'enabled' => filter_var(env('SIGNWELL_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    'api_key' => trim((string) env('SIGNWELL_API_KEY', '')),

    'api_base' => rtrim((string) env('SIGNWELL_API_BASE', 'https://www.signwell.com/api/v1'), '/'),

    'template_id' => trim((string) env('SIGNWELL_TEMPLATE_ID', 'dc1e7295-e1d8-4ed0-9988-2182837f37b8')),

    /*
    | Must match a placeholder name on the SignWell template exactly (case-insensitive).
    | Open the template in SignWell → look at the recipient/placeholder label.
    */
    'recipient_placeholder' => trim((string) env('SIGNWELL_RECIPIENT_PLACEHOLDER', 'Student')),

    /*
    | Used only if the template has extra non-student roles that cannot be excluded.
    | document_sender is excluded by default so only the student signs.
    */
    'sender_name' => trim((string) env('SIGNWELL_SENDER_NAME', env('MAIL_FROM_NAME', 'SMARTSOURCING USA Academy'))),

    'sender_email' => trim((string) env('SIGNWELL_SENDER_EMAIL', env('MAIL_FROM_ADDRESS', ''))),

    'test_mode' => filter_var(env('SIGNWELL_TEST_MODE', true), FILTER_VALIDATE_BOOLEAN),

    /*
    | Optional shared secret for verifying SignWell webhooks (if configured).
    */
    'webhook_secret' => trim((string) env('SIGNWELL_WEBHOOK_SECRET', '')),
];
