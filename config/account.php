<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Email change verification window (minutes)
    |--------------------------------------------------------------------------
    */
    'email_change_token_expiry_minutes' => (int) env('EMAIL_CHANGE_TOKEN_EXPIRY_MINUTES', 60),

    /*
    |--------------------------------------------------------------------------
    | Single active session (Phase 1)
    |--------------------------------------------------------------------------
    |
    | When enabled, a successful login invalidates every other browser session
    | for that user (students, trainers, and admins). Requires SESSION_DRIVER=database.
    |
    */
    'single_session' => [
        'enabled' => filter_var(env('SSU_SINGLE_SESSION', true), FILTER_VALIDATE_BOOL),
    ],
];
