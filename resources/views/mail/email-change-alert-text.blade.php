Hello, {{ $user->name }}!

We received a request to change the email address on your {{ config('mail.from.name') }} account to {{ $newEmail }}.

A verification link was sent to that new address. The change will only complete after that link is confirmed.
This request expires in {{ (int) config('account.email_change_token_expiry_minutes', 60) }} minutes.

If you did not request this change, secure your account immediately by changing your password and contact support.

Thanks,
{{ config('mail.from.name') }} Team
