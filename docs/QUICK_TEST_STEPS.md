# Quick Test Steps for Apple Wallet

## Step 1: Pull Latest Changes & Run Migration

```bash
cd /var/www/kawhe
git pull origin main
php artisan migrate
php artisan config:clear
php artisan cache:clear
```

## Step 2: Run Test Script

```bash
php test-wallet-quick.php
```

This will:
- Find or create a test account
- Generate test passes
- Show you the correct URLs

**Copy the URLs from the output!**

## Step 3: Test on iPhone

### Option A: Use Card Page (Recommended)

1. **Open the card page URL** in Safari on your iPhone:
   ```
   https://testing.kawhe.shop/c/{public_token}
   ```
   (Replace `{public_token}` with the one from the test script output)

2. **Click "Add to Apple Wallet"** button
   - This will use the properly signed URL automatically

3. **Verify pass appears in Wallet**

### Option B: Direct Download URL

1. **Copy the signed download URL** from test script output
   - It will look like: `https://testing.kawhe.shop/wallet/apple/.../download?signature=...`

2. **Open it in Safari on iPhone**
   - The pass should download and prompt to add to Wallet

## Step 4: Test Stamping

1. **Open the pass in Apple Wallet**
2. **Scan the QR code** with your merchant scanner
   - QR should show: `LA:{public_token}`
3. **Verify:**
   - Stamps increment
   - Pass updates automatically (if APNs enabled)
   - QR code changes to `LR:{redeem_token}` when reward earned

## Step 5: Test Redeeming

1. **Give the account a reward** (via tinker or scanner):
   ```bash
   php artisan tinker
   ```
   ```php
   $account = \App\Models\LoyaltyAccount::find(11); // Use your account ID
   $account->reward_balance = 1;
   $account->redeem_token = \Illuminate\Support\Str::random(40);
   $account->save();
   
   // Trigger wallet update
   \App\Jobs\UpdateWalletPassJob::dispatch($account->id);
   exit
   ```

2. **Check Wallet:**
   - QR code should now show `LR:{redeem_token}`
   - Scan it to redeem
   - Verify token rotates after redemption

## Quick Tinker Commands

### Get Account Info
```php
$account = \App\Models\LoyaltyAccount::find(11);
echo "Public Token: {$account->public_token}\n";
echo "Wallet Auth Token: {$account->wallet_auth_token}\n";
echo "Stamps: {$account->stamp_count}\n";
echo "Rewards: " . ($account->reward_balance ?? 0) . "\n";
echo "Card URL: " . config('app.url') . "/c/{$account->public_token}\n";
```

### Generate Signed Download URL
```php
$account = \App\Models\LoyaltyAccount::find(11);
$url = \Illuminate\Support\Facades\URL::signedRoute('wallet.apple.download', ['public_token' => $account->public_token]);
echo "Download URL: {$url}\n";
```

### Test Pass Generation
```php
$account = \App\Models\LoyaltyAccount::find(11);
$service = app(\App\Services\Wallet\AppleWalletPassService::class);
$pkpass = $service->generatePass($account);
file_put_contents('/tmp/test.pkpass', $pkpass);
echo "Pass saved to /tmp/test.pkpass\n";
echo "Size: " . strlen($pkpass) . " bytes\n";
```

### Add Rewards for Testing
```php
$account = \App\Models\LoyaltyAccount::find(11);
$account->reward_balance = 1;
$account->redeem_token = \Illuminate\Support\Str::random(40);
$account->save();
\App\Jobs\UpdateWalletPassJob::dispatch($account->id);
echo "Reward added, wallet update dispatched\n";
```

## Troubleshooting

### 403 Invalid Signature
- Make sure you're using `URL::signedRoute()` to generate URLs
- Check `APP_KEY` in `.env` is set correctly
- Clear config cache: `php artisan config:clear`

### 404 Not Found
- Card route is `/c/{public_token}`, not `/card/{public_token}`
- Download route is `/wallet/apple/{public_token}/download` (with signature)

### Pass Won't Download
- Check certificates exist: `ls -la passgenerator/certs/`
- Check logs: `tail -f storage/logs/laravel.log`
- Verify pass generation works: Run test script

### Pass Won't Update
- Check APNs is enabled: `php artisan config:show wallet.apple.push_enabled`
- Check registrations exist: `php artisan tinker --execute="echo \App\Models\AppleWalletRegistration::where('active', true)->count();"`
- Test APNs push: `php artisan wallet:apns-test kawhe-1-10`
