<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Kawhe Loyalty!</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background-color: #f8f9fa; padding: 30px; border-radius: 8px;">
        <h1 style="color: #16A34A; margin-top: 0;">Welcome to Kawhe Loyalty, {{ $user->name }}!</h1>
        
        <p>Thank you for joining Kawhe Loyalty. We're excited to help you build and manage your customer loyalty program.</p>
        
        <p>Your account has been successfully created. Here's what you can do next:</p>
        
        <ul style="margin: 20px 0; padding-left: 20px;">
            <li>Create your first store</li>
            <li>Set up your loyalty program (stamps, rewards, etc.)</li>
            <li>Generate QR codes to share with customers</li>
            <li>Start tracking customer loyalty and rewards</li>
        </ul>
        
        <p style="margin: 30px 0;">
            <a href="{{ $dashboardUrl }}" 
               style="display: inline-block; background-color: #16A34A; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">
                Go to Dashboard
            </a>
        </p>
        
        <p style="color: #666; font-size: 14px;">
            If the button doesn't work, copy and paste this link into your browser:<br>
            <a href="{{ $dashboardUrl }}" style="color: #16A34A; word-break: break-all;">{{ $dashboardUrl }}</a>
        </p>
        
        <p style="color: #666; font-size: 14px; margin-top: 30px;">
            If you have any questions or need help getting started, please don't hesitate to reach out to our support team.
        </p>
        
        <p style="margin-top: 30px; color: #666; font-size: 14px;">
            Best regards,<br>
            The Kawhe Loyalty Team
        </p>
    </div>
</body>
</html>
