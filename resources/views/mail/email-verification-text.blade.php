Hello, {{ $user->name }}!

Enter this code on the {{ config('app.name') }} website to confirm {{ $user->email }}:

{{ $code }}

@php
   $expireMinutes = (int) ($expireMinutes ?? 15);
@endphp
This code expires in {{ $expireMinutes }} minutes. If it expires, sign in and request a new one.

If you do not see this message in your inbox, check spam, junk, and promotions.

If you did not create an account, you can ignore this email.

Thanks,
{{ config('mail.from.name') }}
