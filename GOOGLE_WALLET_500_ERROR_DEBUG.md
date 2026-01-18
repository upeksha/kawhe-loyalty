# Google Wallet 500 Error - Debugging Guide

## Common Causes

### 1. Missing Dependencies

**Error**: Class `Google_Client` not found

**Solution**:
```bash
composer require google/apiclient
composer install --no-dev --optimize-autoloader
```

### 2. Missing Environment Variables

**Check your `.env` file has**:
```env
GOOGLE_WALLET_ISSUER_ID=your_issuer_id
GOOGLE_WALLET_CLASS_ID=loyalty_class_kawhe
GOOGLE_WALLET_SERVICE_ACCOUNT_KEY=storage/app/private/google-wallet/service-account.json
```

**Verify they're loaded**:
```bash
php artisan tinker
```
```php
config('services.google_wallet.issuer_id')
config('services.google_wallet.class_id')
config('services.google_wallet.service_account_key')
```

If any return `null`, clear config cache:
```bash
php artisan config:clear
php artisan config:cache
```

### 3. Service Account Key Not Found

**Check file exists**:
```bash
ls -la storage/app/private/google-wallet/service-account.json
```

**Check path in .env matches actual location**:
```bash
# If file is at: storage/app/private/google-wallet/service-account.json
# .env should have:
GOOGLE_WALLET_SERVICE_ACCOUNT_KEY=storage/app/private/google-wallet/service-account.json

# Or absolute path:
GOOGLE_WALLET_SERVICE_ACCOUNT_KEY=/var/www/kawhe/storage/app/private/google-wallet/service-account.json
```

**Check permissions**:
```bash
chmod 600 storage/app/private/google-wallet/service-account.json
chown www-data:www-data storage/app/private/google-wallet/service-account.json
```

### 4. Invalid Service Account JSON

**Verify JSON is valid**:
```bash
cat storage/app/private/google-wallet/service-account.json | php -r "json_decode(file_get_contents('php://stdin')); echo json_last_error() === JSON_ERROR_NONE ? 'Valid JSON' : 'Invalid JSON';"
```

**Check required fields exist**:
```bash
php artisan tinker
```
```php
$keyPath = config('services.google_wallet.service_account_key');
$keyPath = storage_path('app/private/' . $keyPath); // Adjust if using absolute path
$credentials = json_decode(file_get_contents($keyPath), true);
echo "Has client_email: " . (isset($credentials['client_email']) ? 'Yes' : 'No') . "\n";
echo "Has private_key: " . (isset($credentials['private_key']) ? 'Yes' : 'No') . "\n";
echo "Has project_id: " . (isset($credentials['project_id']) ? 'Yes' : 'No') . "\n";
```

### 5. OpenSSL Extension Missing

**Check if OpenSSL is enabled**:
```bash
php -m | grep openssl
```

If not found, install:
```bash
# Ubuntu/Debian
sudo apt-get install php-openssl
sudo systemctl restart php8.2-fpm  # or your PHP version
```

### 6. Google API Authentication Issues

**Test service account access**:
```bash
php artisan tinker
```
```php
try {
    $service = new \App\Services\Wallet\GoogleWalletPassService();
    echo "✅ Service initialized successfully\n";
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
```

## Step-by-Step Debugging

### Step 1: Check Laravel Logs

```bash
tail -f storage/logs/laravel.log
```

Then click "Add to Google Wallet" again and watch for errors.

### Step 2: Test Service Initialization

```bash
php artisan tinker
```
```php
// Test 1: Check config
echo "Issuer ID: " . config('services.google_wallet.issuer_id') . "\n";
echo "Class ID: " . config('services.google_wallet.class_id') . "\n";
echo "Key Path: " . config('services.google_wallet.service_account_key') . "\n";

// Test 2: Check file exists
$keyPath = config('services.google_wallet.service_account_key');
$fullPath = storage_path('app/private/' . $keyPath);
echo "Full path: " . $fullPath . "\n";
echo "Exists: " . (file_exists($fullPath) ? 'Yes' : 'No') . "\n";

// Test 3: Try to initialize service
try {
    $service = new \App\Services\Wallet\GoogleWalletPassService();
    echo "✅ Service created\n";
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
```

### Step 3: Test Pass Generation

```bash
php artisan tinker
```
```php
$account = \App\Models\LoyaltyAccount::first();
echo "Account ID: " . $account->id . "\n";
echo "Public Token: " . $account->public_token . "\n";

try {
    $service = new \App\Services\Wallet\GoogleWalletPassService();
    $url = $service->generateSaveLink($account);
    echo "✅ Save URL generated: " . $url . "\n";
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
```

### Step 4: Check Route Works

```bash
php artisan tinker
```
```php
$account = \App\Models\LoyaltyAccount::first();
$url = URL::signedRoute('wallet.google.save', ['public_token' => $account->public_token]);
echo "Signed URL: " . $url . "\n";
```

## Quick Fix Checklist

Run these commands in order:

```bash
# 1. Install dependencies
composer require google/apiclient
composer install --no-dev --optimize-autoloader

# 2. Verify .env has all required variables
grep GOOGLE_WALLET .env

# 3. Verify service account file exists
ls -la storage/app/private/google-wallet/service-account.json

# 4. Check file permissions
chmod 600 storage/app/private/google-wallet/service-account.json
chown www-data:www-data storage/app/private/google-wallet/service-account.json

# 5. Clear and rebuild config
php artisan config:clear
php artisan config:cache

# 6. Check OpenSSL
php -m | grep openssl

# 7. Check logs
tail -20 storage/logs/laravel.log
```

## Common Error Messages

### "Service account key not found"
- **Fix**: Check path in `.env` matches actual file location
- **Fix**: Use absolute path if relative doesn't work

### "Failed to load private key"
- **Fix**: Verify JSON file has `private_key` field
- **Fix**: Check OpenSSL extension is enabled

### "Invalid issuer ID"
- **Fix**: Verify Issuer ID from Google Wallet API Console
- **Fix**: Clear config cache after updating `.env`

### "Permission denied"
- **Fix**: Check service account has "Wallet Object Issuer" role
- **Fix**: Verify Google Wallet API is enabled in Google Cloud

### "Class Google_Client not found"
- **Fix**: Run `composer require google/apiclient`
- **Fix**: Run `composer install --no-dev --optimize-autoloader`

## Enable Detailed Error Display

Temporarily enable debug mode to see full error:

```bash
# Edit .env
APP_DEBUG=true
APP_ENV=local

# Clear config
php artisan config:clear
```

**⚠️ Remember to disable after debugging:**
```bash
APP_DEBUG=false
APP_ENV=production
php artisan config:clear
php artisan config:cache
```

## Test Directly in Controller

Add temporary logging to see exact error:

Edit `app/Http/Controllers/WalletController.php`:

```php
public function saveGooglePass(string $public_token)
{
    try {
        \Log::info('Google Wallet: Starting', ['public_token' => $public_token]);
        
        $account = LoyaltyAccount::with(['store', 'customer'])
            ->where('public_token', $public_token)
            ->firstOrFail();
        
        \Log::info('Google Wallet: Account found', ['account_id' => $account->id]);
        
        $saveUrl = $this->googlePassService->generateSaveLink($account);
        
        \Log::info('Google Wallet: URL generated', ['url' => $saveUrl]);
        
        return redirect($saveUrl);
    } catch (\Exception $e) {
        \Log::error('Google Wallet: Error', [
            'public_token' => $public_token,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);
        
        // ... rest of error handling
    }
}
```

Then check logs:
```bash
tail -f storage/logs/laravel.log
```
