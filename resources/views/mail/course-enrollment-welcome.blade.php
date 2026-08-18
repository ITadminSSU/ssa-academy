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
         <td style="padding: 28px 28px 8px; font-size: 13px; letter-spacing: 0.04em; text-transform: uppercase; color: #6b635a;">
            {{ config('branding.short_name', config('app.name')) }}
         </td>
      </tr>
      <tr>
         <td style="padding: 0 28px 20px; font-size: 22px; font-weight: 700; color: #14110f;">
            Welcome to {{ $courseTitle }}
         </td>
      </tr>
      <tr>
         <td style="padding: 0 28px 16px; font-size: 16px;">
            {{ $greeting }}
         </td>
      </tr>
      <tr>
         <td style="padding: 0 28px 14px; font-size: 15px; color: #2c2824;">
            You are enrolled in <strong>{{ $courseTitle }}</strong>. Welcome — we are glad you are here.
         </td>
      </tr>

      @if ($instructorName || $instructorBio)
         <tr>
            <td style="padding: 8px 28px 18px;">
               <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background: #f8f6f3; border: 1px solid #e6e1da;">
                  <tr>
                     <td style="padding: 18px 20px;">
                        <p style="margin: 0 0 8px; font-size: 13px; letter-spacing: 0.04em; text-transform: uppercase; color: #6b635a;">
                           Your instructor
                        </p>
                        @if ($instructorName)
                           <p style="margin: 0 0 10px; font-size: 17px; font-weight: 700; color: #14110f;">
                              {{ $instructorName }}
                           </p>
                        @endif
                        @if ($instructorBio)
                           <p style="margin: 0; font-size: 15px; color: #2c2824;">
                              {{ $instructorBio }}
                           </p>
                        @endif
                     </td>
                  </tr>
               </table>
            </td>
         </tr>
      @endif

      <tr>
         <td style="padding: 0 28px 12px; font-size: 15px; color: #2c2824;">
            Join our Facebook community to meet other learners, ask questions, and stay up to date:
         </td>
      </tr>
      <tr>
         <td style="padding: 4px 28px 10px;">
            <a href="{{ $facebookUrl }}" style="display: inline-block; padding: 12px 20px; background-color: #1877F2; color: #ffffff; text-decoration: none; font-weight: 600; font-size: 15px;">
               Join the Facebook group
            </a>
         </td>
      </tr>
      <tr>
         <td style="padding: 0 28px 22px; font-size: 13px; color: #6b635a; word-break: break-all;">
            {{ $facebookUrl }}
         </td>
      </tr>
      <tr>
         <td style="padding: 4px 28px 10px;">
            <a href="{{ $ctaUrl }}" style="display: inline-block; padding: 12px 20px; background-color: #1f4d3a; color: #ffffff; text-decoration: none; font-weight: 600; font-size: 15px;">
               {{ $ctaLabel }}
            </a>
         </td>
      </tr>
      <tr>
         <td style="padding: 0 28px 18px; font-size: 13px; color: #6b635a; word-break: break-all;">
            Or open: {{ $ctaUrl }}
         </td>
      </tr>
      <tr>
         <td style="padding: 8px 28px 28px; font-size: 14px; color: #2c2824;">
            Thanks,<br>
            {{ config('mail.from.name', config('app.name')) }} Team
         </td>
      </tr>
   </table>
</body>
</html>
