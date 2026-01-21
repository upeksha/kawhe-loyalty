# Debug Scanner Server Errors

## Step 1: Pull Latest Changes

```bash
cd /var/www/kawhe
git pull origin main
php artisan config:clear
php artisan config:cache
```

## Step 2: Run Diagnostic Test

```bash
php test-scan-flow.php
```

This will test all components and show what's failing.

## Step 3: Check Recent Errors

```bash
# Get last 50 lines with errors
tail -n 200 storage/logs/laravel.log | grep -A 15 -i "error\|exception" | tail -n 50

# Or check for specific scanner errors
tail -n 500 storage/logs/laravel.log | grep -i "scanner\|stamp\|redeem" | tail -n 30
```

## Step 4: Check Queue Status

```bash
# Check if queue worker is running
ps aux | grep "queue:work"

# Check pending jobs
php artisan queue:monitor

# Or check database queue directly
php artisan tinker --execute="echo DB::table('jobs')->count() . ' pending jobs';"
```

## Step 5: Test a Scan Manually

```bash
php artisan tinker
```

Then in tinker:
```php
$account = \App\Models\LoyaltyAccount::with(['store', 'customer'])->first();
$user = \App\Models\User::whereHas('stores', function($q) use ($account) {
    $q->where('stores.id', $account->store_id);
})->first();

$service = app(\App\Services\Loyalty\StampLoyaltyService::class);
$result = $service->stamp($account, $user, 1, 'test-' . time());
echo "Success! Stamp count: {$result->stampCount}\n";
```

## Step 6: Check for Common Issues

### Memory Issues
```bash
php -i | grep memory_limit
```

### Database Connection
```bash
php artisan tinker --execute="DB::connection()->getPdo(); echo 'DB OK';"
```

### Broadcasting
```bash
php artisan tinker --execute="echo config('broadcasting.default');"
```

## Step 7: Monitor Logs in Real-Time

In one terminal:
```bash
tail -f storage/logs/laravel.log
```

In another terminal, try scanning. Watch for errors.

## Step 8: Check for Transaction Issues

```bash
php artisan tinker --execute="
\$account = \App\Models\LoyaltyAccount::first();
echo 'Account ID: ' . \$account->id . PHP_EOL;
echo 'Stamp count: ' . \$account->stamp_count . PHP_EOL;
echo 'Reward balance: ' . (\$account->reward_balance ?? 0) . PHP_EOL;
echo 'Redeem token: ' . (\$account->redeem_token ? 'Set' : 'Null') . PHP_EOL;
"
```

## Common Causes of Intermittent Errors

1. **Queue Worker Not Running**: Jobs pile up and cause timeouts
2. **Database Connection Issues**: Intermittent connection failures
3. **Memory Limits**: Running out of memory on large operations
4. **Transaction Timeouts**: Long-running transactions
5. **Event Broadcasting Failures**: Reverb not configured or down
6. **Race Conditions**: Multiple simultaneous scans

## Quick Fixes

### If Queue Worker Issues:
```bash
# Stop all queue workers
pkill -f "queue:work"

# Start fresh queue worker
php artisan queue:work --sleep=1 --tries=3 --timeout=120 &
```

### If Memory Issues:
```bash
# Check PHP memory limit
php -i | grep memory_limit

# Increase if needed (in php.ini or .htaccess)
# memory_limit = 256M
```

### If Database Issues:
```bash
# Check database connection
php artisan db:show

# Check for locked tables
php artisan tinker --execute="DB::select('SHOW PROCESSLIST');"
```

## What to Look For in Logs

When the error occurs, check logs for:
- `Failed to dispatch UpdateWalletPassJob`
- `Failed to dispatch StampUpdated event`
- `Error formatting stamp response`
- `Database connection` errors
- `Memory exhausted` errors
- `Transaction` errors

## Next Steps

After running the diagnostic test, share the output and we can identify the exact issue.
