@props(['title', 'actionUrl', 'actionLabel', 'footnote' => null])
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f0f7f4;color:#1c3329;font-family:Arial,Helvetica,sans-serif;">
    <div style="display:none;max-height:0;overflow:hidden;mso-hide:all;">{{ $title }}</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f0f7f4;">
        <tr><td align="center" style="padding:32px 16px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;">
                <tr><td style="padding:0 0 24px;font-size:26px;font-weight:bold;letter-spacing:-1px;color:#2d5d47;">kawhe<span style="font-size:12px;letter-spacing:2px;font-weight:normal;"> &nbsp; LOYALTY</span></td></tr>
                <tr><td style="background-color:#ffffff;border:1px solid #dcebe4;border-radius:12px;padding:32px 24px;font-size:16px;line-height:1.65;">
                    <h1 style="margin:0 0 20px;font-size:26px;line-height:1.25;letter-spacing:-0.5px;color:#1c3329;">{{ $title }}</h1>
                    {{ $slot }}
                    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:28px 0;"><tr><td bgcolor="#2d5d47" style="border-radius:6px;text-align:center;">
                        <a href="{{ $actionUrl }}" style="display:inline-block;background-color:#2d5d47;border:1px solid #2d5d47;border-radius:6px;padding:14px 24px;color:#ffffff;font-size:16px;font-weight:bold;text-decoration:none;mso-padding-alt:14px 24px;">{{ $actionLabel }}</a>
                    </td></tr></table>
                    @if($footnote)<p style="margin:0 0 24px;color:#53665b;font-size:14px;line-height:1.6;">{{ $footnote }}</p>@endif
                    <p style="margin:24px 0 8px;padding-top:20px;border-top:1px solid #dcebe4;color:#53665b;font-size:12px;line-height:1.6;">If the button doesn't work, copy and paste this link into your browser:</p>
                    <a href="{{ $actionUrl }}" style="color:#2d5d47;font-size:12px;line-height:1.6;word-break:break-all;overflow-wrap:anywhere;">{{ $actionUrl }}</a>
                </td></tr>
                <tr><td style="padding:20px 8px;color:#53665b;font-size:12px;line-height:1.6;">Kawhe Loyalty<br>Loyalty, one visit at a time.</td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
