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
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',
    'password_updated' => 'Your password has been updated.',
    'verification_link_sent' => 'A fresh verification link has been sent to your email address.',
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
    'register_description' => 'External learners can register here. Company email addresses are recognized automatically for internal access.',
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
    'verify_title' => 'Verify email',
    'verify_description' => 'Please verify your email address by clicking on the link we just emailed to you.',
    'verification_sent' => 'A new verification link has been sent to the email address you provided during registration.',
];
