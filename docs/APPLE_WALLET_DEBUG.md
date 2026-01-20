# Apple Wallet 500 Error - Step-by-Step Debugging Guide

If you're getting a 500 server error when clicking "Add to Apple Wallet", follow these steps:

## Step 1: Check Laravel Logs

**On your server, check the Laravel log file:**
```bash
tail -f storage/logs/laravel.log
```

Then try to download the pass again. The log will show the exact error message.

**Or check the last 50 lines:**
```bash
tail -n 50 storage/logs/laravel.log | grep -A 20 "Apple Wallet"
```

## Step 2: Verify Certificate File Location

The package expects certificates in the storage disk. Check your `.env`:

```env
CERTIFICATE_PATH=passgenerator/certs/certificate.p12
CERTIFICATE_PASS=your_password_here
WWDR_CERTIFICATE=passgenerator/certs/AppleWWDRCA.pem
```

**Verify files exist:**
```bash
# Check if certificate exists
ls -la storage/app/passgenerator/certs/certificate.p12

# Check if WWDR certificate exists
ls -la storage/app/passgenerator/certs/AppleWWDRCA.pem

# Check file permissions (should be readable)
chmod 644 storage/app/passgenerator/certs/certificate.p12
chmod 644 storage/app/passgenerator/certs/AppleWWDRCA.pem
```

**Important:** The path in `.env` is relative to `storage/app/` (or your configured storage disk).

## Step 3: Verify Package Configuration

The package uses its own config. Check if it's published:

```bash
php artisan vendor:publish --provider="Byte5\PassGeneratorServiceProvider"
```

**Check the package config file:**
```bash
cat config/passgenerator.php
```

**Verify environment variables are loaded:**
```bash
php artisan tinker
```
```php
config('passgenerator.certificate_path')
config('passgenerator.certificate_pass')
config('passgenerator.wwdr_certificate')
config('passgenerator.pass_type_identifier')
config('passgenerator.team_identifier')
```

## Step 4: Check Certificate Password

**Verify the certificate password is correct:**
```bash
# Test if certificate can be opened (replace with your actual password)
openssl pkcs12 -info -in storage/app/passgenerator/certs/certificate.p12 -passin pass:YOUR_PASSWORD -noout
```

If this fails, the password is wrong.

## Step 5: Test Pass Generation Manually

**Create a test script:**
```bash
php artisan tinker
```

```php
use App\Models\LoyaltyAccount;
use App\Services\Wallet\AppleWalletPassService;

$account = LoyaltyAccount::first();
$service = new AppleWalletPassService();

try {
    $pkpass = $service->generatePass($account);
    echo "Success! Pass size: " . strlen($pkpass) . " bytes\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
```

## Step 6: Common Issues and Fixes

### Issue 1: "Certificate file not found"
**Fix:**
- Verify file path in `.env` matches actual file location
- Path should be relative to `storage/app/` (or your storage disk)
- Example: If file is at `storage/app/passgenerator/certs/certificate.p12`, use `passgenerator/certs/certificate.p12` in `.env`

### Issue 2: "Invalid certificate password"
**Fix:**
- Double-check `CERTIFICATE_PASS` in `.env`
- Make sure there are no extra spaces or quotes
- Test with `openssl` command (Step 4)

### Issue 3: "WWDR certificate not found"
**Fix:**
- Download WWDR certificate from Apple: https://www.apple.com/certificateauthority/
- Convert to `.pem` format
- Place in `storage/app/passgenerator/certs/AppleWWDRCA.pem`

### Issue 4: "Pass type identifier mismatch"
**Fix:**
- Verify `APPLE_PASS_TYPE_IDENTIFIER` matches your Apple Developer Portal
- Format: `pass.com.yourcompany.appname`
- Must match exactly (case-sensitive)

### Issue 5: "Team identifier missing"
**Fix:**
- Get your Team ID from Apple Developer Portal
- Add to `.env`: `APPLE_TEAM_IDENTIFIER=YOUR_TEAM_ID`
- Usually 10 characters (letters and numbers)

### Issue 6: Config not cached properly
**Fix:**
```bash
php artisan config:clear
php artisan config:cache
```

### Issue 7: File permissions
**Fix:**
```bash
chmod 644 storage/app/passgenerator/certs/*.p12
chmod 644 storage/app/passgenerator/certs/*.pem
chown www-data:www-data storage/app/passgenerator/certs/*  # Adjust user/group as needed
```

## Step 7: Enable Debug Mode (Temporary)

**In `.env`:**
```env
APP_DEBUG=true
```

This will show detailed error messages in the response. **Remember to disable after debugging!**

## Step 8: Check Package Storage Disk Configuration

The package might be using a different storage disk. Check:

```bash
php artisan tinker
```
```php
config('passgenerator.storage_disk')
config('passgenerator.config_disk')
config('filesystems.disks.local.root')
```

If using a custom disk, make sure certificates are in the correct location.

## Step 9: Verify Required PHP Extensions

```bash
php -m | grep -E "openssl|zip|json"
```

All three should be listed. If missing:
```bash
# Ubuntu/Debian
sudo apt-get install php-openssl php-zip php-json

# Restart PHP-FPM
sudo systemctl restart php8.2-fpm  # Adjust version
```

## Step 10: Test with Minimal Pass Definition

If still failing, test with minimal configuration:

```php
// In tinker
$pass = new \Byte5\PassGenerator('test-123');
$pass->setPassDefinition([
    'formatVersion' => 1,
    'passTypeIdentifier' => config('passgenerator.pass_type_identifier'),
    'teamIdentifier' => config('passgenerator.team_identifier'),
    'organizationName' => config('passgenerator.organization_name'),
    'description' => 'Test',
    'serialNumber' => 'test-123',
    'storeCard' => [
        'primaryFields' => [
            ['key' => 'test', 'label' => 'Test', 'value' => 'Value']
        ]
    ]
]);

try {
    $pkpass = $pass->create();
    echo "Success!\n";
} catch (\Exception $e) {
    echo $e->getMessage() . "\n";
}
```

## Quick Checklist

- [ ] Certificate file exists at correct path
- [ ] WWDR certificate exists at correct path
- [ ] Certificate password is correct
- [ ] File permissions are correct (644)
- [ ] `.env` variables are set correctly
- [ ] Config is cached: `php artisan config:cache`
- [ ] PHP extensions installed (openssl, zip, json)
- [ ] Team ID and Pass Type ID are correct
- [ ] Checked Laravel logs for exact error
- [ ] Tested certificate with openssl command

## Still Not Working?

1. **Check the exact error in logs** - this is the most important step
2. **Share the error message** from `storage/logs/laravel.log`
3. **Verify certificate is valid** - not expired, correct format
4. **Test on local environment first** - easier to debug

## Production-Specific Issues

- **Nginx/Apache permissions**: Web server user needs read access to certificate files
- **SELinux**: May block file access (if enabled)
- **AppArmor**: May restrict file access (if enabled)
- **Storage disk**: If using S3 or other cloud storage, certificates must be on local disk
