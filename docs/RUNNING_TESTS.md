# Running Tests - Guide

## Important Note

**Tests should NOT be run on production servers.** They are for local development and CI/CD pipelines only.

## Why Tests Aren't Available on Production

- Test dependencies (Pest, PHPUnit) are in `require-dev`
- Production uses `composer install --no-dev` (excludes dev dependencies)
- This is a security and performance best practice

## Running Tests Locally

### Option 1: Using Composer Script (Recommended)
```bash
composer test
```

This runs:
```bash
php artisan config:clear
php artisan test
```

### Option 2: Using Pest Directly
```bash
./vendor/bin/pest
```

### Option 3: Filter Specific Tests
```bash
# Using composer
composer test -- --filter AppleWalletWebServiceTest

# Using Pest directly
./vendor/bin/pest --filter AppleWalletWebServiceTest
```

## Verifying Functionality on Production

Instead of running tests, verify functionality manually:

### 1. Test APNs Push Command
```bash
php artisan wallet:apns-test kawhe-1-2
```

### 2. Check Logs
```bash
tail -f storage/logs/laravel.log | grep -i "wallet\|push\|apns"
```

### 3. Monitor Nginx Access Logs
```bash
tail -f /var/log/nginx/access.log | grep "wallet/v1"
```

### 4. Check Registration in Database
```bash
php artisan tinker --execute="
\$reg = \App\Models\AppleWalletRegistration::where('active', true)->latest()->first();
if (\$reg) {
    echo 'Serial: ' . \$reg->serial_number . PHP_EOL;
    echo 'Device: ' . \$reg->device_library_identifier . PHP_EOL;
    echo 'Active: ' . (\$reg->active ? 'Yes' : 'No') . PHP_EOL;
}
"
```

## If You MUST Run Tests on Production (Not Recommended)

**Warning:** Only do this temporarily for debugging. Remove dev dependencies after.

```bash
# Install dev dependencies
composer install

# Run tests
./vendor/bin/pest --filter AppleWalletWebServiceTest

# Remove dev dependencies after (recommended)
composer install --no-dev --optimize-autoloader
```

## Recommended Workflow

1. **Local Development:**
   ```bash
   composer test
   ```

2. **Before Deploying:**
   - Run tests locally
   - Commit and push
   - Deploy to production

3. **On Production:**
   - Use manual verification commands
   - Monitor logs
   - Use `wallet:apns-test` command

## CI/CD Integration

For automated testing, set up CI/CD (GitHub Actions, GitLab CI, etc.) to:
- Run tests on every push
- Run tests before deployment
- Never run tests on production servers
