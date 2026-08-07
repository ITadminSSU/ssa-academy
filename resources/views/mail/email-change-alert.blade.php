<!DOCTYPE
   html
   PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"
>
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
   <title>{{ config('app.name') }}</title>
   <meta name="viewport" content="width=device-width, initial-scale=1.0" />
   <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
</head>

<body>
   <h1 style="font-size: 1.5em; font-weight: 600; margin-bottom: 1em;">
      Email change requested
   </h1>

   <p style="margin-bottom: 1em;">
      Hello, {{ $user->name }}!
   </p>

   <p style="margin-bottom: 1.5em;">
      We received a request to change the email address on your {{ config('mail.from.name') }} account
      to <strong>{{ $newEmail }}</strong>.
   </p>

   <p style="margin-bottom: 1.5em;">
      A verification link was sent to that new address. The change will only complete after that link is confirmed.
      This request expires in {{ (int) config('account.email_change_token_expiry_minutes', 60) }} minutes.
   </p>

   <p style="margin-bottom: 1em;">
      If you did not request this change, secure your account immediately by changing your password and contact support.
      You can ignore this message if you made the request yourself.
   </p>

   <p style="margin: 2em 0 0;">
      Thanks,<br>
      {{ config('mail.from.name') }} Team
   </p>
</body>

</html>
