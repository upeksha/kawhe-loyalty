# Apple Wallet Pass Web Service - Testing Guide

## Quick Verification Checklist

### 1. Verify Configuration

```bash
# Check all wallet config
php artisan config:show wallet

# Should show:
# - web_service_auth_token: (your token)
# - push_enabled: true
# - apns_key_id: 5JGMHRZC36
# - apns_team_id: 4XCV53NVXP
# - apns_auth_key_path: apns/AuthKey_5JGMHRZC36.p8
# - apns_topic: pass.com.kawhe.loyalty
# - apns_production: true
```

### 2. Verify APNs Key File

```bash
# Check file exists
ls -la storage/app/private/apns/AuthKey_5JGMHRZC36.p8

# Should show: -rw------- (600 permissions)
```

### 3. Verify Database Migration

```bash
# Check table exists
php artisan tinker
```

```php
Schema::hasTable('apple_wallet_registrations'); // Should return true
exit
```

### 4. Test Web Service Endpoints

#### A. Get a Test Loyalty Account

```bash
php artisan tinker
```

```php
// Create or get a test account
$user = \App\Models\User::first();
$store = \App\Models\Store::first();
$customer = \App\Models\Customer::first();
$account = \App\Models\LoyaltyAccount::firstOrCreate([
    'store_id' => $store->id,
    'customer_id' => $customer->id,
], [
    'stamp_count' => 0,
    'reward_balance' => 0,
]);

$serialNumber = "kawhe-{$store->id}-{$customer->id}";
echo "Serial Number: {$serialNumber}\n";
echo "Public Token: {$account->public_token}\n";
exit
```

#### B. Test Device Registration

```bash
# Replace YOUR_DOMAIN, SERIAL_NUMBER, and AUTH_TOKEN with actual values
curl -X POST https://YOUR_DOMAIN/wallet/v1/devices/test-device-123/registrations/pass.com.kawhe.loyalty/SERIAL_NUMBER \
  -H "Authorization: ApplePass YOUR_AUTH_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"pushToken": "test-push-token-456"}' \
  -v
```

**Expected Response:**
- `201 Created` - New registration
- `200 OK` - Already registered (idempotent)

#### C. Test Pass Retrieval

```bash
curl -X GET https://YOUR_DOMAIN/wallet/v1/passes/pass.com.kawhe.loyalty/SERIAL_NUMBER \
  -H "Authorization: ApplePass YOUR_AUTH_TOKEN" \
  --output test-pass.pkpass \
  -v
```

**Expected Response:**
- `200 OK` with Content-Type: `application/vnd.apple.pkpass`
- File should be a valid ZIP (pkpass is a ZIP file)
- Check: `file test-pass.pkpass` should show "Zip archive"

#### D. Test 304 Not Modified

```bash
# First request - note the Last-Modified header
curl -X GET https://YOUR_DOMAIN/wallet/v1/passes/pass.com.kawhe.loyalty/SERIAL_NUMBER \
  -H "Authorization: ApplePass YOUR_AUTH_TOKEN" \
  -v 2>&1 | grep -i "last-modified"

# Second request with If-Modified-Since (use the value from above)
curl -X GET https://YOUR_DOMAIN/wallet/v1/passes/pass.com.kawhe.loyalty/SERIAL_NUMBER \
  -H "Authorization: ApplePass YOUR_AUTH_TOKEN" \
  -H "If-Modified-Since: Mon, 15 Jan 2024 10:00:00 GMT" \
  -v
```

