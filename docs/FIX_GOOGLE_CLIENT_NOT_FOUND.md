# Fix: Google_Client NOT FOUND Error

## Problem Identified

The debugging script shows:
- ✅ Service account file exists and is valid
- ✅ All environment variables set
- ❌ **Google_Client NOT FOUND**
- ❌ **Google_Service_Walletobjects NOT FOUND**

This means the Google API client classes aren't being autoloaded properly.

## Solution

### Step 1: Regenerate Composer Autoloader

```bash
composer dump-autoload --optimize
```

### Step 2: Verify Classes Are Installed

```bash
php -r "require 'vendor/autoload.php'; echo class_exists('Google_Client') ? '✅ Google_Client found' : '❌ Google_Client NOT found'; echo PHP_EOL;"
```

### Step 3: Clear All Laravel Caches

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan optimize:clear
```

### Step 4: Rebuild Caches

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Step 5: Fix Issuer ID

Your `.env` still has placeholder:
```
GOOGLE_WALLET_ISSUER_ID=your_issuer_id_here
```

Update it with your actual Issuer ID from Google Wallet API Console:
```bash
nano .env
# Change to:
GOOGLE_WALLET_ISSUER_ID=BCR2DN4TU6BMFTBR  # Your actual issuer ID
```

Then clear config again:
```bash
php artisan config:clear
php artisan config:cache
```

### Step 6: Test Again

```bash
php artisan tinker
```

```php
// Test 1: Check if classes exist
echo "Google_Client: " . (class_exists('Google_Client') ? '✅' : '❌') . "\n";
echo "Google_Service_Walletobjects: " . (class_exists('Google_Service_Walletobjects') ? '✅' : '❌') . "\n";

// Test 2: Try to create service
try {
    $service = new \App\Services\Wallet\GoogleWalletPassService();
    echo "✅ Service created\n";
    
    // Test 3: Try to generate link
    $account = \App\Models\LoyaltyAccount::first();
    if ($account) {
        $url = $service->generateSaveLink($account);
        echo "✅ URL generated: " . substr($url, 0, 80) . "...\n";
    }
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
```

## If Classes Still Not Found

### Option 1: Reinstall Package

```bash
composer remove google/apiclient
composer require google/apiclient
composer dump-autoload --optimize
```

### Option 2: Check Vendor Directory

```bash
ls -la vendor/google/apiclient/src/Google/
```

Should show `Client.php` file.

### Option 3: Check Autoload Files

```bash
grep -r "Google_Client" vendor/composer/autoload_classmap.php
```

Should show the class mapping.

## Quick Fix Command Sequence

Run all these in order:

```bash
# 1. Regenerate autoloader
composer dump-autoload --optimize

# 2. Clear all caches
php artisan optimize:clear

# 3. Rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 4. Fix issuer ID in .env (edit manually)
# GOOGLE_WALLET_ISSUER_ID=BCR2DN4TU6BMFTBR

# 5. Clear config again after .env change
php artisan config:clear
php artisan config:cache

# 6. Test
php artisan tinker --execute="echo class_exists('Google_Client') ? '✅ Found' : '❌ Not Found';"
```
