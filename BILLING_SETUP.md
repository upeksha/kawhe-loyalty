# Stripe Billing Setup Guide

This guide covers setting up Stripe subscriptions for merchant billing in Kawhe Loyalty.

## Overview

Kawhe Loyalty uses Laravel Cashier (Stripe) to manage merchant subscriptions:
- **Free Plan**: Up to 50 loyalty cards per merchant account
- **Pro Plan**: Unlimited loyalty cards (requires subscription)
- Merchants can upgrade/downgrade via Stripe Checkout and Billing Portal

## Prerequisites

1. A Stripe account (sign up at https://stripe.com)
2. Access to Stripe Dashboard

## Step 1: Configure Stripe Keys

Add these environment variables to your `.env` file:

```env
STRIPE_KEY=pk_test_...  # Your Stripe publishable key
STRIPE_SECRET=sk_test_...  # Your Stripe secret key
STRIPE_PRICE_ID=price_...  # Your subscription price ID (see Step 2)
STRIPE_WEBHOOK_SECRET=whsec_...  # Your webhook signing secret (see Step 4)
```

### Getting Your Stripe Keys

1. Go to [Stripe Dashboard](https://dashboard.stripe.com)
2. Navigate to **Developers** → **API keys**
3. Copy your **Publishable key** → `STRIPE_KEY`
4. Copy your **Secret key** → `STRIPE_SECRET`

**Note**: Use test keys (`pk_test_...` / `sk_test_...`) for development, and live keys (`pk_live_...` / `sk_live_...`) for production.

## Step 2: Create Subscription Price

1. Go to [Stripe Dashboard](https://dashboard.stripe.com)
2. Navigate to **Products** → **Add product**
3. Create a new product:
   - **Name**: "Kawhe Pro Plan" (or your preferred name)
   - **Description**: "Unlimited loyalty cards for merchants"
   - **Pricing**: Set your monthly/yearly price
   - **Billing period**: Monthly or Yearly
4. After creating, copy the **Price ID** (starts with `price_...`)
5. Add it to `.env` as `STRIPE_PRICE_ID`

## Step 3: Run Migrations

Cashier requires database tables for subscriptions. Run migrations:

```bash
php artisan migrate
```

This creates:
- `subscriptions` table
- `subscription_items` table
- Stripe-related columns on `users` table

## Step 4: Configure Webhook Endpoint

Stripe webhooks notify your app about subscription events (payment succeeded, cancelled, etc.).

### For Local Development (using ngrok)

1. Start your local server:
   ```bash
   php artisan serve
   ```

2. In another terminal, expose your local server:
   ```bash
   ngrok http 8000
   ```

3. Copy the HTTPS URL (e.g., `https://abc123.ngrok-free.app`)

4. Go to [Stripe Dashboard](https://dashboard.stripe.com) → **Developers** → **Webhooks**

5. Click **Add endpoint**

6. Set:
   - **Endpoint URL**: `https://your-ngrok-url.ngrok-free.app/stripe/webhook`
   - **Events to send**: Select these events:
     - `customer.subscription.created`
     - `customer.subscription.updated`
     - `customer.subscription.deleted`
     - `invoice.payment_succeeded`
     - `invoice.payment_failed`
     - `customer.subscription.trial_will_end`

7. Click **Add endpoint**

8. Copy the **Signing secret** (starts with `whsec_...`)

9. Add it to `.env` as `STRIPE_WEBHOOK_SECRET`

### For Production

1. Deploy your application

2. Go to [Stripe Dashboard](https://dashboard.stripe.com) → **Developers** → **Webhooks**

3. Click **Add endpoint**

4. Set:
   - **Endpoint URL**: `https://yourdomain.com/stripe/webhook`
   - **Events to send**: Same events as above

5. Copy the **Signing secret** and add to production `.env`

## Step 5: Test the Integration

### Test Free Plan Limit

1. Create a merchant account
2. Create 50 loyalty cards (should work)
3. Try to create the 51st card → Should show "Limit Reached" page
4. Existing cards should still work (stamping/redeeming)

### Test Subscription Flow

1. As a merchant, go to `/billing`
2. Click **Upgrade to Pro**
3. Complete Stripe Checkout (use test card: `4242 4242 4242 4242`)
4. After successful payment, verify:
   - Dashboard shows "Pro Plan Active"
   - Can create unlimited cards
   - `/billing` shows subscription details

### Test Webhook

1. In Stripe Dashboard → **Developers** → **Webhooks**
2. Find your webhook endpoint
3. Click **Send test webhook**
4. Select event type (e.g., `customer.subscription.created`)
5. Verify your app receives it (check Laravel logs)

## Environment Variables Summary

```env
# Stripe API Keys
STRIPE_KEY=pk_test_51AbC123...  # Publishable key
STRIPE_SECRET=sk_test_51XyZ789...  # Secret key

# Subscription Price
STRIPE_PRICE_ID=price_1AbC123...  # Price ID from Stripe Dashboard

# Webhook Security
STRIPE_WEBHOOK_SECRET=whsec_1XyZ789...  # Webhook signing secret
```

## Production Deployment Checklist

After deploying to production:

1. ✅ Update `.env` with **live** Stripe keys (not test keys)
2. ✅ Create a **live** subscription price in Stripe Dashboard
3. ✅ Update `STRIPE_PRICE_ID` with live price ID
4. ✅ Configure production webhook endpoint in Stripe Dashboard
5. ✅ Update `STRIPE_WEBHOOK_SECRET` with production webhook secret
6. ✅ Run migrations: `php artisan migrate --force`
7. ✅ Clear config cache: `php artisan config:clear && php artisan config:cache`
8. ✅ Test subscription flow with a real payment method

## Troubleshooting

### "Stripe price ID not configured"

- Ensure `STRIPE_PRICE_ID` is set in `.env`
- Verify the price ID exists in your Stripe Dashboard
- Clear config cache: `php artisan config:clear`

### Webhook not receiving events

- Verify webhook URL is accessible (not behind firewall)
- Check webhook signing secret matches in `.env`
- View webhook logs in Stripe Dashboard → **Developers** → **Webhooks**
- Check Laravel logs: `storage/logs/laravel.log`

### Subscription not activating after payment

- Check webhook is configured correctly
- Verify webhook events are being sent
- Check Laravel logs for webhook processing errors
- Manually sync subscription: `php artisan cashier:webhook` (if available)

### "Limit Reached" but merchant is subscribed

- Verify subscription status in Stripe Dashboard
- Check `subscriptions` table in database
- Ensure webhook processed subscription creation event
- Clear config cache: `php artisan config:clear`

## Additional Resources

- [Laravel Cashier Documentation](https://laravel.com/docs/cashier)
- [Stripe API Documentation](https://stripe.com/docs/api)
- [Stripe Webhooks Guide](https://stripe.com/docs/webhooks)
