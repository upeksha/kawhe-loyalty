# Fix for "Unable to create a directory" Error

## The Problem

The error shows:
```
Unable to create a directory at /var/www/kawhe/storage/app/private/passgenerator/certs/kawhe-2-4.
```

**Issues:**
1. Package is using "private" disk instead of "local"
2. Passes are being saved in "certs" directory (should be "passes")
3. Directory creation failing due to permissions

## The Fix

### Step 1: Create the Passes Directory

```bash
# Create the directory structure
mkdir -p /var/www/kawhe/storage/app/passgenerator/passes

# Set proper permissions (web server user needs write access)
chmod 775 /var/www/kawhe/storage/app/passgenerator/passes
chown www-data:www-data /var/www/kawhe/storage/app/passgenerator/passes
# OR if your web server user is different:
# chown nginx:nginx /var/www/kawhe/storage/app/passgenerator/passes
```

### Step 2: Update .env File

Make sure your `.env` has:

```env
# Use 'local' disk, not 'private'
PASSGENERATOR_STORAGE_DISK=local

# Store passes in 'passes' directory, NOT 'certs'
PASSGENERATOR_STORAGE_PATH=passgenerator/passes

# Certificates should be in a different location
CERTIFICATE_PATH=passgenerator/certs/certificate.p12
WWDR_CERTIFICATE=passgenerator/certs/AppleWWDRCA.pem
```

### Step 3: Clear and Recache Config

```bash
cd /var/www/kawhe
php artisan config:clear
php artisan config:cache
```

### Step 4: Verify Directory Permissions

```bash
# Check current permissions
ls -la /var/www/kawhe/storage/app/passgenerator/

# Should show:
# drwxr-xr-x ... passgenerator/
#   drwxr-xr-x ... certs/        (for certificates)
#   drwxrwxr-x ... passes/       (for generated passes - needs write permission)
```

### Step 5: Test Directory Creation

```bash
# Test if web server can create directories
sudo -u www-data mkdir -p /var/www/kawhe/storage/app/passgenerator/passes/test
sudo -u www-data rmdir /var/www/kawhe/storage/app/passgenerator/passes/test

# If this fails, fix permissions:
chmod 775 /var/www/kawhe/storage/app/passgenerator/passes
chown www-data:www-data /var/www/kawhe/storage/app/passgenerator/passes
```

## Quick Fix Commands

Run these commands on your server:

```bash
cd /var/www/kawhe

# Create passes directory
mkdir -p storage/app/passgenerator/passes

# Set permissions (adjust user/group as needed)
chmod 775 storage/app/passgenerator/passes
chown www-data:www-data storage/app/passgenerator/passes

# Update .env (if needed)
# Make sure PASSGENERATOR_STORAGE_DISK=local
# Make sure PASSGENERATOR_STORAGE_PATH=passgenerator/passes

# Clear and recache config
php artisan config:clear
php artisan config:cache

# Test
php artisan tinker
```

In tinker:
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
}
```

## Why This Happened

The package stores generated passes in a separate directory from certificates:
- **Certificates**: `storage/app/passgenerator/certs/` (read-only)
- **Generated Passes**: `storage/app/passgenerator/passes/` (write access needed)

The package was trying to create the pass file in the wrong location because:
1. The `passes` directory didn't exist
2. Or permissions were wrong
3. Or config was pointing to wrong disk/path

## After Fix

The pass should generate successfully and be stored at:
```
/var/www/kawhe/storage/app/passgenerator/passes/kawhe-2-4/kawhe-2-4.pkpass
```

The package will create a subdirectory for each pass ID automatically.