**Expected Response:**
- `304 Not Modified` (if pass hasn't changed)

#### E. Test Updated Serials List

```bash
curl -X GET "https://YOUR_DOMAIN/wallet/v1/devices/test-device-123/registrations/pass.com.kawhe.loyalty?passesUpdatedSince=0" \
  -H "Authorization: ApplePass YOUR_AUTH_TOKEN" \
  -v
```

**Expected Response:**
```json
{
  "lastUpdated": 1705320000,
  "serialNumbers": ["kawhe-1-2"]
}
```

#### F. Test Log Endpoint

```bash
curl -X POST https://YOUR_DOMAIN/wallet/v1/log \
  -H "Authorization: ApplePass YOUR_AUTH_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"logs": [{"level": "info", "message": "Test log"}]}' \
  -v
```

**Expected Response:**
- `200 OK`

### 5. Test Authentication

#### Test Invalid Token

```bash
curl -X POST https://YOUR_DOMAIN/wallet/v1/log \
  -H "Authorization: ApplePass wrong-token" \
  -H "Content-Type: application/json" \
  -d '{"logs": []}' \
  -v
```

**Expected Response:**
- `401 Unauthorized`

#### Test Missing Token

```bash
curl -X POST https://YOUR_DOMAIN/wallet/v1/log \
  -H "Content-Type: application/json" \
  -d '{"logs": []}' \
  -v
```

**Expected Response:**
- `401 Unauthorized`

### 6. Test Full Flow (Stamp → Push → Pass Update)

#### A. Register a Device

```bash
# Register device for a real account
curl -X POST https://YOUR_DOMAIN/wallet/v1/devices/real-device-123/registrations/pass.com.kawhe.loyalty/SERIAL_NUMBER \
  -H "Authorization: ApplePass YOUR_AUTH_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"pushToken": "REAL_DEVICE_PUSH_TOKEN"}' \
  -v
```

#### B. Add a Stamp (via your app or API)

```bash
# Via your stamping endpoint
curl -X POST https://YOUR_DOMAIN/stamp \
  -H "Authorization: Bearer YOUR_MERCHANT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "token": "LA:PUBLIC_TOKEN",
    "store_id": 1
  }' \
  -v
```

#### C. Check Queue Worker

```bash
# If using queue worker, check it's running
ps aux | grep "queue:work"

# Or check systemd service
systemctl status kawhe-queue

# Check queue logs
tail -f storage/logs/laravel.log | grep -i "wallet\|apns\|push"
```

#### D. Verify Push Was Sent

```bash
# Check logs for push notification attempts
tail -f storage/logs/laravel.log | grep -i "push"
```

**Look for:**
- "Sending Apple Wallet push notifications"
- "Apple Wallet push notification sent successfully"
- Or any error messages

### 7. Verify Database

```bash
php artisan tinker
```

```php
// Check registrations
\App\Models\AppleWalletRegistration::all();

// Check specific registration
\App\Models\AppleWalletRegistration::where('device_library_identifier', 'test-device-123')->first();

exit
```

### 8. Common Issues to Check

#### Issue: 500 Error on Pass Generation

**Check:**
```bash
# Verify pass generation works independently
php artisan tinker
```

```php
$account = \App\Models\LoyaltyAccount::first();
$service = app(\App\Services\Wallet\Apple\ApplePassService::class);
$pkpass = $service->generatePkpassForAccount($account);
echo "Pass size: " . strlen($pkpass) . " bytes\n";
exit
```

#### Issue: APNs Push Not Working

**Check:**
1. Verify APNs key file exists and is readable
2. Check logs for JWT generation errors
3. Verify cURL supports HTTP/2: `curl --version | grep HTTP`
4. Test JWT generation manually (see below)

#### Issue: 401 Unauthorized

**Check:**
```bash
# Verify token matches
php artisan tinker
```

```php
config('wallet.apple.web_service_auth_token');
exit
```

Compare with the token in your `.env` file.

### 9. Advanced Testing

#### Test JWT Generation (if APNs push fails)

```bash
php artisan tinker
```

```php
$service = app(\App\Services\Wallet\Apple\ApplePushService::class);
// This will test JWT generation
// Note: This uses reflection to access protected method
// Or check logs for JWT errors
exit
```

#### Test Pass Generation with Updated Data

```bash
php artisan tinker
```

```php
$account = \App\Models\LoyaltyAccount::first();
$account->stamp_count = 5;
$account->reward_balance = 1;
$account->save();

$service = app(\App\Services\Wallet\Apple\ApplePassService::class);
$pkpass = $service->generatePkpassForAccount($account);
file_put_contents('/tmp/test-pass.pkpass', $pkpass);
echo "Pass saved to /tmp/test-pass.pkpass\n";
exit
```

Then download and verify the pass shows updated stamp count.

### 10. Production Checklist

Before going live:

- [ ] HTTPS is enabled (required by Apple)
- [ ] Web service auth token is strong and secure
- [ ] APNs key file has 600 permissions
- [ ] Queue worker is running and monitored
- [ ] Logs are being monitored
- [ ] Error notifications are set up
- [ ] Test with a real iPhone and Apple Wallet

### Quick Test Script

Save this as `test-wallet.sh`:

```bash
#!/bin/bash

DOMAIN="your-domain.com"
AUTH_TOKEN="your-auth-token"
SERIAL_NUMBER="kawhe-1-2"

echo "Testing Apple Wallet Web Service..."
echo ""

echo "1. Testing registration..."
curl -X POST "https://${DOMAIN}/wallet/v1/devices/test-device-123/registrations/pass.com.kawhe.loyalty/${SERIAL_NUMBER}" \
  -H "Authorization: ApplePass ${AUTH_TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{"pushToken": "test-token"}' \
  -w "\nHTTP Status: %{http_code}\n" \
  -s -o /dev/null

echo ""
echo "2. Testing pass retrieval..."
curl -X GET "https://${DOMAIN}/wallet/v1/passes/pass.com.kawhe.loyalty/${SERIAL_NUMBER}" \
  -H "Authorization: ApplePass ${AUTH_TOKEN}" \
  -w "\nHTTP Status: %{http_code}\n" \
  -s -o /tmp/test-pass.pkpass

if [ -f /tmp/test-pass.pkpass ]; then
    echo "Pass file size: $(stat -f%z /tmp/test-pass.pkpass) bytes"
    file /tmp/test-pass.pkpass
fi

echo ""
echo "3. Testing log endpoint..."
curl -X POST "https://${DOMAIN}/wallet/v1/log" \
  -H "Authorization: ApplePass ${AUTH_TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{"logs": [{"level": "info", "message": "Test"}]}' \
  -w "\nHTTP Status: %{http_code}\n" \
  -s -o /dev/null

echo ""
echo "Testing complete!"
```

Make it executable and run:
```bash
chmod +x test-wallet.sh
./test-wallet.sh
```

## Monitoring

### Watch Logs in Real-Time

```bash
tail -f storage/logs/laravel.log | grep -E "wallet|apns|push|Apple"
```

### Check Queue Status

```bash
# If using database queue
php artisan queue:monitor

# Check failed jobs
php artisan queue:failed
```

## Success Indicators

✅ All endpoints return expected status codes  
✅ Pass files are valid ZIP archives  
✅ 304 Not Modified works correctly  
✅ Registrations are stored in database  
✅ Push notifications are sent (check logs)  
✅ No errors in logs  

If all tests pass, your Apple Wallet Pass Web Service is ready for production!
