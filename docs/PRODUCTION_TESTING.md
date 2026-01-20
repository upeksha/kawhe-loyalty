# Running Tests on Production Server

## Option 1: Install Dev Dependencies Temporarily (Not Recommended)

**Warning:** Only do this temporarily for debugging. Remove dev dependencies after testing.

```bash
# On production server
cd /var/www/kawhe

# Install dev dependencies
composer install

# Run tests
./vendor/bin/pest --filter AppleWalletWebServiceTest

# Or run all tests
./vendor/bin/pest

# IMPORTANT: Remove dev dependencies after testing
composer install --no-dev --optimize-autoloader
```

## Option 2: Verify Functionality Without Tests (Recommended)

Instead of running tests, verify the functionality works:

### 1. Test APNs Push Command
```bash
php artisan wallet:apns-test kawhe-1-2
```

### 2. Check if Registration Endpoint Works
```bash
# Get a real serial number from your database
php artisan tinker --execute="
\$account = \App\Models\LoyaltyAccount::whereHas('appleWalletRegistrations', function(\$q) {
    \$q->where('active', true);
})->first();
if (\$account) {
    \$serial = \App\Services\Wallet\Apple\AppleWalletSerial::fromAccount(\$account);
    echo 'Serial: ' . \$serial . PHP_EOL;
    echo 'Account ID: ' . \$account->id . PHP_EOL;
}
"
```

### 3. Test Device Registration Endpoint (Manual)
```bash
# Get serial and device info
SERIAL="kawhe-1-2"  # Replace with actual serial
DEVICE="test-device-123"
PUSH_TOKEN=$(openssl rand -hex 32)  # Generate 64-char token
AUTH_TOKEN="your-account-public-token"  # Get from database

# Test registration
curl -X POST "https://your-domain.com/wallet/v1/devices/${DEVICE}/registrations/pass.com.kawhe.loyalty/${SERIAL}" \
  -H "Authorization: ApplePass ${AUTH_TOKEN}" \
  -H "Content-Type: application/json" \
  -d "{\"pushToken\":\"${PUSH_TOKEN}\"}"
```

### 4. Test Device Updates List Endpoint
```bash
# This should work WITHOUT authentication
curl "https://your-domain.com/wallet/v1/devices/${DEVICE}/registrations/pass.com.kawhe.loyalty?passesUpdatedSince=0"
```

### 5. Monitor Logs in Real-Time
```bash
# Watch for wallet activity
tail -f storage/logs/laravel.log | grep -i "wallet\|push\|apns\|registration"

# Watch nginx access logs
tail -f /var/log/nginx/access.log | grep "wallet/v1"
```

### 6. Test Full Flow Manually

1. **Install pass on iPhone** (if you have one)
2. **Check registration saved:**
   ```bash
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

3. **Apply a stamp** (via scanner or manually)
   ```bash
   php artisan tinker --execute="
   \$account = \App\Models\LoyaltyAccount::first();
   \$account->increment('stamp_count');
   \$account->touch();  // Update updated_at
   echo 'Stamp count: ' . \$account->stamp_count . PHP_EOL;
   "
   ```

4. **Trigger wallet sync manually:**
   ```bash
   php artisan tinker --execute="
   \$account = \App\Models\LoyaltyAccount::first();
   \$service = app(\App\Services\Wallet\WalletSyncService::class);
   \$service->syncLoyaltyAccount(\$account);
   echo 'Wallet sync triggered' . PHP_EOL;
   "
   ```

5. **Check logs for push notification:**
   ```bash
   tail -n 50 storage/logs/laravel.log | grep -i "push notification"
   ```

## Quick Verification Checklist

- [ ] `php artisan wallet:apns-test` command works
- [ ] Device registration endpoint accepts POST requests
- [ ] Device updates list endpoint returns 200/204 (no auth required)
- [ ] Log endpoint accepts POST requests (no auth required)
- [ ] Pass download endpoint requires auth and returns pkpass
- [ ] Logs show push notifications being sent
- [ ] Nginx logs show iPhone requests to wallet endpoints

## Recommended Approach

**For production:** Use Option 2 (manual verification) instead of running tests. Tests are meant for:
- Local development
- CI/CD pipelines
- Pre-deployment validation

Production should be verified through:
- Manual testing with real devices
- Monitoring logs
- Using the `wallet:apns-test` command
- Checking database state
