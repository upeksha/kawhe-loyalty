# Stripe Subscription Sync - Local Test Checklist

This checklist verifies that subscription sync works correctly after Stripe Checkout payment.

## Prerequisites

Before testing, ensure your `.env` has:

```env
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_PRICE_ID=price_...
STRIPE_WEBHOOK_SECRET=whsec_...  # From Stripe CLI or Dashboard
APP_URL=http://localhost:8000  # Or your ngrok URL
```

## Test Steps

### 1. Start Local Environment

- [ ] Start Laravel server: `php artisan serve`
- [ ] (Optional) Start Stripe CLI: `stripe listen --forward-to localhost:8000/stripe/webhook`
- [ ] Copy webhook secret from Stripe CLI output to `.env` if using CLI
- [ ] Clear config cache: `php artisan config:clear`

### 2. Pre-Checkout Verification

- [ ] Log in as a merchant user
- [ ] Navigate to `/billing`
- [ ] Verify "Free Plan" is shown
- [ ] Verify usage meter shows current card count (e.g., "X / 50")
- [ ] Click "Upgrade to Pro" button
- [ ] Verify redirect to Stripe Checkout page

### 3. Complete Payment

- [ ] On Stripe Checkout page, use test card: `4242 4242 4242 4242`
- [ ] Enter any future expiry date (e.g., 12/34)
- [ ] Enter any CVC (e.g., 123)
- [ ] Enter any ZIP code
- [ ] Click "Subscribe" or "Pay"
- [ ] **Verify URL includes `?session_id=cs_test_...`** in browser address bar after redirect

### 4. Success Page Verification

- [ ] After payment, verify redirect to `/billing/success?session_id=...`
- [ ] Check for one of these outcomes:
  - ✅ **Best case**: Redirected to dashboard with success message "Pro Plan Active"
  - ✅ **Good case**: Success page shows "Subscription Activating" with sync button
  - ⚠️ **Fallback**: Success page shows error message (should still have sync option)

### 5. Database Verification

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

### 6. Dashboard Verification

- [ ] Navigate to `/merchant/dashboard`
- [ ] Verify "Pro Plan Active" is shown (green checkmark)
- [ ] Verify usage meter shows "Cards issued: X / ∞" (unlimited)
- [ ] Verify "Upgrade" button is **NOT** visible
- [ ] Verify no "Limit Reached" warning banner

### 7. Billing Page Verification

- [ ] Navigate to `/billing`
- [ ] Verify "Pro Plan Active" is shown
- [ ] Verify "Manage Subscription" button is visible
- [ ] Verify subscription details show status: `active` or `trialing`

### 8. Manual Sync Test (if needed)

- [ ] If subscription not detected, click "Sync Subscription Status" on billing page
- [ ] Or use artisan command: `php artisan kawhe:sync-subscriptions {user_id}`
- [ ] Verify subscription syncs correctly
- [ ] Re-check dashboard and billing page

### 9. Webhook Verification (if using Stripe CLI)

- [ ] Check Stripe CLI output for webhook events:
  - `checkout.session.completed`
  - `customer.subscription.created`
  - `customer.subscription.updated`
- [ ] Check Laravel logs: `tail -f storage/logs/laravel.log`
- [ ] Verify webhook processing logs appear
- [ ] Verify no webhook errors

### 10. Functional Verification (Non-Breaking)

- [ ] **Stamping still works**: Go to scanner, scan a card, verify stamp is added
- [ ] **Redeeming still works**: Redeem a reward, verify it works
- [ ] **Reverb still works**: Open card page, stamp from scanner, verify real-time update
- [ ] **Store management still works**: Create/edit stores, verify no errors
- [ ] **Customer join still works**: Create new loyalty account, verify it works (should be unlimited now)

### 11. Edge Cases

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

## Expected Results

✅ **All tests should pass**

- Subscription syncs immediately after payment (best case)
- Or syncs within a few seconds via webhook
- Or can be manually synced via button/command
- Dashboard shows "Pro Plan Active"
- All existing functionality (stamping, redeeming, Reverb) still works
- No errors in logs

## Troubleshooting

### Subscription not syncing

1. Check `.env` has correct `STRIPE_SECRET`
2. Check Laravel logs for errors
3. Verify `APP_URL` matches your actual URL
4. Try manual sync: `php artisan kawhe:sync-subscriptions {user_id}`
5. Check Stripe Dashboard → Customers → verify customer exists
6. Check Stripe Dashboard → Subscriptions → verify subscription exists

### Webhook not working

1. Verify Stripe CLI is running (if testing locally)
2. Check webhook URL is correct: `http://localhost:8000/stripe/webhook`
3. Verify `STRIPE_WEBHOOK_SECRET` matches Stripe CLI output
4. Check `bootstrap/app.php` excludes webhook from CSRF
5. Check Laravel logs for webhook processing errors

### CSRF errors

1. Verify `bootstrap/app.php` has webhook excluded:
   ```php
   $middleware->validateCsrfTokens(except: ['stripe/webhook']);
   ```
2. Clear config cache: `php artisan config:clear`
