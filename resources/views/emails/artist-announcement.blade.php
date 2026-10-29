<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;max-width:600px;width:100%;">
                    <tr>
                        <td style="background:#0a0a0a;padding:20px 32px;">
                            <span style="color:#ffffff;font-size:20px;font-weight:bold;">{{ settings('branding.site_name') }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <p style="font-size:15px;color:#111;margin:0 0 16px;">
                                Hello {{ $recipientName }},
                            </p>
                            <div style="font-size:15px;color:#333;line-height:1.6;">
                                @if (!empty($isHtml))
                                    {!! $bodyText !!}
                                @else
                                    {!! nl2br(e($bodyText)) !!}
                                @endif
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 32px;border-top:1px solid #eee;">
                            <p style="font-size:12px;color:#888;margin:0;">
                                {{ settings('branding.site_name') }} &middot;
                                <a href="{{ config('app.url') }}" style="color:#888;">{{ config('app.url') }}</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
