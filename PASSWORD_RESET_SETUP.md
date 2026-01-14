# Password Reset Email Setup with SendGrid

## Overview

The password reset functionality uses Laravel's built-in password reset system, which sends emails via the configured mail driver. This guide ensures password reset emails work with SendGrid in production.

## Current Implementation

The password reset flow uses:
- `PasswordResetLinkController` - Handles forgot password requests
- Laravel's `Password::sendResetLink()` - Sends reset link via email
- Default `ResetPassword` notification - Uses configured mail driver

## Configuration Required

### 1. Production `.env` File

Ensure these settings are configured in your production `.env`:

```env
# Mail Configuration (REQUIRED for password reset)
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=SG.your_actual_sendgrid_api_key_here
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Kawhe Loyalty"

# Queue Configuration (optional, but recommended)
QUEUE_CONNECTION=database
```

**Important Notes:**
- `MAIL_USERNAME` must be exactly `apikey` (SendGrid requirement)
- `MAIL_PASSWORD` should be your full SendGrid API key (starts with `SG.`)
- `MAIL_FROM_ADDRESS` must be a verified sender in SendGrid
- `MAIL_MAILER` must be `smtp` (not `log`) for production

### 2. Verify SendGrid Sender

1. Login to [SendGrid Dashboard](https://app.sendgrid.com/)
2. Go to **Settings** → **Sender Authentication**
3. Verify your sender email (or domain)
4. Use the verified email as `MAIL_FROM_ADDRESS`

### 3. Queue Worker (If Using Queues)

If `QUEUE_CONNECTION=database`, ensure queue worker is running:

```bash
php artisan queue:work --tries=3
```

Or use Supervisor/systemd to keep it running.

## Testing Password Reset

### Local Testing

1. Set `MAIL_MAILER=log` in `.env` for local testing
2. Check `storage/logs/laravel.log` for password reset emails
3. Or use Mailtrap/MailHog for local SMTP testing

### Production Testing

1. Ensure SendGrid credentials are set in `.env`
2. Clear config cache: `php artisan config:clear && php artisan config:cache`
3. Test password reset flow:
   - Go to `/forgot-password`
   - Enter a valid user email
   - Check email inbox for reset link
   - Check `storage/logs/laravel.log` for any errors

## Troubleshooting

### Password Reset Link Not Received

1. **Check Mail Configuration:**
   ```bash
   php artisan tinker
   >>> config('mail.default')
   >>> config('mail.mailers.smtp.host')
   >>> config('mail.from.address')
   ```

2. **Check Logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```
   Look for "Password reset link sent" or "Password reset link failed to send"

3. **Verify SendGrid API Key:**
   - Ensure API key has "Mail Send" permissions
   - Check API key is not expired/revoked
   - Verify `MAIL_USERNAME=apikey` (exact match)

4. **Check Sender Verification:**
   - Sender email must be verified in SendGrid
   - Domain must be authenticated (if using domain)

5. **Test SMTP Connection:**
   ```bash
   php artisan tinker
   >>> Mail::raw('Test email', function($message) {
   ...     $message->to('your-email@example.com')
   ...            ->subject('Test');
   ... });
   ```

6. **Check Queue (if using queues):**
   ```bash
   php artisan queue:work --once
   ```
   Or check failed jobs:
   ```bash
   php artisan queue:failed
   ```

### Common Issues

**Issue: "Email not sent" but no error**
- Check `MAIL_MAILER` is `smtp` (not `log`)
- Verify SendGrid credentials are correct
- Check queue worker is running (if using queues)

**Issue: "Invalid credentials"**
- Verify `MAIL_USERNAME=apikey` (exact match, lowercase)
- Check `MAIL_PASSWORD` is the full API key (starts with `SG.`)
- Ensure API key has correct permissions

**Issue: "Sender not verified"**
- Verify sender email in SendGrid dashboard
- Use verified email as `MAIL_FROM_ADDRESS`
- Wait for verification to complete (can take a few minutes)

## Deployment Checklist

- [ ] SendGrid API key added to production `.env`
- [ ] `MAIL_MAILER=smtp` in production `.env`
- [ ] `MAIL_FROM_ADDRESS` is verified in SendGrid
- [ ] Config cache cleared and rebuilt: `php artisan config:clear && php artisan config:cache`
- [ ] Queue worker running (if using queues)
- [ ] Test password reset flow in production
- [ ] Check logs for any errors

## Code Changes Made

1. **Added logging to `PasswordResetLinkController`:**
   - Logs successful password reset link sends
   - Logs failures with mail configuration details
   - Helps debug email delivery issues

2. **No changes needed to:**
   - User model (uses default `ResetPassword` notification)
   - Routes (already configured)
   - Views (already configured)

## Additional Notes

- Password reset emails use Laravel's default `ResetPassword` notification
- Emails are sent synchronously (unless queue is configured)
- Reset links expire after 60 minutes (Laravel default)
- Reset tokens are one-time use only
