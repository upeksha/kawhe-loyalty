# Platform Changes Summary

**Last updated:** June 2026

High-level changelog for engineers onboarding to the codebase. Older entries (ledger, rate limiting, etc.) are preserved below.

---

## June 2026 — Onboarding, join, and multi-card parity

### Store ↔ LoyaltyProgram sync
- Customer join reads **`LoyaltyProgram`** (slug, token, branding, form config)
- Onboarding wizard writes **`Store`**, then **`Store::syncDefaultProgramFromStore()`** after each step
- Registration creates store + default program and syncs immediately

### Required branding
- **`StoreBrandingRules`**: brand/background colors + logo + wallet logo + wallet hero **required** on onboarding and add-store
- Wizard revisit: files required only if not already saved

### Merchant onboarding wizard (4 steps)
- Dedicated **`onboarding-layout`** (no sidebar)
- Steps: store basics → card design → customer form → card ready
- Legacy `POST /merchant/onboarding/store` redirects to wizard
- Removed `continue-trial` step (route redirects to card-ready)

### Shared registration form editor
- **`RegistrationFormConfig`** support class
- **`registration-form-config-editor`** Blade component
- Used in wizard step 3 and program create/edit forms
- Presets: Fastest, Balanced, Marketing-friendly

### Customer join UX
- Branded **`join/invalid`** for bad links
- Primary CTA order on landing; loading states on join/lookup forms
- `<x-input-error>` on join forms
- Welcome banner + one-time wallet nudge on card page
- localStorage scoped by **`loyalty_program_id`**

### Existing card recovery
- Email lookup (program-scoped)
- **Phone lookup** when phone field enabled on card’s join form

### Multi-card / Apple Wallet
- Migration: unique `(loyalty_program_id, customer_id)` instead of `(store_id, customer_id)`
- Apple serial: **`kawhe-{loyalty_account_id}`**; legacy serial still resolves
- One customer can join multiple loyalty cards (programs)

### Programs index
- Empty state when no active or archived cards

### Key new/updated files
- `app/Support/RegistrationFormConfig.php`
- `app/Support/StoreBrandingRules.php`
- `resources/views/components/registration-form-config-editor.blade.php`
- `resources/views/components/onboarding-layout.blade.php`
- `resources/views/join/invalid.blade.php`
- `app/Services/Wallet/Apple/AppleWalletSerial.php`
- `database/migrations/2026_06_25_000001_allow_multiple_loyalty_accounts_per_customer.php`

---

## Earlier — Data integrity & safety

### Data Integrity & Safety
- **New Table**: `points_transactions` — Immutable ledger of all point changes
- **Idempotency**: Prevents duplicate processing with unique keys
- **Optimistic Locking**: Version column prevents race conditions
- **Database Transactions**: All operations are atomic

### Security & Fraud Mitigation
- **Rate Limiting**:
  - 10 stamps/minute per customer
  - 100 stamps/minute per store
  - 50 stamps/minute per IP
  - 10 join lookups/minute per IP
- **Logging**: User agent and IP address logged for transactions

### UX & Reliability
- Transaction history on customer card
- Receipt data in scanner responses
- Retry logic for failed WebSocket updates

---

## API endpoints (customer)

- `GET /api/card/{public_token}/transactions` — transaction history

---

## Testing

```bash
php artisan test --filter='MerchantOnboardingIntegrationTest|RefactoredJoinFlowTest|JoinTest|LoyaltyProgramTest|SelfServeSaasBaselineTest|AppleWalletSerialTest|StoreTest'
```

See `docs/DEVELOPER_HANDOVER.md` for full handover guide.
