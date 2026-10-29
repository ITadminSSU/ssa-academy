<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
   <title>{{ config('app.name') }}</title>
   <meta name="viewport" content="width=device-width, initial-scale=1.0" />
   <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
</head>
<body style="margin: 0; padding: 24px; font-family: Georgia, 'Times New Roman', serif; color: #1a1a1a; background: #f7f5f2; line-height: 1.55;">
   <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 560px; margin: 0 auto; background: #ffffff; border: 1px solid #e6e1da;">
      <tr>
         <td style="padding: 28px 28px 16px;">
            @include('mail.partials.brand-logo')
         </td>
      </tr>
      <tr>
         <td style="padding: 0 28px 20px; font-size: 22px; font-weight: 700; color: #14110f;">
            Your PEF courses are ready
         </td>
      </tr>
      <tr>
         <td style="padding: 0 28px 16px; font-size: 16px;">
            Hello {{ $studentName }},
         </td>
      </tr>
      <tr>
         <td style="padding: 0 28px 14px; font-size: 15px; color: #2c2824;">
            Your account is tagged PEF. These courses are now open on your student dashboard.
            @if (! empty($accessEndsAt))
               Access lasts through {{ $accessEndsAt }}. After that date, lessons you already finished stay available.
            @endif
         </td>
      </tr>
      <tr>
         <td style="padding: 4px 28px 18px;">
            <ul style="margin: 0; padding-left: 18px; font-size: 15px; color: #2c2824;">
               @foreach ($courses as $course)
                  <li style="margin: 0 0 8px;">
                     <a href="{{ $course['url'] }}" style="color: #8C2A23;">{{ $course['title'] }}</a>
                  </li>
               @endforeach
            </ul>
         </td>
      </tr>
      <tr>
         <td style="padding: 0 28px 28px;">
            <a href="{{ $dashboardUrl }}" style="display: inline-block; background: #8C2A23; color: #ffffff; text-decoration: none; padding: 10px 16px; font-size: 14px;">
               Open my courses
            </a>
         </td>
      </tr>
   </table>
</body>
</html>
