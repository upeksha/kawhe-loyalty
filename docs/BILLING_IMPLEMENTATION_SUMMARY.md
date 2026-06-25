# Billing Implementation Summary

This document summarizes plan entitlements and how Stripe subscriptions gate merchant growth via Laravel Cashier.

## Plan matrix

| Dimension | Free | Pro | Business (coming soon) |
|-----------|------|-----|------------------------|
| Stores | 1 | 3 | Unlimited (TBD) |
| Loyalty cards | 1 per store | 5 per store | Unlimited |
| Customers | 100 per card | Unlimited | Unlimited |
| Stripe price | — | `STRIPE_PRICE_ID` | Future second price |

Configuration: `config/billing.php`

## Soft gates (downgrade-safe)

Limits apply only to **new** growth:

- **New store** — blocked when store cap reached
- **New loyalty card** — blocked when per-store card cap reached
- **New customer join** — blocked when per-program customer cap reached (Free: 100)

What always keeps working:

- Existing stores, cards, and customers
- Stamping, redeeming, scanner, wallet passes
- Returning customers re-joining the same card (same email/phone on that program)

After Pro cancellation, merchants fall back to Free limits for new actions. Existing Pro-era stores/cards/customers are **not** deleted (grandfathering for programs created before `subscription.ends_at` is still tracked for UI messaging).

## Architecture

### Entitlements service

`app/Services/Billing/UsageService.php` is the single gate for plan limits.

| Method | Purpose |
|--------|---------|
| `planFor(User $user)` | `'free'` or `'pro'` (Business stub in config) |
| `canCreateStore(User $user)` | Store count vs plan |
| `canCreateProgram(User $user, ?Store $store)` | Cards per store (or any capacity if `$store` omitted) |
| `canAcceptNewCustomer(LoyaltyProgram $program)` | Loyalty accounts per program |
| `getUsageStats(User $user)` | Dashboard/billing metrics |

### Enforcement points

| Action | Controller | Gate |
|--------|------------|------|
| Create store | `StoreController::store` | `canCreateStore()` |
| Create loyalty card | `LoyaltyProgramController::store` | `canCreateProgram($user, $store)` |
| Customer join | `JoinController::store` | `canAcceptNewCustomer($program)` → `join/limit-reached` |

### Customer counting

- Counts `loyalty_accounts` rows **per `loyalty_program_id`**
- Returning customer with existing account on that program skips the cap check
- Does not block stamp/redeem APIs

### Subscription

- Laravel Cashier, subscription name `'default'`
- Pro resolved via active/trialing Stripe subscription
- Checkout: `BillingController::checkout`
- Portal: `BillingController::portal`
- Webhooks: `/stripe/webhook` (Cashier)

## Key files

| Area | Path |
|------|------|
| Plan config | `config/billing.php` |
| Entitlements | `app/Services/Billing/UsageService.php` |
| Billing UI | `resources/views/billing/index.blade.php` |
| Plan comparison | `resources/views/components/billing/plan-comparison.blade.php` |
| Customer limit page | `resources/views/join/limit-reached.blade.php` |
| Dashboard usage | `resources/views/dashboard.blade.php` |

## Environment variables

```env
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_PRICE_ID=price_...          # Pro plan
STRIPE_WEBHOOK_SECRET=whsec_...
```

## Tests

```bash
php artisan test --filter='UsageServiceTest|CustomerJoinLimitTest|BillingDiagnosticsTest|StoreTest'
```

Coverage includes:

- Free: 1 store, 1 card/store, 100 customers/program
- Pro: 3 stores, 5 cards/store, unlimited customers
- Join blocked at 101st customer on Free; returning customer still works
- Free merchant cannot create second store

## Business plan (future)

`config/billing.php` includes a `business` plan stub (`coming_soon: true`).

To launch:

1. Create Stripe product/price for Business
2. Extend `UsageService::planFor()` to map subscription price → `'business'`
3. Set Business entitlements in config (stores > 3, unlimited programs, advanced features)
4. Enable checkout CTA on billing plan comparison (currently disabled)

## Merchant-facing UI

The billing page shows:

1. **Plan state** hero (Free / Pro / limit reached)
2. **Compare plans** — Free vs Pro vs Business (coming soon)
3. **Usage right now** — stores, cards, customers on primary store/card
4. **Advanced billing help** — diagnostics and recovery actions

See `BILLING_SETUP.md` for Stripe setup steps.
