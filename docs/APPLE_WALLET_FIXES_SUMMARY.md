# Apple Wallet Fixes Summary

## Changes Made

### 1. Fixed 304 Not Modified Response Headers ✅
**File:** `app/Http/Controllers/Wallet/AppleWalletController.php`
- Added `Last-Modified` header to both 200 and 304 responses
- Used `withHeaders()` method for proper header setting
- Headers now include RFC7231 timestamp format

### 2. Added wallet_auth_token for Security ✅
**Files:**
- `database/migrations/2026_01_20_071000_add_wallet_auth_token_to_loyalty_accounts_table.php` (new)
- `app/Models/LoyaltyAccount.php`
- `database/factories/LoyaltyAccountFactory.php`

**Changes:**
- Added `wallet_auth_token` column to `loyalty_accounts` table
- Migration backfills existing records with secure random tokens
- Model auto-generates token on creation
- Factory includes token for tests

### 3. Updated Pass Generation for Dynamic QR ✅
**File:** `app/Services/Wallet/AppleWalletPassService.php`
- Changed `authenticationToken` from `public_token` to `wallet_auth_token`
- QR message is now dynamic:
  - `LR:{redeem_token}` when `reward_balance > 0` and `redeem_token` exists
  - `LA:{public_token}` otherwise
- QR format remains `PKBarcodeFormatQR`

### 4. Updated Authentication Middleware ✅
**File:** `app/Http/Middleware/ApplePassAuthMiddleware.php`
- Changed validation from `public_token` to `wallet_auth_token`
- Maintains per-pass authentication security

### 5. Updated Scanner to Handle LR: Prefix ✅
**File:** `app/Http/Controllers/ScannerController.php`
- `store()` method now detects `LR:` prefix and routes to redeem
- `redeem()` method supports both `LR:` and `REDEEM:` prefixes (backward compatible)
- Redeem flow now rotates `redeem_token` after each redemption
- Redeem flow dispatches `UpdateWalletPassJob` after commit

### 6. Fixed APNs Push Configuration ✅
**File:** `app/Services/Wallet/Apple/ApplePushService.php`
- Changed `apns-priority` from 10 to 5 (recommended for Wallet updates)
- Payload is already correct: `json_encode(['aps' => []])`
- Endpoints correctly use sandbox/production based on `APPLE_APNS_USE_SANDBOX`

### 7. Updated Tests ✅
**File:** `tests/Feature/AppleWalletWebServiceTest.php`
- Updated all test cases to use `wallet_auth_token` instead of `public_token`

## Migration Required

Run the migration to add `wallet_auth_token` column:
```bash
php artisan migrate
```

This will:
1. Add nullable `wallet_auth_token` column
2. Backfill all existing records with secure random tokens
3. Make column NOT NULL and add unique index

## Testing on iPhone

### Step 1: Generate a Pass
1. Create a loyalty account (or use existing)
2. Visit the card page and click "Add to Apple Wallet"
3. Verify pass appears in Wallet

### Step 2: Test Stamping
1. Scan the QR code (should show `LA:{public_token}`)
2. Verify stamps increment
3. Verify pass updates automatically (if APNs enabled)

### Step 3: Test Redeeming
1. Earn enough stamps to get a reward (`reward_balance > 0`)
2. Check Wallet - QR should now show `LR:{redeem_token}`
3. Scan the QR code to redeem
4. Verify:
   - Reward balance decreases
   - QR code changes back to `LA:{public_token}` (if no rewards left)
   - Old redeem QR cannot be reused (token rotated)

### Step 4: Verify Security
1. QR code contains `public_token` (for scanning)
2. Wallet authentication uses `wallet_auth_token` (separate, secure)
3. These tokens are independent and serve different purposes

## Verification Checklist

- [ ] Migration runs successfully
- [ ] Existing passes still work (after regeneration)
- [ ] New passes use `wallet_auth_token` for authentication
- [ ] QR code shows `LA:{public_token}` when no rewards
- [ ] QR code shows `LR:{redeem_token}` when rewards available
- [ ] Stamping works with `LA:` prefix
- [ ] Redeeming works with `LR:` prefix
- [ ] Redeem token rotates after each redemption
- [ ] Old redeem QR codes cannot be reused
- [ ] Pass updates automatically after stamp/redeem
- [ ] APNs push sends correctly (if enabled)
- [ ] All tests pass: `./vendor/bin/pest --filter AppleWalletWebServiceTest`

## Notes

- The APNs payload `['aps' => []]` is correct for Wallet updates (empty aps dictionary)
- Priority 5 is recommended for Wallet background updates
- Token rotation ensures security - old QR codes become invalid after redemption
- Backward compatibility maintained for `REDEEM:` prefix in redeem method
