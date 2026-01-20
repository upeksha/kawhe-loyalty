# Debug: QR Code Not Updating After Redemption

## Analysis of Your Logs

Looking at your logs, I can see:

### ✅ What's Working:
1. **Job IS being dispatched:**
   ```
   [21:52:32] Dispatching StampUpdated event (redeem)
   [21:52:32] Updating wallet pass for loyalty account
   [21:52:32] Wallet sync requested for loyalty account
   ```
   The `UpdateWalletPassJob` IS being dispatched after redemption!

2. **Push notification IS being sent:**
   ```
   [21:52:32] Apple Wallet push notification sent successfully
   ```

3. **iPhone IS downloading updated pass:**
   ```
   [21:52:33] Apple Wallet pass generated and served
   {"stamp_count":1,"reward_balance":0}
   ```

### ❌ The Problem:

Looking at the pass generation logic in `AppleWalletPassService.php` (line 42-44):

```php
'message' => ($account->reward_balance ?? 0) > 0 && $account->redeem_token
    ? 'LR:' . $account->redeem_token
    : 'LA:' . $account->public_token,
```

**The QR code should be:**
- `LR:{redeem_token}` when `reward_balance > 0` AND `redeem_token` exists
- `LA:{public_token}` when `reward_balance = 0` OR `redeem_token` is null

## Root Cause Analysis

### Scenario: After Redemption

When a reward is redeemed:
1. `reward_balance` decreases (e.g., from 1 to 0)
2. `redeem_token` is rotated (if balance > 0) or set to null (if balance = 0)
3. Account is saved
4. Job is dispatched

**The issue:** When the job runs, it should see:
- `reward_balance = 0`
- `redeem_token = null` (if all rewards redeemed)
- QR code should be `LA:{public_token}`

**But the user says QR code doesn't update automatically.**

## Possible Causes

### 1. Account Not Refreshed in Job

The job might be using stale account data. Check `UpdateWalletPassJob`:

```php
$account = LoyaltyAccount::with(['store', 'customer'])->findOrFail($this->loyaltyAccountId);
```

This should get fresh data, but let's verify.

### 2. Timing Issue

The job might be dispatched before the transaction commits, so it reads old data.

**Current code (line 365-366):**
```php
\App\Jobs\UpdateWalletPassJob::dispatch($account->id)
    ->afterCommit();
```

The `->afterCommit()` on the job might not work reliably inside `DB::transaction()`.

### 3. Account State After Redemption

Let's trace what happens:

**Before redemption:**
- `reward_balance = 1`
- `redeem_token = "abc123..."`
- QR code: `LR:abc123...`

**After redemption (in transaction):**
- `reward_balance = 0`
- `redeem_token = null` (if all redeemed)
- Account saved

**When job runs:**
- Should read: `reward_balance = 0`, `redeem_token = null`
- Should generate: `LA:{public_token}`

**But if job reads stale data:**
- Might read: `reward_balance = 1`, `redeem_token = "abc123..."`
- Would generate: `LR:abc123...` (wrong!)

## Verification Steps

### Step 1: Check Account State in Job

Add logging to `UpdateWalletPassJob::handle()`:

```php
$account = LoyaltyAccount::with(['store', 'customer'])->findOrFail($this->loyaltyAccountId);

Log::info('UpdateWalletPassJob: Account state', [
    'loyalty_account_id' => $this->loyaltyAccountId,
    'reward_balance' => $account->reward_balance,
    'redeem_token' => $account->redeem_token ? 'exists' : 'null',
    'stamp_count' => $account->stamp_count,
    'updated_at' => $account->updated_at,
]);
```

### Step 2: Check QR Code in Generated Pass

Add logging to `AppleWalletPassService::generatePass()`:

```php
$qrMessage = ($account->reward_balance ?? 0) > 0 && $account->redeem_token
    ? 'LR:' . $account->redeem_token
    : 'LA:' . $account->public_token;

Log::info('Apple Wallet pass: QR code generated', [
    'loyalty_account_id' => $account->id,
    'reward_balance' => $account->reward_balance,
    'redeem_token' => $account->redeem_token ? 'exists' : 'null',
    'qr_message' => $qrMessage,
]);
```

### Step 3: Compare Before/After Redemption

**Before redemption:**
```bash
php artisan tinker
```

```php
$account = \App\Models\LoyaltyAccount::find(17);
echo "Before:\n";
echo "  Reward balance: {$account->reward_balance}\n";
echo "  Redeem token: " . ($account->redeem_token ?: 'null') . "\n";
exit
```

**Redeem a reward, then immediately:**

```php
$account = \App\Models\LoyaltyAccount::find(17);
$account->refresh(); // Force refresh
echo "After:\n";
echo "  Reward balance: {$account->reward_balance}\n";
echo "  Redeem token: " . ($account->redeem_token ?: 'null') . "\n";
exit
```

## Expected Behavior

### When Reward is Redeemed:

1. **Transaction commits:**
   - `reward_balance` decreases
   - `redeem_token` rotated or set to null

2. **Job dispatched (after commit):**
   - Reads fresh account data
   - Sees `reward_balance = 0`
   - Generates QR: `LA:{public_token}`

3. **Pass regenerated:**
   - QR code is `LA:{public_token}`

4. **Push sent:**
   - iPhone receives notification

5. **iPhone downloads pass:**
   - QR code should be `LA:{public_token}`

## The Real Issue

Based on your logs, the job IS being dispatched. The problem is likely:

1. **Job reads stale data** - Account not refreshed before pass generation
2. **Timing issue** - Job dispatched before transaction commits (despite `->afterCommit()`)
3. **Account state** - The account data in the job might be from before the redemption

## Solution

The fix should be:

1. **Change `->afterCommit()` to `DB::afterCommit()` callback** (like stamping service)
2. **Ensure account is refreshed in the job** (already done with `findOrFail`)
3. **Add logging** to verify account state when pass is generated

## Quick Test

After redemption, check what QR code was generated:

```bash
# Extract QR code from the last generated pass
# The pass is stored temporarily, but we can check logs

tail -n 100 storage/logs/laravel.log | grep -A 5 "Wallet sync requested"
```

Look for the `reward_balance` value when the pass was generated. If it shows `reward_balance: 0` but QR code is still `LR:`, then the logic is wrong. If it shows `reward_balance: 1`, then the job is reading stale data.

## Summary

**The job IS being dispatched** (your logs confirm this), but:
- The QR code might not be updating correctly
- The account data in the job might be stale
- The `->afterCommit()` on the job might not be working reliably

**The fix:** Change to `DB::afterCommit()` callback (like stamping service) to ensure job runs with committed data.
