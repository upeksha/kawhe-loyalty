# Full Real-World Test Flow

Test the complete flow: Create Merchant → Create Card → Add to Wallet → Test Stamping

## Step 1: Create a Merchant Account

### Via Web App

1. **Register/Login:**
   - Go to: `https://app.kawhe.shop/register`
   - Create a new account or login
   - Complete onboarding if needed

2. **Create a Store:**
   - After login, you'll be prompted to create a store
   - Or go to: `https://app.kawhe.shop/stores/create`
   - Fill in:
     - Store Name
     - Brand Color (optional)
     - Background Color (optional)
     - Logo (optional)
   - Click "Create Store"

3. **Get Store QR Code:**
   - Go to your dashboard: `https://app.kawhe.shop/dashboard`
   - You'll see your store's QR code
   - This is what customers will scan to join

## Step 2: Create a Customer Card

### Option A: Via Store QR Code (Real Customer Flow)

1. **On Your iPhone:**
   - Open Camera app
   - Scan the store QR code from the merchant dashboard
   - This will take you to: `https://app.kawhe.shop/join/{store_id}`

2. **Join as Customer:**
   - Enter your name (e.g., "Test Customer")
   - Enter your email (optional)
   - Click "Join" or "Create Card"

3. **Card Created:**
   - You'll be redirected to your loyalty card page
   - URL will be: `https://app.kawhe.shop/c/{public_token}`
   - **Save this URL!**

### Option B: Via Direct URL

1. **Get Store ID:**
   ```bash
   cd /var/www/kawhe
   php artisan tinker
   ```
   ```php
   $store = \App\Models\Store::first();
   echo "Store ID: {$store->id}\n";
   echo "Store Name: {$store->name}\n";
   echo "Join URL: " . config('app.url') . "/join/{$store->id}\n";
   exit
   ```

2. **Open Join URL on iPhone:**
   - Go to: `https://app.kawhe.shop/join/{store_id}`
   - Enter customer name
   - Click "Join"

## Step 3: Add Card to Apple Wallet

1. **On the Card Page:**
   - Scroll down to see "Add to Apple Wallet" button
   - Tap "Add to Apple Wallet"
   - Confirm the add

2. **Verify in Wallet:**
   - Open Wallet app
   - You should see your loyalty pass
   - Tap to view details
   - Note the current stamp count (should be 0)

3. **Verify Device Registration:**
   ```bash
   # Easiest way - use the command
   php artisan wallet:check-registration
   
   # Or check specific account
   php artisan wallet:check-registration {public_token}
   ```
   
   This will show:
   - Account information
   - Card URL
   - Device registrations (if any)
   - Push token preview

## Step 4: Test Stamping (As Merchant)

### Option A: Use Merchant Scanner (Recommended)

1. **Open Merchant Scanner:**
   - Go to: `https://app.kawhe.shop/scanner`
   - Make sure you're logged in as the merchant

2. **Scan QR from Wallet:**
   - Open Wallet app on iPhone
   - Tap on your loyalty pass
   - Show the QR code to the scanner
   - The scanner should detect it and stamp

3. **Watch Wallet:**
   - Keep Wallet app open
   - The pass should update automatically within 1-2 seconds
   - Stamp count should increment

4. **Monitor Logs:**
   ```bash
   tail -f storage/logs/laravel.log | grep -i "push\|stamp"
   ```
   
   You should see:
   - `StampLoyaltyService: Stamping account`
   - `Apple Wallet push notification sent successfully`
   - `apns_id` in the response

### Option B: Manual Stamp via Tinker

```bash
php artisan tinker
```

```php
// Get the account
$account = \App\Models\LoyaltyAccount::latest()->first();
$store = $account->store;
$user = $store->user;

echo "Account: {$account->id}\n";
echo "Store: {$store->name}\n";
echo "Merchant: {$user->name}\n";
echo "Current Stamps: {$account->stamp_count}\n\n";

// Stamp it
$service = app(\App\Services\Loyalty\StampLoyaltyService::class);
$result = $service->stamp($account, $user, 1);

echo "✓ Stamped successfully!\n";
echo "New stamp count: {$result->stampCount}\n";
echo "Reward balance: {$result->rewardBalance}\n";
exit
```

**Then immediately check Wallet app** - pass should update automatically!

## Step 5: Test Multiple Stamps

```bash
php artisan tinker
```

```php
$account = \App\Models\LoyaltyAccount::latest()->first();
$store = $account->store;
$user = $store->user;
$service = app(\App\Services\Loyalty\StampLoyaltyService::class);

// Stamp 5 times
for ($i = 1; $i <= 5; $i++) {
    $result = $service->stamp($account, $user, 1);
    echo "Stamp {$i}: Count = {$result->stampCount}, Rewards = {$result->rewardBalance}\n";
    sleep(2); // Wait 2 seconds between stamps
}

echo "\n✓ Done! Check Wallet - pass should show updated counts\n";
exit
```

