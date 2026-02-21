# Fix APNs Push Notifications - Step by Step

## Step 1: Verify APNs Configuration in .env

Check your `.env` file has these variables:

```bash
# Enable push notifications
WALLET_APPLE_PUSH_ENABLED=true

# APNs Configuration
APPLE_APNS_KEY_ID=5JGMHRZC36
APPLE_APNS_TEAM_ID=4XCV53NVXP
APPLE_APNS_AUTH_KEY_PATH=storage/app/private/apns/AuthKey_5JGMHRZC36.p8
APPLE_APNS_TOPIC=pass.com.kawhe.loyalty

# Use sandbox for testing, production for live
APPLE_APNS_USE_SANDBOX=false  # false = production, true = sandbox
```

**On your server, check:**
```bash
cd /var/www/kawhe
grep -E "WALLET_APPLE_PUSH|APPLE_APNS" .env
```

## Step 2: Verify APNs Key File Exists

```bash
# Check if key file exists
ls -la storage/app/private/apns/AuthKey_5JGMHRZC36.p8

# If it doesn't exist, check alternative locations
find . -name "*.p8" -type f 2>/dev/null
```

**If file is missing:**
1. Download your APNs key from Apple Developer Portal
2. Place it at: `storage/app/private/apns/AuthKey_5JGMHRZC36.p8`
3. Set correct permissions:
   ```bash
   chmod 600 storage/app/private/apns/AuthKey_5JGMHRZC36.p8
   chown www-data:www-data storage/app/private/apns/AuthKey_5JGMHRZC36.p8
   ```

## Step 3: Verify Configuration is Loaded

```bash
php artisan config:show wallet.apple
```

**Expected output:**
```
wallet.apple .....................................................................................................................................
  web_service_auth_token .......................................................... [your-token]
  push_enabled ................................................................................................................................ true
  apns_key_id ........................................................................................................................... 5JGMHRZC36
  apns_team_id .......................................................................................................................... 4XCV53NVXP
  apns_auth_key_path ................................................................................ storage/app/private/apns/AuthKey_5JGMHRZC36.p8
  apns_topic ................................................................................................................ pass.com.kawhe.loyalty
  apns_production ............................................................................................................................. true
```

**If values are wrong:**
```bash
php artisan config:clear
php artisan config:cache
php artisan config:show wallet.apple
```

## Step 4: Verify Device Registration

Check if devices are registered:

```bash
php artisan tinker
```

```php
// Check registrations
$regs = \App\Models\AppleWalletRegistration::where('active', true)->get();
echo "Active registrations: " . $regs->count() . "\n";

foreach ($regs as $reg) {
    echo "Serial: {$reg->serial_number}, Device: {$reg->device_library_identifier}\n";
    echo "Push Token: " . substr($reg->push_token, 0, 20) . "...\n";
}
exit
```

**If no registrations:**
- Add the pass to Apple Wallet on iPhone first
- Apple Wallet will automatically register the device
- Check logs: `tail -f storage/logs/laravel.log | grep "Apple Wallet device registered"`

## Step 5: Test APNs Push Manually

```bash
# Get serial number from an account
php artisan tinker --execute="
\$account = \App\Models\LoyaltyAccount::first();
echo 'Serial: kawhe-' . \$account->store_id . '-' . \$account->customer_id . PHP_EOL;
"
```

Then test push:
```bash
php artisan wallet:apns-test kawhe-1-10
```

**Expected output:**
```
✓ Push notification sent successfully!
```

**If you get errors, check:**
- Key file permissions
- Key ID matches the filename
- Team ID is correct
- Topic matches pass type identifier

## Step 6: Check APNs Logs

```bash
# Watch logs in real-time
tail -f storage/logs/laravel.log | grep -i "apns\|push\|wallet"
```

**Look for:**
- `Apple Wallet push notification sent successfully` (HTTP 200)
- `Apple Wallet push notification failed` (with error details)
- `APNs 403 Forbidden` (authentication issue)
- `APNs 410 Gone` (invalid device token)

## Step 7: Verify Pass Has webServiceURL

The pass must have `webServiceURL` and `authenticationToken` for push to work:

```bash
php artisan tinker
```

```php
$account = \App\Models\LoyaltyAccount::first();
$service = app(\App\Services\Wallet\AppleWalletPassService::class);
$pkpass = $service->generatePass($account);

// Extract pass.json
$tempFile = tempnam(sys_get_temp_dir(), 'pass');
file_put_contents($tempFile, $pkpass);
$zip = new \ZipArchive();
$zip->open($tempFile);
$passJson = json_decode($zip->getFromName('pass.json'), true);
$zip->close();
unlink($tempFile);

echo "webServiceURL: " . ($passJson['webServiceURL'] ?? 'NOT SET') . "\n";
echo "authenticationToken: " . ($passJson['authenticationToken'] ?? 'NOT SET') . "\n";
exit
```

**Expected:**
- `webServiceURL`: `https://app.kawhe.shop/wallet`
- `authenticationToken`: The account's `wallet_auth_token`

## Step 8: Test Full Flow

1. **Add pass to iPhone Wallet**
   - Visit card page: `https://app.kawhe.shop/c/{public_token}`
   - Click "Add to Apple Wallet"
   - Verify pass appears in Wallet

2. **Check registration was created:**
   ```bash
   php artisan tinker --execute="
   echo 'Registrations: ' . \App\Models\AppleWalletRegistration::where('active', true)->count() . PHP_EOL;
   "
   ```

