# Testing Pass Branding & Circle Indicators

This guide covers testing the new merchant-controlled pass branding and circle indicator features.

## Prerequisites

1. **Run the migration:**
   ```bash
   php artisan migrate
   ```

2. **Ensure you have:**
   - A merchant account with at least one store
   - Test images for pass logo and hero image (PNG/JPG/WebP, max 2MB)
   - Access to test on a device with Apple Wallet or Google Wallet

## Step 1: Test Store Branding Assets Upload

### 1.1 Upload Pass Logo and Hero Image

1. Log in as a merchant
2. Navigate to **Stores** → **Edit Store**
3. Scroll to the new upload fields:
   - **Pass Logo (Wallet Passes)**: Upload a logo (recommended: 160x50px)
   - **Pass Hero Image (Wallet Passes)**: Upload a banner image (recommended: 640x180px for Apple, 640x200px for Google)
4. Click **Update Store**
5. Verify the images appear in the preview

### 1.2 Verify Images Are Stored

```bash
# Check if images are stored correctly
ls -la storage/app/public/pass-logos/
ls -la storage/app/public/pass-heroes/
```

## Step 2: Test Brand Colors

### 2.1 Set Store Colors

1. In **Store Edit**, set:
   - **Brand Color**: e.g., `#0EA5E9` (blue)
   - **Background Color**: e.g., `#1F2937` (dark gray)
2. Save the store

### 2.2 Verify Colors in Database

```bash
php artisan tinker
```

```php
$store = \App\Models\Store::first();
echo "Brand Color: " . $store->brand_color . "\n";
echo "Background Color: " . $store->background_color . "\n";
echo "Pass Logo: " . ($store->pass_logo_path ?? 'not set') . "\n";
echo "Pass Hero: " . ($store->pass_hero_image_path ?? 'not set') . "\n";
```

## Step 3: Test Circle Indicators

### 3.1 Create a Test Loyalty Account

1. As a customer, join a store and create a loyalty card
2. Note the `reward_target` (e.g., 5 stamps)

### 3.2 Test Different Stamp Counts

Use tinker to test different stamp counts:

```bash
php artisan tinker
```

```php
$account = \App\Models\LoyaltyAccount::first();
$store = $account->store;
$user = $store->user;

// Test with 0 stamps (should show all empty circles)
$account->stamp_count = 0;
$account->save();
// Generate pass and check: should show "○○○○○"

// Test with 3 stamps out of 5 (should show 3 filled, 2 empty)
$account->stamp_count = 3;
$account->save();
// Generate pass and check: should show "●●●○○"

// Test with full stamps (should show all filled)
$account->stamp_count = 5;
$account->save();
// Generate pass and check: should show "●●●●●"

// Test overshoot (stamp_count > reward_target)
$account->stamp_count = 7; // More than target of 5
$account->save();
// Generate pass and check: should show "●●●●●" (all filled, clamped)
```

### 3.3 Verify Circle Generation Logic

```php
$service = app(\App\Services\Wallet\AppleWalletPassService::class);

// Test circle generation directly
$account = \App\Models\LoyaltyAccount::first();
$store = $account->store;

// Use reflection to test private method (or make it public for testing)
$reflection = new \ReflectionClass($service);
$method = $reflection->getMethod('generateCircleIndicators');
$method->setAccessible(true);

echo $method->invoke($service, 0, 5) . "\n";   // Should output: ○○○○○
echo $method->invoke($service, 3, 5) . "\n";   // Should output: ●●●○○
echo $method->invoke($service, 5, 5) . "\n";   // Should output: ●●●●●
echo $method->invoke($service, 7, 5) . "\n";   // Should output: ●●●●● (clamped)
```

## Step 4: Test Apple Wallet Pass

### 4.1 Generate Apple Wallet Pass

1. As a customer, view your loyalty card page
2. Click **Add to Apple Wallet**
3. Download the `.pkpass` file

### 4.2 Verify Pass Contents

1. **Check Pass Logo:**
   - If you uploaded a pass logo, it should appear in the pass
   - Otherwise, default logo should appear

2. **Check Hero/Strip Image:**
   - If you uploaded a hero image, it should appear as the strip
   - Otherwise, default strip should appear

3. **Check Colors:**
   - Background should match store's `background_color`
   - Text should match store's `brand_color`

4. **Check Circle Indicators:**
   - Primary field should show circles like "●●●○○" instead of "3/5"
   - Verify the number of filled circles matches `stamp_count` (clamped to `reward_target`)

### 4.3 Test Pass Update

1. Stamp the card (add stamps)
2. Wait for Apple Wallet to update (or manually refresh)
3. Verify circles update correctly

## Step 5: Test Google Wallet Pass

### 5.1 Generate Google Wallet Save Link

1. As a customer, view your loyalty card page
2. Click **Save to Google Wallet**
3. Complete the save process

### 5.2 Verify Pass Contents

