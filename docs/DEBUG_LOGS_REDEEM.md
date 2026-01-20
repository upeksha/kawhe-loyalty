# Debug: No Logs Appearing for Redemption

## Issue
When running `tail -f storage/logs/laravel.log | grep -E "UpdateWalletPassJob|redeem|WalletSyncService"`, nothing appears.

## Possible Reasons

### 1. No Redemption is Happening
The grep is waiting for logs, but you haven't redeemed a reward yet.

**Solution:** Actually perform a redemption while watching logs.

### 2. Logs Are Being Written to Different File
Laravel might be using a different log channel or file.

**Check:**
```bash
# Check all log files
ls -la storage/logs/

# Check if logs are being written at all
tail -f storage/logs/laravel.log
# (without grep - just watch all logs)
```

### 3. Log Level Too High
If `APP_DEBUG=false` and log level is high, some logs might not be written.

**Check:**
```bash
php artisan config:show logging.default
php artisan config:show logging.channels.single.level
```

### 4. Job Not Being Dispatched
This confirms the bug - the job isn't being dispatched at all.

**Check:**
```bash
# Watch ALL logs (no grep)
tail -f storage/logs/laravel.log

# Then redeem a reward and see what appears
```

### 5. Queue Connection Issue
If using database queue and worker not running, jobs are queued but not processed.

**Check:**
```bash
# Check if jobs are being queued
php artisan tinker
\DB::table('jobs')->count();
exit

# Check if queue worker is running
ps aux | grep queue:work
```

## Step-by-Step Debugging

### Step 1: Check Logs Are Working

```bash
# Watch ALL logs (no filter)
tail -f storage/logs/laravel.log
```

Then perform ANY action (login, visit a page). You should see logs appearing.

**If no logs appear:**
- Check log file permissions: `ls -la storage/logs/laravel.log`
- Check log directory permissions: `ls -la storage/logs/`
- Check Laravel config: `php artisan config:show logging`

### Step 2: Test Redemption and Watch Logs

**Terminal 1 - Watch logs:**
```bash
tail -f storage/logs/laravel.log
```

**Terminal 2 - Perform redemption:**
- Go to scanner
- Scan a redeem QR code (LR: token)
- Or use tinker to redeem

**What to look for:**
- `Dispatching StampUpdated event (redeem)` - should appear
- `UpdateWalletPassJob` - might NOT appear (this is the bug!)
- Any errors or exceptions

### Step 3: Check if Job is Queued

```bash
php artisan tinker
```

```php
// Before redemption
$before = \DB::table('jobs')->count();
echo "Jobs before: {$before}\n";

// Now redeem a reward (via scanner or tinker)
// Then check again:

$after = \DB::table('jobs')->count();
echo "Jobs after: {$after}\n";

// Check recent jobs
$recent = \DB::table('jobs')->orderBy('id', 'desc')->limit(5)->get();
foreach ($recent as $job) {
    $payload = json_decode($job->payload, true);
    echo "Job: " . ($payload['displayName'] ?? 'unknown') . "\n";
}
exit
```

**If job count doesn't increase after redemption:**
- Job is NOT being dispatched
- This confirms the bug

### Step 4: Check Queue Worker

```bash
# Check if queue worker is running
ps aux | grep queue:work

# If not running, jobs won't be processed
# But they should still be QUEUED (visible in jobs table)
```

### Step 5: Manual Test with Tinker

```bash
php artisan tinker
```

```php
// Get an account with rewards
$account = \App\Models\LoyaltyAccount::where('reward_balance', '>', 0)->first();
if (!$account) {
    echo "No accounts with rewards. Earn a reward first.\n";
    exit;
}

echo "Account ID: {$account->id}\n";
echo "Reward balance: {$account->reward_balance}\n";
echo "Redeem token: {$account->redeem_token}\n\n";

// Check jobs before
$before = \DB::table('jobs')->count();
echo "Jobs before: {$before}\n";

// Manually dispatch the wallet update job
\App\Jobs\UpdateWalletPassJob::dispatch($account->id);

// Check jobs after
$after = \DB::table('jobs')->count();
echo "Jobs after: {$after}\n";

if ($after > $before) {
    echo "✅ Job was dispatched!\n";
} else {
    echo "❌ Job was NOT dispatched (using sync queue?)\n";
}

exit
```

**If manual dispatch works:**
- Job system is working
- The issue is in the redeem method not dispatching

