# Apple Wallet Registration Fix - Implementation Summary

## Problem

Apple Wallet was not calling the registration endpoints when passes were added to iPhone wallets. The root causes were:

1. **Missing `webServiceURL` in pass.json** - Apple Wallet didn't know where to send registration requests
2. **Incorrect `webServiceURL` format** - Was set to `/wallet/v1` but should be `/wallet` (Apple appends `/v1` automatically)
3. **Missing `authenticationToken`** - Passes didn't include authentication tokens
4. **Insufficient logging** - Hard to debug registration issues

## Changes Made

### 1. Pass Generation (`app/Services/Wallet/AppleWalletPassService.php`)

**Fixed:**
- Added `webServiceURL` set to `{APP_URL}/wallet` (NOT `/wallet/v1`)
- Added `authenticationToken` using the loyalty account's `public_token` for per-pass security
- Apple automatically appends `/v1` to `webServiceURL`, so we only specify `/wallet`

```php
'webServiceURL' => rtrim(config('app.url'), '/') . '/wallet',
'authenticationToken' => $account->public_token,
```

### 2. Authentication Middleware (`app/Http/Middleware/ApplePassAuthMiddleware.php`)

**Enhanced:**
- Now validates tokens against the pass's `public_token` (per-pass authentication)
- Falls back to global config token for backward compatibility
- Added detailed logging for authentication failures

### 3. Registration Controller (`app/Http/Controllers/Wallet/AppleWalletController.php`)

**Enhanced:**
- Added comprehensive logging for all registration requests
- Logs device library identifier, serial number, push token presence, IP address
- Logs successful registrations with full details

### 4. Routes (`routes/web.php`)

**Verified:**
- Routes are correctly defined under `/wallet/v1/`
- CSRF is excluded for `wallet/v1/*` in `bootstrap/app.php`
- Middleware is properly applied

## Testing Checklist

### 1. Verify Pass Generation

```bash
# Generate a pass and check pass.json
php artisan tinker
```

```php
$account = \App\Models\LoyaltyAccount::first();
$service = app(\App\Services\Wallet\AppleWalletPassService::class);
$passData = $service->generatePass($account);

// Save to file
file_put_contents('/tmp/test-pass.pkpass', $passData);

// Extract and check
exec('cd /tmp && unzip -o test-pass.pkpass pass.json');
$passJson = json_decode(file_get_contents('/tmp/pass.json'), true);

echo "webServiceURL: " . ($passJson['webServiceURL'] ?? 'NOT SET') . "\n";
echo "authenticationToken: " . ($passJson['authenticationToken'] ?? 'NOT SET') . "\n";
exit
```

**Expected:**
- `webServiceURL`: `https://testing.kawhe.shop/wallet` (NOT `/wallet/v1`)
- `authenticationToken`: The account's `public_token`

### 2. Verify Routes

```bash
php artisan route:list | grep wallet/v1
```

**Expected:** Should show:
- `POST /wallet/v1/devices/{deviceLibraryIdentifier}/registrations/{passTypeIdentifier}/{serialNumber}`
- `GET /wallet/v1/passes/{passTypeIdentifier}/{serialNumber}`
- `DELETE /wallet/v1/devices/{deviceLibraryIdentifier}/registrations/{passTypeIdentifier}/{serialNumber}`
- `GET /wallet/v1/devices/{deviceLibraryIdentifier}/registrations/{passTypeIdentifier}`
- `POST /wallet/v1/log`

### 3. Test Registration Endpoint Manually

```bash
# Get account details
php artisan tinker --execute="
\$account = \App\Models\LoyaltyAccount::first();
\$serial = 'kawhe-' . \$account->store_id . '-' . \$account->customer_id;
echo 'Serial: ' . \$serial . PHP_EOL;
echo 'Token: ' . \$account->public_token . PHP_EOL;
"

# Test registration
curl -X POST https://testing.kawhe.shop/wallet/v1/devices/test-device-123/registrations/pass.com.kawhe.loyalty/kawhe-2-1 \
  -H "Content-Type: application/json" \
  -H "Authorization: ApplePass <PUBLIC_TOKEN>" \
  -d '{"pushToken":"test-push-token"}' \
  -v
```

