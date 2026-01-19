# Test Google Wallet Full Flow

## Step 1: Check Latest Logs (All Errors)

```bash
tail -100 storage/logs/laravel.log
```

Look for any errors, especially around the time you clicked "Add to Google Wallet".

## Step 2: Test Full Flow in Tinker

```bash
php artisan tinker
```

Then run:

```php
// Get an account
$account = \App\Models\LoyaltyAccount::with(['store', 'customer'])->first();

if (!$account) {
    echo "❌ No accounts found. Create one first.\n";
    exit;
}

echo "Testing with Account ID: " . $account->id . "\n";
echo "Public Token: " . $account->public_token . "\n";
echo "Store: " . $account->store->name . "\n";
echo "\n";

// Test service creation
try {
    echo "1. Creating service...\n";
    $service = new \App\Services\Wallet\GoogleWalletPassService();
    echo "   ✅ Service created\n\n";
    
    // Test generating save link
    echo "2. Generating save link...\n";
    $url = $service->generateSaveLink($account);
    echo "   ✅ URL generated\n";
    echo "   URL: " . $url . "\n";
    echo "\n";
    echo "✅ SUCCESS! Copy this URL and open in browser.\n";
    
} catch (\Exception $e) {
    echo "❌ ERROR:\n";
    echo "   Message: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . "\n";
    echo "   Line: " . $e->getLine() . "\n";
    echo "\n";
    echo "Full trace:\n";
    echo $e->getTraceAsString() . "\n";
}
```

## Step 3: Check Specific Error Points

If it fails, test each step:

```php
// Test 1: Config
echo "Issuer ID: " . config('services.google_wallet.issuer_id') . "\n";
echo "Class ID: " . config('services.google_wallet.class_id') . "\n";
echo "Key Path: " . config('services.google_wallet.service_account_key') . "\n";

// Test 2: File exists
$keyPath = config('services.google_wallet.service_account_key');
$fullPath = storage_path('app/private/' . $keyPath);
echo "Full path: " . $fullPath . "\n";
echo "Exists: " . (file_exists($fullPath) ? 'Yes' : 'No') . "\n";

// Test 3: Read credentials
$credentials = json_decode(file_get_contents($fullPath), true);
echo "Has client_email: " . (isset($credentials['client_email']) ? 'Yes' : 'No') . "\n";
echo "Has private_key: " . (isset($credentials['private_key']) ? 'Yes' : 'No') . "\n";
```

## Step 4: Enable Debug Mode Temporarily

If you need more details, temporarily enable debug:

```bash
# Edit .env
nano .env

# Change:
APP_DEBUG=true
APP_ENV=local

# Clear config
php artisan config:clear
```

Then try clicking "Add to Google Wallet" again. You'll see the full error on the page.

**⚠️ Remember to disable after:**
```bash
APP_DEBUG=false
APP_ENV=production
php artisan config:clear
php artisan config:cache
```
