<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify your Kawhe Loyalty email</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background-color: #f8f9fa; padding: 30px; border-radius: 8px;">
        <h1 style="color: #2563eb; margin-top: 0;">Verify your Kawhe Loyalty email</h1>
        
        <p>Hello,</p>
        
        <p>Please verify your email address to enable card recovery features for your loyalty card.</p>
        
        <p style="margin: 30px 0;">
            <a href="{{ $verificationUrl }}" 
               style="display: inline-block; background-color: #2563eb; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">
                Verify Email Address
            </a>
        </p>
        
        <p style="color: #666; font-size: 14px;">
            If the button doesn't work, copy and paste this link into your browser:<br>
            <a href="{{ $verificationUrl }}" style="color: #2563eb; word-break: break-all;">{{ $verificationUrl }}</a>
        </p>
        
        <p style="color: #666; font-size: 14px; margin-top: 30px;">
            This link will expire in 60 minutes. If you didn't request this verification, you can safely ignore this email.
        </p>
        
        <p style="margin-top: 30px; color: #666; font-size: 14px;">
            Best regards,<br>
            Kawhe Loyalty Team
        </p>
    </div>
</body>
</html>