3. **Stamp the account:**
   - Scan QR code from Wallet
   - Or use tinker:
     ```php
     $account = \App\Models\LoyaltyAccount::first();
     $user = \App\Models\User::first();
     $service = app(\App\Services\Loyalty\StampLoyaltyService::class);
     $result = $service->stamp($account, $user, 1);
     echo "Stamped! New count: {$result->stampCount}\n";
     ```

4. **Check logs for push:**
   ```bash
   tail -n 50 storage/logs/laravel.log | grep -i "push"
   ```

5. **Verify pass updates in Wallet:**
   - Open Wallet on iPhone
   - Pass should show updated stamp count

## Common Issues & Fixes

### Issue: "Push notifications are disabled"
**Fix:**
```bash
# In .env
WALLET_APPLE_PUSH_ENABLED=true

# Clear config
php artisan config:clear
php artisan config:cache
```

### Issue: "APNs key file not found"
**Fix:**
```bash
# Check file exists
ls -la storage/app/private/apns/AuthKey_5JGMHRZC36.p8

# If missing, upload the .p8 file
# Then set permissions
chmod 600 storage/app/private/apns/AuthKey_5JGMHRZC36.p8
chown www-data:www-data storage/app/private/apns/AuthKey_5JGMHRZC36.p8
```

### Issue: "403 Forbidden" from APNs
**Possible causes:**
1. **Wrong Topic**: Must match `passTypeIdentifier`
   ```bash
   php artisan config:show passgenerator.pass_type_identifier
   php artisan config:show wallet.apple.apns_topic
   # These should match!
   ```

2. **Wrong Key ID**: Must match filename
   ```bash
   # Key file: AuthKey_5JGMHRZC36.p8
   # Config: APPLE_APNS_KEY_ID=5JGMHRZC36
   # These should match!
   ```

3. **Wrong Team ID**: Check Apple Developer account
   ```bash
   php artisan config:show wallet.apple.apns_team_id
   ```

4. **Key file permissions**: Must be readable
   ```bash
   chmod 600 storage/app/private/apns/AuthKey_5JGMHRZC36.p8
   ```

### Issue: "No registrations found"
**Fix:**
- Add pass to iPhone Wallet first
- Apple Wallet will call registration endpoint automatically
- Check logs: `tail -f storage/logs/laravel.log | grep "device registered"`

### Issue: "410 Gone" (Invalid device token)
**Fix:**
- Device token expired or invalid
- Registration is automatically deactivated
- User needs to re-add pass to Wallet

## Debugging Commands

### Check APNs Configuration
```bash
php artisan config:show wallet.apple.push_enabled
php artisan config:show wallet.apple.apns_key_id
php artisan config:show wallet.apple.apns_team_id
php artisan config:show wallet.apple.apns_topic
php artisan config:show wallet.apple.apns_production
```

### Check Key File
```bash
ls -la storage/app/private/apns/AuthKey_5JGMHRZC36.p8
file storage/app/private/apns/AuthKey_5JGMHRZC36.p8
```

### Test JWT Generation
```bash
php artisan tinker
```

```php
$service = app(\App\Services\Wallet\Apple\ApplePushService::class);
// Check if service can read key file
$keyPath = config('wallet.apple.apns_auth_key_path');
echo "Key path: {$keyPath}\n";
echo "File exists: " . (file_exists($keyPath) ? 'Yes' : 'No') . "\n";
echo "File readable: " . (is_readable($keyPath) ? 'Yes' : 'No') . "\n";
exit
```

### Monitor Push Attempts
```bash
# Watch logs in real-time
tail -f storage/logs/laravel.log | grep -E "push|apns|APNs"
```

## Complete Verification Checklist

- [ ] `WALLET_APPLE_PUSH_ENABLED=true` in `.env`
- [ ] APNs key file exists at correct path
- [ ] Key file has correct permissions (600)
- [ ] `APPLE_APNS_KEY_ID` matches key filename
- [ ] `APPLE_APNS_TEAM_ID` is correct
- [ ] `APPLE_APNS_TOPIC` matches `passTypeIdentifier`
- [ ] Config cache cleared and rebuilt
- [ ] Device is registered (check database)
- [ ] Pass has `webServiceURL` and `authenticationToken`
- [ ] Test push command works: `php artisan wallet:apns-test {serial}`
- [ ] Logs show successful push (HTTP 200)

## Quick Fix Script

Run this on your server to check everything:

```bash
#!/bin/bash
cd /var/www/kawhe

echo "=== APNs Configuration Check ==="
php artisan config:show wallet.apple.push_enabled
php artisan config:show wallet.apple.apns_key_id
php artisan config:show wallet.apple.apns_team_id
php artisan config:show wallet.apple.apns_topic

echo ""
echo "=== Key File Check ==="
KEY_PATH=$(php artisan tinker --execute="echo config('wallet.apple.apns_auth_key_path');" | tail -1)
echo "Key path: $KEY_PATH"
if [ -f "$KEY_PATH" ]; then
    echo "✓ File exists"
    ls -la "$KEY_PATH"
else
    echo "✗ File NOT found!"
fi

echo ""
echo "=== Registrations Check ==="
php artisan tinker --execute="echo 'Active registrations: ' . \App\Models\AppleWalletRegistration::where('active', true)->count() . PHP_EOL;"

echo ""
echo "=== Test Push ==="
SERIAL=$(php artisan tinker --execute="\$a = \App\Models\LoyaltyAccount::first(); echo 'kawhe-' . \$a->store_id . '-' . \$a->customer_id . PHP_EOL;" | tail -1)
echo "Testing serial: $SERIAL"
php artisan wallet:apns-test "$SERIAL"
```

Save as `check-apns.sh`, make executable, and run:
```bash
chmod +x check-apns.sh
./check-apns.sh
```
