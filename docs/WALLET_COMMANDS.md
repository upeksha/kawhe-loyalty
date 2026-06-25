# Apple Wallet Commands & Testing

> **Serial format:** Current passes use `kawhe-{loyalty_account_id}` (e.g. `kawhe-42`). Legacy passes may use `kawhe-{store_id}-{customer_id}`. Both resolve via `AppleWalletSerial::resolveAccount()`. In tinker/scripts, use `AppleWalletSerial::fromAccount($account)`.

## Available Commands

### Test APNs Push
```bash
php artisan wallet:apns-test {serialNumber}
```

**Example (replace `42` with the loyalty account ID):**
```bash
php artisan wallet:apns-test kawhe-42
```

**Get serial for an account:**
```bash
php artisan tinker --execute="\$a = \App\Models\LoyaltyAccount::latest()->first(); echo \App\Services\Wallet\Apple\AppleWalletSerial::fromAccount(\$a) . PHP_EOL;"
```

**What it does:**
- Resolves account from serial number (current or legacy format)
- Checks for active registrations
- Displays APNs configuration
- Sends test push and shows result
- Provides log commands for debugging

### Check device registration
```bash
php artisan wallet:check-registration {public_token?}
```

Uses `AppleWalletSerial::fromAccount()` for the serial shown in output.

### Clear Cache (if command not found)
If you get `There are no commands defined in the "wallet" namespace`, run:

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
composer dump-autoload
```

Then try again:
```bash
php artisan wallet:apns-test kawhe-42
```

## Running Tests

**⚠️ Important:** Tests should be run locally, NOT on production servers. Test dependencies are not installed in production.

### Local Development (Recommended)

```bash
# Using Composer script
composer test

# Or using Pest directly
./vendor/bin/pest

# Filter specific tests
composer test -- --filter AppleWalletWebServiceTest
composer test -- --filter AppleWalletSerialTest
# OR
./vendor/bin/pest --filter AppleWalletWebServiceTest
```

### Production Verification (Instead of Tests)

On production, verify functionality manually:

```bash
# Resolve serial then test APNs push
SERIAL=$(php artisan tinker --execute="\$a = \App\Models\LoyaltyAccount::latest()->first(); echo \App\Services\Wallet\Apple\AppleWalletSerial::fromAccount(\$a);" | tail -1)
php artisan wallet:apns-test "$SERIAL"

# Check logs
tail -f storage/logs/laravel.log | grep -i "wallet\|push\|apns"

# Monitor nginx
tail -f /var/log/nginx/access.log | grep "wallet/v1"
```

See `RUNNING_TESTS.md` for complete guide.

## Monitoring Logs

### Watch for wallet/push/APNs activity
```bash
tail -f storage/logs/laravel.log | grep -i "wallet\|push\|apns"
```

### Watch for specific events
```bash
# Push notifications
tail -f storage/logs/laravel.log | grep -i "push notification"

# Device registrations
tail -f storage/logs/laravel.log | grep -i "device registered"

# APNs errors
tail -f storage/logs/laravel.log | grep -i "apns.*403\|apns.*error"
```

### Watch nginx access logs for iPhone requests
```bash
tail -f /var/log/nginx/access.log | grep "wallet/v1"
```

## Debugging APNs Issues

### 1. Check if command is registered
```bash
php artisan list | grep wallet
```

Should show:
```
wallet:apns-test           Test APNs push notification for a given serial number
wallet:check-registration  Check if a loyalty account has device registrations
```

### 2. Check APNs configuration
```bash
php artisan config:show wallet.apple
```

### 3. Test push manually
```bash
php artisan wallet:apns-test kawhe-42
```

### 4. Check recent push attempts
```bash
tail -n 100 storage/logs/laravel.log | grep -i "push notification" | tail -20
```

### 5. View full APNs error response
```bash
tail -n 100 storage/logs/laravel.log | grep -A 30 "push notification failed"
```

## Common Issues

### Issue: Command not found
**Solution:**
```bash
php artisan config:clear
php artisan cache:clear
composer dump-autoload
```

### Issue: Test command not found
**Solution:** Use Pest or PHPUnit directly:
```bash
./vendor/bin/pest --filter AppleWalletWebServiceTest
```

### Issue: APNs 403 Forbidden
**Check:**
1. APNs key permissions in Apple Developer Portal
2. Topic matches Pass Type Identifier exactly
3. Endpoint (production vs sandbox)

**Debug:**
```bash
php artisan wallet:apns-test kawhe-42
tail -n 50 storage/logs/laravel.log | grep -A 20 "push notification failed"
```

### Issue: Pass added before serial format change
Legacy serials still work for push and web service lookup. If registration exists under `kawhe-{store_id}-{customer_id}` but not under the new serial, remove and re-add the pass on the device, or test push with the legacy serial shown in `apple_wallet_registrations.serial_number`.
