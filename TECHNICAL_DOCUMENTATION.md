# Kawhe Loyalty - Complete Technical Documentation

**Version:** 2.0  
**Last Updated:** January 2026

This is the complete, comprehensive documentation for the Kawhe Loyalty system. It covers everything from initial setup to advanced technical details, organized in the order you need to do things.

---

## Table of Contents

### PART 1: SETUP & INSTALLATION (Do These First)
1. [Overview & Introduction](#part-1-overview--introduction)
2. [Initial Setup](#part-1-initial-setup)
3. [Environment Configuration](#part-1-environment-configuration)
4. [Database Setup](#part-1-database-setup)
5. [SendGrid Email Setup](#part-1-sendgrid-email-setup)
6. [Stripe Billing Setup](#part-1-stripe-billing-setup)
7. [Apple Wallet Setup](#part-1-apple-wallet-setup)
8. [Google Wallet Setup](#part-1-google-wallet-setup)

### PART 2: HOW THE APP WORKS (Technical Details)
9. [System Architecture](#part-2-system-architecture)
10. [Database Schema & Logic](#part-2-database-schema--logic)
11. [Core Features Explained](#part-2-core-features-explained)
12. [API Endpoints](#part-2-api-endpoints)
13. [Real-time Features](#part-2-real-time-features)
14. [Security & Data Integrity](#part-2-security--data-integrity)
15. [Billing & Subscription Logic](#part-2-billing--subscription-logic)
16. [Wallet Integration Details](#part-2-wallet-integration-details)

### PART 3: DEPLOYMENT
17. [Production Deployment](#part-3-production-deployment)
18. [Safe Deployment Guide](#part-3-safe-deployment-guide)

### PART 4: TROUBLESHOOTING
19. [Common Issues & Solutions](#part-4-common-issues--solutions)
20. [Debug Guides](#part-4-debug-guides)

---

5. Test with real payment (small amount)
---

## STRIPE_SYNC_TEST_CHECKLIST.md
<a id="appendix-stripesynctestchecklistmd"></a>

**Source file**: `STRIPE_SYNC_TEST_CHECKLIST.md`

### Stripe Subscription Sync - Local Test Checklist

This checklist verifies that subscription sync works correctly after Stripe Checkout payment.

#### Prerequisites

Before testing, ensure your `.env` has:

```env
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_PRICE_ID=price_...
STRIPE_WEBHOOK_SECRET=whsec_...  # From Stripe CLI or Dashboard
APP_URL=http://localhost:8000  # Or your ngrok URL
```

#### Test Steps

##### 1. Start Local Environment

- [ ] Start Laravel server: `php artisan serve`
- [ ] (Optional) Start Stripe CLI: `stripe listen --forward-to localhost:8000/stripe/webhook`
- [ ] Copy webhook secret from Stripe CLI output to `.env` if using CLI
- [ ] Clear config cache: `php artisan config:clear`

##### 2. Pre-Checkout Verification

- [ ] Log in as a merchant user
- [ ] Navigate to `/billing`
- [ ] Verify "Free Plan" is shown
- [ ] Verify usage meter shows current card count (e.g., "X / 50")
- [ ] Click "Upgrade to Pro" button
- [ ] Verify redirect to Stripe Checkout page

##### 3. Complete Payment

- [ ] On Stripe Checkout page, use test card: `4242 4242 4242 4242`
- [ ] Enter any future expiry date (e.g., 12/34)
- [ ] Enter any CVC (e.g., 123)
- [ ] Enter any ZIP code
- [ ] Click "Subscribe" or "Pay"
- [ ] **Verify URL includes `?session_id=cs_test_...`** in browser address bar after redirect

##### 4. Success Page Verification

- [ ] After payment, verify redirect to `/billing/success?session_id=...`
- [ ] Check for one of these outcomes:
  - ✅ **Best case**: Redirected to dashboard with success message "Pro Plan Active"
  - ✅ **Good case**: Success page shows "Subscription Activating" with sync button
  - ⚠️ **Fallback**: Success page shows error message (should still have sync option)

##### 5. Database Verification

- [ ] Check `users` table:
  ```sql
  SELECT id, email, stripe_id FROM users WHERE id = {your_user_id};
  ```
  - Verify `stripe_id` is set (should be `cus_...`)

- [ ] Check `subscriptions` table:
  ```sql
  SELECT * FROM subscriptions WHERE user_id = {your_user_id};
  ```
  - Verify subscription exists
  - Verify `stripe_status` is `active` or `trialing`
  - Verify `type` is `default`

##### 6. Dashboard Verification

- [ ] Navigate to `/merchant/dashboard`
- [ ] Verify "Pro Plan Active" is shown (green checkmark)
- [ ] Verify usage meter shows "Cards issued: X / ∞" (unlimited)
- [ ] Verify "Upgrade" button is **NOT** visible
- [ ] Verify no "Limit Reached" warning banner

##### 7. Billing Page Verification

- [ ] Navigate to `/billing`
- [ ] Verify "Pro Plan Active" is shown
- [ ] Verify "Manage Subscription" button is visible
- [ ] Verify subscription details show status: `active` or `trialing`

##### 8. Manual Sync Test (if needed)

- [ ] If subscription not detected, click "Sync Subscription Status" on billing page
- [ ] Or use artisan command: `php artisan kawhe:sync-subscriptions {user_id}`
- [ ] Verify subscription syncs correctly
- [ ] Re-check dashboard and billing page

##### 9. Webhook Verification (if using Stripe CLI)

- [ ] Check Stripe CLI output for webhook events:
  - `checkout.session.completed`
  - `customer.subscription.created`
  - `customer.subscription.updated`
- [ ] Check Laravel logs: `tail -f storage/logs/laravel.log`
- [ ] Verify webhook processing logs appear
- [ ] Verify no webhook errors

##### 10. Functional Verification (Non-Breaking)

- [ ] **Stamping still works**: Go to scanner, scan a card, verify stamp is added
- [ ] **Redeeming still works**: Redeem a reward, verify it works
- [ ] **Reverb still works**: Open card page, stamp from scanner, verify real-time update
- [ ] **Store management still works**: Create/edit stores, verify no errors
- [ ] **Customer join still works**: Create new loyalty account, verify it works (should be unlimited now)

##### 11. Edge Cases

- [ ] Test with missing `session_id`: Go to `/billing/success` directly
  - Should show friendly error message
  - Should have link back to billing page

- [ ] Test manual sync endpoint: `POST /billing/sync` with `session_id`
  - Should sync subscription
  - Should be idempotent (safe to call multiple times)

- [ ] Test async payment (if Klarna enabled):
  - Use Klarna payment method
  - Verify success page shows "Payment Processing" message
  - Verify sync button is available
  - Wait for webhook, verify subscription activates

#### Expected Results

✅ **All tests should pass**

- Subscription syncs immediately after payment (best case)
- Or syncs within a few seconds via webhook
- Or can be manually synced via button/command
- Dashboard shows "Pro Plan Active"
- All existing functionality (stamping, redeeming, Reverb) still works
- No errors in logs

#### Troubleshooting

##### Subscription not syncing

1. Check `.env` has correct `STRIPE_SECRET`
2. Check Laravel logs for errors
3. Verify `APP_URL` matches your actual URL
4. Try manual sync: `php artisan kawhe:sync-subscriptions {user_id}`
5. Check Stripe Dashboard → Customers → verify customer exists
6. Check Stripe Dashboard → Subscriptions → verify subscription exists

##### Webhook not working

1. Verify Stripe CLI is running (if testing locally)
2. Check webhook URL is correct: `http://localhost:8000/stripe/webhook`
3. Verify `STRIPE_WEBHOOK_SECRET` matches Stripe CLI output
4. Check `bootstrap/app.php` excludes webhook from CSRF
5. Check Laravel logs for webhook processing errors

##### CSRF errors

1. Verify `bootstrap/app.php` has webhook excluded:
   ```php
   $middleware->validateCsrfTokens(except: ['stripe/webhook']);
   ```
2. Clear config cache: `php artisan config:clear`
---

## STRIPE_DEPLOYMENT.md
<a id="appendix-stripedeploymentmd"></a>

**Source file**: `STRIPE_DEPLOYMENT.md`

### Stripe Subscription Sync - Production Deployment Guide

This guide covers deploying the Stripe subscription sync fixes to DigitalOcean.

#### Pre-Deployment Checklist

- [ ] All local tests pass (see `STRIPE_SYNC_TEST_CHECKLIST.md`)
- [ ] Code committed and pushed to Git
- [ ] Stripe account has live API keys (not test keys)
- [ ] Subscription price created in Stripe Dashboard (live mode)
- [ ] Webhook endpoint URL ready (e.g., `https://yourdomain.com/stripe/webhook`)

#### Server Deployment Steps

##### 1. SSH into DigitalOcean Server

```bash
ssh user@your-server-ip
cd /path/to/kawhe-loyalty
```

##### 2. Pull Latest Code

```bash
git pull origin main
### Or your branch name
```

##### 3. Install Dependencies

```bash
### PHP dependencies
composer install --no-dev --optimize-autoloader

### Frontend assets (if changed)
npm ci
npm run build
```

##### 4. Run Migrations

```bash
php artisan migrate --force
```

This ensures all Cashier tables exist.

##### 5. Clear and Cache Configuration ⚠️ CRITICAL

**IMPORTANT:** The new `price_id` config key must be cached. If you skip this step, `STRIPE_PRICE_ID` will show as "Not set" even if it's in your `.env` file.

```bash
### Clear old config (removes cached config that doesn't have price_id)
php artisan config:clear
php artisan route:clear
php artisan view:clear

### Cache for production (includes new price_id from config/cashier.php)
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

**Verify config is cached correctly:**
```bash
php artisan tinker
>>> config('cashier.price_id')
### Should return your STRIPE_PRICE_ID value, not null
```

##### 6. Update Environment Variables

Edit `.env` file on server:

```env
### Stripe Live Keys (NOT test keys)
STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
STRIPE_PRICE_ID=price_...  # Live price ID from Stripe Dashboard
STRIPE_WEBHOOK_SECRET=whsec_...  # From Stripe Dashboard webhook (see below)

### App URL (your production domain)
APP_URL=https://yourdomain.com
APP_ENV=production
```

**⚠️ After updating .env, you MUST clear and recache config:**
```bash
php artisan config:clear
php artisan config:cache
```

This is critical because the new `price_id` config key must be in the cached config.

##### 7. Restart PHP-FPM (if applicable)

```bash
### For PHP-FPM
sudo systemctl restart php8.2-fpm
### Or
sudo service php-fpm restart

### For Nginx
sudo systemctl restart nginx
```

##### 8. Restart Queue Workers (if using queues)

```bash
### If using Supervisor
sudo supervisorctl restart kawhe-queue-worker:*

### If using systemd
sudo systemctl restart kawhe-queue-worker

### Or manually
php artisan queue:restart
```

##### 9. Verify Application

```bash
### Check application is running
curl https://yourdomain.com/up

### Check routes are cached
php artisan route:list | grep billing
php artisan route:list | grep stripe
```

#### Stripe Dashboard Configuration

##### 1. Create/Update Webhook Endpoint

1. Go to [Stripe Dashboard](https://dashboard.stripe.com) → **Developers** → **Webhooks**
2. Click **Add endpoint** (or edit existing)
3. Set **Endpoint URL**: `https://yourdomain.com/stripe/webhook`
4. Select **Events to send**:
   - `checkout.session.completed`
   - `customer.subscription.created`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
   - `checkout.session.async_payment_succeeded` (if using Klarna)
   - `checkout.session.async_payment_failed` (if using Klarna)
5. Click **Add endpoint**
6. **Copy the Signing secret** (starts with `whsec_...`)
7. Add to server `.env` as `STRIPE_WEBHOOK_SECRET`

##### 2. Test Webhook

1. In Stripe Dashboard → **Developers** → **Webhooks**
2. Find your webhook endpoint
3. Click **Send test webhook**
4. Select event: `checkout.session.completed`
5. Click **Send test webhook**
6. Check server logs: `tail -f storage/logs/laravel.log`
7. Verify webhook is received and processed (200 response)

##### 3. Verify Subscription Price

1. Go to **Products** → Find your Pro Plan product
2. Verify **Price ID** matches `STRIPE_PRICE_ID` in `.env`
3. Verify it's in **Live mode** (not test mode)

#### Post-Deployment Verification

##### 1. Test Subscription Flow

- [ ] Go to `https://yourdomain.com/billing`
- [ ] Click "Upgrade to Pro"
- [ ] Complete payment with real card (or test card in test mode)
- [ ] Verify redirect to success page with `session_id`
- [ ] Verify subscription syncs and dashboard shows "Pro Plan Active"

##### 2. Check Logs

```bash
### Watch logs in real-time
tail -f storage/logs/laravel.log

### Look for:
### - "Checkout session retrieved"
### - "Subscription synced after checkout"
### - Webhook processing logs
### - Any errors
```

##### 3. Verify Database

```bash
### Connect to database
php artisan tinker

### Check subscription
$user = User::find({user_id});
$user->subscription('default');
$user->subscribed('default');  // Should return true
```

##### 4. Functional Tests

- [ ] Stamping works: Scanner → scan card → verify stamp added
- [ ] Redeeming works: Redeem reward → verify it works
- [ ] Reverb works: Real-time updates still function
- [ ] Store management works: Create/edit stores
- [ ] Customer join works: New customers can join (unlimited for Pro)

#### Troubleshooting

##### Subscription Not Syncing After Payment

1. **Check success URL includes session_id**:
   - Payment should redirect to `/billing/success?session_id=cs_...`
   - If missing, check `APP_URL` in `.env`

2. **Check Stripe API keys**:
   - Verify `STRIPE_SECRET` is correct (live key for production)
   - Test with: `php artisan tinker` → `\Stripe\Stripe::setApiKey(config('cashier.secret'));`

3. **Check logs for errors**:
   ```bash
   tail -f storage/logs/laravel.log | grep -i "stripe\|subscription\|checkout"
   ```

4. **Manual sync**:
   ```bash
   php artisan kawhe:sync-subscriptions {user_id}
   ```

##### Webhook Not Receiving Events

1. **Verify webhook URL is accessible**:
   ```bash
   curl -X POST https://yourdomain.com/stripe/webhook
   # Should return 200 or 400 (not 404)
   ```

2. **Check CSRF exclusion**:
   - Verify `bootstrap/app.php` has: `$middleware->validateCsrfTokens(except: ['stripe/webhook']);`
   - Clear config cache: `php artisan config:clear && php artisan config:cache`

3. **Check webhook secret**:
   - Verify `STRIPE_WEBHOOK_SECRET` matches Stripe Dashboard
   - Clear config cache after updating

4. **Check Stripe Dashboard**:
   - Go to **Developers** → **Webhooks**
   - Click on your endpoint
   - Check **Recent events** tab
   - Verify events are being sent
   - Check for failed deliveries

##### CSRF Errors on Webhook

1. **Verify exclusion in bootstrap/app.php**:
   ```php
   $middleware->validateCsrfTokens(except: ['stripe/webhook']);
   ```

2. **Clear all caches**:
   ```bash
   php artisan config:clear
   php artisan route:clear
   php artisan cache:clear
   php artisan config:cache
   php artisan route:cache
   ```

##### Subscription Shows But Dashboard Still Shows Free Plan

1. **Clear application cache**:
   ```bash
   php artisan cache:clear
   ```

2. **Check UsageService logic**:
   - Verify `isSubscribed()` method checks subscription status correctly
   - Check `subscriptions` table has correct `stripe_status`

3. **Manual refresh**:
   - Go to `/billing?refresh=1`
   - Or use: `php artisan kawhe:sync-subscriptions {user_id}`

#### Monitoring

##### Recommended Monitoring

1. **Log Monitoring**:
   - Set up log rotation: `logrotate` for `storage/logs/laravel.log`
   - Monitor for Stripe/webhook errors

2. **Queue Monitoring** (if using queues):
   - Monitor queue:work process
   - Set up alerts for failed jobs

3. **Stripe Dashboard**:
   - Monitor webhook delivery success rate
   - Set up alerts for failed webhooks

#### Rollback Plan

If issues occur:

1. **Revert code**:
   ```bash
   git checkout {previous-commit}
   git pull
   composer install --no-dev
   php artisan config:clear
   php artisan config:cache
   ```

2. **Clear caches**:
   ```bash
   php artisan cache:clear
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   ```

3. **Restart services**:
   ```bash
   sudo systemctl restart php-fpm
   sudo systemctl restart nginx
   ```

#### Support Contacts

- **Stripe Support**: https://support.stripe.com
- **Laravel Cashier Docs**: https://laravel.com/docs/cashier
- **Server Issues**: Contact your DigitalOcean support
---

## GRANDFATHERING_IMPLEMENTATION.md
<a id="appendix-grandfatheringimplementationmd"></a>

**Source file**: `GRANDFATHERING_IMPLEMENTATION.md`

### Grandfathering Implementation - Subscription Cancellation Handling

#### Overview

This document describes the implementation of **grandfathering** for loyalty cards when a merchant cancels their Pro subscription. Cards created during the Pro subscription period remain active (grandfathered), while new card creation is limited to the free plan limit (50 cards).

#### Implementation Details

##### Core Logic

1. **Grandfathered Cards**: Cards created **before** subscription cancellation (`ends_at` date) remain active forever
2. **Non-Grandfathered Cards**: Cards created **after** subscription cancellation count toward the 50-card free limit
3. **Active Subscription**: All cards work, unlimited creation
4. **Cancelled Subscription**: Grandfathered cards work, but new creation limited to 50 non-grandfathered cards

##### Key Changes

#### 1. `UsageService` Updates

**New Methods:**
- `cardsCountForUser($user, $includeGrandfathered = true)`: Counts cards, optionally excluding grandfathered ones
- `grandfatheredCardsCount($user)`: Returns count of grandfathered cards

**Updated Methods:**
- `canCreateCard($user)`: Now checks only non-grandfathered cards against the limit
- `getUsageStats($user)`: Returns additional stats:
  - `non_grandfathered_count`: Cards created after cancellation
  - `grandfathered_count`: Cards created before cancellation
  - `has_cancelled_subscription`: Boolean flag

**Logic:**
```php
// If subscription cancelled (has ends_at), only count cards created AFTER ends_at
if ($subscription && $subscription->ends_at) {
    $query->where('created_at', '>=', $subscription->ends_at);
}
```

#### 2. UI Updates

**Dashboard (`dashboard.blade.php`):**
- Shows grandfathered count in card display
- Displays warning banner for cancelled subscriptions with grandfathered cards
- Progress bar uses non-grandfathered count

**Billing Page (`billing/index.blade.php`):**
- Shows grandfathered count
- Usage stats reflect non-grandfathered cards
- Clear messaging about grandfathered cards

**Profile Page (`profile/partials/subscription-details.blade.php`):**
- Shows grandfathered count
- Displays info message about grandfathered cards
- Limit checks use non-grandfathered count

#### 3. JoinController Updates

- Updated logging to include grandfathered vs non-grandfathered counts
- Limit enforcement uses `canCreateCard()` which respects grandfathering

##### Webhook Handling

Laravel Cashier's `WebhookController` automatically handles subscription cancellation:

1. **`customer.subscription.updated`**: Updates subscription status and `ends_at` when cancelled
2. **`customer.subscription.deleted`**: Marks subscription as deleted

The `ends_at` field is automatically set by Cashier when:
- Subscription is cancelled (immediate or at period end)
- Subscription expires

Our `isSubscribed()` method correctly returns `false` for cancelled subscriptions (checks for 'active' or 'trialing' status only).

##### Example Scenarios

#### Scenario 1: Merchant with 100 cards cancels subscription
- **Before cancellation**: 100 cards, all active, unlimited creation
- **After cancellation**: 
  - 100 cards remain active (all grandfathered)
  - Cannot create new cards (non-grandfathered count = 0, but limit is 50)
  - Wait, this is wrong... Let me recalculate

Actually, if they have 100 cards and cancel:
- All 100 cards created BEFORE cancellation → all grandfathered
- Non-grandfathered count = 0
- Can create up to 50 new cards (0 < 50)

#### Scenario 2: Merchant cancels, then creates 60 new cards
- **After cancellation**: 0 grandfathered, 0 non-grandfathered
- **Creates 50 cards**: 0 grandfathered, 50 non-grandfathered (at limit)
- **Tries to create 51st**: Blocked (50 >= 50)
- **Grandfathered cards from before**: Still work (if any existed)

#### Scenario 3: Merchant resubscribes
- All cards work (grandfathered + non-grandfathered)
- Unlimited creation restored
- Grandfathered status becomes irrelevant (all cards work)

##### Testing Checklist

- [ ] Merchant with active subscription can create unlimited cards
- [ ] Merchant cancels subscription → `ends_at` is set
- [ ] Cards created before cancellation remain active (grandfathered)
- [ ] Cards created after cancellation count toward 50 limit
- [ ] Cannot create new cards if non-grandfathered count >= 50
- [ ] Can create new cards if non-grandfathered count < 50
- [ ] Dashboard shows grandfathered count
- [ ] Billing page shows correct usage stats
- [ ] Profile page shows grandfathered info
- [ ] Resubscribing removes restrictions

##### Database Schema

No new migrations required. Uses existing fields:
- `subscriptions.ends_at`: Set by Cashier when subscription cancelled
- `loyalty_accounts.created_at`: Used to determine if card is grandfathered

##### Backward Compatibility

✅ **Fully backward compatible:**
- Existing functionality unchanged
- No breaking changes to API
- All existing cards continue to work
- Only affects new card creation logic

##### Future Considerations

1. **Resubscription**: When merchant resubscribes, all cards work (no need to track grandfathering)
2. **Multiple Cancellations**: If merchant cancels and resubscribes multiple times, only the most recent `ends_at` matters
3. **Edge Cases**: 
   - What if subscription is cancelled but then reactivated before `ends_at`? → `isSubscribed()` returns true, all cards work
   - What if `ends_at` is in the future? → Cards created now are grandfathered (created before ends_at)

#### Files Modified

1. `app/Services/Billing/UsageService.php` - Core grandfathering logic
2. `app/Http/Controllers/JoinController.php` - Updated logging
3. `resources/views/dashboard.blade.php` - UI updates
4. `resources/views/billing/index.blade.php` - UI updates
5. `resources/views/profile/partials/subscription-details.blade.php` - UI updates

#### Notes

- Grandfathering is automatic based on `created_at` vs `ends_at` comparison
- No manual intervention needed
- Webhook handling is automatic via Cashier
- All existing functionality preserved
---

## SENDGRID_SETUP.md
<a id="appendix-sendgridsetupmd"></a>

**Source file**: `SENDGRID_SETUP.md`

### SendGrid SMTP Setup Guide

#### ✅ What's Already Configured

1. **Mail Configuration** - Laravel mail config is set up for SMTP
2. **Email Mailable** - `VerifyCustomerEmail` class is ready
3. **Queue System** - Emails are queued (using `Mail::queue()`)
4. **Jobs Table Migration** - Database queue tables exist

#### 🔧 What You Need to Complete

##### Step 1: Get Your SendGrid API Key

1. Sign up/Login to [SendGrid](https://sendgrid.com/)
2. Go to **Settings** → **API Keys**
3. Click **Create API Key**
4. Name it (e.g., "Kawhe Loyalty App")
5. Select **Full Access** or **Restricted Access** (with Mail Send permissions)
6. Copy the API key (you'll only see it once!)

##### Step 2: Verify Your Sender Email

1. In SendGrid, go to **Settings** → **Sender Authentication**
2. Click **Verify a Single Sender** (for testing) or **Authenticate Your Domain** (for production)
3. Follow the verification steps
4. Note the verified email address (e.g., `noreply@yourdomain.com`)

##### Step 3: Update Your `.env` File

Add these lines to your `.env` file:

```env
### Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=SG.your_actual_sendgrid_api_key_here
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Kawhe Loyalty"

### Queue Configuration (for processing emails)
QUEUE_CONNECTION=database
```

**Important:**
- Replace `SG.your_actual_sendgrid_api_key_here` with your actual SendGrid API key
- Replace `noreply@yourdomain.com` with your verified sender email
- The `MAIL_USERNAME` must be exactly `apikey` (this is SendGrid's requirement)

##### Step 4: Run Database Migrations (if not done)

```bash
php artisan migrate
```

This creates the `jobs` and `failed_jobs` tables needed for queued emails.

##### Step 5: Start the Queue Worker

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

#### 🧪 Testing the Setup

##### Test 1: Send a Test Email

You can test by:
1. Going to a customer card page
2. Clicking "Verify Email" 
3. Check the queue worker terminal for processing
4. Check your email inbox

##### Test 2: Check Queue Status

```bash
### See pending jobs
php artisan queue:monitor

### See failed jobs
php artisan queue:failed
```

##### Test 3: Check Logs

If emails aren't sending, check:
```bash
### Laravel logs
tail -f storage/logs/laravel.log

### Or use Pail
php artisan pail
```

---

#### 🚨 Troubleshooting

##### Emails Not Sending?

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

##### Queue Jobs Failing?

```bash
### See failed jobs
php artisan queue:failed

### Retry failed jobs
php artisan queue:retry all

### Clear failed jobs
php artisan queue:flush
```

---

#### 📝 Production Recommendations

1. **Use Supervisor** (Linux) or **Laravel Horizon** for queue management
2. **Set up email monitoring** in SendGrid
3. **Use domain authentication** instead of single sender verification
4. **Set up webhooks** for bounce/spam handling
5. **Monitor queue:failed** table regularly

---

#### 🔗 Quick Reference

- **SendGrid Dashboard:** https://app.sendgrid.com/
- **API Keys:** https://app.sendgrid.com/settings/api_keys
- **Sender Verification:** https://app.sendgrid.com/settings/sender_auth
- **Activity Feed:** https://app.sendgrid.com/activity
---

## SENDGRID_TROUBLESHOOTING.md
<a id="appendix-sendgridtroubleshootingmd"></a>

**Source file**: `SENDGRID_TROUBLESHOOTING.md`

### SendGrid Troubleshooting Guide

#### Current Issue: "Maximum credits exceeded"

Your SendGrid account has reached its free tier limit (100 emails/day for free accounts).

#### Quick Fix: Use Log Driver (Temporary)

To test the email verification flow without SendGrid, switch to log driver:

##### Option 1: Update .env file

```env
MAIL_MAILER=log
```

Then clear config cache:
```bash
php artisan config:clear
```

**Note**: Emails will be written to `storage/logs/laravel.log` instead of being sent. You can view the email content there.

##### Option 2: Test Email Content

To see what the email would look like, check the log file:
```bash
tail -f storage/logs/laravel.log
```

Then trigger a verification email and look for the email content in the logs.

#### Fix SendGrid Account

##### Check Your SendGrid Account

1. **Login to SendGrid**: https://app.sendgrid.com/
2. **Check Usage**: 
   - Go to **Activity** → **Overview**
   - Check your daily/monthly email count
   - Free tier: 100 emails/day

##### Solutions

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

#### Verify Email Configuration

Check your current mail configuration:
```bash
php artisan tinker
>>> config('mail.default')
>>> config('mail.mailers.smtp.host')
>>> config('mail.from.address')
```

#### Test Email Sending

##### Test with Log Driver
```bash
### Set MAIL_MAILER=log in .env
php artisan config:clear

### Send test email
php artisan tinker
>>> Mail::raw('Test email', function($message) {
...     $message->to('your-email@example.com')->subject('Test');
... });

### Check logs
tail -f storage/logs/laravel.log
```

##### Test with SendGrid (after fixing account)
```bash
### Make sure MAIL_MAILER=smtp in .env
php artisan config:clear

### Test send
php artisan tinker
>>> Mail::raw('Test email', function($message) {
...     $message->to('your-email@example.com')->subject('Test');
... });
```

#### Current Status

- ✅ Email verification code is working
- ✅ Error handling is in place
- ⚠️ SendGrid account needs attention (credits exceeded)
- ✅ Can use log driver for testing

#### Next Steps

1. **Immediate**: Switch to `MAIL_MAILER=log` to test functionality
2. **Short-term**: Check SendGrid account and wait for reset or upgrade
3. **Long-term**: Consider domain authentication or alternative service

#### Monitoring

Check email sending status:
```bash
### View recent email attempts
tail -50 storage/logs/laravel.log | grep -i "mail\|email\|sendgrid"

### Check for errors
tail -50 storage/logs/laravel.log | grep -i "error\|exception\|failed"
```
---

## QUICK_FIX_SENDGRID.md
<a id="appendix-quickfixsendgridmd"></a>

**Source file**: `QUICK_FIX_SENDGRID.md`

### Quick Fix: SendGrid Credits Exceeded

#### ✅ What I Just Did

I've temporarily switched your email driver to `log` mode so you can test the email verification functionality.

**Current Status**:
- ✅ Email verification code is working
- ✅ Emails will be logged to `storage/logs/laravel.log`
- ✅ You can test the full flow without SendGrid

#### How to View Emails

When you trigger a verification email, it will be written to the log file. To view it:

```bash
### Watch the log file in real-time
tail -f storage/logs/laravel.log

### Or view the last 100 lines
tail -100 storage/logs/laravel.log
```

The email content (HTML) will be in the log file, and you can see the verification link.

#### Fix SendGrid (When Ready)

##### Option 1: Wait for Daily Reset
- SendGrid free tier resets daily at midnight UTC
- Check your account: https://app.sendgrid.com/
- Wait until tomorrow to resume sending

##### Option 2: Upgrade SendGrid
- Go to SendGrid → Settings → Billing
- Upgrade to Essential Plan ($19.95/month for 50,000 emails)

##### Option 3: Switch Back to SendGrid
Once your SendGrid account is fixed:

```bash
### Edit .env file
MAIL_MAILER=smtp

### Clear config cache
php artisan config:clear
```

#### Test Email Verification Now

1. Go to a customer card page
2. Click "Verify Email" button
3. Check `storage/logs/laravel.log` for the email content
4. Copy the verification link from the log
5. Paste it in your browser to test verification

The verification flow will work - you just need to get the link from the log file instead of your inbox.

#### Alternative: Use Different Email Service

If you want to use a different service, see `SENDGRID_TROUBLESHOOTING.md` for options like Mailgun, Postmark, or Amazon SES.
---

## PASSWORD_RESET_SETUP.md
<a id="appendix-passwordresetsetupmd"></a>

**Source file**: `PASSWORD_RESET_SETUP.md`

### Password Reset Email Setup with SendGrid

#### Overview

The password reset functionality uses Laravel's built-in password reset system, which sends emails via the configured mail driver. This guide ensures password reset emails work with SendGrid in production.

#### Current Implementation

The password reset flow uses:
- `PasswordResetLinkController` - Handles forgot password requests
- Laravel's `Password::sendResetLink()` - Sends reset link via email
- Default `ResetPassword` notification - Uses configured mail driver

#### Configuration Required

##### 1. Production `.env` File

Ensure these settings are configured in your production `.env`:

```env
### Mail Configuration (REQUIRED for password reset)
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=SG.your_actual_sendgrid_api_key_here
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Kawhe Loyalty"

### Queue Configuration (optional, but recommended)
QUEUE_CONNECTION=database
```

**Important Notes:**
- `MAIL_USERNAME` must be exactly `apikey` (SendGrid requirement)
- `MAIL_PASSWORD` should be your full SendGrid API key (starts with `SG.`)
- `MAIL_FROM_ADDRESS` must be a verified sender in SendGrid
- `MAIL_MAILER` must be `smtp` (not `log`) for production

##### 2. Verify SendGrid Sender

1. Login to [SendGrid Dashboard](https://app.sendgrid.com/)
2. Go to **Settings** → **Sender Authentication**
3. Verify your sender email (or domain)
4. Use the verified email as `MAIL_FROM_ADDRESS`

##### 3. Queue Worker (If Using Queues)

If `QUEUE_CONNECTION=database`, ensure queue worker is running:

```bash
php artisan queue:work --tries=3
```

Or use Supervisor/systemd to keep it running.

#### Testing Password Reset

##### Local Testing

1. Set `MAIL_MAILER=log` in `.env` for local testing
2. Check `storage/logs/laravel.log` for password reset emails
3. Or use Mailtrap/MailHog for local SMTP testing

##### Production Testing

1. Ensure SendGrid credentials are set in `.env`
2. Clear config cache: `php artisan config:clear && php artisan config:cache`
3. Test password reset flow:
   - Go to `/forgot-password`
   - Enter a valid user email
   - Check email inbox for reset link
   - Check `storage/logs/laravel.log` for any errors

#### Troubleshooting

##### Password Reset Link Not Received

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

##### Common Issues

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

#### Deployment Checklist

- [ ] SendGrid API key added to production `.env`
- [ ] `MAIL_MAILER=smtp` in production `.env`
- [ ] `MAIL_FROM_ADDRESS` is verified in SendGrid
- [ ] Config cache cleared and rebuilt: `php artisan config:clear && php artisan config:cache`
- [ ] Queue worker running (if using queues)
- [ ] Test password reset flow in production
- [ ] Check logs for any errors

#### Code Changes Made

1. **Added logging to `PasswordResetLinkController`:**
   - Logs successful password reset link sends
   - Logs failures with mail configuration details
   - Helps debug email delivery issues

2. **No changes needed to:**
   - User model (uses default `ResetPassword` notification)
   - Routes (already configured)
   - Views (already configured)

#### Additional Notes

- Password reset emails use Laravel's default `ResetPassword` notification
- Emails are sent synchronously (unless queue is configured)
- Reset links expire after 60 minutes (Laravel default)
- Reset tokens are one-time use only
---

## EMAIL_VERIFICATION_FIX.md
<a id="appendix-emailverificationfixmd"></a>

**Source file**: `EMAIL_VERIFICATION_FIX.md`

### Email Verification Fix

#### Problem
Verification emails were not being sent because:
1. Emails were queued but queue worker wasn't running
2. The Mailable class implemented `ShouldQueue` which forced queuing
3. No fallback mechanism if queue failed

#### Solution

##### Changes Made

1. **Removed `ShouldQueue` from Mailable** (`app/Mail/VerifyCustomerEmail.php`)
   - Removed `implements ShouldQueue`
   - Now the mailable can be sent synchronously or queued based on controller logic

2. **Updated Controller Logic** (`app/Http/Controllers/CustomerEmailVerificationController.php`)
   - Sends synchronously in `local` environment or when queue is `sync`
   - Queues in production environments
   - Added comprehensive error handling and logging

3. **Added Error Handling**
   - Catches exceptions during email sending
   - Logs errors for debugging
   - Returns user-friendly error messages

#### How It Works Now

##### Development (Local Environment)
- Emails are sent **synchronously** (immediately)
- No queue worker needed
- Errors are logged to `storage/logs/laravel.log`

##### Production
- Emails are **queued** for background processing
- Requires queue worker running: `php artisan queue:work`
- Failed jobs can be retried: `php artisan queue:retry all`

#### Testing

##### Test Email Sending

1. **Check Mail Configuration**:
   ```bash
   php artisan tinker
   >>> config('mail.default')
   >>> config('mail.mailers.smtp.host')
   >>> config('mail.from.address')
   ```

2. **Test Sending Email**:
   - Go to a customer card page: `/c/{public_token}`
   - Click "Verify Email" button
   - Check logs: `tail -f storage/logs/laravel.log`
   - Check your email inbox

3. **Check Queue Status** (if using queues):
   ```bash
   php artisan queue:failed
   php artisan queue:work
   ```

##### Verify SendGrid Configuration

Make sure your `.env` has:
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=SG.your_actual_sendgrid_api_key
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Kawhe Loyalty"
```

#### Troubleshooting

##### Emails Still Not Sending?

1. **Check Logs**:
   ```bash
   tail -f storage/logs/laravel.log | grep -i "email\|mail\|verify"
   ```

2. **Test SendGrid Connection**:
   ```bash
   php artisan tinker
   >>> Mail::raw('Test email', function($message) {
   ...     $message->to('your-email@example.com')->subject('Test');
   ... });
   ```

3. **Check Environment**:
   - If `APP_ENV=local`, emails send synchronously
   - If `APP_ENV=production`, emails are queued (need queue worker)

4. **Verify SendGrid**:
   - Check SendGrid dashboard for activity
   - Verify sender email is authenticated
   - Check API key permissions

##### Queue Worker Not Running?

If you're in production and emails are queued:
```bash
### Start queue worker
php artisan queue:work

### Or use supervisor for production
### See: https://laravel.com/docs/queues#supervisor-configuration
```

#### Next Steps

1. **Test the fix**: Try sending a verification email from a customer card
2. **Check logs**: Verify emails are being sent/logged
3. **Monitor**: Check SendGrid dashboard for email delivery
4. **Production**: Set up queue worker if using production environment
---

## EMAIL_VERIFICATION_TEST_CHECKLIST.md
<a id="appendix-emailverificationtestchecklistmd"></a>

**Source file**: `EMAIL_VERIFICATION_TEST_CHECKLIST.md`

### Email Verification Test Checklist

This document provides step-by-step test procedures to verify email verification works correctly in both local and production environments.

#### Prerequisites

- Application deployed and accessible
- Database configured and migrated
- Queue worker running (for production)
- SendGrid account configured (for production)

#### Test 1: Local Test with Log Driver

**Purpose:** Verify email queuing works without external dependencies.

##### Steps:

1. **Configure environment:**
   ```bash
   # In .env
   MAIL_MAILER=log
   QUEUE_CONNECTION=database
   ```

2. **Start queue worker:**
   ```bash
   php artisan queue:work
   ```

3. **Create a customer:**
   - Navigate to a store join link: `/join/{slug}?t={token}`
   - Enter name and email
   - Submit form
   - **Expected:** Customer created, redirected to card page

4. **Request verification email:**
   - On card page, click "Verify Email" button
   - **Expected:** Success message "Verification email sent! Please check your inbox."

5. **Check queue:**
   ```bash
   # In queue worker terminal, you should see:
   # Processing: App\Mail\VerifyCustomerEmail
   ```

6. **Check log file:**
   ```bash
   tail -f storage/logs/laravel.log
   # Should see email content logged
   ```

7. **Extract verification link:**
   - Find the verification URL in the log file
   - Copy the full URL

8. **Verify email:**
   - Open the verification URL in browser
   - **Expected:** Redirected to card page with "Email verified successfully!" message

9. **Test redemption:**
   - Earn enough stamps to unlock a reward
   - Try to redeem
   - **Expected:** Redemption succeeds (email is verified)

10. **Test unverified redemption:**
    - Create a new customer (different email)
    - Earn stamps to unlock reward
    - Try to redeem without verifying email
    - **Expected:** Error message "You must verify your email address before you can redeem rewards"

##### Success Criteria:
- ✅ Customer creation never fails due to email issues
- ✅ Verification email is queued successfully
- ✅ Verification link works and redirects correctly
- ✅ Redemption only works after email verification
- ✅ Unverified customers cannot redeem

---

#### Test 2: Production Test with SendGrid (Normal Operation)

**Purpose:** Verify email sending works with SendGrid in production.

##### Steps:

1. **Configure environment:**
   ```bash
   # In .env
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.sendgrid.net
   MAIL_PORT=587
   MAIL_USERNAME=apikey
   MAIL_PASSWORD=your_sendgrid_api_key
   MAIL_ENCRYPTION=tls
   MAIL_FROM_ADDRESS=noreply@yourdomain.com
   MAIL_FROM_NAME="Kawhe Loyalty"
   QUEUE_CONNECTION=database
   APP_URL=https://testing.kawhe.shop
   APP_ENV=production
   ```

2. **Ensure queue worker is running:**
   ```bash
   # Check status
   sudo supervisorctl status kawhe-queue-worker:*
   # OR
   sudo systemctl status kawhe-queue-worker
   
   # If not running, start it
   sudo supervisorctl start kawhe-queue-worker:*
   # OR
   sudo systemctl start kawhe-queue-worker
   ```

3. **Test email command:**
   ```bash
   php artisan kawhe:mail-test your-email@example.com
   # Expected: "✓ Email queued successfully!"
   ```

4. **Process queue:**
   ```bash
   php artisan queue:work --once
   # Expected: Email sent successfully
   ```

5. **Check email inbox:**
   - Open your email inbox
   - **Expected:** Test verification email received

6. **Create customer and verify:**
   - Follow steps from Test 1 (steps 3-9)
   - **Expected:** Real email received, verification link works

##### Success Criteria:
- ✅ Test email command works
- ✅ Real verification emails are sent via SendGrid
- ✅ Verification links work correctly
- ✅ Emails arrive in inbox (not spam)

---

#### Test 3: SendGrid Down / Credits Exceeded

**Purpose:** Verify application continues working when SendGrid is unavailable.

##### Steps:

1. **Simulate SendGrid failure:**
   ```bash
   # Option A: Use invalid API key
   # In .env, set:
   MAIL_PASSWORD=invalid_key
   
   # Option B: Temporarily block SendGrid (if possible)
   # Or use SendGrid dashboard to disable account
   ```

2. **Clear config cache:**
   ```bash
   php artisan config:clear
   php artisan config:cache
   ```

3. **Create customer:**
   - Navigate to join link
   - Enter details and submit
   - **Expected:** Customer created successfully (no error)

4. **Request verification email:**
   - Click "Verify Email" button
   - **Expected:** Success message (email queued, not sent yet)

5. **Check queue:**
   ```bash
   php artisan queue:work --once
   # Expected: Job fails, logged to failed_jobs table
   ```

6. **Check failed jobs:**
   ```bash
   php artisan queue:failed
   # Expected: Shows failed job with error message
   ```

7. **Check logs:**
   ```bash
   tail -f storage/logs/laravel.log | grep -i mail
   # Expected: Error logged about SendGrid failure
   ```

8. **Verify customer can still use card:**
   - Try to stamp the card
   - **Expected:** Stamping works normally

9. **Try to redeem (unverified):**
   - Earn stamps to unlock reward
   - Try to redeem
   - **Expected:** Error "You must verify your email address" (expected behavior)

10. **Fix SendGrid and retry:**
    ```bash
    # Restore valid API key in .env
    php artisan config:clear
    php artisan config:cache
    
    # Retry failed job
    php artisan queue:retry all
    
    # Process queue
    php artisan queue:work --once
    # Expected: Email sent successfully
    ```

##### Success Criteria:
- ✅ Customer creation never fails
- ✅ Verification request succeeds (email queued)
- ✅ Failed jobs are logged and can be retried
- ✅ Card functionality (stamping) works normally
- ✅ Failed emails can be retried after fixing SendGrid

---

#### Test 4: Queue Worker Stopped

**Purpose:** Verify queued emails accumulate when worker is stopped.

##### Steps:

1. **Stop queue worker:**
   ```bash
   sudo supervisorctl stop kawhe-queue-worker:*
   # OR
   sudo systemctl stop kawhe-queue-worker
   ```

2. **Create multiple customers and request verification:**
   - Create 3-5 customers
   - Request verification email for each
   - **Expected:** All requests succeed (emails queued)

3. **Check queue:**
   ```bash
   php artisan queue:monitor
   # Expected: Shows pending jobs in queue
   ```

4. **Check database:**
   ```bash
   php artisan tinker
   >>> DB::table('jobs')->count();
   # Expected: Number of queued emails
   ```

5. **Verify customer functionality:**
   - Try to stamp cards
   - **Expected:** All functionality works normally

6. **Start queue worker:**
   ```bash
   sudo supervisorctl start kawhe-queue-worker:*
   # OR
   sudo systemctl start kawhe-queue-worker
   ```

7. **Monitor queue processing:**
   ```bash
   tail -f storage/logs/queue-worker.log
   # OR
   tail -f storage/logs/laravel.log
   # Expected: Jobs being processed
   ```

8. **Check queue again:**
   ```bash
   php artisan queue:monitor
   # Expected: Queue empty (all jobs processed)
   ```

9. **Check emails:**
   - Check inboxes for all test emails
   - **Expected:** All verification emails received

##### Success Criteria:
- ✅ Customer creation works when queue worker is stopped
- ✅ Emails accumulate in queue
- ✅ Queue worker processes all accumulated jobs when started
- ✅ All emails are sent after worker starts

---

#### Test 5: Verification Link Expiry

**Purpose:** Verify expired tokens are handled gracefully.

##### Steps:

1. **Request verification email:**
   - Create customer and request verification
   - **Expected:** Email received

2. **Wait for token expiry:**
   - Tokens expire after 60 minutes
   - For testing, manually expire in database:
   ```bash
   php artisan tinker
   >>> $customer = App\Models\Customer::where('email', 'test@example.com')->first();
   >>> $customer->update(['email_verification_expires_at' => now()->subMinute()]);
   ```

3. **Click expired verification link:**
   - Open the verification URL
   - **Expected:** Redirected with error "Invalid or expired verification token"
   - **Expected:** No 500 error, friendly error message

4. **Request new verification email:**
   - Click "Verify Email" again
   - **Expected:** New email sent with new token

5. **Verify with new token:**
   - Click new verification link
   - **Expected:** Email verified successfully

##### Success Criteria:
- ✅ Expired tokens show friendly error (no 500)
- ✅ Users can request new verification email
- ✅ New tokens work correctly

---

#### Test 6: Multiple Rewards with Verification

**Purpose:** Verify email verification works with multiple rewards system.

##### Steps:

1. **Create and verify customer:**
   - Create customer with email
   - Verify email

2. **Earn multiple rewards:**
   - Stamp card to earn 2+ rewards (e.g., 12 stamps on 5-target card)

3. **Redeem first reward:**
   - Click "Redeem My Reward"
   - Show QR code to merchant
   - **Expected:** First reward redeemed successfully

4. **Redeem second reward:**
   - Click "Redeem My Reward" again
   - Show QR code to merchant
   - **Expected:** Second reward redeemed successfully

5. **Verify unverified customer cannot redeem:**
   - Create new unverified customer
   - Earn rewards
   - Try to redeem
   - **Expected:** Error "You must verify your email address"

##### Success Criteria:
- ✅ Verified customers can redeem multiple rewards
- ✅ Unverified customers cannot redeem
- ✅ Verification status persists across multiple redemptions

---

#### Quick Verification Commands

```bash
### Check queue status
php artisan queue:monitor

### Check failed jobs
php artisan queue:failed

### Retry all failed jobs
php artisan queue:retry all

### Test email configuration
php artisan kawhe:mail-test your-email@example.com

### Check queue worker status (supervisor)
sudo supervisorctl status kawhe-queue-worker:*

### Check queue worker status (systemd)
sudo systemctl status kawhe-queue-worker

### View logs
tail -f storage/logs/laravel.log
tail -f storage/logs/queue-worker.log
```

---

#### Common Issues and Solutions

##### Issue: Emails not sending
**Solution:**
1. Check queue worker is running
2. Check SendGrid API key is correct
3. Check SendGrid account has credits
4. Check logs: `tail -f storage/logs/laravel.log`

##### Issue: Verification links not working
**Solution:**
1. Check `APP_URL` matches production domain
2. Check `APP_ENV=production` (forces HTTPS)
3. Verify link in email matches `APP_URL`

##### Issue: Queue worker not processing
**Solution:**
1. Check worker is running: `sudo supervisorctl status`
2. Check logs for errors
3. Restart worker: `sudo supervisorctl restart kawhe-queue-worker:*`

##### Issue: Failed jobs accumulating
**Solution:**
1. Check error in logs
2. Fix underlying issue (e.g., SendGrid API key)
3. Retry jobs: `php artisan queue:retry all`
---

## TEST_EMAIL_VERIFICATION_LOCAL.md
<a id="appendix-testemailverificationlocalmd"></a>

**Source file**: `TEST_EMAIL_VERIFICATION_LOCAL.md`

### Testing Email Verification & Reward Claiming Locally

#### Current Setup ✅

- **MAIL_MAILER**: `log` (emails written to logs, not sent)
- **APP_ENV**: `local` (emails send synchronously)
- **Production**: Will use SendGrid (configured in `.env` for production)

#### Testing Flow

##### Step 1: Start the App

Make sure these are running:
```bash
### Terminal 1
php artisan serve --port=8000

### Terminal 2 (optional for real-time updates)
php artisan reverb:start
```

##### Step 2: Create Test Data

1. **Register a merchant** (if you don't have one):
   - Visit: http://localhost:8000/register
   - Create account

2. **Create a store**:
   - Visit: http://localhost:8000/merchant/stores/create
   - Set reward target to **5 stamps** (for quick testing)
   - Save the store

3. **Get join link**:
   - Visit: http://localhost:8000/merchant/stores/{store_id}/qr
   - Copy the join link

##### Step 3: Create Customer Account

1. **Open join link** in a new tab/incognito window
2. **Click "New Customer"**
3. **Enter details**:
   - Name: Test Customer
   - Email: test@example.com (or your real email)
4. **Submit** → You'll be redirected to the loyalty card

##### Step 4: Test Email Verification

1. **On the customer card page**, you should see a blue banner asking to verify email
2. **Click "Verify Email"** button
3. **Check the logs** for the verification email:
   ```bash
   tail -f storage/logs/laravel.log
   ```
4. **Look for the verification link** in the log output. It will look like:
   ```
   http://localhost:8000/verify-email/{token}?card={public_token}
   ```
5. **Copy the full URL** from the logs
6. **Paste it in your browser** to verify the email
7. **You should be redirected** back to the card page with "Email verified successfully!"

##### Step 5: Test Reward Claiming

1. **Add stamps** to reach the reward target:
   - Go to: http://localhost:8000/merchant/scanner
   - Select your store
   - Scan the customer's stamp QR code multiple times until you reach the target (e.g., 5 stamps)

2. **Check the customer card** - you should see:
   - Reward is now available
   - A redeem QR code is shown (not the lock icon)

3. **Redeem the reward**:
   - Go back to scanner
   - Scan the **redeem QR code** (starts with `REDEEM:`)
   - Should show success message

4. **Verify redemption**:
   - Check customer card - reward should show as redeemed
   - Stamps should be deducted
   - New cycle starts

#### Quick Test Script

Here's a quick way to test without waiting:

##### Option 1: Manually Verify Email (Skip Email Step)

```bash
php artisan tinker
```

Then run:
```php
$customer = \App\Models\Customer::where('email', 'test@example.com')->first();
$customer->update(['email_verified_at' => now()]);
```

Now you can test redemption immediately!

##### Option 2: Extract Verification Link from Logs

```bash
### Watch logs in real-time
tail -f storage/logs/laravel.log | grep -i "verify-email"

### Or search for the link
grep -o "http://localhost:8000/verify-email/[^ ]*" storage/logs/laravel.log | tail -1
```

#### Expected Behavior

##### ✅ Email Verification Flow
- [ ] Blue banner appears on card if email not verified
- [ ] "Verify Email" button works
- [ ] Email content appears in logs
- [ ] Verification link works when clicked
- [ ] Card updates to show email is verified
- [ ] Redeem QR appears (if reward available)

##### ✅ Reward Claiming Flow
- [ ] Stamps can be added via scanner
- [ ] Reward becomes available when target reached
- [ ] Redeem QR code appears (if email verified)
- [ ] Lock icon appears if email not verified
- [ ] Redemption works when scanning redeem QR
- [ ] Stamps are deducted after redemption
- [ ] New cycle starts

#### Troubleshooting

##### Email Not in Logs?
- Check: `storage/logs/laravel.log`
- Make sure `MAIL_MAILER=log` in `.env`
- Clear cache: `php artisan config:clear`

##### Verification Link Not Working?
- Check token hasn't expired (60 minutes)
- Make sure you copy the FULL URL including `?card=...`
- Check logs for errors

##### Can't Redeem Reward?
- Verify email is verified: Check `email_verified_at` in database
- Make sure reward is available: Check `reward_available_at` is set
- Make sure reward not already redeemed: Check `reward_redeemed_at` is null

##### Check Database Directly

```bash
php artisan tinker
```

```php
// Check customer verification
$customer = \App\Models\Customer::where('email', 'test@example.com')->first();
echo "Email verified: " . ($customer->email_verified_at ? 'Yes' : 'No') . PHP_EOL;

// Check reward status
$account = $customer->loyaltyAccounts()->first();
echo "Stamps: " . $account->stamp_count . PHP_EOL;
echo "Reward available: " . ($account->reward_available_at ? 'Yes' : 'No') . PHP_EOL;
echo "Reward redeemed: " . ($account->reward_redeemed_at ? 'Yes' : 'No') . PHP_EOL;
```

#### Production Notes

When deploying to Digital Ocean:
- Set `MAIL_MAILER=smtp` in production `.env`
- Configure SendGrid credentials
- Run `php artisan queue:work` for email processing
- Everything else works the same way!
---

## PRODUCTION_DEPLOY_CHECKLIST.md
<a id="appendix-productiondeploychecklistmd"></a>

**Source file**: `PRODUCTION_DEPLOY_CHECKLIST.md`

### Production Deploy Checklist - Stripe Config Fix

#### ⚠️ Critical: Config Cache Issue

After deploying, `STRIPE_PRICE_ID` may show as "Not set" even if it's in your `.env` file. This happens because:

1. The new `price_id` key was added to `config/cashier.php`
2. If config is cached without this key, it won't be available
3. **Solution: Always clear and recache config after deployment**

#### Quick Deploy Commands

```bash
### 1. Pull code
git pull origin main

### 2. Install dependencies
composer install --no-dev --optimize-autoloader

### 3. Run migrations (if any)
php artisan migrate --force

### 4. ⚠️ CRITICAL: Clear and recache config
php artisan config:clear
php artisan config:cache

### 5. Cache routes and views
php artisan route:cache
php artisan view:cache

### 6. Restart services
sudo systemctl restart php-fpm
sudo supervisorctl restart kawhe-queue-worker:*
```

#### Verify Config is Working

After deployment, verify the config:

```bash
php artisan tinker
```

Then run:
```php
config('cashier.price_id')
```

**Expected:** Should return your `STRIPE_PRICE_ID` value (e.g., `"price_1AbC123..."`)

**If null:** Config cache is stale. Run:
```bash
php artisan config:clear && php artisan config:cache
```

#### Check Billing Page

1. Go to `https://yourdomain.com/billing`
2. Check the debug section at bottom
3. All three should show "✅ Set":
   - STRIPE_KEY: ✅ Set
   - STRIPE_SECRET: ✅ Set
   - STRIPE_PRICE_ID: ✅ Set

If `STRIPE_PRICE_ID` shows "❌ Not set":
- Verify it's in `.env` file
- Run `php artisan config:clear && php artisan config:cache`
- Refresh the page

#### Environment Variables Required

Make sure your production `.env` has:

```env
STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
STRIPE_PRICE_ID=price_...  # ⚠️ Must be set
STRIPE_WEBHOOK_SECRET=whsec_...
APP_URL=https://yourdomain.com
```

#### Why This Happens

Laravel caches configuration files for performance. When you:
1. Add a new config key (`price_id` in `config/cashier.php`)
2. Deploy to production
3. Don't clear the old cached config

The cached config doesn't have the new key, so `config('cashier.price_id')` returns `null` even though it's in `.env`.

**Solution:** Always run `php artisan config:clear && php artisan config:cache` after deploying config changes.
---

## PRODUCTION_EMAIL_SETUP.md
<a id="appendix-productionemailsetupmd"></a>

**Source file**: `PRODUCTION_EMAIL_SETUP.md`

### Production Email Setup Guide

This guide covers setting up production-ready email verification using SendGrid with Laravel queues.

#### Prerequisites

- Laravel 11 application
- Database configured (for queue jobs table)
- SendGrid account with API key

#### Step 1: Environment Configuration

Add these to your `.env` file:

```env
### Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=your_sendgrid_api_key_here
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Kawhe Loyalty"

### Queue Configuration
QUEUE_CONNECTION=database

### App URL (must be correct for verification links)
APP_URL=https://testing.kawhe.shop
APP_ENV=production
```

**Important:** 
- `MAIL_USERNAME` must be exactly `apikey` for SendGrid
- `MAIL_PASSWORD` is your SendGrid API key (not your SendGrid password)
- `APP_URL` must match your production domain exactly

#### Step 2: Database Queue Setup

The queue uses the database driver. Ensure the jobs table exists:

```bash
php artisan migrate
```

This creates:
- `jobs` table (queued jobs)
- `job_batches` table (batch jobs)
- `failed_jobs` table (failed job tracking)

#### Step 3: Queue Worker Setup

##### Option A: Using Supervisor (Recommended for Production)

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

##### Option B: Using systemd (Alternative)

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

##### Option C: Manual (For Testing)

For testing or development, you can run the queue worker manually:

```bash
php artisan queue:work database --sleep=3 --tries=3
```

**Note:** This runs in the foreground. Use Ctrl+C to stop.

#### Step 4: Testing Email Configuration

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

#### Step 5: Monitoring

##### Check Queue Status

```bash
### View pending jobs
php artisan queue:monitor

### View failed jobs
php artisan queue:failed

### Retry failed jobs
php artisan queue:retry all
```

##### Check Logs

```bash
### View application logs
tail -f storage/logs/laravel.log

### View queue worker logs (if using supervisor)
tail -f storage/logs/queue-worker.log
```

#### Troubleshooting

##### Emails Not Sending

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

##### SendGrid Errors

If you see "Maximum credits exceeded" or "Authentication failed":

1. Check SendGrid dashboard for account status
2. Verify API key is correct in `.env`
3. Emails will be retried automatically (3 attempts with backoff)
4. Failed jobs can be retried: `php artisan queue:retry all`

##### Verification Links Not Working

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

#### Fallback to Log Driver

If SendGrid is down, you can temporarily switch to log driver for testing:

```env
MAIL_MAILER=log
```

Emails will be written to `storage/logs/laravel.log` instead of being sent.

**Important:** The application will continue to work even if email sending fails. Customer creation and card usage are not blocked by email failures.

#### After Deployment Checklist

After deploying from git, run these commands:

```bash
### 1. Install dependencies (if needed)
composer install --no-dev --optimize-autoloader

### 2. Run migrations (creates jobs table if needed)
php artisan migrate --force

### 3. Clear and cache config
php artisan config:clear
php artisan config:cache

### 4. Restart queue worker
sudo supervisorctl restart kawhe-queue-worker:*
### OR
sudo systemctl restart kawhe-queue-worker

### 5. Test email
php artisan kawhe:mail-test your-email@example.com

### 6. Process queue to send test email
php artisan queue:work --once
```

#### Security Notes

- Never commit `.env` file to git
- Store SendGrid API key securely
- Use environment variables for all sensitive data
- Rotate API keys periodically
---

## PRODUCTION_SERVICE_RESTART.md
<a id="appendix-productionservicerestartmd"></a>

**Source file**: `PRODUCTION_SERVICE_RESTART.md`

### Production Service Restart Guide

#### Finding the Correct PHP-FPM Service Name

The service name varies by PHP version and system. Try these commands to find yours:

##### 1. Check Available PHP-FPM Services

```bash
### List all PHP-FPM services
systemctl list-units | grep php
### OR
systemctl list-units | grep fpm

### Check what's running
systemctl status php*-fpm*
```

##### 2. Common Service Names

Try these (replace X.X with your PHP version):

```bash
### PHP 8.2
sudo systemctl restart php8.2-fpm

### PHP 8.3
sudo systemctl restart php8.3-fpm

### PHP 8.1
sudo systemctl restart php8.1-fpm

### Generic (some systems)
sudo systemctl restart php-fpm
sudo service php-fpm restart
```

##### 3. Find Your PHP Version

```bash
php -v
```

This will show your PHP version (e.g., PHP 8.2.15), then use that version in the service name.

##### 4. Alternative: Restart Web Server Instead

If you can't find php-fpm, restarting your web server (Nginx/Apache) will also reload PHP:

**For Nginx:**
```bash
sudo systemctl restart nginx
### OR
sudo service nginx restart
```

**For Apache:**
```bash
sudo systemctl restart apache2
### OR (on some systems)
sudo systemctl restart httpd
### OR
sudo service apache2 restart
```

##### 5. Check if PHP-FPM is Even Needed

If you're using mod_php (Apache) or PHP-CGI, you don't need to restart php-fpm. Just restart the web server.

##### 6. For Laravel Specifically

After config changes, you might not need to restart anything if you're using:
- **PHP-FPM with opcache disabled** - Config changes take effect immediately
- **PHP-FPM with opcache enabled** - You need to restart php-fpm OR clear opcache

**Clear opcache instead:**
```bash
### Create a route to clear opcache (temporary)
### Or restart php-fpm if you find the service name
```

#### Complete Deployment Without PHP-FPM Restart

If you can't restart php-fpm, you can still deploy successfully:

```bash
### 1. Pull code
git pull origin main

### 2. Install dependencies
composer install --no-dev --optimize-autoloader

### 3. Run migrations
php artisan migrate --force

### 4. Clear and cache config (THIS IS THE CRITICAL STEP)
php artisan config:clear
php artisan config:cache

### 5. Cache routes and views
php artisan route:cache
php artisan view:cache

### 6. Restart web server (this will reload PHP)
sudo systemctl restart nginx
### OR
sudo systemctl restart apache2

### 7. Restart queue workers (if using)
sudo supervisorctl restart kawhe-queue-worker:*
### OR
sudo systemctl restart kawhe-queue-worker
```

#### Verify It Worked

After deployment, check:

```bash
### Verify config is cached
php artisan tinker
>>> config('cashier.price_id')
### Should return your STRIPE_PRICE_ID

### Check application is running
curl https://yourdomain.com/up
```

#### Troubleshooting

##### If config changes don't take effect:

1. **Clear all caches:**
   ```bash
   php artisan cache:clear
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

2. **Restart web server** (this reloads PHP):
   ```bash
   sudo systemctl restart nginx
   ```

3. **If using opcache**, you might need to find and restart php-fpm:
   ```bash
   # Find the service
   systemctl list-units | grep php
   
   # Restart it (example for PHP 8.2)
   sudo systemctl restart php8.2-fpm
   ```

#### Quick Reference

**Most important commands for this deployment:**
```bash
php artisan config:clear
php artisan config:cache
sudo systemctl restart nginx  # or apache2
```

The web server restart is usually sufficient to reload PHP and pick up config changes.
