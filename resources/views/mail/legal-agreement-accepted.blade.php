<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ config('app.name') }} — Terms & Conditions Confirmation</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1a1b1b; line-height: 1.6; margin: 0; padding: 0; background: #f5f7fa;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background: #f5f7fa; padding: 24px 0;">
        <tr>
            <td align="center">
                <table width="680" cellpadding="0" cellspacing="0" style="background: #ffffff; border-radius: 8px; overflow: hidden;">
                    <tr>
                        <td style="background: #2d537c; color: #ffffff; padding: 24px 32px;">
                            @include('mail.partials.brand-logo', ['mailLogoVariant' => 'light', 'mailLogoWidth' => 160])
                            <h1 style="margin: 16px 0 0; font-size: 22px;">Terms & Conditions Confirmation</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 32px;">
                            <p>Hello {{ $user->name }},</p>
                            <p>
                                Thank you for registering with {{ config('app.name') }}. This email confirms that you accepted
                                the Terms &amp; Conditions on
                                <strong>{{ $acceptedAt->format('F j, Y \a\t g:i A T') }}</strong>.
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 20px 0; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                                <tr>
                                    <td style="padding: 16px 20px;">
                                        <p style="margin: 0 0 8px;"><strong>Email:</strong> {{ $user->email }}</p>
                                        <p style="margin: 0 0 8px;"><strong>Agreement version:</strong> {{ $agreementVersion }}</p>
                                        @if ($ipAddress)
                                            <p style="margin: 0;"><strong>IP address:</strong> {{ $ipAddress }}</p>
                                        @endif
                                    </td>
                                </tr>
                            </table>

                            <p>A PDF copy of the Terms &amp; Conditions is attached to this email for your records.</p>

                            <h2 style="color: #2d537c; font-size: 18px; margin-top: 28px;">{{ $terms->title }}</h2>
                            <div style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px 20px; background: #fcfcfd;">
                                {!! $terms->description !!}
                            </div>

                            <p style="margin-top: 28px;">
                                You can also review this document online at any time:
                            </p>
                            <ul>
                                <li><a href="{{ url('/' . $terms->slug) }}">{{ $terms->title }}</a></li>
                            </ul>

                            <p style="margin-top: 24px; color: #64748b; font-size: 13px;">
                                If you did not create this account, please contact
                                <a href="mailto:training@smartsourcingusa.com">training@smartsourcingusa.com</a>.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
