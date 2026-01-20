# Debug: QR Code Not Updating After Redemption

## Problem
When a reward is redeemed, the QR code in Apple Wallet doesn't automatically update. However, when the card is scanned (stamped), it does update correctly.

## Root Cause Analysis

### Comparison: Stamping vs Redeeming

#### ✅ Stamping Flow (WORKS):
**Location:** `app/Services/Loyalty/StampLoyaltyService.php` (line 201-204)

```php
DB::transaction(function () use (...) {
    // ... stamping logic ...
    
    // Dispatch wallet update job AFTER transaction commits
    DB::afterCommit(function () use ($account) {
        UpdateWalletPassJob::dispatch($account->id)
            ->onQueue('default');
    });
    
    return $result;
});
```

**Why it works:**
- Uses `DB::afterCommit()` **callback** - explicitly waits for transaction to commit
- Job is dispatched **after** the transaction successfully commits
- Reliable and guaranteed to fire after commit

#### ❌ Redeem Flow (DOESN'T WORK):
**Location:** `app/Http/Controllers/ScannerController.php` (line 365-366)

```php
DB::transaction(function () use (...) {
    // ... redemption logic ...
    
    // Dispatch wallet update job
    \App\Jobs\UpdateWalletPassJob::dispatch($account->id)
        ->afterCommit();
    
    // ... more code (StampEvent::create) ...
    
    return response()->json([...]);
});
```

**Why it doesn't work:**
- Uses `->afterCommit()` **on the job** - this is a Laravel queue feature
- This is NOT the same as `DB::afterCommit()` callback
- The `->afterCommit()` on jobs only works if:
  1. Queue connection supports it (database queue does, but...)
  2. The transaction is properly detected by Laravel
  3. The job is dispatched BEFORE the transaction completes
- **Problem:** Inside a transaction closure, `->afterCommit()` on the job might not be reliable
- **Additional issue:** The job is dispatched, but then `StampEvent::create()` happens after, which might cause timing issues

## The Issue

1. **Different mechanisms:**
   - Stamping uses: `DB::afterCommit(callback)` - **explicit callback**
   - Redeem uses: `Job::dispatch()->afterCommit()` - **queue feature**

2. **Transaction context:**
   - Both are inside `DB::transaction()` closures
   - But `->afterCommit()` on jobs might not work correctly inside transaction closures
   - `DB::afterCommit()` callback is more reliable

3. **Timing:**
   - The redeem method dispatches the job, then continues with `StampEvent::create()`
   - The job might be queued before the transaction fully commits
   - Or the `->afterCommit()` flag might not be properly detected

## Why Scanning Works But Redeeming Doesn't

**When scanning (stamping):**
- Uses `StampLoyaltyService::stamp()`
- Uses `DB::afterCommit()` callback
- Job is guaranteed to dispatch after transaction commits
- ✅ Works correctly

**When redeeming:**
- Uses `ScannerController::redeem()` directly
- Uses `->afterCommit()` on the job
- Job might dispatch before transaction commits or the flag might not work
- ❌ Doesn't work reliably

## Solution

Change the redeem method to use `DB::afterCommit()` callback instead of `->afterCommit()` on the job, matching the stamping service pattern.

**Current (line 365-366):**
```php
\App\Jobs\UpdateWalletPassJob::dispatch($account->id)
    ->afterCommit();
```

**Should be:**
```php
DB::afterCommit(function () use ($account) {
    \App\Jobs\UpdateWalletPassJob::dispatch($account->id)
        ->onQueue('default');
});
```

## Additional Observations

1. **Consistency:**
   - The stamping service uses `DB::afterCommit()` callback
   - The redeem method should use the same pattern for consistency

2. **Reliability:**
   - `DB::afterCommit()` callback is more reliable than `->afterCommit()` on jobs
   - It's explicitly called after the transaction commits
   - No dependency on queue connection features

3. **Code location:**
   - The job dispatch happens at line 365
   - But `StampEvent::create()` happens at line 369
   - This suggests the job might be dispatched before all transaction work is complete

## Verification Steps

To verify this is the issue, check logs:

```bash
tail -f storage/logs/laravel.log | grep -E "UpdateWalletPassJob|redeem|WalletSyncService"
```

**What to look for:**
- After redeeming, do you see `UpdateWalletPassJob` being dispatched?
- Do you see `WalletSyncService` being called?
- Do you see APNs push being sent?

**If you DON'T see these logs after redemption:**
- The job is not being dispatched
- This confirms the `->afterCommit()` on the job is not working

**If you DO see these logs but wallet doesn't update:**
- The job is being dispatched
- But might be dispatched before transaction commits
- Or the account data might be stale

## Expected Behavior After Fix

After changing to `DB::afterCommit()` callback:

1. **Transaction commits** (reward_balance updated, redeem_token rotated)
2. **`DB::afterCommit()` callback fires**
3. **Job is dispatched** with committed data
4. **Job processes** and calls `WalletSyncService`
5. **Pass is regenerated** with new QR code (LA: or LR: based on reward_balance)
6. **APNs push is sent**
7. **iPhone receives push** and checks for updates
8. **Wallet app updates** with new QR code

## Summary

**Root Cause:** 
- Redeem method uses `->afterCommit()` on the job (unreliable inside transaction)
- Stamping uses `DB::afterCommit()` callback (reliable)

**Fix:**
- Change redeem method to use `DB::afterCommit()` callback
- Match the pattern used in `StampLoyaltyService`

**Why it works when scanning:**
- Scanning uses the stamping service which has the correct pattern

**Why it doesn't work when redeeming:**
- Redeem method uses the unreliable `->afterCommit()` on the job
