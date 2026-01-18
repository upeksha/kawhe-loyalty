# Google Wallet Testing Guide

This guide will help you test the Google Wallet integration after deployment.

## Prerequisites

Before testing, ensure you have:

1. ✅ **Google Cloud Project** created
2. ✅ **Google Wallet API** enabled
3. ✅ **Service Account** created with "Wallet Object Issuer" role
4. ✅ **Service Account JSON key** downloaded
5. ✅ **Issuer ID** from Google Wallet API console
6. ✅ **Dependencies installed**: `composer require google/apiclient`

## Step 1: Install Dependencies

```bash
composer require google/apiclient
```

## Step 2: Configure Environment Variables

Add to your `.env` file:

```env
GOOGLE_WALLET_ISSUER_ID=your_issuer_id_here
GOOGLE_WALLET_CLASS_ID=loyalty_class_kawhe
GOOGLE_WALLET_SERVICE_ACCOUNT_KEY=storage/app/private/google-wallet/service-account.json
```

**Important**: Replace `your_issuer_id_here` with your actual Issuer ID from Google Wallet API console.

## Step 3: Store Service Account Key

```bash
# Create directory
mkdir -p storage/app/private/google-wallet

# Copy your service account JSON file
# (Downloaded from Google Cloud Console)
cp ~/Downloads/your-service-account-key.json storage/app/private/google-wallet/service-account.json

# Set permissions
chmod 600 storage/app/private/google-wallet/service-account.json
```

## Step 4: Clear Config Cache

```bash
php artisan config:clear
php artisan config:cache
```

## Step 5: Test the Integration

### Option A: Test via Web Interface

1. **Create or use an existing loyalty account**
   - Go to a store's join page
   - Create a new customer account
   - Note the `public_token` from the card URL

2. **Visit the customer card page**
   - URL: `https://yourdomain.com/c/{public_token}`
   - You should see both "Add to Apple Wallet" and "Add to Google Wallet" buttons

3. **Click "Add to Google Wallet"**
   - Should redirect to Google Wallet save page
   - If successful, you'll see the pass preview
   - Click "Add to Google Wallet" to save

### Option B: Test via Direct URL

1. **Generate signed URL** (in tinker or browser console):
   ```php
   // In Laravel Tinker
   php artisan tinker
   ```
   ```php
   $account = \App\Models\LoyaltyAccount::first();
   $url = URL::signedRoute('wallet.google.save', ['public_token' => $account->public_token]);
   echo $url;
   ```

2. **Open the URL in a browser**
   - Should redirect to Google Wallet
   - Pass should be ready to add

### Option C: Test via Command Line

```bash
# Get a public token
php artisan tinker
```

```php
$account = \App\Models\LoyaltyAccount::first();
echo "Public Token: " . $account->public_token . "\n";

# Generate signed URL
$url = URL::signedRoute('wallet.google.save', ['public_token' => $account->public_token]);
echo "Google Wallet URL: " . $url . "\n";
```

Then open the URL in a browser.

## Step 6: Verify Pass Content

After adding the pass to Google Wallet, verify:

1. **Pass displays correctly**
   - Store name visible
   - Customer name visible
   - Stamp count displayed
   - Rewards displayed (if available)

2. **Barcode works**
   - Scan the QR code with merchant scanner
   - Should recognize `LA:{public_token}` format
   - Should allow stamping/redeeming

3. **Pass updates**
   - Add stamps to the account
   - Pass should update (may require refresh in Google Wallet)

## Troubleshooting

### Error: "Service account key not found"

**Solution:**
```bash
# Check file exists
ls -la storage/app/private/google-wallet/service-account.json

# Check path in .env
grep GOOGLE_WALLET_SERVICE_ACCOUNT_KEY .env

# Verify permissions
chmod 600 storage/app/private/google-wallet/service-account.json
```

### Error: "Invalid issuer ID"

