# SendGrid Troubleshooting Guide

## Current Issue: "Maximum credits exceeded"

Your SendGrid account has reached its free tier limit (100 emails/day for free accounts).

## Quick Fix: Use Log Driver (Temporary)

To test the email verification flow without SendGrid, switch to log driver:

### Option 1: Update .env file

```env
MAIL_MAILER=log
```

Then clear config cache:
```bash
php artisan config:clear
```

**Note**: Emails will be written to `storage/logs/laravel.log` instead of being sent. You can view the email content there.

### Option 2: Test Email Content

To see what the email would look like, check the log file:
```bash
tail -f storage/logs/laravel.log
```

Then trigger a verification email and look for the email content in the logs.

## Fix SendGrid Account

### Check Your SendGrid Account

1. **Login to SendGrid**: https://app.sendgrid.com/
2. **Check Usage**: 
   - Go to **Activity** → **Overview**
   - Check your daily/monthly email count
   - Free tier: 100 emails/day

### Solutions

#### Option A: Wait for Reset
- Free tier resets daily at midnight UTC
- Wait until tomorrow to send more emails

#### Option B: Upgrade SendGrid Plan
- Go to **Settings** → **Billing**
- Upgrade to a paid plan for more credits
- Essential Plan: $19.95/month for 50,000 emails

#### Option C: Verify Domain (Increases Limits)
- Go to **Settings** → **Sender Authentication**
- Authenticate your domain
- This can increase your sending limits

#### Option D: Use Alternative Email Service

**Mailgun** (Free tier: 5,000 emails/month):
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=your-mailgun-username
MAIL_PASSWORD=your-mailgun-password
MAIL_ENCRYPTION=tls
```

**Postmark** (Free tier: 100 emails/month):
```env
MAIL_MAILER=postmark
POSTMARK_TOKEN=your-postmark-token
```

**Amazon SES** (Pay as you go):
```env
MAIL_MAILER=ses
AWS_ACCESS_KEY_ID=your-key
AWS_SECRET_ACCESS_KEY=your-secret
AWS_DEFAULT_REGION=us-east-1
```

## Verify Email Configuration

Check your current mail configuration:
```bash
php artisan tinker
>>> config('mail.default')
>>> config('mail.mailers.smtp.host')
>>> config('mail.from.address')
```

## Test Email Sending

### Test with Log Driver
```bash
# Set MAIL_MAILER=log in .env
php artisan config:clear

# Send test email
php artisan tinker
>>> Mail::raw('Test email', function($message) {
...     $message->to('your-email@example.com')->subject('Test');
... });

# Check logs
tail -f storage/logs/laravel.log
```

### Test with SendGrid (after fixing account)
```bash
# Make sure MAIL_MAILER=smtp in .env
php artisan config:clear

# Test send
php artisan tinker
>>> Mail::raw('Test email', function($message) {
...     $message->to('your-email@example.com')->subject('Test');
... });
```

## Current Status

- ✅ Email verification code is working
- ✅ Error handling is in place
- ⚠️ SendGrid account needs attention (credits exceeded)
- ✅ Can use log driver for testing

## Next Steps

1. **Immediate**: Switch to `MAIL_MAILER=log` to test functionality
2. **Short-term**: Check SendGrid account and wait for reset or upgrade
3. **Long-term**: Consider domain authentication or alternative service

## Monitoring

Check email sending status:
```bash
# View recent email attempts
tail -50 storage/logs/laravel.log | grep -i "mail\|email\|sendgrid"

# Check for errors
tail -50 storage/logs/laravel.log | grep -i "error\|exception\|failed"
```
