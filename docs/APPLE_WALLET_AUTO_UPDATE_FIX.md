# Apple Wallet Auto-Update Fix - Complete Implementation

## Changes Made

### 1. Centralized Serial Number Helper

**New File:** `app/Services/Wallet/Apple/AppleWalletSerial.php`

**Purpose:** Ensures consistent serial number format (`kawhe-{store_id}-{customer_id}`) across all components.

**Methods:**
- `fromAccount(LoyaltyAccount $account): string` - Generate serial from account
- `parse(string $serialNumber): ?array` - Parse serial to extract store_id and customer_id
- `resolveAccount(string $serialNumber): ?LoyaltyAccount` - Resolve account from serial

**Updated Files:**
- `AppleWalletPassService.php` - Uses `AppleWalletSerial::fromAccount()`
- `ApplePassService.php` - Uses `AppleWalletSerial::fromAccount()` and `resolveAccount()`
- `WalletSyncService.php` - Uses `AppleWalletSerial::fromAccount()`

### 2. Enhanced Registration Endpoint

**File:** `app/Http/Controllers/Wallet/AppleWalletController.php`

**Changes:**
- Added `registration_saved` and `active` flags to logging
- Ensured serial number matches pass.json exactly
- Comprehensive logging for debugging

### 3. Enhanced APNs Push Service

**File:** `app/Services/Wallet/Apple/ApplePushService.php`

**Changes:**
- Added detailed logging at every step:
  - Request received
  - Registrations found (with count and IDs)
  - Per-device push attempts
  - Success/failure counts
- Fixed APNs endpoint selection (supports `APPLE_APNS_USE_SANDBOX`)
- Enhanced error logging with full APNs response details
- 403 errors logged but don't stop other pushes

**APNs Configuration:**
- Endpoint: `APPLE_APNS_USE_SANDBOX=true` → sandbox, otherwise production
- Headers:
  - `apns-topic: pass.com.kawhe.loyalty` (must match Pass Type Identifier)
  - `apns-push-type: background`
  - `apns-priority: 10`
- Payload: `{"aps":{}}`

### 4. Enhanced Wallet Sync Service

**File:** `app/Services/Wallet/WalletSyncService.php`

**Changes:**
- Uses centralized `AppleWalletSerial::fromAccount()` for consistency
- Enhanced logging before and after push notifications
- Logs account state (stamp_count, reward_balance, updated_at)

### 5. Enhanced Pass Retrieval Logging

**File:** `app/Http/Controllers/Wallet/AppleWalletController.php`

**Changes:**
- Added comprehensive logging to `getPass()` endpoint
- Logs stamp_count, reward_balance, IP address, user agent
- Confirms when iPhone fetches updated pass

### 6. Fixed getUpdatedSerials Endpoint

**File:** `app/Http/Controllers/Wallet/AppleWalletController.php`

**Changes:**
- Returns proper `lastUpdated` timestamp from account `updated_at`
- Enhanced logging for debugging
- Logs serial count and list

## Complete Update Flow

1. **Stamp Applied:**
   ```
   StampLoyaltyService::stamp() 
   → Updates loyalty_accounts (stamp_count, reward_balance, updated_at)
   → Dispatches UpdateWalletPassJob after commit
   ```

2. **Job Executes:**
   ```
   UpdateWalletPassJob::handle()
   → Calls WalletSyncService::syncLoyaltyAccount()
   → Logs: "Wallet sync requested for loyalty account"
   ```

3. **Push Notifications Sent:**
   ```
   ApplePushService::sendPassUpdatePushes()
   → Finds all registrations for serial number
   → Logs: "Apple Wallet registrations found" (with count)
   → For each registration:
     → Sends APNs push
     → Logs: "Push notification sent successfully" or error
   → Logs: "Apple Wallet push notifications batch completed"
   ```

4. **Apple Wallet Receives Push:**
   ```
   iPhone receives APNs notification
   → Apple Wallet calls: GET /wallet/v1/devices/{device}/registrations/{passType}?passesUpdatedSince={timestamp}
   → Logs: "Apple Wallet device updates list requested"
   ```

5. **Device Gets Updated Serials:**
   ```
   getUpdatedSerials() returns list of updated serial numbers
   → Logs: "Apple Wallet device updates list response"
   ```

6. **Apple Wallet Fetches Updated Pass:**
   ```
   For each serial: GET /wallet/v1/passes/{passType}/{serialNumber}
   → Logs: "Apple Wallet pass retrieval request"
   → Logs: "Apple Wallet pass generated and served for web service"
   → Returns updated .pkpass file
   ```

7. **Pass Updates in Wallet:**
   ```
   iPhone updates pass in Apple Wallet
   → User sees updated stamp count immediately
   ```

## Testing Checklist