**Solution:**
1. Go to [Google Wallet API Console](https://pay.google.com/business/console)
2. Copy your Issuer ID (format: `3388000000022XXXXXXXXX`)
3. Update `.env`:
   ```env
   GOOGLE_WALLET_ISSUER_ID=3388000000022XXXXXXXXX
   ```
4. Clear config:
   ```bash
   php artisan config:clear
   php artisan config:cache
   ```

### Error: "Permission denied" or "Insufficient permissions"

**Solution:**
1. Check service account has "Wallet Object Issuer" role
2. Verify Google Wallet API is enabled in your project
3. Check service account JSON file is valid:
   ```bash
   cat storage/app/private/google-wallet/service-account.json | jq .
   ```

### Error: "JWT signing failed"

**Solution:**
1. Verify OpenSSL extension is enabled:
   ```bash
   php -m | grep openssl
   ```
2. Check service account JSON has `private_key` field
3. Verify private key is valid (not corrupted)

### Pass shows "[TEST ONLY]"

**This is normal!** During development, all passes show "[TEST ONLY]". To remove:
1. Go to Google Wallet API Console
2. Request publishing access
3. Once approved, passes won't show test tag

### Redirect doesn't work / Blank page

**Solution:**
1. Check Laravel logs:
   ```bash
   tail -f storage/logs/laravel.log
   ```
2. Verify signed URL is valid (not expired - 30 minutes)
3. Check Google Wallet API is accessible from your server
4. Verify service account credentials are correct

### Barcode doesn't scan

**Solution:**
1. Verify barcode contains `LA:{public_token}` format
2. Check scanner is configured to recognize this format
3. Test with web QR code first to ensure scanner works

## Testing Checklist

- [ ] Dependencies installed (`composer require google/apiclient`)
- [ ] Environment variables configured
- [ ] Service account key stored and has correct permissions
- [ ] Config cache cleared and rebuilt
- [ ] "Add to Google Wallet" button visible on card page
- [ ] Clicking button redirects to Google Wallet
- [ ] Pass preview shows correct information
- [ ] Pass can be added to Google Wallet
- [ ] Pass displays correctly in Google Wallet
- [ ] Barcode scans correctly with merchant scanner
- [ ] Pass updates when stamps/rewards change

## Advanced Testing

### Test Pass Creation Directly

```php
php artisan tinker
```

```php
$account = \App\Models\LoyaltyAccount::first();
$service = new \App\Services\Wallet\GoogleWalletPassService();

// Create/update loyalty object
$object = $service->createOrUpdateLoyaltyObject($account);
echo "Object ID: " . $object->getId() . "\n";

// Generate save link
$url = $service->generateSaveLink($account);
echo "Save URL: " . $url . "\n";
```

### Test JWT Generation

```php
php artisan tinker
```

```php
$account = \App\Models\LoyaltyAccount::first();
$service = new \App\Services\Wallet\GoogleWalletPassService();

// This will create the object and generate JWT
$url = $service->generateSaveLink($account);

// Extract JWT from URL
$jwt = str_replace('https://pay.google.com/gp/v/save/', '', $url);
echo "JWT Token: " . substr($jwt, 0, 50) . "...\n";
echo "Full URL: " . $url . "\n";
```

### Verify Service Account Access

```php
php artisan tinker
```

```php
$service = new \App\Services\Wallet\GoogleWalletPassService();
$store = \App\Models\Store::first();

// Try to create loyalty class
try {
    $class = $service->createLoyaltyClass($store);
    echo "✅ Class created successfully\n";
    echo "Class ID: " . $class->getId() . "\n";
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
```

## Production Testing

After deploying to production:

1. **Verify environment variables** are set correctly
2. **Test with a real customer account**
3. **Check logs** for any errors:
   ```bash
   tail -f storage/logs/laravel.log
   ```
4. **Monitor Google Cloud Console** for API usage
5. **Test on actual Android device** (Google Wallet works best on Android)

## Next Steps

Once basic testing passes:

1. **Customize pass design** (logos, colors, text)
2. **Set up pass updates** (when stamps change)
3. **Request publishing access** (remove "[TEST ONLY]" tag)
4. **Monitor usage** in Google Cloud Console
5. **Add location-based notifications** (optional)

## Support

If you encounter issues:

1. Check Laravel logs: `storage/logs/laravel.log`
2. Check Google Cloud Console for API errors
3. Verify all environment variables are set
4. Ensure service account has correct permissions
5. Test with a simple pass first before customizing
