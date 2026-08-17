Hello, {{ $user->name }}!

Confirm this email address to finish creating your {{ config('app.name') }} account:

{{ $user->email }}

Open this link in your browser:

{{ $url }}

@php
   $expireMinutes = (int) ($expireMinutes ?? 1440);
   $expireHours = max(1, (int) ceil($expireMinutes / 60));
@endphp
This link expires in {{ $expireHours }} {{ $expireHours === 1 ? 'hour' : 'hours' }}. After that, sign in and request a new one.

If you do not see this message in your inbox, check spam, junk, and promotions.

If you did not create an account, you can ignore this email.

Thanks,
{{ config('mail.from.name') }}
