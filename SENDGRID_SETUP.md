# SendGrid SMTP Setup Guide

## ✅ What's Already Configured

1. **Mail Configuration** - Laravel mail config is set up for SMTP
2. **Email Mailable** - `VerifyCustomerEmail` class is ready
3. **Queue System** - Emails are queued (using `Mail::queue()`)
4. **Jobs Table Migration** - Database queue tables exist

## 🔧 What You Need to Complete

### Step 1: Get Your SendGrid API Key

1. Sign up/Login to [SendGrid](https://sendgrid.com/)
2. Go to **Settings** → **API Keys**
3. Click **Create API Key**
4. Name it (e.g., "Kawhe Loyalty App")
5. Select **Full Access** or **Restricted Access** (with Mail Send permissions)
6. Copy the API key (you'll only see it once!)

### Step 2: Verify Your Sender Email

1. In SendGrid, go to **Settings** → **Sender Authentication**
2. Click **Verify a Single Sender** (for testing) or **Authenticate Your Domain** (for production)
3. Follow the verification steps
4. Note the verified email address (e.g., `noreply@yourdomain.com`)

### Step 3: Update Your `.env` File

Add these lines to your `.env` file:

```env
# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=SG.your_actual_sendgrid_api_key_here
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Kawhe Loyalty"

# Queue Configuration (for processing emails)
QUEUE_CONNECTION=database
```

**Important:**
- Replace `SG.your_actual_sendgrid_api_key_here` with your actual SendGrid API key
- Replace `noreply@yourdomain.com` with your verified sender email
- The `MAIL_USERNAME` must be exactly `apikey` (this is SendGrid's requirement)

### Step 4: Run Database Migrations (if not done)

```bash
php artisan migrate
```

This creates the `jobs` and `failed_jobs` tables needed for queued emails.

### Step 5: Start the Queue Worker

Since emails are queued, you need to run a queue worker to process them:

```bash
php artisan queue:work
```

Or for development with auto-restart:
```bash
php artisan queue:listen
```

**Note:** Keep this running in a separate terminal window while your app is running.

---

## 🧪 Testing the Setup

### Test 1: Send a Test Email

You can test by:
1. Going to a customer card page
2. Clicking "Verify Email" 
3. Check the queue worker terminal for processing
4. Check your email inbox

### Test 2: Check Queue Status

```bash
# See pending jobs
php artisan queue:monitor

# See failed jobs
php artisan queue:failed
```

### Test 3: Check Logs

If emails aren't sending, check:
```bash
# Laravel logs
tail -f storage/logs/laravel.log

# Or use Pail
php artisan pail
```

---

## 🚨 Troubleshooting

### Emails Not Sending?

1. **Check Queue Worker is Running**
   - Make sure `php artisan queue:work` is running
   - Emails won't send if the queue worker isn't processing jobs

2. **Verify .env Settings**
   - Make sure `MAIL_USERNAME=apikey` (literally the word "apikey")
   - Verify your API key starts with `SG.`
   - Check `MAIL_FROM_ADDRESS` matches your verified sender

3. **Check SendGrid Dashboard**
   - Go to SendGrid → Activity
   - See if emails are being received/rejected
   - Check for bounce or spam reports

4. **Test SMTP Connection**
   ```bash
   php artisan tinker
   ```
   Then in tinker:
   ```php
   Mail::raw('Test email', function($message) {
       $message->to('your-email@example.com')
               ->subject('Test');
   });
   ```

### Queue Jobs Failing?

```bash
# See failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush
```

---

## 📝 Production Recommendations

1. **Use Supervisor** (Linux) or **Laravel Horizon** for queue management
2. **Set up email monitoring** in SendGrid
3. **Use domain authentication** instead of single sender verification
4. **Set up webhooks** for bounce/spam handling
5. **Monitor queue:failed** table regularly

---

## 🔗 Quick Reference

- **SendGrid Dashboard:** https://app.sendgrid.com/
- **API Keys:** https://app.sendgrid.com/settings/api_keys
- **Sender Verification:** https://app.sendgrid.com/settings/sender_auth
- **Activity Feed:** https://app.sendgrid.com/activity