**Watch Wallet app** - each stamp should trigger an automatic update!

## Step 6: Test Reward Earning

When you reach the reward target (usually 10 stamps), a reward is earned:

```bash
php artisan tinker
```

```php
$account = \App\Models\LoyaltyAccount::latest()->first();
$store = $account->store;
$rewardTarget = $store->reward_target ?? config('loyalty.reward_target', 10);

echo "Current stamps: {$account->stamp_count}\n";
echo "Reward target: {$rewardTarget}\n";

$needed = $rewardTarget - $account->stamp_count;
if ($needed > 0) {
    echo "Stamping {$needed} more times to earn reward...\n\n";
    
    $user = $store->user;
    $service = app(\App\Services\Loyalty\StampLoyaltyService::class);
    
    for ($i = 0; $i < $needed; $i++) {
        $result = $service->stamp($account, $user, 1);
        echo "Stamped: Count = {$result->stampCount}, Rewards = {$result->rewardBalance}\n";
        sleep(1);
    }
    
    echo "\n✓ Reward earned! Check Wallet:\n";
    echo "  - QR code should change to LR: token\n";
    echo "  - Pass should show reward available\n";
} else {
    echo "Already has reward!\n";
}
exit
```

**Check Wallet:**
- QR code should change from `LA:{public_token}` to `LR:{redeem_token}`
- This happens automatically via push notification!

## Step 7: Test Reward Redemption

### Option A: Via Merchant Scanner

1. **Open Wallet:**
   - Show the QR code (should be `LR:{redeem_token}`)

2. **Scan with Merchant Scanner:**
   - Go to `https://app.kawhe.shop/scanner`
   - Scan the QR code
   - It should detect `LR:` prefix and redeem

3. **Verify:**
   - Reward balance decreases
   - QR code changes back to `LA:{public_token}` (if no more rewards)
   - Pass updates automatically

### Option B: Via Tinker

```bash
php artisan tinker
```

```php
$account = \App\Models\LoyaltyAccount::latest()->first();
$store = $account->store;
$user = $store->user;

if ($account->reward_balance > 0) {
    echo "Redeeming reward...\n";
    echo "Current reward balance: {$account->reward_balance}\n";
    
    // Create a request
    $request = \Illuminate\Http\Request::create('/scanner/redeem', 'POST', [
        'token' => $account->redeem_token,
        'store_id' => $store->id,
    ]);
    $request->setUserResolver(function() use ($user) { return $user; });
    
    // Redeem
    $controller = app(\App\Http\Controllers\ScannerController::class);
    $response = $controller->redeem($request);
    
    echo "Response: " . $response->getContent() . "\n";
    
    // Refresh account
    $account->refresh();
    echo "New reward balance: {$account->reward_balance}\n";
    echo "✓ Redeemed! Check Wallet - QR should change back to LA: token\n";
} else {
    echo "No rewards to redeem. Earn a reward first!\n";
}
exit
```

## Step 8: Full Flow Verification

Run this to verify everything is working:

```bash
php artisan tinker
```

```php
$account = \App\Models\LoyaltyAccount::latest()->first();
$serial = 'kawhe-' . $account->store_id . '-' . $account->customer_id;

echo "=== Account Status ===\n";
echo "Account ID: {$account->id}\n";
echo "Store: {$account->store->name}\n";
echo "Customer: {$account->customer->name}\n";
echo "Stamps: {$account->stamp_count}\n";
echo "Rewards: {$account->reward_balance}\n";
echo "Public Token: {$account->public_token}\n";
echo "Wallet Auth Token: {$account->wallet_auth_token}\n";
echo "Serial: {$serial}\n\n";

echo "=== Device Registration ===\n";
$reg = \App\Models\AppleWalletRegistration::where('serial_number', $serial)
    ->where('active', true)
    ->first();

if ($reg) {
    echo "✓ Registered\n";
    echo "Device ID: {$reg->device_library_identifier}\n";
    echo "Push Token: " . substr($reg->push_token, 0, 30) . "...\n";
    echo "Registered: {$reg->last_registered_at}\n";
} else {
    echo "✗ Not registered\n";
}
echo "\n";

echo "=== Card URLs ===\n";
echo "Card Page: " . config('app.url') . "/c/{$account->public_token}\n";
echo "Download Pass: " . \Illuminate\Support\Facades\URL::signedRoute('wallet.apple.download', ['public_token' => $account->public_token]) . "\n";
echo "\n";

echo "=== Test Push ===\n";
echo "Run: php artisan wallet:apns-test {$serial}\n";
exit
```

## Monitoring During Test

Keep this running in a terminal:

