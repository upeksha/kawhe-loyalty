# Apple Wallet Push Notifications - Production Debugging Guide

## Current Status

**Problem:** Push notifications are not working in production.

**Root Cause:** `Active registrations: 0` - No devices have registered for push notifications yet.

## Why This Happens

Apple Wallet **automatically** registers devices when a pass is added to the wallet, but **only if**:

1. ✅ The pass includes a `webServiceURL` in its manifest
2. ✅ The pass is properly signed with a valid certificate
3. ✅ The device can reach your server
4. ✅ The registration endpoint responds correctly

## Step-by-Step Debugging

### 1. Check if Passes Include Web Service URL

The pass must include `webServiceURL` in its manifest. Check your pass generation:

```bash
# On your server, check if passes are being generated with webServiceURL
php artisan tinker
```

Then:
```php
$account = \App\Models\LoyaltyAccount::first();
$service = app(\App\Services\Wallet\AppleWalletPassService::class);
$passPath = $service->generatePass($account);

// Extract and check manifest
$zip = new ZipArchive();
$zip->open($passPath);
$manifest = json_decode($zip->getFromName('manifest.json'), true);
$passJson = json_decode($zip->getFromName('pass.json'), true);

echo "Web Service URL: " . ($passJson['webServiceURL'] ?? 'NOT SET') . "\n";
echo "Authentication Token: " . ($passJson['authenticationToken'] ?? 'NOT SET') . "\n";
exit
```

### 2. Test Registration Endpoint Manually

Test if the registration endpoint is accessible:

```bash
# Get a loyalty account serial number
php artisan tinker --execute="
\$account = \App\Models\LoyaltyAccount::first();
\$serial = 'kawhe-' . \$account->store_id . '-' . \$account->customer_id;
echo 'Serial: ' . \$serial . PHP_EOL;
echo 'Public Token: ' . \$account->public_token . PHP_EOL;
"

# Then test registration (replace with your values)
curl -X POST https://app.kawhe.shop/wallet/v1/devices/test-device-123/registrations/pass.com.kawhe.loyalty/kawhe-2-1 \
  -H "Authorization: ApplePass 658b39f8f3f73f0de6ac8bdb2643b09b3e85cc8a0ff04304d9911b8b26e7b45a" \
  -H "Content-Type: application/json" \
  -d '{"pushToken": "test-token-12345"}' \
  -v
```

Expected response: `201 Created` or `200 OK`

### 3. Check Logs for Registration Attempts

```bash
# Check if Apple Wallet is trying to register
tail -n 100 storage/logs/laravel.log | grep -i "registration\|wallet\|device" | tail -20

# Or watch logs in real-time
tail -f storage/logs/laravel.log | grep -i "wallet\|registration"
```

### 4. Verify Pass Generation Includes Web Service URL

**CRITICAL:** The pass must include `webServiceURL` in `pass.json`. Check your `AppleWalletPassService`:

```php
// In pass.json, you need:
{
  "webServiceURL": "https://app.kawhe.shop/wallet/v1",
  "authenticationToken": "658b39f8f3f73f0de6ac8bdb2643b09b3e85cc8a0ff04304d9911b8b26e7b45a",
  // ... other pass fields
}
```

### 5. Test Full Flow

1. **Generate a new pass** with webServiceURL
2. **Download it** to an iPhone
3. **Add it to Apple Wallet**
4. **Check logs** - you should see a registration request
5. **Check database** - registration should appear

```bash
# After adding pass to wallet, check registrations
php artisan tinker --execute="
\$count = \App\Models\AppleWalletRegistration::where('active', true)->count();
echo 'Active registrations: ' . \$count . PHP_EOL;
if (\$count > 0) {
    \$reg = \App\Models\AppleWalletRegistration::where('active', true)->first();
    echo 'Device: ' . \$reg->device_library_identifier . PHP_EOL;
    echo 'Serial: ' . \$reg->serial_number . PHP_EOL;
}
"
```

### 6. Common Issues

#### Issue: Pass doesn't include webServiceURL

**Fix:** Update `AppleWalletPassService::generatePass()` to include:
```php
$passDefinition['webServiceURL'] = config('app.url') . '/wallet/v1';
$passDefinition['authenticationToken'] = config('wallet.apple.web_service_auth_token');
```

#### Issue: Registration endpoint returns 404

**Check:**
- Routes are properly defined in `routes/web.php`
- CSRF is excluded for `/wallet/v1/*`
- Middleware is correctly applied

#### Issue: Device can't reach server

**Check:**
- Server is accessible from internet
- HTTPS is properly configured
- No firewall blocking requests

#### Issue: Pass is not signed correctly

**Check:**
- Certificate is valid
- Pass Type Identifier matches
- Certificate is not expired

## Testing Checklist

- [ ] Pass includes `webServiceURL` in `pass.json`
- [ ] Pass includes `authenticationToken` in `pass.json`
- [ ] Registration endpoint is accessible (test with curl)
- [ ] Registration endpoint returns 201/200
- [ ] After adding pass to wallet, registration appears in database
- [ ] After stamping, push notification is sent
- [ ] Logs show registration attempts
- [ ] Logs show push notification attempts

## Next Steps

1. **Verify pass generation includes webServiceURL** (most likely missing)
2. **Regenerate passes** with webServiceURL included
3. **Re-download passes** to devices
4. **Check registrations** appear in database
5. **Test stamping** to trigger push notifications

## Quick Test Command

Run this on your server to check everything:

```bash
# Check config
php artisan config:show wallet.apple

# Check registrations
php artisan tinker --execute="
echo 'Active registrations: ' . \App\Models\AppleWalletRegistration::where('active', true)->count() . PHP_EOL;
"

# Check recent wallet logs
tail -n 50 storage/logs/laravel.log | grep -i "wallet\|registration\|push" | tail -10
```
