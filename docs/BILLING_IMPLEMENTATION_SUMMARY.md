# Billing Implementation Summary

This document summarizes the monetization gate implementation using Stripe subscriptions via Laravel Cashier.

## Overview

- **Free Plan**: Up to 50 loyalty cards per merchant account
- **Pro Plan**: Unlimited loyalty cards (requires subscription)
- Limit enforcement only applies to **new** loyalty account creation
- Existing customers can still use their cards (stamping/redeeming works)

## Files Changed

### 1. Package Installation
- `composer.json` - Added `laravel/cashier` dependency

### 2. Models
- `app/Models/User.php` - Added `Billable` trait from Laravel Cashier

### 3. Services
- `app/Services/Billing/UsageService.php` - **NEW** - Service for counting cards and checking limits
  - `cardsCountForUser(User $user): int` - Counts loyalty cards across all stores
  - `freeLimit(): int` - Returns 50
  - `isSubscribed(User $user): bool` - Checks subscription status
  - `canCreateCard(User $user): bool` - Determines if merchant can create new cards
  - `getUsageStats(User $user): array` - Returns usage statistics

### 4. Controllers
- `app/Http/Controllers/JoinController.php` - Modified `store()` method
  - Checks if loyalty account already exists (allows existing customers)
  - Enforces limit before creating new loyalty accounts
  - Returns friendly error page if limit reached
  - Logs blocked attempts

- `app/Http/Controllers/BillingController.php` - **NEW**
  - `index()` - Billing overview page
  - `checkout()` - Creates Stripe Checkout session
  - `portal()` - Redirects to Stripe Billing Portal
  - `success()` - Success page after subscription
  - `cancel()` - Cancel page after cancelled checkout

### 5. Routes
- `routes/web.php`
  - Added billing routes (`/billing`, `/billing/checkout`, `/billing/portal`, etc.)
  - Added Stripe webhook route (`/stripe/webhook`)
  - Updated dashboard route to pass usage stats

### 6. Views
- `resources/views/dashboard.blade.php` - Updated
  - Shows plan status (Free/Pro)
  - Displays usage meter (X / 50 cards)
  - Shows warning banner when limit reached
  - Upgrade CTA button

- `resources/views/billing/index.blade.php` - **NEW**
  - Billing overview page
  - Current plan status
  - Usage statistics
  - Subscription details
  - Upgrade benefits

- `resources/views/billing/success.blade.php` - **NEW**
  - Success page after subscription activation

- `resources/views/billing/cancel.blade.php` - **NEW**
  - Cancel page after cancelled checkout

- `resources/views/join/limit-reached.blade.php` - **NEW**
  - Customer-facing error page when limit reached
  - Friendly message with store name
  - "Try Again Later" button

### 7. Migrations
- `database/migrations/2026_01_13_001909_create_subscriptions_table.php` - **NEW** (from Cashier)
- `database/migrations/2026_01_13_001910_create_subscription_items_table.php` - **NEW** (from Cashier)
- Additional Cashier migrations for user table columns

### 8. Configuration
- `config/cashier.php` - **NEW** (published from Cashier)
  - Stripe keys configuration
  - Webhook secret configuration

### 9. Documentation
- `README.md` - Updated with billing features and links
- `BILLING_SETUP.md` - **NEW** - Complete Stripe setup guide

## Key Implementation Details

### Limit Enforcement Logic

1. **When**: Only enforced when creating a **new** loyalty account
2. **Where**: `JoinController::store()` method
3. **How**:
   - First checks if loyalty account already exists for customer + store
   - If exists, allows (no limit check)
   - If new, checks `UsageService::canCreateCard()`
   - If blocked, returns friendly error page
   - Logs blocked attempts for debugging

### Usage Counting

- Counts all `loyalty_accounts` where `store_id` belongs to stores owned by the merchant
- Uses efficient query: `LoyaltyAccount::whereIn('store_id', $storeIds)->count()`
- Single query per check (no N+1 issues)

### Subscription Management

- Uses Laravel Cashier's built-in subscription handling
- Subscription name: `'default'`
- Stripe Checkout for new subscriptions
- Stripe Billing Portal for managing/cancelling
- Webhooks automatically handled by Cashier

## Environment Variables Required

```env
STRIPE_KEY=pk_test_...  # Stripe publishable key
STRIPE_SECRET=sk_test_...  # Stripe secret key
STRIPE_PRICE_ID=price_...  # Subscription price ID
STRIPE_WEBHOOK_SECRET=whsec_...  # Webhook signing secret
```

## Artisan Commands After Deploy

```bash
# 1. Run migrations (creates Cashier tables)
php artisan migrate --force

# 2. Clear and cache config
php artisan config:clear
php artisan config:cache
```

## Testing Checklist

### Free Merchant Under Limit
- [ ] Create store
- [ ] Create multiple customer joins (should work)
- [ ] Verify dashboard shows usage meter

### Free Merchant At Limit
- [ ] Create 50 loyalty accounts
- [ ] Attempt 51st join → Should show "Limit Reached" page
- [ ] Existing customer re-joining same store → Should work
- [ ] Merchant scanner stamping/redeem → Should work for existing cards

### Subscribed Merchant
- [ ] Subscribe via `/billing`
- [ ] Complete Stripe Checkout
- [ ] Verify dashboard shows "Pro Plan Active"
- [ ] Create customer join beyond 50 → Should work
- [ ] Verify unlimited cards

### Webhook Testing
- [ ] Configure webhook endpoint in Stripe Dashboard
- [ ] Send test webhook from Stripe
- [ ] Verify subscription status updates in database

## Important Notes

1. **No Breaking Changes**: All existing flows (joining, stamping, redeeming, Reverb) remain unchanged
2. **Backward Compatible**: Existing merchants and customers unaffected
3. **Production Safe**: Proper error handling, logging, and user-friendly messages
4. **Efficient**: Single query for usage counting, no N+1 issues
5. **Secure**: Webhook signature verification via Cashier middleware

## Next Steps

1. Set up Stripe account and get API keys
2. Create subscription price in Stripe Dashboard
3. Configure webhook endpoint
4. Test the flow end-to-end
5. Deploy to production with live Stripe keys

See `BILLING_SETUP.md` for detailed setup instructions.