**Expected:**
- Response: `201 Created` or `200 OK`
- Database: New row in `apple_wallet_registrations` table
- Logs: Registration logged with full details

### 4. Test on Real iPhone

1. **Generate a new pass** (old passes won't have webServiceURL)
2. **Download to iPhone** via `/wallet/apple/{public_token}/download`
3. **Add to Apple Wallet**
4. **Check nginx access.log:**
   ```bash
   tail -f /var/log/nginx/access.log | grep wallet/v1
   ```
   Should see: `POST /wallet/v1/devices/.../registrations/... 201`

5. **Check database:**
   ```bash
   php artisan tinker --execute="
   echo 'Active registrations: ' . \App\Models\AppleWalletRegistration::where('active', true)->count() . PHP_EOL;
   "
   ```

6. **Check logs:**
   ```bash
   tail -n 50 storage/logs/laravel.log | grep -i "registration"
   ```

## Verification Commands

Run the automated test script:

```bash
./test-apple-wallet-registration.sh
```

Or manually:

```bash
# 1. Check pass.json includes webServiceURL
unzip -p storage/app/private/passgenerator/passes/kawhe-2-2.pkpass pass.json | python3 -m json.tool | grep -E "webServiceURL|authenticationToken"

# 2. Check routes
php artisan route:list | grep wallet

# 3. Check registrations
php artisan tinker --execute="
\$count = \App\Models\AppleWalletRegistration::where('active', true)->count();
echo 'Active registrations: ' . \$count . PHP_EOL;
"

# 4. Check logs
tail -n 100 storage/logs/laravel.log | grep -i "wallet\|registration" | tail -10
```

## Acceptance Criteria

✅ **Pass Generation:**
- `pass.json` includes `webServiceURL` set to `{APP_URL}/wallet` (NOT `/wallet/v1`)
- `pass.json` includes `authenticationToken` set to account's `public_token`

✅ **Registration Endpoint:**
- Returns `201 Created` for new registrations
- Returns `200 OK` for existing registrations (idempotent)
- Creates/updates row in `apple_wallet_registrations` table
- Logs all registration attempts with full details

✅ **Real iPhone Test:**
- When pass is added to Apple Wallet, nginx access.log shows `POST /wallet/v1/devices/.../registrations/...`
- Database contains real `device_library_identifier` and `push_token`
- Logs show successful registration

✅ **Existing Functionality:**
- `/wallet/apple/{public_token}/download` still works
- No CSRF errors
- No 404 route mismatches

## Deployment Steps

1. **Deploy code:**
   ```bash
   git add .
   git commit -m "Fix Apple Wallet registration: Add webServiceURL and authenticationToken to passes"
   git push origin main
   ```

2. **On server:**
   ```bash
   cd /var/www/kawhe
   git pull origin main
   php artisan config:clear
   php artisan cache:clear
   php artisan route:clear
   ```

3. **Test:**
   ```bash
   ./test-apple-wallet-registration.sh
   ```

4. **Important:** Customers need to **re-download passes** - old passes won't have `webServiceURL` and won't register.

## Troubleshooting

### Issue: Still no registration requests

**Check:**
1. Pass includes `webServiceURL` (regenerate pass)
2. `webServiceURL` format is correct (`/wallet` not `/wallet/v1`)
3. Server is accessible from internet
4. HTTPS is properly configured
5. Check nginx error logs: `tail -f /var/log/nginx/error.log`

### Issue: 401 Unauthorized

**Check:**
1. `Authorization` header includes `ApplePass` prefix
2. Token matches the pass's `public_token` (or global config token)
3. Check middleware logs for authentication failures

### Issue: 404 Not Found

**Check:**
1. Routes are registered: `php artisan route:list | grep wallet`
2. Nginx forwards requests to Laravel correctly
3. CSRF is excluded for `wallet/v1/*`

### Issue: Registration created but no push notifications

**Note:** Push notifications are a separate feature. This fix only ensures registration works. Push notifications require:
- APNs configuration
- `WALLET_APPLE_PUSH_ENABLED=true`
- Valid APNs certificates

## Next Steps

Once registration is confirmed working:
1. Test pass retrieval: `GET /wallet/v1/passes/{passTypeIdentifier}/{serialNumber}`
2. Test stamp updates trigger pass regeneration
3. Implement APNs push notifications (Phase 2)