**If manual dispatch doesn't work:**
- Check queue connection: `php artisan config:show queue.default`
- If `sync`, jobs run immediately (won't appear in jobs table)
- Check logs for job execution

### Step 6: Check Logs Without Grep

The grep might be filtering out important logs. Try:

```bash
# Watch all logs
tail -f storage/logs/laravel.log

# Or watch last 50 lines
tail -n 50 storage/logs/laravel.log | less

# Search for "redeem" (case insensitive)
tail -f storage/logs/laravel.log | grep -i redeem

# Search for "wallet" (case insensitive)
tail -f storage/logs/laravel.log | grep -i wallet
```

### Step 7: Check Recent Logs

```bash
# Show last 100 lines
tail -n 100 storage/logs/laravel.log

# Search for redeem-related entries
grep -i "redeem" storage/logs/laravel.log | tail -20

# Search for wallet-related entries
grep -i "wallet" storage/logs/laravel.log | tail -20

# Search for UpdateWalletPassJob
grep "UpdateWalletPassJob" storage/logs/laravel.log | tail -20
```

## Expected Logs After Redemption

If everything worked, you should see:

```
[timestamp] INFO: Dispatching StampUpdated event (redeem) {"public_token":"...","channel":"..."}
[timestamp] INFO: Updating wallet pass for loyalty account {"loyalty_account_id":17,"public_token":"..."}
[timestamp] INFO: Wallet sync: Preparing to send Apple Wallet push notifications {"loyalty_account_id":17,...}
[timestamp] INFO: Apple Wallet push notification sent successfully {"registration_id":12,...}
```

## If No Logs Appear

1. **Check log file exists and is writable:**
   ```bash
   ls -la storage/logs/laravel.log
   touch storage/logs/test.log
   ```

2. **Check Laravel is logging:**
   ```bash
   php artisan tinker
   \Log::info('Test log entry');
   exit
   # Then check: tail -n 5 storage/logs/laravel.log
   ```

3. **Check log configuration:**
   ```bash
   php artisan config:show logging
   ```

4. **Check if using different log channel:**
   ```bash
   # Check all log files
   ls -la storage/logs/
   ```

## Quick Test Script

Save as `test-redeem-logs.sh`:

```bash
#!/bin/bash

echo "=== Testing Redemption Logs ==="
echo ""

echo "1. Checking log file..."
if [ -f storage/logs/laravel.log ]; then
    echo "   ✅ Log file exists"
    echo "   Size: $(du -h storage/logs/laravel.log | cut -f1)"
else
    echo "   ❌ Log file not found!"
    exit 1
fi

echo ""
echo "2. Checking recent logs..."
RECENT=$(tail -n 10 storage/logs/laravel.log | wc -l)
echo "   Recent entries: $RECENT"

echo ""
echo "3. Checking for redeem-related logs..."
REDEEM_COUNT=$(grep -i "redeem" storage/logs/laravel.log 2>/dev/null | wc -l)
echo "   Redeem entries found: $REDEEM_COUNT"

echo ""
echo "4. Checking for wallet-related logs..."
WALLET_COUNT=$(grep -i "wallet" storage/logs/laravel.log 2>/dev/null | wc -l)
echo "   Wallet entries found: $WALLET_COUNT"

echo ""
echo "5. Checking queue jobs..."
JOBS=$(php artisan tinker --execute="echo \DB::table('jobs')->count();" 2>/dev/null | tail -1)
echo "   Pending jobs: $JOBS"

echo ""
echo "6. Testing log write..."
php artisan tinker --execute="\Log::info('Test log entry from script');" 2>/dev/null
sleep 1
if tail -n 5 storage/logs/laravel.log | grep -q "Test log entry"; then
    echo "   ✅ Log writing works"
else
    echo "   ❌ Log writing failed"
fi

echo ""
echo "=== Next Steps ==="
echo "1. Watch logs: tail -f storage/logs/laravel.log"
echo "2. Perform a redemption"
echo "3. Check if logs appear"
```

Make executable: `chmod +x test-redeem-logs.sh`

## Summary

**If nothing appears in logs:**
1. Make sure you're actually performing a redemption
2. Check logs are being written at all (watch without grep)
3. Check if job is being queued (check jobs table)
4. Check queue worker is running (if using database queue)
5. The bug might be preventing job dispatch entirely

**The fact that nothing appears confirms the bug** - the job isn't being dispatched, so there are no logs to show.
