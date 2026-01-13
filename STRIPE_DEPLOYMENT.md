# Stripe Subscription Sync - Production Deployment Guide

This guide covers deploying the Stripe subscription sync fixes to DigitalOcean.

## Pre-Deployment Checklist

- [ ] All local tests pass (see `STRIPE_SYNC_TEST_CHECKLIST.md`)
- [ ] Code committed and pushed to Git
- [ ] Stripe account has live API keys (not test keys)
- [ ] Subscription price created in Stripe Dashboard (live mode)
- [ ] Webhook endpoint URL ready (e.g., `https://yourdomain.com/stripe/webhook`)

## Server Deployment Steps

### 1. SSH into DigitalOcean Server

```bash
ssh user@your-server-ip
cd /path/to/kawhe-loyalty
```

### 2. Pull Latest Code

```bash
git pull origin main
# Or your branch name
```

### 3. Install Dependencies

```bash
# PHP dependencies
composer install --no-dev --optimize-autoloader

# Frontend assets (if changed)
npm ci
npm run build
```

### 4. Run Migrations

```bash
php artisan migrate --force
```

This ensures all Cashier tables exist.

### 5. Clear and Cache Configuration

```bash
# Clear old config
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Cache for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 6. Update Environment Variables

Edit `.env` file on server:

```env
# Stripe Live Keys (NOT test keys)
STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
STRIPE_PRICE_ID=price_...  # Live price ID from Stripe Dashboard
STRIPE_WEBHOOK_SECRET=whsec_...  # From Stripe Dashboard webhook (see below)

# App URL (your production domain)
APP_URL=https://yourdomain.com
APP_ENV=production
```

### 7. Restart PHP-FPM (if applicable)

```bash
# For PHP-FPM
sudo systemctl restart php8.2-fpm
# Or
sudo service php-fpm restart

# For Nginx
sudo systemctl restart nginx
```

### 8. Restart Queue Workers (if using queues)

```bash
# If using Supervisor
sudo supervisorctl restart kawhe-queue-worker:*

# If using systemd
sudo systemctl restart kawhe-queue-worker

# Or manually
php artisan queue:restart
```

### 9. Verify Application

```bash
# Check application is running
curl https://yourdomain.com/up

# Check routes are cached
php artisan route:list | grep billing
php artisan route:list | grep stripe
```

## Stripe Dashboard Configuration

### 1. Create/Update Webhook Endpoint

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

### 2. Test Webhook

1. In Stripe Dashboard → **Developers** → **Webhooks**
2. Find your webhook endpoint
3. Click **Send test webhook**
4. Select event: `checkout.session.completed`
5. Click **Send test webhook**
6. Check server logs: `tail -f storage/logs/laravel.log`
7. Verify webhook is received and processed (200 response)

### 3. Verify Subscription Price

1. Go to **Products** → Find your Pro Plan product
2. Verify **Price ID** matches `STRIPE_PRICE_ID` in `.env`
3. Verify it's in **Live mode** (not test mode)

## Post-Deployment Verification

### 1. Test Subscription Flow

- [ ] Go to `https://yourdomain.com/billing`
- [ ] Click "Upgrade to Pro"
- [ ] Complete payment with real card (or test card in test mode)
- [ ] Verify redirect to success page with `session_id`
- [ ] Verify subscription syncs and dashboard shows "Pro Plan Active"

### 2. Check Logs

```bash
# Watch logs in real-time
tail -f storage/logs/laravel.log

# Look for:
# - "Checkout session retrieved"
# - "Subscription synced after checkout"
# - Webhook processing logs
# - Any errors
```

### 3. Verify Database

```bash
# Connect to database
php artisan tinker

# Check subscription
$user = User::find({user_id});
$user->subscription('default');
$user->subscribed('default');  // Should return true
```

### 4. Functional Tests

- [ ] Stamping works: Scanner → scan card → verify stamp added
- [ ] Redeeming works: Redeem reward → verify it works
- [ ] Reverb works: Real-time updates still function
- [ ] Store management works: Create/edit stores
- [ ] Customer join works: New customers can join (unlimited for Pro)

## Troubleshooting

### Subscription Not Syncing After Payment

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

### Webhook Not Receiving Events

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

### CSRF Errors on Webhook

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

### Subscription Shows But Dashboard Still Shows Free Plan

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

## Monitoring

### Recommended Monitoring

1. **Log Monitoring**:
   - Set up log rotation: `logrotate` for `storage/logs/laravel.log`
   - Monitor for Stripe/webhook errors

2. **Queue Monitoring** (if using queues):
   - Monitor queue:work process
   - Set up alerts for failed jobs

3. **Stripe Dashboard**:
   - Monitor webhook delivery success rate
   - Set up alerts for failed webhooks

## Rollback Plan

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

## Support Contacts

- **Stripe Support**: https://support.stripe.com
- **Laravel Cashier Docs**: https://laravel.com/docs/cashier
- **Server Issues**: Contact your DigitalOcean support
