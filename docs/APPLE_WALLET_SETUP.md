# Apple Wallet Setup Guide

This guide explains how to set up Apple Wallet pass generation for Kawhe loyalty cards.

## Prerequisites

1. **Apple Developer Account** with Wallet capability enabled
2. **Pass Type ID** created in Apple Developer Portal
3. **Certificate** (.p12 file) downloaded from Apple Developer Portal
4. **WWDR Certificate** (Apple Worldwide Developer Relations Certificate)

## Step 1: Install Package

```bash
composer require byte5/laravel-passgenerator
```

## Step 2: Configure Environment Variables

Add these to your `.env` file:

```env
# Apple Wallet Configuration
CERTIFICATE_PATH=passgenerator/certs/certificate.p12
CERTIFICATE_PASS=your_certificate_password
WWDR_CERTIFICATE=passgenerator/certs/AppleWWDRCA.pem
APPLE_PASS_TYPE_IDENTIFIER=pass.com.kawhe.loyalty
APPLE_TEAM_IDENTIFIER=YOUR_TEAM_ID
APPLE_ORGANIZATION_NAME=Kawhe
```

**Important**: Replace `YOUR_TEAM_ID` with your actual Apple Team ID (found in Apple Developer Portal).

## Step 3: Store Certificates

1. Create the certificates directory:
   ```bash
   mkdir -p storage/app/passgenerator/certs
   ```

2. Place your certificates:
   - `storage/app/passgenerator/certs/certificate.p12` - Your pass certificate
   - `storage/app/passgenerator/certs/AppleWWDRCA.pem` - WWDR certificate (download from Apple)

3. Set proper permissions:
   ```bash
   chmod 600 storage/app/passgenerator/certs/*.p12
   chmod 644 storage/app/passgenerator/certs/*.pem
   ```

## Step 4: Add Pass Images

Create placeholder images in `resources/wallet/apple/default/`:

- **icon.png** - 29x29px (required) - Icon shown in notifications and on lock screen
- **logo.png** - 160x50px (required) - Logo shown at top of pass
- **background.png** - 180x220px (optional) - Background image for pass
- **strip.png** - 375x98px (optional) - Strip image shown behind pass content

**Image Requirements**:
- Format: PNG with transparency
- Dimensions: Exact sizes as specified above
- For now, create simple placeholder images (colored squares with text)

**Quick Placeholder Creation** (using ImageMagick or online tools):
```bash
# Example: Create simple placeholder icon
convert -size 29x29 xc:#0EA5E9 -pointsize 20 -fill white -gravity center -annotate +0+0 "K" icon.png
```

Or use an online tool like https://placeholder.com or create simple colored PNGs.

## Step 5: Test the Implementation

1. **Create a test loyalty account**:
   ```bash
   php artisan tinker
   ```
   ```php
   $store = App\Models\Store::first();
   $customer = App\Models\Customer::first();
   $account = App\Models\LoyaltyAccount::where('store_id', $store->id)->where('customer_id', $customer->id)->first();
   echo $account->public_token;
   ```

2. **Generate signed URL** (in tinker or browser):
   ```php
   $url = URL::signedRoute('wallet.apple.download', ['public_token' => 'YOUR_PUBLIC_TOKEN']);
   echo $url;
   ```

3. **Test on iPhone**:
   - Open the signed URL in Safari on iPhone
   - The pass should download and prompt to "Add to Apple Wallet"
   - Verify the barcode contains the `public_token`
   - Verify stamps and rewards are displayed correctly

## Step 6: Verify Barcode

**Critical**: The barcode in the Apple Wallet pass must contain **exactly** the `public_token` (without any prefix).

When a merchant scans the pass:
- Scanner expects: `LA:{public_token}` format
- But the pass barcode contains: `{public_token}` only
- **Solution**: The scanner should strip the `LA:` prefix if present, OR we can add it to the pass barcode

**Current Implementation**: Pass barcode contains `public_token` only. Scanner already handles both formats (with/without `LA:` prefix).

## Troubleshooting

### "Invalid pass" error
- Check certificate path and password are correct
- Verify WWDR certificate is valid and not expired
- Ensure `APPLE_PASS_TYPE_IDENTIFIER` matches your Apple Developer Portal configuration

### Pass downloads but won't install
- Verify Team ID is correct
- Check pass type identifier matches Apple Developer Portal
- Ensure certificates are not expired

### Barcode doesn't scan
- Verify barcode message equals `public_token` exactly
- Test with a QR code scanner app to see what the barcode contains
- Ensure scanner is looking for the correct format

### Images not showing
- Verify image files exist in `resources/wallet/apple/default/`
- Check image dimensions match requirements exactly
- Ensure images are valid PNG files

## Production Deployment

1. **Upload certificates securely**:
   - Never commit certificates to git
   - Use secure file transfer (SFTP) or environment-specific storage
   - Set restrictive file permissions (600 for .p12, 644 for .pem)

2. **Update .env** with production values:
   ```env
   APP_URL=https://app.kawhe.shop
   APPLE_PASS_TYPE_IDENTIFIER=pass.com.kawhe.loyalty
   APPLE_TEAM_IDENTIFIER=YOUR_PRODUCTION_TEAM_ID
   ```

3. **Clear config cache**:
   ```bash
   php artisan config:clear
   php artisan config:cache
   ```

4. **Test in production**:
   - Generate a pass from production URL
   - Verify it installs correctly on iPhone
   - Test scanning the barcode with merchant scanner

## Security Notes

- The download route uses **signed URLs** for security
- `public_token` is already random and unguessable (40 characters)
- Signed URLs add an extra layer of protection
- Passes are generated on-demand (not cached) to ensure fresh data

## Next Steps (Phase 2)

Future enhancements:
- Push notifications when stamps/rewards change
- Automatic pass updates via Apple Push Notification service
- Pass expiration and renewal
- Multiple pass types (coupon, event ticket, etc.)