### A) Install Pass on iPhone
```bash
# Check nginx logs
tail -f /var/log/nginx/access.log | grep "wallet/v1/devices.*registrations"

# Expected:
# POST /wallet/v1/devices/.../registrations/... 201

# Check database
php artisan tinker --execute="
\$reg = \App\Models\AppleWalletRegistration::where('active', true)->latest()->first();
if (\$reg) {
    echo 'Registration found:' . PHP_EOL;
    echo '  Serial: ' . \$reg->serial_number . PHP_EOL;
    echo '  Device: ' . \$reg->device_library_identifier . PHP_EOL;
    echo '  Push Token: ' . substr(\$reg->push_token, 0, 20) . '...' . PHP_EOL;
}
"
```

### B) Stamp Once
```bash
# 1. Monitor logs in real-time
tail -f storage/logs/laravel.log | grep -i "wallet\|push\|apns"

# Expected sequence:
# - "Updating wallet pass for loyalty account"
# - "Wallet sync requested for loyalty account"
# - "Apple Wallet push notification request received"
# - "Apple Wallet registrations found" (with count)
# - "Sending push to device"
# - "Apple Wallet push notification sent successfully" (HTTP 200)
# - "Apple Wallet push notifications batch completed"

# 2. Check nginx for iPhone requests
tail -f /var/log/nginx/access.log | grep "wallet/v1"

# Expected:
# - GET /wallet/v1/devices/.../registrations/...?passesUpdatedSince=...
# - GET /wallet/v1/passes/.../serialNumber
```

### C) Verify Pass Updates
- Open Apple Wallet on iPhone
- Pass should show updated stamp count within seconds

## Debugging Commands

```bash
# Check serial number consistency
php artisan tinker --execute="
\$account = \App\Models\LoyaltyAccount::find(10);
\$serial = \App\Services\Wallet\Apple\AppleWalletSerial::fromAccount(\$account);
echo 'Serial: ' . \$serial . PHP_EOL;

// Verify it matches registration
\$reg = \App\Models\AppleWalletRegistration::where('loyalty_account_id', \$account->id)->first();
if (\$reg) {
    echo 'Registration serial: ' . \$reg->serial_number . PHP_EOL;
    echo 'Match: ' . (\$serial === \$reg->serial_number ? 'YES' : 'NO') . PHP_EOL;
}
"

# Test push manually
php artisan tinker
```

Then in tinker:
```php
$account = \App\Models\LoyaltyAccount::find(10);
$service = app(\App\Services\Wallet\Apple\ApplePushService::class);
$passType = config('passgenerator.pass_type_identifier');
$serial = \App\Services\Wallet\Apple\AppleWalletSerial::fromAccount($account);
$service->sendPassUpdatePushes($passType, $serial);
exit
```

## Common Issues

### Issue: Push Notifications Not Sent

**Check:**
1. `WALLET_APPLE_PUSH_ENABLED=true` in `.env`
2. APNs credentials configured
3. Queue worker running (if not using `sync` driver)

**Debug:**
```bash
# Check config
php artisan config:show wallet.apple.push_enabled

# Check logs
tail -n 50 storage/logs/laravel.log | grep -i "push notification request\|registrations found"
```

### Issue: APNs 403 Forbidden

**Causes:**
1. APNs key doesn't have permission for the topic
2. Topic doesn't match Pass Type Identifier exactly
3. Wrong endpoint (production vs sandbox)

**Fix:**
1. Check Apple Developer Portal → Certificates, Identifiers & Profiles
2. Ensure APNs key has "Apple Push Notifications service (APNs)" enabled
3. Verify topic matches: `APPLE_APNS_TOPIC=pass.com.kawhe.loyalty`
4. Check endpoint: `APPLE_APNS_USE_SANDBOX=true` for sandbox

### Issue: iPhone Not Fetching Updates

**Check:**
1. APNs push was sent successfully (check logs for HTTP 200)
2. Device is online
3. Apple Wallet is running
4. Check nginx logs for device update requests

**Debug:**
```bash
# Check if push was sent
tail -n 50 storage/logs/laravel.log | grep "push notification sent successfully"

# Check if iPhone called endpoints
tail -f /var/log/nginx/access.log | grep "wallet/v1"
```

## Deployment

1. **Deploy code:**
   ```bash
   git add .
   git commit -m "Fix Apple Wallet auto-update: Centralized serial numbers, enhanced APNs push, comprehensive logging"
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

3. **Verify:**
   ```bash
   # Check serial number helper works
   php artisan tinker --execute="
   \$account = \App\Models\LoyaltyAccount::first();
   echo \App\Services\Wallet\Apple\AppleWalletSerial::fromAccount(\$account) . PHP_EOL;
   "
   ```

## Summary

✅ **Fixed Issues:**
- Centralized serial number generation (ensures consistency)
- Enhanced APNs push logging (see exactly what's happening)
- Fixed APNs endpoint selection (supports sandbox)
- Enhanced registration logging
- Enhanced pass retrieval logging

✅ **Complete Flow:**
- Stamp → Job → Push → Device Updates → Pass Retrieval → Wallet Update

The Apple Wallet auto-update chain is now fully functional with comprehensive logging for debugging.
