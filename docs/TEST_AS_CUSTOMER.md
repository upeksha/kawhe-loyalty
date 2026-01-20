# Test Apple Wallet as a Real Customer

## Step 1: Create/Get a Test Loyalty Card

### Option A: Use Existing Card
```bash
cd /var/www/kawhe
php artisan tinker
```

```php
// Find an existing account
$account = \App\Models\LoyaltyAccount::first();
echo "Card URL: " . config('app.url') . "/c/" . $account->public_token . "\n";
echo "Store: " . $account->store->name . "\n";
echo "Customer: " . $account->customer->name . "\n";
echo "Current Stamps: " . $account->stamp_count . "\n";
exit
```

### Option B: Create New Test Card
```bash
php test-wallet-quick.php
```

This will:
- Create a test customer and loyalty account
- Generate the card page URL
- Show you the download link

**Copy the card page URL** (looks like: `https://testing.kawhe.shop/c/{public_token}`)

## Step 2: Add Pass to iPhone Wallet

1. **Open the card URL on your iPhone:**
   - Open Safari on your iPhone
   - Go to: `https://testing.kawhe.shop/c/{public_token}`
   - (Replace `{public_token}` with the one from Step 1)

2. **Add to Wallet:**
   - Scroll down to see the "Add to Apple Wallet" button
   - Tap "Add to Apple Wallet"
   - Confirm the add
   - The pass should appear in your Wallet app

3. **Verify Pass Details:**
   - Open Wallet app
   - Tap on the loyalty pass
   - Note the current stamp count
   - Check the QR code is visible

## Step 3: Verify Device Registration

After adding the pass, Apple Wallet should automatically register the device. Check:

```bash
php artisan tinker
```

```php
// Check for new registration
$account = \App\Models\LoyaltyAccount::where('public_token', '{public_token}')->first();
$serial = 'kawhe-' . $account->store_id . '-' . $account->customer_id;
$reg = \App\Models\AppleWalletRegistration::where('serial_number', $serial)
    ->where('active', true)
    ->first();

if ($reg) {
    echo "✓ Device registered!\n";
    echo "Device ID: {$reg->device_library_identifier}\n";
    echo "Push Token: " . substr($reg->push_token, 0, 30) . "...\n";
    echo "Registered at: {$reg->last_registered_at}\n";
} else {
    echo "⚠️  No registration found yet. Wait a few seconds and check again.\n";
}
exit
```

**If no registration:**
- Wait 10-20 seconds (Apple Wallet may take a moment)
- Check logs: `tail -f storage/logs/laravel.log | grep "device registered"`
- Try removing and re-adding the pass

## Step 4: Test Stamping (As Merchant)

### Option A: Use Merchant Scanner (Recommended)

1. **Open Merchant Scanner:**
   - Go to: `https://testing.kawhe.shop/scanner`
   - Log in as a merchant

2. **Scan QR Code from Wallet:**
   - Open Wallet app on iPhone
   - Tap on the loyalty pass
   - Show the QR code to the merchant scanner
   - The scanner should detect `LA:{public_token}`

3. **Watch for Push:**
   ```bash
   # In another terminal, watch logs
   tail -f storage/logs/laravel.log | grep -i "push\|stamp"
   ```

4. **Verify Pass Updates:**
   - Go back to Wallet app
   - Tap on the pass
   - **The stamp count should update automatically!**
   - No need to refresh or re-add the pass

### Option B: Manual Stamp via Tinker

```bash
php artisan tinker
```

```php
// Get the account
$account = \App\Models\LoyaltyAccount::where('public_token', '{public_token}')->first();
$store = $account->store;
$user = $store->user;

// Stamp it
$service = app(\App\Services\Loyalty\StampLoyaltyService::class);
$result = $service->stamp($account, $user, 1);

echo "✓ Stamped successfully!\n";
echo "New stamp count: {$result->stampCount}\n";
echo "Reward balance: {$result->rewardBalance}\n";
exit
```

**Then check Wallet app** - pass should update automatically within 1-2 seconds!

## Step 5: Test Multiple Stamps

