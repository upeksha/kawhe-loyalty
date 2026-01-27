<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {{ $store->name }}!</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background-color: #f8f9fa; padding: 30px; border-radius: 8px;">
        <h1 style="color: #16A34A; margin-top: 0;">Welcome to {{ $store->name }}, {{ $customer->name ?? 'there' }}!</h1>
        
        <p>Thank you for joining our loyalty program! We're excited to have you as a customer.</p>
        
        <p>Your loyalty card has been created and you can start earning stamps right away. Here's what you can do:</p>
        
        <ul style="margin: 20px 0; padding-left: 20px;">
            <li>View your loyalty card and track your progress</li>
            <li>Earn stamps with each visit</li>
            <li>Redeem rewards when you reach your goal</li>
            <li>Add your card to Apple Wallet or Google Wallet for easy access</li>
        </ul>
        
        <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 30px 0; border-radius: 4px;">
            <p style="margin: 0; font-weight: bold; color: #856404;">📧 Verify Your Email Address</p>
            <p style="margin: 10px 0 0 0; color: #856404;">
                To redeem rewards, please verify your email address by clicking the button below. This will verify your email and take you to your loyalty card.
            </p>
        </div>
        
        <p style="margin: 30px 0;">
            <a href="{{ $verificationUrl }}" 
               style="display: inline-block; background-color: #16A34A; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">
                Verify Email & View Your Card
            </a>
        </p>
        
        <p style="color: #666; font-size: 14px;">
            If the button doesn't work, copy and paste this link into your browser:<br>
            <a href="{{ $verificationUrl }}" style="color: #2563eb; word-break: break-all;">{{ $verificationUrl }}</a>
        </p>
        
        <p style="color: #666; font-size: 14px; margin-top: 30px;">
            This verification link will expire in 60 minutes. If you didn't create this account, you can safely ignore this email.
        </p>
        
        <p style="margin-top: 30px; color: #666; font-size: 14px;">
            Best regards,<br>
            The {{ $store->name }} Team
        </p>
    </div>
</body>
</html>
