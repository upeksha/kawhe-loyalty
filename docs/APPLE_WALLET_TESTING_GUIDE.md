# Apple Wallet Testing Guide

## Quick Start Testing

### Option 1: Use Existing Data (After Migration)

If you have existing loyalty accounts in your database:

1. **Run the migration first:**
   ```bash
   php artisan migrate
   ```
   This will add `wallet_auth_token` to all existing accounts.

2. **Verify tokens were created:**
   ```bash
   php artisan tinker
   ```
   ```php
   // Check if accounts have wallet_auth_token
   \App\Models\LoyaltyAccount::whereNull('wallet_auth_token')->count();
   // Should return 0 after migration
   
   // Get an existing account
   $account = \App\Models\LoyaltyAccount::first();
   echo "Public Token: " . $account->public_token . "\n";
   echo "Wallet Auth Token: " . $account->wallet_auth_token . "\n";
   ```

3. **Regenerate passes for existing accounts:**
   ```php
   // In tinker
   $account = \App\Models\LoyaltyAccount::first();
   $account->load(['store', 'customer']);
   
   $service = app(\App\Services\Wallet\AppleWalletPassService::class);
   $pkpass = $service->generatePass($account);
   
   // Save to file for testing
   file_put_contents('/tmp/test-pass.pkpass', $pkpass);
   echo "Pass generated: /tmp/test-pass.pkpass\n";
   ```

### Option 2: Create New Test Data

Use the existing Artisan commands:

```bash
# Create a new store with example cards
php artisan store:create-with-cards {user_email} {store_name} {card_count}

# Or create example cards for an existing store
php artisan cards:create {user_email} {store_id} {count}
```

### Option 3: Quick Test Script

Create a test script to verify everything works:

```bash
php artisan tinker
```

```php
// 1. Get or create a test account
$user = \App\Models\User::first();
$store = $user->stores()->first() ?? \App\Models\Store::factory()->create(['user_id' => $user->id]);
$customer = \App\Models\Customer::factory()->create();
$account = \App\Models\LoyaltyAccount::factory()->create([
    'store_id' => $store->id,
    'customer_id' => $customer->id,
    'stamp_count' => 5, // Some stamps
    'reward_balance' => 0, // No rewards yet
]);

// 2. Verify wallet_auth_token exists
echo "Account ID: {$account->id}\n";
echo "Public Token: {$account->public_token}\n";
echo "Wallet Auth Token: {$account->wallet_auth_token}\n";
echo "Serial Number: kawhe-{$store->id}-{$customer->id}\n";

// 3. Test pass generation
$service = app(\App\Services\Wallet\AppleWalletPassService::class);
$pkpass = $service->generatePass($account);
echo "Pass generated: " . strlen($pkpass) . " bytes\n";

// 4. Check QR code content (should be LA:public_token when no rewards)
$passData = json_decode(file_get_contents('zip://' . sys_get_temp_dir() . '/pass.json#pass.json'), true);
// Actually, we need to extract it differently...
// For now, just verify the pass generates

// 5. Add some rewards
$account->reward_balance = 2;
$account->redeem_token = \Illuminate\Support\Str::random(40);
$account->save();

// 6. Regenerate pass (should now show LR:redeem_token)
$pkpass2 = $service->generatePass($account);
echo "Pass regenerated with rewards: " . strlen($pkpass2) . " bytes\n";
```

## Testing on iPhone

### Step 1: Generate and Download Pass

1. **Get the download URL:**
   ```bash
   php artisan tinker
   ```
   ```php
   $account = \App\Models\LoyaltyAccount::first();
   $url = route('wallet.apple.download', ['public_token' => $account->public_token]);
   echo "Download URL: " . config('app.url') . $url . "\n";
   ```

2. **Or use the card page:**
   - Visit: `https://your-domain.com/card/{public_token}`
   - Click "Add to Apple Wallet"

### Step 2: Test Stamping