1. **Stamp multiple times:**
   ```bash
   php artisan tinker
   ```
   ```php
   $account = \App\Models\LoyaltyAccount::where('public_token', '{public_token}')->first();
   $store = $account->store;
   $user = $store->user;
   $service = app(\App\Services\Loyalty\StampLoyaltyService::class);
   
   // Stamp 3 times
   for ($i = 1; $i <= 3; $i++) {
       $result = $service->stamp($account, $user, 1);
       echo "Stamp {$i}: Count = {$result->stampCount}\n";
       sleep(2); // Wait 2 seconds between stamps
   }
   exit
   ```

2. **Watch Wallet:**
   - Keep Wallet app open on the pass
   - Each stamp should trigger an automatic update
   - Stamp count should increment in real-time

## Step 6: Test Reward Earning

When stamps reach the reward target, a reward should be earned:

```bash
php artisan tinker
```

```php
$account = \App\Models\LoyaltyAccount::where('public_token', '{public_token}')->first();
$store = $account->store;
$rewardTarget = $store->reward_target ?? config('loyalty.reward_target', 10);

// Calculate stamps needed
$needed = $rewardTarget - $account->stamp_count;
echo "Current stamps: {$account->stamp_count}\n";
echo "Reward target: {$rewardTarget}\n";
echo "Stamps needed: {$needed}\n";

if ($needed > 0) {
    $user = $store->user;
    $service = app(\App\Services\Loyalty\StampLoyaltyService::class);
    
    // Stamp until reward is earned
    for ($i = 0; $i < $needed; $i++) {
        $result = $service->stamp($account, $user, 1);
        echo "Stamped: Count = {$result->stampCount}, Rewards = {$result->rewardBalance}\n";
        sleep(1);
    }
    
    echo "\n✓ Reward earned! Check Wallet - QR should change to LR: token\n";
} else {
    echo "Already has reward!\n";
}
exit
```

**Check Wallet:**
- QR code should change from `LA:{public_token}` to `LR:{redeem_token}`
- Pass should show reward available
- This happens automatically via push!

## Step 7: Test Reward Redemption

1. **Scan Redeem QR:**
   - Open Wallet app
   - Show the QR code (should be `LR:{redeem_token}`)
   - Scan with merchant scanner
   - Or redeem via tinker:
     ```bash
     php artisan tinker
     ```
     ```php
     $account = \App\Models\LoyaltyAccount::where('public_token', '{public_token}')->first();
     $store = $account->store;
     $user = $store->user;
     
     // Redeem
     $controller = app(\App\Http\Controllers\ScannerController::class);
     $request = \Illuminate\Http\Request::create('/scanner/redeem', 'POST', [
         'token' => $account->redeem_token,
         'store_id' => $store->id,
     ]);
     $request->setUserResolver(function() use ($user) { return $user; });
     
     $response = $controller->redeem($request);
     echo "Response: " . $response->getContent() . "\n";
     exit
     ```

2. **Verify:**
   - Reward balance decreases
   - QR code changes back to `LA:{public_token}` (if no more rewards)
   - Pass updates automatically

## Step 8: Monitor Logs During Testing

Keep this running in a terminal:

```bash
tail -f storage/logs/laravel.log | grep -E "push|stamp|wallet|APNs" --color=always
```

**What to look for:**
- `Apple Wallet push notification sent successfully` (HTTP 200)
- `apns_id` in the response
- `Wallet sync: Apple Wallet push notifications completed`
- No 403 or 410 errors

## Troubleshooting

### Pass Doesn't Update Automatically

1. **Check registration exists:**
   ```bash
   php artisan tinker --execute="
   \$account = \App\Models\LoyaltyAccount::where('public_token', '{public_token}')->first();
   \$serial = 'kawhe-' . \$account->store_id . '-' . \$account->customer_id;
   \$reg = \App\Models\AppleWalletRegistration::where('serial_number', \$serial)->where('active', true)->first();
   echo \$reg ? 'Registered' : 'Not registered';
   "
   ```

2. **Check push is enabled:**
   ```bash
   php artisan config:show wallet.apple.push_enabled
   ```

