# Fix OpenSSL "digital envelope routines::unsupported" Error

## The Problem

The error `error:0308010C:digital envelope routines::unsupported` means your `.p12` certificate was created with an older encryption algorithm that OpenSSL 3.x (used by PHP 8.x) doesn't support by default.

## The Solution: Re-export Certificate with Legacy Algorithm

### Option 1: Re-export from Keychain (Mac)

1. Open **Keychain Access**
2. Find your Pass Type ID certificate
3. Right-click → **Export**
4. Choose **Personal Information Exchange (.p12)**
5. **IMPORTANT**: When exporting, select **"Legacy"** or **"3DES"** encryption (not AES-256)
6. Save with a password

### Option 2: Convert Existing Certificate (Linux/Server)

If you have the certificate on your server, convert it:

```bash
# Convert to use legacy algorithm
openssl pkcs12 -in certificate.p12 -out certificate_new.p12 -legacy -nodes

# This will prompt for the old password, then you can set a new one
# Or keep the same password
```

### Option 3: Use OpenSSL Legacy Provider (Temporary Workaround)

Add this to your PHP configuration or create a wrapper:

**Create a script to enable legacy provider:**

```bash
# Check OpenSSL version
openssl version

# If OpenSSL 3.x, you can use legacy provider
export OPENSSL_CONF=/dev/null
```

But this is not recommended for production. Better to re-export the certificate.

## Step-by-Step: Re-export from Apple Developer Portal

1. Go to [Apple Developer Portal → Certificates](https://developer.apple.com/account/resources/certificates/list)
2. Find your Pass Type ID certificate
3. Download it (it will be a `.cer` file)
4. **On Mac:**
   - Double-click to import to Keychain
   - Open Keychain Access
   - Find the certificate
   - Right-click → Export
   - Choose **.p12 format**
   - **Select "Legacy" encryption** (not AES-256)
   - Set password
   - Upload to server

5. **On Linux/Server:**
   ```bash
   # Convert .cer to .p12 with legacy algorithm
   openssl x509 -inform DER -in certificate.cer -out certificate.pem
   openssl pkcs12 -export -legacy -out certificate.p12 -inkey private_key.pem -in certificate.pem
   ```

## Quick Test After Re-export

```bash
# Test if certificate can be read
openssl pkcs12 -info -in storage/app/private/passgenerator/certs/certificate.p12 -passin pass:YOUR_PASSWORD -legacy -noout
```

If this works, the certificate is compatible.

## Alternative: Use OpenSSL Legacy Provider in PHP

If you can't re-export immediately, you can enable legacy provider:

**Create `/etc/ssl/openssl.cnf` or modify existing:**

```ini
openssl_conf = openssl_init

[openssl_init]
providers = provider_sect

[provider_sect]
default = default_sect
legacy = legacy_sect

[default_sect]
activate = 1

[legacy_sect]
activate = 1
```

Then restart PHP-FPM:
```bash
sudo systemctl restart php8.2-fpm
# or
sudo systemctl restart php-fpm
```

## Recommended Solution

**Re-export the certificate with legacy encryption** - this is the cleanest solution and doesn't require server configuration changes.

## After Fixing Certificate

1. Upload the new certificate to server
2. Test:
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
       echo "SUCCESS! Size: " . strlen($pkpass) . " bytes\n";
   } catch (\Exception $e) {
       echo "ERROR: " . $e->getMessage() . "\n";
   }
   ```