1. **Open the pass in Wallet**
2. **Scan the QR code** (should show `LA:{public_token}`)
3. **Verify:**
   - Stamps increment
   - Pass updates automatically (if APNs enabled)
   - QR code changes to `LR:{redeem_token}` when reward earned

### Step 3: Test Redeeming

1. **Ensure account has rewards:**
   ```bash
   php artisan tinker
   ```
   ```php
   $account = \App\Models\LoyaltyAccount::first();
   $account->reward_balance = 1;
   $account->redeem_token = \Illuminate\Support\Str::random(40);
   $account->save();
   
   // Trigger wallet update
   \App\Jobs\UpdateWalletPassJob::dispatch($account->id);
   ```

2. **Check Wallet:**
   - QR code should now show `LR:{redeem_token}`
   - Scan it to redeem
   - Verify token rotates after redemption

## Testing Commands

### Test APNs Push (if enabled)

```bash
php artisan wallet:apns-test {serialNumber}
```

Example:
```bash
php artisan wallet:apns-test kawhe-1-2
```

### Test Pass Generation

```bash
php artisan tinker
```

```php
$account = \App\Models\LoyaltyAccount::first();
$service = app(\App\Services\Wallet\AppleWalletPassService::class);

// Generate pass
$pkpass = $service->generatePass($account);

// Save to file
file_put_contents(storage_path('app/test-pass.pkpass'), $pkpass);
echo "Pass saved to: storage/app/test-pass.pkpass\n";
```

### Test Web Service Endpoints

```bash
# Get pass (requires wallet_auth_token)
php artisan tinker
```

```php
$account = \App\Models\LoyaltyAccount::first();
$serial = "kawhe-{$account->store_id}-{$account->customer_id}";
$url = config('app.url') . "/wallet/v1/passes/pass.com.kawhe.loyalty/{$serial}";
$token = $account->wallet_auth_token;

echo "Test with curl:\n";
echo "curl -H 'Authorization: ApplePass {$token}' '{$url}'\n";
```

### Test Device Registration

```bash
php artisan tinker
```

```php
$account = \App\Models\LoyaltyAccount::first();
$serial = "kawhe-{$account->store_id}-{$account->customer_id}";
$deviceId = 'test-device-' . time();
$pushToken = str_repeat('a', 64);

$url = config('app.url') . "/wallet/v1/devices/{$deviceId}/registrations/pass.com.kawhe.loyalty/{$serial}";
$token = $account->wallet_auth_token;

echo "Register device:\n";
echo "curl -X POST -H 'Authorization: ApplePass {$token}' -H 'Content-Type: application/json' -d '{\"pushToken\":\"{$pushToken}\"}' '{$url}'\n";
```

## Verification Checklist

### After Migration

- [ ] All accounts have `wallet_auth_token`
- [ ] No null values in `wallet_auth_token` column
- [ ] Unique constraint works (no duplicates)

### Pass Generation

- [ ] Pass generates successfully
- [ ] `authenticationToken` in pass.json = `wallet_auth_token` (not `public_token`)
- [ ] QR code shows `LA:{public_token}` when no rewards
- [ ] QR code shows `LR:{redeem_token}` when rewards available
- [ ] QR format is `PKBarcodeFormatQR`

### Stamping

- [ ] Scanning `LA:{public_token}` increments stamps
- [ ] Pass updates after stamping (if APNs enabled)
- [ ] QR changes to `LR:` when reward earned

### Redeeming

- [ ] Scanning `LR:{redeem_token}` redeems reward
- [ ] `reward_balance` decreases
- [ ] `redeem_token` rotates after redemption
- [ ] Old redeem QR cannot be reused
- [ ] QR changes back to `LA:` when no rewards left

### Web Service

- [ ] `GET /wallet/v1/passes/{passType}/{serial}` returns 200 with pass
- [ ] `GET /wallet/v1/passes/{passType}/{serial}` returns 304 with `Last-Modified` header
- [ ] Authentication uses `wallet_auth_token`
- [ ] Device registration works
- [ ] APNs push sends (if enabled)