```bash
tail -f storage/logs/laravel.log | grep -E "push|stamp|wallet|APNs|registration" --color=always
```

**What to look for:**
- ✅ `Apple Wallet device registered` - when pass is added
- ✅ `Apple Wallet push notification sent successfully` - when stamping
- ✅ `apns_id` in response - confirms push was accepted
- ✅ `Wallet sync: Apple Wallet push notifications completed` - push sent
- ❌ No 403 or 410 errors

## Quick Test Script

Save this as `test-full-flow.sh`:

```bash
#!/bin/bash

cd /var/www/kawhe

echo "=== Full Real-World Test Flow ==="
echo ""

echo "Step 1: Create Merchant Account"
echo "  1. Go to: https://app.kawhe.shop/register"
echo "  2. Create account and store"
echo "  3. Note your store ID from dashboard"
echo ""

echo "Step 2: Create Customer Card"
echo "  1. Get store ID:"
STORE_ID=$(php artisan tinker --execute="
\$store = \App\Models\Store::first();
if (\$store) {
    echo \$store->id . PHP_EOL;
    echo \$store->name . PHP_EOL;
} else {
    echo 'No stores found. Create one first.';
}
" 2>/dev/null | head -1)

if [ -n "$STORE_ID" ] && [ "$STORE_ID" != "No" ]; then
    echo "  Store ID: $STORE_ID"
    echo "  2. Join URL: https://app.kawhe.shop/join/$STORE_ID"
    echo "  3. Open on iPhone and create card"
else
    echo "  ⚠️  No stores found. Create one first via web app."
fi
echo ""

echo "Step 3: Add to Wallet"
echo "  1. On card page, tap 'Add to Apple Wallet'"
echo "  2. Wait 10-20 seconds for registration"
echo ""

echo "Step 4: Test Stamping"
echo "  Option A: Use merchant scanner at https://app.kawhe.shop/scanner"
echo "  Option B: Manual stamp via tinker (see FULL_REAL_WORLD_TEST.md)"
echo ""

echo "Step 5: Monitor Logs"
echo "  tail -f storage/logs/laravel.log | grep -i push"
echo ""

echo "=== Quick Commands ==="
echo ""
echo "Check latest account:"
echo "  php artisan tinker --execute=\"\$a = \App\Models\LoyaltyAccount::latest()->first(); echo 'ID: ' . \$a->id . ', Token: ' . \$a->public_token . PHP_EOL;\""
echo ""
echo "Check registration:"
echo "  php artisan tinker --execute=\"\$a = \App\Models\LoyaltyAccount::latest()->first(); \$s = 'kawhe-' . \$a->store_id . '-' . \$a->customer_id; echo \App\Models\AppleWalletRegistration::where('serial_number', \$s)->where('active', true)->count() . PHP_EOL;\""
echo ""
echo "Test push:"
echo "  php artisan wallet:apns-test kawhe-{store_id}-{customer_id}"
echo ""
```

Make it executable:
```bash
chmod +x test-full-flow.sh
./test-full-flow.sh
```

## Success Checklist

- [ ] Merchant account created
- [ ] Store created with QR code
- [ ] Customer card created via join flow
- [ ] Card added to Apple Wallet
- [ ] Device registered (check database)
- [ ] Push notification works (HTTP 200)
- [ ] Pass updates automatically when stamped
- [ ] QR code changes when reward earned
- [ ] QR code changes back after redemption
- [ ] Multiple stamps trigger multiple updates
- [ ] No manual refresh needed in Wallet

## Troubleshooting

### Card Won't Add to Wallet
- Check pass generation: `php artisan tinker --execute="\$s = app(\App\Services\Wallet\AppleWalletPassService::class); \$a = \App\Models\LoyaltyAccount::first(); \$p = \$s->generatePass(\$a); echo 'Pass size: ' . strlen(\$p) . ' bytes';"`
- Check certificates exist: `ls -la passgenerator/certs/`
- Check logs: `tail -f storage/logs/laravel.log`

### Device Not Registering
- Wait 10-20 seconds after adding pass
- Remove and re-add the pass
- Check logs: `tail -f storage/logs/laravel.log | grep "device registered"`

### Pass Not Updating
- Verify registration exists
- Check push is enabled: `php artisan config:show wallet.apple.push_enabled`
- Test push manually: `php artisan wallet:apns-test {serial}`
- Check logs for errors

### Push Succeeds But Pass Doesn't Update
- Ensure pass was generated with `webServiceURL` (re-add if needed)
- Check iPhone has internet
- Try removing and re-adding pass
- Check Wallet app is not in background

## Tips

1. **Use real iPhone** - Simulators don't support Wallet
2. **Keep Wallet open** - Updates are more visible
3. **Watch logs** - See push notifications in real-time
4. **Test incrementally** - One step at a time
5. **Use merchant scanner** - Most realistic testing
