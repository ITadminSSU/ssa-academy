<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Email change verification window (minutes)
    |--------------------------------------------------------------------------
    */
    'email_change_token_expiry_minutes' => (int) env('EMAIL_CHANGE_TOKEN_EXPIRY_MINUTES', 60),
];
