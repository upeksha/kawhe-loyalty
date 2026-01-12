# Production Email Setup Guide

This guide covers setting up production-ready email verification using SendGrid with Laravel queues.

## Prerequisites

- Laravel 11 application
- Database configured (for queue jobs table)
- SendGrid account with API key

## Step 1: Environment Configuration

Add these to your `.env` file:

```env
# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=your_sendgrid_api_key_here
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Kawhe Loyalty"

# Queue Configuration
QUEUE_CONNECTION=database

# App URL (must be correct for verification links)
APP_URL=https://testing.kawhe.shop
APP_ENV=production
```

**Important:** 
- `MAIL_USERNAME` must be exactly `apikey` for SendGrid
- `MAIL_PASSWORD` is your SendGrid API key (not your SendGrid password)
- `APP_URL` must match your production domain exactly

## Step 2: Database Queue Setup

The queue uses the database driver. Ensure the jobs table exists:

```bash
php artisan migrate
```

This creates:
- `jobs` table (queued jobs)
- `job_batches` table (batch jobs)
- `failed_jobs` table (failed job tracking)

## Step 3: Queue Worker Setup

### Option A: Using Supervisor (Recommended for Production)

Create supervisor config file at `/etc/supervisor/conf.d/kawhe-queue-worker.conf`:

```ini
[program:kawhe-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/app/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/your/app/storage/logs/queue-worker.log
stopwaitsecs=3600
```

Then:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start kawhe-queue-worker:*
```

### Option B: Using systemd (Alternative)

Create systemd service file at `/etc/systemd/system/kawhe-queue-worker.service`:

```ini
[Unit]
Description=Kawhe Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
ExecStart=/usr/bin/php /path/to/your/app/artisan queue:work database --sleep=3 --tries=3 --max-time=3600

[Install]
WantedBy=multi-user.target
```

Then:

```bash
sudo systemctl daemon-reload
sudo systemctl enable kawhe-queue-worker
sudo systemctl start kawhe-queue-worker
sudo systemctl status kawhe-queue-worker
```

### Option C: Manual (For Testing)

For testing or development, you can run the queue worker manually:

```bash
php artisan queue:work database --sleep=3 --tries=3
```

**Note:** This runs in the foreground. Use Ctrl+C to stop.

## Step 4: Testing Email Configuration

Test your email setup:

```bash
php artisan kawhe:mail-test your-email@example.com
```

This will:
1. Queue a test verification email
2. Show queue status
3. Provide troubleshooting tips if it fails

Then process the queue:

```bash
php artisan queue:work
```

Check the email was sent (or check logs if using `log` driver).

## Step 5: Monitoring

### Check Queue Status

```bash
# View pending jobs
php artisan queue:monitor

# View failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

### Check Logs

```bash
# View application logs
tail -f storage/logs/laravel.log

# View queue worker logs (if using supervisor)
tail -f storage/logs/queue-worker.log
```

## Troubleshooting

### Emails Not Sending

1. **Check SendGrid Account:**
   - Verify API key is correct
   - Check SendGrid account has credits
   - Verify sender email is verified in SendGrid

2. **Check Queue Worker:**
   ```bash
   # Check if worker is running
   sudo supervisorctl status kawhe-queue-worker:*
   # OR
   sudo systemctl status kawhe-queue-worker
   
   # Check for failed jobs
   php artisan queue:failed
   ```

3. **Check Logs:**
   ```bash
   tail -f storage/logs/laravel.log | grep -i mail
   ```

### SendGrid Errors

If you see "Maximum credits exceeded" or "Authentication failed":

1. Check SendGrid dashboard for account status
2. Verify API key is correct in `.env`
3. Emails will be retried automatically (3 attempts with backoff)
4. Failed jobs can be retried: `php artisan queue:retry all`

### Verification Links Not Working

1. **Check APP_URL:**
   ```bash
   php artisan tinker
   >>> config('app.url')
   ```
   Should match your production domain exactly.

2. **Check HTTPS:**
   - Ensure `APP_ENV=production` in `.env`
   - App automatically forces HTTPS in production

3. **Test verification link:**
   - Request verification email
   - Check email for link
   - Click link and verify it redirects correctly

## Fallback to Log Driver

If SendGrid is down, you can temporarily switch to log driver for testing:

```env
MAIL_MAILER=log
```

Emails will be written to `storage/logs/laravel.log` instead of being sent.

**Important:** The application will continue to work even if email sending fails. Customer creation and card usage are not blocked by email failures.

## After Deployment Checklist

After deploying from git, run these commands:

```bash
# 1. Install dependencies (if needed)
composer install --no-dev --optimize-autoloader

# 2. Run migrations (creates jobs table if needed)
php artisan migrate --force

# 3. Clear and cache config
php artisan config:clear
php artisan config:cache

# 4. Restart queue worker
sudo supervisorctl restart kawhe-queue-worker:*
# OR
sudo systemctl restart kawhe-queue-worker

# 5. Test email
php artisan kawhe:mail-test your-email@example.com

# 6. Process queue to send test email
php artisan queue:work --once
```

## Security Notes

- Never commit `.env` file to git
- Store SendGrid API key securely
- Use environment variables for all sensitive data
- Rotate API keys periodically
