# Apple Wallet Certificate Issue - Debugging Guide

## Problem Identified

Your certificate is **self-signed** (`isurus.local`), but Apple Wallet requires a certificate issued by Apple through the Apple Developer Portal.

## What You Need

1. **Apple Developer Account** (paid $99/year)
2. **Pass Type ID** registered in Apple Developer Portal
3. **Apple-issued certificate** (not self-signed)

## Steps to Fix

### Step 1: Register Pass Type ID in Apple Developer Portal

1. Go to https://developer.apple.com/account
2. Navigate to **Certificates, Identifiers & Profiles**
3. Click **Identifiers** → **+** (Add new)
4. Select **Pass Type IDs**
5. Register: `pass.com.kawhe.loyalty`
6. Note your **Team ID** (e.g., `4XCV53NVXP`)

### Step 2: Create Pass Type ID Certificate

1. In Apple Developer Portal, go to your Pass Type ID
2. Click **Edit** → **Create Certificate**
3. Follow the wizard to create a certificate
4. Download the certificate
5. Double-click to install in Keychain Access (Mac)

### Step 3: Export Certificate as .p12

1. Open **Keychain Access** on Mac
2. Find your Pass Type ID certificate
3. Right-click → **Export**
4. Choose **Personal Information Exchange (.p12)**
5. **Important**: Use "Legacy" or "3DES" encryption (not AES-256)
6. Set a password
7. Save as `certificate.p12`

### Step 4: Update Your Server

1. Upload the new `certificate.p12` to:
   ```
   storage/app/private/passgenerator/certs/certificate.p12
   ```

2. Update `.env`:
   ```env
   CERTIFICATE_PASS=your_new_password
   APPLE_PASS_TYPE_IDENTIFIER=pass.com.kawhe.loyalty
   APPLE_TEAM_IDENTIFIER=4XCV53NVXP  # Your actual Team ID
   APPLE_ORGANIZATION_NAME=Kawhe
   ```

3. Clear cache:
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

### Step 5: Test Again

After updating the certificate, test pass generation:

```bash
php artisan tinker
```

```php
$account = \App\Models\LoyaltyAccount::first();
$service = new \App\Services\Wallet\AppleWalletPassService();
$pkpass = $service->generatePass($account);
echo "Pass generated: " . strlen($pkpass) . " bytes\n";
```

Then try downloading on iPhone Safari again.

## Why Self-Signed Certificates Don't Work

Apple Wallet validates passes using Apple's certificate chain. A self-signed certificate:
- ✅ Can generate a .pkpass file
- ✅ Has a valid signature
- ❌ Won't be trusted by Apple's validation
- ❌ Safari will show black screen or fail silently

## Alternative: Test Without Real Certificate

If you don't have an Apple Developer account yet, you can:
1. Test pass generation (works with self-signed)
2. Test pass structure (works)
3. But **cannot** actually add to Wallet on real devices

For production, you **must** have:
- Apple Developer account ($99/year)
- Registered Pass Type ID
- Apple-issued certificate

## Quick Check: Verify Your Setup

Run this to check your current config:

```bash
php artisan tinker
```

```php
echo "Pass Type ID: " . config('passgenerator.pass_type_identifier') . "\n";
echo "Team ID: " . config('passgenerator.team_identifier') . "\n";
echo "Org Name: " . config('passgenerator.organization_name') . "\n";
echo "Cert exists: " . (file_exists(storage_path('app/private/' . config('passgenerator.certificate_store_path'))) ? "YES" : "NO") . "\n";
```

If your Team ID is set and certificate exists, but it's still self-signed, you need to replace it with an Apple-issued certificate.
