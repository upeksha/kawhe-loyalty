# Production Deploy Checklist - Stripe Config Fix

## ⚠️ Critical: Config Cache Issue

After deploying, `STRIPE_PRICE_ID` may show as "Not set" even if it's in your `.env` file. This happens because:

1. The new `price_id` key was added to `config/cashier.php`
2. If config is cached without this key, it won't be available
3. **Solution: Always clear and recache config after deployment**

## Quick Deploy Commands

```bash
# 1. Pull code
git pull origin main

# 2. Install dependencies
composer install --no-dev --optimize-autoloader

# 3. Run migrations (if any)
php artisan migrate --force

# 4. ⚠️ CRITICAL: Clear and recache config
php artisan config:clear
php artisan config:cache

# 5. Cache routes and views
php artisan route:cache
php artisan view:cache

# 6. Restart services
sudo systemctl restart php-fpm
sudo supervisorctl restart kawhe-queue-worker:*
```

## Verify Config is Working

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

## Check Billing Page

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

## Environment Variables Required

Make sure your production `.env` has:

```env
STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
STRIPE_PRICE_ID=price_...  # ⚠️ Must be set
STRIPE_WEBHOOK_SECRET=whsec_...
APP_URL=https://yourdomain.com
```

## Why This Happens

Laravel caches configuration files for performance. When you:
1. Add a new config key (`price_id` in `config/cashier.php`)
2. Deploy to production
3. Don't clear the old cached config

The cached config doesn't have the new key, so `config('cashier.price_id')` returns `null` even though it's in `.env`.

**Solution:** Always run `php artisan config:clear && php artisan config:cache` after deploying config changes.
