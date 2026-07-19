<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Employee Email Domains
    |--------------------------------------------------------------------------
    |
    | Comma-separated list of email domains that identify internal employees.
    | Registrations with a matching domain are assigned user_type "employee".
    | All other self-registrations receive user_type "external".
    | Admins can override learner type from Dashboard → Users.
    |
    */

    'employee_email_domains' => array_values(array_filter(array_map(
        static fn (string $domain) => strtolower(trim($domain)),
        explode(',', env('EMPLOYEE_EMAIL_DOMAINS', 'smartsourcingusa.com,lmsacademy.local'))
    ))),

];
