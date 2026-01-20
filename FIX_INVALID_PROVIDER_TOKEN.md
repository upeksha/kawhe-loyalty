# Fix InvalidProviderToken Error

## Problem
APNs is returning `403 Forbidden` with error `InvalidProviderToken`. This means the JWT token being sent is invalid.

## Root Cause
The JWT signature generation had a bug in the DER (Distinguished Encoding Rules) to R|S format conversion. The DER parser was too simplistic and didn't handle all edge cases correctly.

## Solution Applied
1. **Improved DER to R|S conversion**: Created a robust `derToRS()` method that properly parses DER-encoded ECDSA signatures
2. **Enhanced error handling**: Added better error messages and logging
3. **Path resolution fix**: Improved key file path resolution to handle both absolute and relative paths
4. **Added validation**: Verify signature length is exactly 64 bytes (32 bytes R + 32 bytes S)

## Testing Steps

### Step 1: Pull Latest Changes
```bash
cd /var/www/kawhe
git pull origin main
php artisan config:clear
php artisan config:cache
```

### Step 2: Test JWT Generation
```bash
php artisan wallet:test-jwt
```

**Expected output:**
```
✅ JWT generated successfully!
  ✓ Algorithm: ES256
  ✓ Key ID matches
  ✓ Team ID (iss) matches
  ✓ Issued at: [timestamp]
```

### Step 3: Test APNs Push
```bash
# Get a serial number
php artisan tinker --execute="
\$account = \App\Models\LoyaltyAccount::first();
echo 'kawhe-' . \$account->store_id . '-' . \$account->customer_id . PHP_EOL;
"

# Test push
php artisan wallet:apns-test kawhe-1-10
```

**Expected:** `✅ Push notification sent successfully!`

### Step 4: Check Logs
```bash
tail -n 50 storage/logs/laravel.log | grep -i "push notification"
```

**Look for:**
- `Apple Wallet push notification sent successfully` (HTTP 200)
- NOT `InvalidProviderToken` errors

### Step 5: Test Full Flow
1. **Stamp an account:**
   ```bash
   php artisan tinker
   ```
   ```php
   $account = \App\Models\LoyaltyAccount::find(11);
   $user = \App\Models\User::whereHas('stores', function($q) use ($account) {
       $q->where('stores.id', $account->store_id);
   })->first();
   
   if ($user) {
       $service = app(\App\Services\Loyalty\StampLoyaltyService::class);
       $result = $service->stamp($account, $user, 1);
       echo "Stamped! New count: {$result->stampCount}\n";
   } else {
       echo "No user found for store {$account->store_id}\n";
   }
   exit
   ```

2. **Check logs for push:**
   ```bash
   tail -f storage/logs/laravel.log | grep -i "push"
   ```

3. **Verify pass updates in Wallet** (on iPhone)

## If Still Getting InvalidProviderToken

### Check 1: Verify Key File
```bash
# Check file exists and is readable
ls -la storage/app/private/apns/AuthKey_5JGMHRZC36.p8

# Verify it's a valid PEM key
openssl ec -in storage/app/private/apns/AuthKey_5JGMHRZC36.p8 -text -noout
```

**Expected:** Should show EC private key details without errors

### Check 2: Verify Key ID Matches
```bash
# Key filename should match Key ID
# File: AuthKey_5JGMHRZC36.p8
# Key ID: 5JGMHRZC36
php artisan config:show wallet.apple.apns_key_id
```

### Check 3: Verify Team ID
```bash
# Team ID should match your Apple Developer account
php artisan config:show wallet.apple.apns_team_id
```

### Check 4: Verify Topic Matches Pass Type
```bash
php artisan config:show passgenerator.pass_type_identifier
php artisan config:show wallet.apple.apns_topic
# These must match exactly!
```

### Check 5: Test JWT Manually
```bash
php artisan wallet:test-jwt
```

If this fails, check:
- Key file is correct (downloaded from Apple Developer Portal)
- Key ID matches filename
- Team ID is correct
- Key has "Apple Push Notifications service (APNs)" enabled in Apple Developer Portal

## Common Issues

### Issue: "Failed to load APNs private key"
**Fix:**
- Verify key file exists: `ls -la storage/app/private/apns/AuthKey_5JGMHRZC36.p8`
- Check permissions: `chmod 600 storage/app/private/apns/AuthKey_5JGMHRZC36.p8`
- Verify it's a valid .p8 file (should start with `-----BEGIN PRIVATE KEY-----`)

### Issue: "Invalid signature length"
**Fix:**
- This should be fixed by the new DER parser
- If still happening, check logs for full error details

### Issue: "Key ID mismatch"
**Fix:**
- Ensure `APPLE_APNS_KEY_ID` in `.env` matches the key filename
- Example: File `AuthKey_5JGMHRZC36.p8` → `APPLE_APNS_KEY_ID=5JGMHRZC36`

### Issue: "Team ID mismatch"
**Fix:**
- Verify `APPLE_APNS_TEAM_ID` matches your Apple Developer Team ID
- Check Apple Developer Portal → Membership

## Verification Checklist

- [ ] JWT generation test passes: `php artisan wallet:test-jwt`
- [ ] APNs push test succeeds: `php artisan wallet:apns-test {serial}`
- [ ] Logs show HTTP 200 (not 403)
- [ ] No `InvalidProviderToken` errors in logs
- [ ] Key file exists and is readable
- [ ] Key ID matches filename
- [ ] Team ID is correct
- [ ] Topic matches Pass Type Identifier
- [ ] Full stamp flow works and triggers push

## Next Steps After Fix

1. Test on iPhone:
   - Add pass to Wallet
   - Scan QR code to stamp
   - Verify pass updates automatically

2. Monitor logs:
   ```bash
   tail -f storage/logs/laravel.log | grep -i "wallet\|push\|apns"
   ```

3. If still having issues, check:
   - Apple Developer Portal → Certificates, Identifiers & Profiles
   - Ensure APNs key has correct permissions
   - Verify Pass Type Identifier matches exactly