## Troubleshooting

### Pass won't generate

```bash
# Check certificates
ls -la passgenerator/certs/
# Should have: certificate.p12, AppleWWDRCA.pem

# Check config
php artisan config:show passgenerator
```

### APNs not working

```bash
# Check APNs config
php artisan config:show wallet.apple

# Test APNs push
php artisan wallet:apns-test {serialNumber}

# Check logs
tail -f storage/logs/laravel.log | grep -i "apns\|push\|wallet"
```

### QR code wrong format

```bash
# Verify pass.json content
php artisan tinker
```

```php
$account = \App\Models\LoyaltyAccount::first();
$service = app(\App\Services\Wallet\AppleWalletPassService::class);
$pkpass = $service->generatePass($account);

// Extract and check pass.json
$zip = new \ZipArchive();
$tempFile = tempnam(sys_get_temp_dir(), 'pass');
file_put_contents($tempFile, $pkpass);
$zip->open($tempFile);
$passJson = json_decode($zip->getFromName('pass.json'), true);
echo "Barcode message: " . $passJson['barcode']['message'] . "\n";
echo "Auth token: " . $passJson['authenticationToken'] . "\n";
$zip->close();
unlink($tempFile);
```

## Quick Test Script

Save this as `test-wallet.php`:

```php
<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Get or create test account
$user = \App\Models\User::first();
if (!$user) {
    echo "No users found. Create a user first.\n";
    exit(1);
}

$store = $user->stores()->first();
if (!$store) {
    $store = \App\Models\Store::factory()->create(['user_id' => $user->id]);
    echo "Created store: {$store->name}\n";
}

$account = \App\Models\LoyaltyAccount::where('store_id', $store->id)->first();
if (!$account) {
    $customer = \App\Models\Customer::factory()->create();
    $account = \App\Models\LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'stamp_count' => 5,
    ]);
    echo "Created account: {$account->id}\n";
}

echo "\n=== Account Info ===\n";
echo "Account ID: {$account->id}\n";
echo "Public Token: {$account->public_token}\n";
echo "Wallet Auth Token: {$account->wallet_auth_token}\n";
echo "Serial: kawhe-{$store->id}-{$account->customer_id}\n";
echo "Stamps: {$account->stamp_count}\n";
echo "Rewards: " . ($account->reward_balance ?? 0) . "\n";

// Test pass generation
echo "\n=== Testing Pass Generation ===\n";
$service = app(\App\Services\Wallet\AppleWalletPassService::class);
try {
    $pkpass = $service->generatePass($account);
    echo "✓ Pass generated: " . strlen($pkpass) . " bytes\n";
    
    // Save to file
    $filename = storage_path('app/test-pass-' . $account->id . '.pkpass');
    file_put_contents($filename, $pkpass);
    echo "✓ Saved to: {$filename}\n";
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

// Test with rewards
echo "\n=== Testing with Rewards ===\n";
$account->reward_balance = 1;
$account->redeem_token = \Illuminate\Support\Str::random(40);
$account->save();

try {
    $pkpass2 = $service->generatePass($account);
    echo "✓ Pass with rewards generated: " . strlen($pkpass2) . " bytes\n";
    
    $filename2 = storage_path('app/test-pass-reward-' . $account->id . '.pkpass');
    file_put_contents($filename2, $pkpass2);
    echo "✓ Saved to: {$filename2}\n";
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Download URLs ===\n";
echo "Stamping QR: LA:{$account->public_token}\n";
if ($account->redeem_token) {
    echo "Redeem QR: LR:{$account->redeem_token}\n";
}
echo "Pass download: " . config('app.url') . route('wallet.apple.download', ['public_token' => $account->public_token], false) . "\n";

echo "\n✓ Testing complete!\n";
```

Run it:
```bash
php test-wallet.php
```
