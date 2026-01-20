# Apple Wallet Commands & Testing

## Available Commands

### Test APNs Push
```bash
php artisan wallet:apns-test {serialNumber}
```

**Example:**
```bash
php artisan wallet:apns-test kawhe-1-2
```

**What it does:**
- Resolves account from serial number
- Checks for active registrations
- Displays APNs configuration
- Sends test push and shows result
- Provides log commands for debugging

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
php artisan wallet:apns-test kawhe-1-2
```

## Running Tests

### Option 1: Using Laravel's test command (if available)
```bash
php artisan test --filter AppleWalletWebServiceTest
```

### Option 2: Using Pest directly
```bash
./vendor/bin/pest --filter AppleWalletWebServiceTest
```

### Option 3: Using PHPUnit directly
```bash
./vendor/bin/phpunit --filter AppleWalletWebServiceTest
```

### Run all tests
```bash
# Laravel
php artisan test

# Pest
./vendor/bin/pest

# PHPUnit
./vendor/bin/phpunit
```

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
wallet:apns-test    Test APNs push notification for a given serial number
```

### 2. Check APNs configuration
```bash
php artisan config:show wallet.apple
```

### 3. Test push manually
```bash
php artisan wallet:apns-test kawhe-1-2
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
php artisan wallet:apns-test kawhe-1-2
tail -n 50 storage/logs/laravel.log | grep -A 20 "push notification failed"
```