3. **Test push manually:**
   ```bash
   php artisan wallet:apns-test kawhe-{store_id}-{customer_id}
   ```

4. **Check iPhone:**
   - Ensure iPhone has internet connection
   - Try removing and re-adding the pass
   - Check Wallet app is not in background (iOS may delay updates)

### Push Succeeds But Pass Doesn't Update

1. **Check pass has webServiceURL:**
   - The pass must have been generated with `webServiceURL` and `authenticationToken`
   - If you added the pass before these were added, remove and re-add it

2. **Verify device registration:**
   - Registration must have a valid `push_token`
   - Check: `php artisan tinker --execute="echo \App\Models\AppleWalletRegistration::where('active', true)->count();"`

3. **Check APNs logs:**
   ```bash
   tail -n 100 storage/logs/laravel.log | grep "push notification"
   ```

## Quick Test Script

Save this as `test-customer-flow.sh`:

```bash
#!/bin/bash

cd /var/www/kawhe

echo "=== Customer Flow Test ==="
echo ""

# Get or create account
echo "1. Getting test account..."
ACCOUNT=$(php artisan tinker --execute="
\$account = \App\Models\LoyaltyAccount::first();
if (!\$account) {
    echo 'No accounts found. Run: php test-wallet-quick.php';
    exit(1);
}
echo \$account->public_token . PHP_EOL;
echo \$account->id . PHP_EOL;
echo \$account->store_id . PHP_EOL;
echo \$account->customer_id . PHP_EOL;
" | tail -4)

PUBLIC_TOKEN=$(echo "$ACCOUNT" | head -1)
ACCOUNT_ID=$(echo "$ACCOUNT" | head -2 | tail -1)
STORE_ID=$(echo "$ACCOUNT" | head -3 | tail -1)
CUSTOMER_ID=$(echo "$ACCOUNT" | tail -1)

SERIAL="kawhe-${STORE_ID}-${CUSTOMER_ID}"

echo "Account ID: $ACCOUNT_ID"
echo "Public Token: $PUBLIC_TOKEN"
echo "Serial: $SERIAL"
echo ""

# Card URL
CARD_URL="https://testing.kawhe.shop/c/${PUBLIC_TOKEN}"
echo "2. Card URL:"
echo "   $CARD_URL"
echo "   Open this on your iPhone to add to Wallet"
echo ""

# Check registration
echo "3. Checking device registration..."
sleep 5
REG_COUNT=$(php artisan tinker --execute="
\$reg = \App\Models\AppleWalletRegistration::where('serial_number', '$SERIAL')->where('active', true)->count();
echo \$reg;
" | tail -1)

if [ "$REG_COUNT" -gt "0" ]; then
    echo "   ✓ Device registered!"
else
    echo "   ⚠️  No registration yet. Add pass to Wallet first."
fi
echo ""

# Test push
echo "4. Testing push notification..."
php artisan wallet:apns-test "$SERIAL"
echo ""

# Instructions
echo "=== Next Steps ==="
echo "1. Open $CARD_URL on iPhone"
echo "2. Add pass to Wallet"
echo "3. Wait 10 seconds for registration"
echo "4. Run: php artisan tinker"
echo "5. Stamp the account (see TEST_AS_CUSTOMER.md)"
echo "6. Watch Wallet app - pass should update automatically!"
```

Make it executable:
```bash
chmod +x test-customer-flow.sh
./test-customer-flow.sh
```

## Success Criteria

✅ Pass adds to Wallet successfully  
✅ Device registers automatically  
✅ Push notification sends (HTTP 200)  
✅ Pass updates automatically when stamped  
✅ QR code changes when reward is earned  
✅ QR code changes back after redemption  
✅ Multiple stamps trigger multiple updates  
✅ No manual refresh needed in Wallet app  

## Tips

1. **Keep Wallet app open** while testing - updates are more visible
2. **Use merchant scanner** for realistic testing
3. **Watch logs** in real-time to see push notifications
4. **Test on real iPhone** - simulators don't support Wallet
5. **Ensure good internet** - APNs requires network connectivity
