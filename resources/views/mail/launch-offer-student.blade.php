<!DOCTYPE
   html
   PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"
>
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
   <title>{{ config('app.name') }}</title>
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
</head>

<body style="margin: 0; padding: 24px; font-family: Georgia, 'Times New Roman', serif; color: #1a1a1a; background: #f7f5f2; line-height: 1.55;">
   <table
      role="presentation"
      width="100%"
      cellpadding="0"
      cellspacing="0"
      style="max-width: 560px; margin: 0 auto; background: #ffffff; border: 1px solid #e6e1da;"
   >
      <tr>
         <td style="padding: 28px 28px 8px; font-size: 13px; letter-spacing: 0.04em; text-transform: uppercase; color: #6b635a;">
            {{ config('branding.short_name', config('app.name')) }}
         </td>
      </tr>
      <tr>
         <td style="padding: 0 28px 20px; font-size: 22px; font-weight: 700; color: #14110f;">
            {{ $emailSubject }}
         </td>
      </tr>
      <tr>
         <td style="padding: 0 28px 16px; font-size: 16px;">
            {{ $greeting }}
         </td>
      </tr>

      @foreach ($paragraphs as $paragraph)
         <tr>
            <td style="padding: 0 28px 14px; font-size: 15px; color: #2c2824;">
               {{ $paragraph }}
            </td>
         </tr>
      @endforeach

      @if (! empty($bullets))
         <tr>
            <td style="padding: 4px 28px 18px;">
               <ul style="margin: 0; padding-left: 20px; font-size: 15px; color: #2c2824;">
                  @foreach ($bullets as $bullet)
                     <li style="margin-bottom: 8px;">{{ $bullet }}</li>
                  @endforeach
               </ul>
            </td>
         </tr>
      @endif

      @if ($ctaUrl && $ctaLabel)
         <tr>
            <td style="padding: 4px 28px 22px;">
               <a
                  href="{{ $ctaUrl }}"
                  style="display: inline-block; padding: 12px 20px; background-color: #1f4d3a; color: #ffffff; text-decoration: none; font-weight: 600; font-size: 15px;"
               >
                  {{ $ctaLabel }}
               </a>
            </td>
         </tr>
         <tr>
            <td style="padding: 0 28px 18px; font-size: 13px; color: #6b635a; word-break: break-all;">
               Or open: {{ $ctaUrl }}
            </td>
         </tr>
      @endif

      @if ($closingNote)
         <tr>
            <td style="padding: 0 28px 18px; font-size: 14px; color: #6b635a;">
               {{ $closingNote }}
            </td>
         </tr>
      @endif

      <tr>
         <td style="padding: 8px 28px 28px; font-size: 14px; color: #2c2824;">
            Thanks,<br>
            {{ config('mail.from.name', config('app.name')) }} Team
         </td>
      </tr>
   </table>
</body>

</html>
