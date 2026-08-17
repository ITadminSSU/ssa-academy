<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user.
    |
    */

    // Authentication Messages
    'failed' => 'These credentials do not match our records.',
    'account_disabled' => 'This account has been disabled. If you believe this is a mistake, contact training@smartsourcingusa.com.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',
    'password_updated' => 'Your password has been updated.',
    'verification_link_sent' => 'A fresh verification code has been sent to your email address.',
    'password_reset_sent' => 'We have emailed your password reset link.',
    'google_auth_settings' => 'Google Auth Settings',
    'google_auth_description' => 'Google Auth Description',

    // Login Page (unified portal — all roles)
    'login_title' => 'Log in to your account',
    'login_description' => 'Enter your email and password below. One portal for administrators, trainers, internal employees, and external learners.',
    'login_audiences_heading' => 'Who signs in here',
    'login_audience_admin' => 'Administrators',
    'login_audience_admin_hint' => 'Manage courses, users, approvals, and platform settings',
    'login_audience_trainer' => 'Trainers',
    'login_audience_trainer_hint' => 'Create courses, review progress, and manage enrollments',
    'login_audience_internal' => 'Internal employees',
    'login_audience_internal_hint' => 'Access assigned training paths inside your organization',
    'login_audience_external' => 'External learners',
    'login_audience_external_hint' => 'Purchase and complete public courses and certifications',
    'login_external_register_note' => "Don't have an account?",
    'remember_me' => 'Remember me',
    'forgot_password' => 'Forgot Password',
    'continue_with' => 'Or continue with',
    'no_account' => "Don't have an account?",
    'google_auth' => 'Google Auth',


    // Register Page
    'register_title' => 'Create your account',
    'register_description' => 'External learners can register here. After you create an account, we email a 6-digit code — enter it on the next screen before the student agreement and dashboard.',
    'have_account' => 'Already have an account?',
    'register_learner_type_note' => 'Company email addresses are recognized automatically for internal access. Other accounts are registered as public learners. Admins can update learner type if needed.',
    'register_required_fields_note' => 'All fields are required. You must upload a CV/resume and agree to the Terms & Conditions and NDA before creating an account.',

    // Forgot Password
    'forgot_description' => 'Enter your email to receive a password reset link',
    'return_to_login' => 'Or, return to',

    // Reset Password
    'reset_title' => 'Reset password',
    'reset_description' => 'Please enter your new password below',

    // Confirm Password
    'confirm_title' => 'Confirm your password',
    'confirm_description' => 'This is a secure area of the application. Please confirm your password before continuing.',

    // Verify Email
    'change_email' => 'Change Email',
    'verify_title' => 'Verify your email',
    'verify_description' => 'Enter the 6-digit code we emailed you to continue to the student agreement and your dashboard.',
    'verification_sent' => 'A new verification code has been sent. Check your inbox and spam folder — the code is valid for 15 minutes.',

    // Two-factor authentication
    'two_factor_title' => 'Two-factor authentication',
    'two_factor_description' => 'Enter the 6-digit code from your authenticator app, or a recovery code.',
    'two_factor_recovery_hint' => 'You can also use one of your one-time recovery codes.',
    'two_factor_settings_description' => 'Add an authenticator app for an extra layer of security on admin and trainer accounts.',
    'two_factor_optional_note' => 'Optional for now. We recommend enabling it.',
    'two_factor_enabled' => 'Two-factor authentication is enabled',
    'two_factor_disabled' => 'Two-factor authentication is not enabled',
    'two_factor_scan_title' => 'Scan this QR code',
    'two_factor_scan_description' => 'Use Google Authenticator, Microsoft Authenticator, or Authy to scan the code, then enter the 6-digit code to confirm.',
    'two_factor_manual_secret' => 'Or enter this secret manually:',
    'two_factor_recovery_title' => 'Save your recovery codes',
    'two_factor_recovery_save' => 'Store these codes somewhere safe. Each code can be used once if you lose access to your authenticator.',
    'two_factor_regenerate_description' => 'Confirm your password and a current authenticator or recovery code to generate a new set of recovery codes.',
    'two_factor_disable_description' => 'Confirm your password and a current authenticator or recovery code to disable two-factor authentication.',
];
