# Quick Fix for Apple Wallet 500 Error

## Step 1: Check Latest Error

```bash
tail -n 100 storage/logs/laravel.log | grep -A 30 "Apple Wallet"
```

Or get the last error:
```bash
tail -n 200 storage/logs/laravel.log | grep "production.ERROR" | tail -1
```

## Step 2: Verify Certificate Files Exist

```bash
# Check if files exist
ls -la storage/app/private/passgenerator/certs/

# Should show:
# -rw-r--r-- certificate.p12
# -rw-r--r-- AppleWWDRCA.pem
```

If files don't exist, upload them to:
- `storage/app/private/passgenerator/certs/certificate.p12`
- `storage/app/private/passgenerator/certs/AppleWWDRCA.pem`

## Step 3: Verify Config Values

```bash
php artisan tinker
```

Then run:
```php
echo "Certificate Path: " . config('passgenerator.certificate_store_path') . "\n";
echo "WWDR Path: " . config('passgenerator.wwdr_certificate_path') . "\n";
echo "Pass Type ID: " . config('passgenerator.pass_type_identifier') . "\n";
echo "Team ID: " . config('passgenerator.team_identifier') . "\n";
echo "Org Name: " . config('passgenerator.organization_name') . "\n";
echo "Storage Disk: " . config('passgenerator.storage_disk') . "\n";
echo "Storage Path: " . config('passgenerator.storage_path') . "\n";
```

All should have values (not empty).

## Step 4: Test Certificate Password

```bash
# Replace YOUR_PASSWORD with actual password from .env
openssl pkcs12 -info -in storage/app/private/passgenerator/certs/certificate.p12 -passin pass:YOUR_PASSWORD -noout
```

If this fails, the password is wrong.

## Step 5: Check File Permissions

```bash
# Certificates should be readable
chmod 644 storage/app/private/passgenerator/certs/*.p12
chmod 644 storage/app/private/passgenerator/certs/*.pem

# Passes directory should be writable
chmod 775 storage/app/private/passgenerator/passes
chown -R www-data:www-data storage/app/private/passgenerator/
```

## Step 6: Test Pass Generation Manually

```bash
php artisan tinker
```

```php
use App\Models\LoyaltyAccount;
use App\Services\Wallet\AppleWalletPassService;

$account = LoyaltyAccount::first();
if (!$account) {
    echo "No loyalty accounts found. Create one first.\n";
    exit;
}

$service = new AppleWalletPassService();

try {
    echo "Generating pass for account: " . $account->public_token . "\n";
    $pkpass = $service->generatePass($account);
    echo "SUCCESS! Pass generated. Size: " . strlen($pkpass) . " bytes\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nFull trace:\n";
    echo $e->getTraceAsString() . "\n";
}
```

This will show the exact error.

## Common Errors and Fixes

### Error: "Certificate file not found"
- Upload certificate.p12 to `storage/app/private/passgenerator/certs/`
- Check CERTIFICATE_PATH in .env matches file location

### Error: "Invalid certificate password"
- Verify CERTIFICATE_PASS in .env is correct
- Test with openssl command (Step 4)

### Error: "Pass type identifier is required"
- Add APPLE_PASS_TYPE_IDENTIFIER to .env
- Format: `pass.com.kawhe.loyalty`

### Error: "Team identifier is required"
- Add APPLE_TEAM_IDENTIFIER to .env
- Get from Apple Developer Portal

### Error: "Unable to create directory"
- Fix permissions: `chmod 775 storage/app/private/passgenerator/passes`
- Fix ownership: `chown www-data:www-data storage/app/private/passgenerator/passes`

## Quick Checklist

Run this to verify everything:

```bash
echo "=== Checking Certificate Files ==="
ls -la storage/app/private/passgenerator/certs/ 2>/dev/null || echo "CERTIFICATE DIRECTORY NOT FOUND"

echo -e "\n=== Checking Passes Directory ==="
ls -ld storage/app/private/passgenerator/passes 2>/dev/null || echo "PASSES DIRECTORY NOT FOUND"

echo -e "\n=== Checking Permissions ==="
stat -c "%a %U:%G %n" storage/app/private/passgenerator/passes 2>/dev/null || echo "Cannot check permissions"

echo -e "\n=== Testing www-data Write Access ==="
sudo -u www-data touch storage/app/private/passgenerator/passes/test.txt 2>/dev/null && echo "✓ Write access OK" || echo "✗ Write access FAILED"
sudo -u www-data rm -f storage/app/private/passgenerator/passes/test.txt 2>/dev/null
```
