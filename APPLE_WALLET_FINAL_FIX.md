# Final Fix for Apple Wallet 500 Error

## Step 1: Pull Latest Changes

```bash
cd /var/www/kawhe
git pull
php artisan config:clear
php artisan config:cache
```

## Step 2: Check Latest Error

```bash
tail -n 200 storage/logs/laravel.log | grep -A 50 "Apple Wallet" | tail -60
```

Or get just the error message:
```bash
tail -n 200 storage/logs/laravel.log | grep "production.ERROR.*Apple Wallet" | tail -1
```

## Step 3: Common Issues After Fix

### Issue 1: "Pass file already exists"
**Status:** ✅ FIXED - Pull latest code

### Issue 2: Missing Image Assets
The pass needs image files. Check:
```bash
ls -la resources/wallet/apple/default/
```

Should have:
- `icon.png` (29x29px)
- `logo.png` (160x50px)
- Optional: `background.png`, `strip.png`

If missing, create placeholder images or the pass generation will fail.

### Issue 3: Certificate Still Not Working
Verify certificate can be read:
```bash
php -r '
$p=file_get_contents("/var/www/kawhe/storage/app/private/passgenerator/certs/certificate.p12");
$a=[];
$ok=openssl_pkcs12_read($p,$a,"P@55w0rd");
var_dump($ok);
if(!$ok) { while($e=openssl_error_string()) echo $e.PHP_EOL; }
'
```

Should return `bool(true)`. If false, certificate password is wrong.

### Issue 4: Missing Required Config
Check all config values:
```bash
php artisan tinker --execute="
echo 'Cert Path: ' . config('passgenerator.certificate_store_path') . PHP_EOL;
echo 'WWDR Path: ' . config('passgenerator.wwdr_certificate_path') . PHP_EOL;
echo 'Pass Type ID: ' . (config('passgenerator.pass_type_identifier') ?: 'NOT SET') . PHP_EOL;
echo 'Team ID: ' . (config('passgenerator.team_identifier') ?: 'NOT SET') . PHP_EOL;
echo 'Org Name: ' . (config('passgenerator.organization_name') ?: 'NOT SET') . PHP_EOL;
"
```

All should have values.

## Step 4: Test Pass Generation Manually

```bash
php artisan tinker
```

```php
use App\Models\LoyaltyAccount;
use App\Services\Wallet\AppleWalletPassService;

$account = LoyaltyAccount::where('public_token', 'Y6S1PZh6WRL2cukm5BozjqmyGG2HHUzuWsAwzOZI')->first();
if (!$account) {
    echo "Account not found\n";
    exit;
}

$service = new AppleWalletPassService();
try {
    $pkpass = $service->generatePass($account);
    echo "SUCCESS! Pass size: " . strlen($pkpass) . " bytes\n";
    file_put_contents('/tmp/test-pass.pkpass', $pkpass);
    echo "Saved to /tmp/test-pass.pkpass\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nFull trace:\n";
    echo $e->getTraceAsString() . "\n";
}
```

This will show the exact error.

## Step 5: Check Image Assets

If the error mentions missing images:

```bash
# Create placeholder images if they don't exist
mkdir -p resources/wallet/apple/default

# Create a simple 29x29 icon (if missing)
# You can use ImageMagick or download placeholder images
convert -size 29x29 xc:blue resources/wallet/apple/default/icon.png 2>/dev/null || echo "ImageMagick not installed"

# Create a simple 160x50 logo (if missing)
convert -size 160x50 xc:gray resources/wallet/apple/default/logo.png 2>/dev/null || echo "ImageMagick not installed"
```

Or download placeholder images from the web and place them in `resources/wallet/apple/default/`.

## Quick Checklist

Run this to verify everything:

```bash
echo "=== Certificate ==="
php -r '$p=file_get_contents("/var/www/kawhe/storage/app/private/passgenerator/certs/certificate.p12"); $a=[]; var_dump(openssl_pkcs12_read($p,$a,"P@55w0rd"));'

echo -e "\n=== Image Assets ==="
ls -la resources/wallet/apple/default/ 2>/dev/null || echo "Directory missing!"

echo -e "\n=== Config ==="
php artisan tinker --execute="echo 'Pass Type ID: ' . (config('passgenerator.pass_type_identifier') ?: 'NOT SET') . PHP_EOL; echo 'Team ID: ' . (config('passgenerator.team_identifier') ?: 'NOT SET') . PHP_EOL;"

echo -e "\n=== Latest Error ==="
tail -n 200 storage/logs/laravel.log | grep "production.ERROR.*Apple Wallet" | tail -1 | cut -d'"' -f4
```
