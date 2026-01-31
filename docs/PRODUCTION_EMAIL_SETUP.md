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

# Optional: send welcome/verification emails immediately (no queue delay).
# When true, emails are sent during the request (1–3 sec slower response).
# When false (default), emails go to the "emails" queue for a worker to process.
# MAIL_WELCOME_SYNC=false

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

Welcome and verification emails are sent to a dedicated **`emails`** queue so they can be processed before other jobs. Run the worker with **`--queue=emails,default`** so the `emails` queue is processed first. Use **`--sleep=0`** or **`--sleep=1`** so new email jobs are picked up quickly (avoid long delays).

### Option A: Using Supervisor (Recommended for Production)

Create supervisor config file at `/etc/supervisor/conf.d/kawhe-queue-worker.conf`:

```ini
[program:kawhe-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/app/artisan queue:work database --queue=emails,default --sleep=1 --tries=3 --max-time=3600
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

**Why `--queue=emails,default`:** Welcome and verification mailables use the `emails` queue. Processing `emails` first ensures they are sent without waiting behind other jobs. **Why `--sleep=1`:** A lower sleep (or 0) means the worker checks for new jobs more often, so queued emails are sent within seconds instead of after a long poll.

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
ExecStart=/usr/bin/php /path/to/your/app/artisan queue:work database --queue=emails,default --sleep=1 --tries=3 --max-time=3600

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
php artisan queue:work database --queue=emails,default --sleep=1 --tries=3
```

**Note:** This runs in the foreground. Use Ctrl+C to stop.

## Improving welcome email delivery time

**What you control (app side):**

1. **Queue priority** – Welcome and verification emails use the `emails` queue. Run the worker with `--queue=emails,default` so these are processed first and not delayed by other jobs.
2. **Worker sleep** – Use `--sleep=0` or `--sleep=1` so the worker picks up new email jobs quickly. A sleep of 3+ seconds can add noticeable delay.
3. **Optional: send synchronously** – Set `MAIL_WELCOME_SYNC=true` in `.env` to send welcome and verification emails during the HTTP request (no queue). Emails reach SendGrid immediately; the tradeoff is the request may take 1–3 seconds longer. Use this if you want the fastest “leave our server” time and can accept slightly slower page loads.

**What SendGrid and recipients control:**

- **SendGrid handoff** – Once your app (or queue worker) sends the message to SendGrid over SMTP, delivery to SendGrid’s systems is usually within seconds. You cannot speed this up from the app.
- **Inbox delivery** – After SendGrid accepts the message, delivery to the recipient’s inbox (Gmail, Outlook, etc.) is determined by the recipient’s mail provider. Delays of a few seconds up to several minutes are normal and cannot be fixed in your code. You can monitor delivery in the SendGrid Activity feed.

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
