# Debug 500 Server Errors - Step by Step Guide

## Step 1: Get the Actual Error Message

Run this on your server to see the real error:

```bash
cd /var/www/kawhe
tail -100 storage/logs/laravel.log | grep -A 3 "local.ERROR\|Exception\|Error" | head -50
```

Or get the most recent error:

```bash
tail -200 storage/logs/laravel.log | head -50
```

**Look for lines that say:**
- `local.ERROR`
- `Exception:`
- `SQLSTATE`
- `Call to undefined`
- `Class not found`

## Step 2: Check if Migrations Are Run

```bash
php artisan migrate:status
```

Look for any migrations that show "Pending". If you see Cashier migrations pending, run:

```bash
php artisan migrate --force
```

## Step 3: Check Database Schema

```bash
php artisan tinker
```

Then run:

```php
// Check users table columns
$columns = \DB::select("PRAGMA table_info(users)");
foreach($columns as $col) {
    echo $col->name . "\n";
}

// Check if stripe_id exists
$hasStripeId = collect($columns)->contains(function($col) { 
    return $col->name === 'stripe_id'; 
});
echo $hasStripeId ? "✓ stripe_id exists\n" : "✗ stripe_id MISSING\n";

// Check if subscriptions table exists
$hasSubs = \Schema::hasTable('subscriptions');
echo $hasSubs ? "✓ subscriptions table exists\n" : "✗ subscriptions table MISSING\n";
```

## Step 4: Clear All Caches

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
composer dump-autoload
```

## Step 5: Check File Permissions

```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

## Step 6: Test the Join Flow Manually

```bash
php artisan tinker
```

```php
// Test creating a customer
$store = \App\Models\Store::first();
echo "Store: " . $store->name . "\n";
echo "Store user_id: " . $store->user_id . "\n";

$merchant = $store->user;
if (!$merchant) {
    echo "ERROR: Store has no owner!\n";
} else {
    echo "Merchant: " . $merchant->email . "\n";
    
    // Test UsageService
    $usageService = app(\App\Services\Billing\UsageService::class);
    try {
        $canCreate = $usageService->canCreateCard($merchant);
        echo "Can create card: " . ($canCreate ? "YES" : "NO") . "\n";
    } catch (\Exception $e) {
        echo "ERROR in canCreateCard: " . $e->getMessage() . "\n";
        echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
}
```

## Step 7: Check PHP Error Log

```bash
tail -50 /var/log/php*-fpm.log
# or
tail -50 /var/log/nginx/error.log
```

## Step 8: Enable Debug Mode (Temporarily)

Edit `.env`:

```env
APP_DEBUG=true
APP_LOG_LEVEL=debug
```

Then check the browser - you should see the actual error message.

**IMPORTANT**: Set `APP_DEBUG=false` after debugging!

## Step 9: Test Card View Directly

```bash
php artisan tinker
```

```php
$account = \App\Models\LoyaltyAccount::first();
if ($account) {
    echo "Account public_token: " . $account->public_token . "\n";
    echo "Test URL: https://app.kawhe.shop/c/" . $account->public_token . "\n";
    
    // Try loading the account with relationships
    $account->load(['store', 'customer']);
    echo "Store: " . $account->store->name . "\n";
    echo "Customer: " . ($account->customer->name ?? 'N/A') . "\n";
} else {
    echo "No loyalty accounts found\n";
}
```

## Common Issues and Fixes

### Issue 1: Missing Cashier Migrations
**Fix:**
```bash
php artisan migrate --force
```

### Issue 2: Store Has No Owner (user_id is null)
**Fix:** Check your stores table:
```sql
SELECT id, name, user_id FROM stores WHERE user_id IS NULL;
```

### Issue 3: Schema::hasColumn Failing
**Fix:** The code now has error handling, but if it still fails, we can use a different approach.

### Issue 4: View File Missing
**Fix:** Check if view files exist:
```bash
ls -la resources/views/join/
ls -la resources/views/card/
```

### Issue 5: Class Not Found
**Fix:**
```bash
composer dump-autoload
php artisan config:clear
```