1. **Check Program Logo:**
   - Should use pass logo if uploaded, otherwise store logo, otherwise default

2. **Check Hero Image:**
   - Should appear in the pass if uploaded

3. **Check Colors:**
   - Background should match store's `background_color`

4. **Check Circle Indicators:**
   - Text module "Progress" should show circles like "●●●○○ (3/5)"
   - Verify circles match stamp count

### 5.3 Test Pass Update

1. Stamp the card
2. Wait for Google Wallet to update (may take a few minutes)
3. Verify circles update correctly

## Step 6: Test Fallbacks

### 6.1 Test Without Pass Images

1. Create a store **without** uploading pass logo or hero image
2. Generate a wallet pass
3. Verify:
   - Default logo is used (for Apple Wallet)
   - Default strip is used (for Apple Wallet)
   - Store logo or default is used (for Google Wallet)

### 6.2 Test Without Colors

1. Create a store **without** setting brand/background colors
2. Generate a wallet pass
3. Verify:
   - Default colors are used (#1F2937 background, #FFFFFF text)

## Step 7: End-to-End Test

### 7.1 Complete Flow

1. **Merchant Setup:**
   - Create/edit store
   - Upload pass logo and hero image
   - Set brand and background colors

2. **Customer Enrollment:**
   - Customer joins store and creates loyalty card
   - Customer adds card to Apple Wallet or Google Wallet

3. **Stamping:**
   - Merchant scans customer's QR code
   - Add stamps (test various counts: 1, 2, 3, etc.)
   - Verify circles update in real-time

4. **Reward Earning:**
   - When stamps reach `reward_target`, verify reward is earned
   - Verify circles show all filled when reward is earned

5. **Redemption:**
   - Customer redeems reward
   - Verify pass updates correctly

## Step 8: Automated Testing

Run the test suite to verify everything works:

```bash
# Run all tests
./vendor/bin/pest

# Run specific wallet-related tests (if they exist)
./vendor/bin/pest tests/Feature/WalletTest.php

# Run store-related tests
./vendor/bin/pest tests/Feature/StoreTest.php
```

## Troubleshooting

### Images Not Appearing

1. **Check file permissions:**
   ```bash
   chmod -R 775 storage/app/public
   chown -R www-data:www-data storage/app/public
   ```

2. **Check storage link:**
   ```bash
   php artisan storage:link
   ```

3. **Verify image URLs are accessible:**
   ```bash
   curl -I https://your-domain.com/storage/pass-logos/your-image.png
   ```

### Colors Not Applying

1. **Verify colors are saved:**
   ```php
   $store = \App\Models\Store::first();
   dd($store->brand_color, $store->background_color);
   ```

2. **Check pass generation:**
   ```php
   $account = \App\Models\LoyaltyAccount::first();
   $service = app(\App\Services\Wallet\AppleWalletPassService::class);
   $pass = $service->generatePass($account);
   // Check the pass.json inside the .pkpass file
   ```

### Circles Not Showing

1. **Verify circle generation:**
   ```php
   $service = app(\App\Services\Wallet\AppleWalletPassService::class);
   // Use reflection to test generateCircleIndicators method
   ```

2. **Check pass definition:**
   - For Apple Wallet: Check `primaryFields[0].value` in pass.json
   - For Google Wallet: Check `textModulesData[0].body`

## Quick Test Script

Save this as `test-pass-branding.php`:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$store = \App\Models\Store::first();
if (!$store) {
    echo "No stores found. Create a store first.\n";
    exit(1);
}

echo "=== Store Branding Test ===\n";
echo "Store: {$store->name}\n";
echo "Brand Color: " . ($store->brand_color ?? 'not set') . "\n";
echo "Background Color: " . ($store->background_color ?? 'not set') . "\n";
echo "Pass Logo: " . ($store->pass_logo_path ?? 'not set') . "\n";
echo "Pass Hero: " . ($store->pass_hero_image_path ?? 'not set') . "\n\n";

$account = \App\Models\LoyaltyAccount::where('store_id', $store->id)->first();
if (!$account) {
    echo "No loyalty accounts found for this store.\n";
    exit(1);
}

echo "=== Circle Indicators Test ===\n";
$rewardTarget = $store->reward_target ?? 10;
echo "Reward Target: {$rewardTarget}\n";
echo "Current Stamps: {$account->stamp_count}\n\n";

// Test circle generation
$service = app(\App\Services\Wallet\AppleWalletPassService::class);
$reflection = new \ReflectionClass($service);
$method = $reflection->getMethod('generateCircleIndicators');
$method->setAccessible(true);

echo "Circle Indicators:\n";
for ($i = 0; $i <= min($rewardTarget + 2, 10); $i++) {
    $circles = $method->invoke($service, $i, $rewardTarget);
    echo "  {$i} stamps: {$circles}\n";
}

echo "\n=== Test Complete ===\n";
```

Run it:
```bash
php test-pass-branding.php
```
