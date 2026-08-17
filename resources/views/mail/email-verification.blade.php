<!DOCTYPE
   html
   PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"
>
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
   <title>Your verification code — {{ config('app.name') }}</title>
   <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0"
   />
   <meta
      http-equiv="Content-Type"
      content="text/html; charset=UTF-8"
   />
   <meta
      name="color-scheme"
      content="light"
   >
   <meta
      name="supported-color-schemes"
      content="light"
   >
   <style>
      @media only screen and (max-width: 600px) {
         .inner-body {
            width: 100% !important;
         }

         .footer {
            width: 100% !important;
         }
      }
   </style>

   {{ $head ?? '' }}
</head>

<body>
   @php
      $expireMinutes = (int) ($expireMinutes ?? 15);
   @endphp

   <h1 style="font-size: 1.5em; font-weight: 600; margin-bottom: 1em;">
      Hello, {{ $user->name }}!
   </h1>
   <p style="margin-bottom: 1.5em;">
      Enter this code on the {{ config('app.name') }} website to confirm
      <strong>{{ $user->email }}</strong>:
   </p>
   <p style="font-size: 2em; font-weight: 700; letter-spacing: 0.25em; margin: 0 0 1.5em; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;">
      {{ $code }}
   </p>
   <p style="margin-bottom: 1em;">
      This code expires in {{ $expireMinutes }} minutes. If it expires, sign in and request a new one.
   </p>
   <p style="margin-bottom: 1em;">
      If you do not see this message in your inbox, check spam, junk, and promotions.
   </p>
   <p style="margin-bottom: 1em;">
      If you did not create an account, you can ignore this email.
   </p>

   <p style="margin: 2em 0 0;">
      Thanks,<br>
      {{ config('mail.from.name') }}
   </p>
</body>

</html>
