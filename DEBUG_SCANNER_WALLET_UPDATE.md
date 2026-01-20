# Debug Scanner Wallet Update Issue

## Problem
When stamping via tinker (using `StampLoyaltyService`), wallet updates work. But when using the actual scanner app flow, wallet doesn't update.

## Root Cause Analysis

The scanner controller calls `StampLoyaltyService::stamp()`, which dispatches `UpdateWalletPassJob` using `DB::afterCommit()`. This should work, but there might be issues with:

1. **Queue not processing** - Jobs are dispatched but not executed
2. **Queue connection misconfigured** - Jobs going to wrong queue
3. **Job failing silently** - Errors not being logged
4. **Transaction not committing** - `afterCommit` callback not firing

## Debugging Steps

### Step 1: Check Queue Configuration

```bash
php artisan config:show queue.default
php artisan config:show queue.connections
```

**Expected:** Should be `database` or `sync` for testing

### Step 2: Check if Queue Worker is Running

```bash
# Check if queue worker process is running
ps aux | grep "queue:work"

# Or check supervisor/systemd
systemctl status laravel-worker
# OR
supervisorctl status
```

**If no worker running:**
- Jobs are queued but not processed
- Need to start queue worker: `php artisan queue:work`

### Step 3: Check Queue Jobs Table

```bash
php artisan tinker
```

```php
// Check if jobs are being queued
$jobs = \DB::table('jobs')->count();
echo "Pending jobs: {$jobs}\n";

// Check failed jobs
$failed = \DB::table('failed_jobs')->count();
echo "Failed jobs: {$failed}\n";

// Show recent jobs
$recent = \DB::table('jobs')->orderBy('id', 'desc')->limit(5)->get();
foreach ($recent as $job) {
    echo "Job ID: {$job->id}, Queue: {$job->queue}, Created: {$job->created_at}\n";
}
exit
```

### Step 4: Check Logs During Scanner Stamping

```bash
# Watch logs in real-time while scanning
tail -f storage/logs/laravel.log | grep -E "UpdateWalletPassJob|WalletSyncService|push|stamp"
```

**What to look for:**
- `UpdateWalletPassJob` being dispatched
- `WalletSyncService` being called
- Push notifications being sent
- Any errors or exceptions

### Step 5: Test Queue Processing

```bash
# Process one job manually
php artisan queue:work --once

# Or process all pending jobs
php artisan queue:work --stop-when-empty
```

### Step 6: Compare Tinker vs Scanner Flow

**Tinker flow:**
```php
$service->stamp($account, $user, 1);
// Uses DB::afterCommit() to dispatch job
// Job should be processed immediately if queue is sync
```

**Scanner flow:**
```php
$stampService->stamp($account, Auth::user(), $count, ...);
// Same service, same dispatch mechanism
// But might be in different transaction context
```

## Quick Fix: Use Sync Queue for Testing

If queue worker isn't running, switch to sync queue:

```bash
# In .env
QUEUE_CONNECTION=sync
```

Then clear config:
```bash
php artisan config:clear
php artisan config:cache
```

**Sync queue processes jobs immediately** (no worker needed)

## Verify Job is Dispatched

Add logging to verify job dispatch:

```php
// In StampLoyaltyService::stamp()
DB::afterCommit(function () use ($account) {
    \Log::info('Dispatching UpdateWalletPassJob', [
        'loyalty_account_id' => $account->id,
        'queue' => 'default',
    ]);
    UpdateWalletPassJob::dispatch($account->id)
        ->onQueue('default');
});
```

## Check Transaction Commit

The `DB::afterCommit()` callback only fires if the transaction commits successfully. Check:

```bash
# Add logging before and after transaction
\Log::info('Before transaction');
DB::transaction(function () {
    // ... stamping logic ...
});
\Log::info('After transaction commit');
```

## Common Issues

### Issue 1: Queue Worker Not Running
**Symptom:** Jobs queued but never processed
**Fix:** Start queue worker or use sync queue

### Issue 2: Jobs Failing Silently
**Symptom:** Jobs in `failed_jobs` table
**Fix:** Check `failed_jobs` table and logs

### Issue 3: Transaction Not Committing
**Symptom:** `afterCommit` callback never fires
**Fix:** Check for exceptions preventing commit

### Issue 4: Queue Connection Wrong
**Symptom:** Jobs going to wrong queue
**Fix:** Verify `QUEUE_CONNECTION` in `.env`

## Testing Commands

### Test 1: Check Queue Status
```bash
php artisan queue:monitor default
```

### Test 2: Process Pending Jobs
```bash
php artisan queue:work --stop-when-empty --verbose
```

### Test 3: Check Failed Jobs
```bash
php artisan queue:failed
```

### Test 4: Retry Failed Jobs
```bash
php artisan queue:retry all
```

## Solution: Ensure Queue is Processing

**Option A: Use Sync Queue (Immediate Processing)**
```bash
# .env
QUEUE_CONNECTION=sync
php artisan config:clear
```

**Option B: Start Queue Worker**
```bash
# Run in background
php artisan queue:work --daemon

# Or use supervisor/systemd for production
```

**Option C: Process Jobs Manually After Stamping**
```bash
# After each stamp, run:
php artisan queue:work --once
```

## Verification

After fixing, test:

1. **Stamp via scanner:**
   - Scan QR code
   - Check logs: `tail -f storage/logs/laravel.log | grep -i wallet`

2. **Verify job dispatched:**
   - Check `jobs` table
   - Should see `UpdateWalletPassJob`

3. **Verify job processed:**
   - Check logs for `WalletSyncService`
   - Check logs for push notification

4. **Verify wallet updates:**
   - Check Wallet app on iPhone
   - Pass should update automatically
