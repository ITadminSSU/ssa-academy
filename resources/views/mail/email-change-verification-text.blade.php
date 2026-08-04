Hello, {{ $user->name }}!

We received a request to update the email address for your {{ config('mail.from.name') }} account.

Confirm your new email address by opening this link in your browser (you do not need to be logged in):

{{ $verificationUrl }}

This link will expire in {{ (int) config('account.email_change_token_expiry_minutes', 60) }} minutes.

If you did not request this change, please secure your account by changing your password and contact our support team.

Thanks,
{{ config('mail.from.name') }} Team
