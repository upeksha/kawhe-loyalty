# Fix "Safari Cannot Download This File" Error

## Step 1: Verify Pass Generation Works

Test if the pass is actually being generated:

```bash
php artisan tinker
```

```php
use App\Models\LoyaltyAccount;
use App\Services\Wallet\AppleWalletPassService;

$account = LoyaltyAccount::where('public_token', 'Y6S1PZh6WRL2cukm5BozjqmyGG2HHUzuWsAwzOZI')->first();
$service = new AppleWalletPassService();

try {
    $pkpass = $service->generatePass($account);
    echo "Pass size: " . strlen($pkpass) . " bytes\n";
    
    // Save to file and check if it's a valid ZIP
    file_put_contents('/tmp/test.pkpass', $pkpass);
    echo "Saved to /tmp/test.pkpass\n";
    
    // Check if it's a valid ZIP file (pkpass is a ZIP)
    $zip = new ZipArchive();
    if ($zip->open('/tmp/test.pkpass') === TRUE) {
        echo "✓ Valid ZIP file\n";
        echo "Files in pass:\n";
        for ($i = 0; $i < $zip->numFiles; $i++) {
            echo "  - " . $zip->getNameIndex($i) . "\n";
        }
        $zip->close();
    } else {
        echo "✗ NOT a valid ZIP file - pass is corrupted!\n";
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
```

## Step 2: Check Response Headers

Test the actual HTTP response:

```bash
# Generate a signed URL
php artisan tinker --execute="
use Illuminate\Support\Facades\URL;
use App\Models\LoyaltyAccount;

\$account = LoyaltyAccount::where('public_token', 'Y6S1PZh6WRL2cukm5BozjqmyGG2HHUzuWsAwzOZI')->first();
\$url = URL::temporarySignedRoute('wallet.apple.download', now()->addMinutes(30), ['public_token' => \$account->public_token]);
echo \$url . PHP_EOL;
"
```

Then test with curl:

```bash
# Replace URL with the one from above
curl -I "https://testing.kawhe.shop/wallet/apple/Y6S1PZh6WRL2cukm5BozjqmyGG2HHUzuWsAwzOZI/download?expires=...&signature=..."
```

Check the headers:
- `Content-Type` should be `application/vnd.apple.pkpass`
- `Content-Length` should be present
- Status should be `200 OK`

## Step 3: Download and Verify Pass File

```bash
# Download the pass file
curl -L -o /tmp/downloaded.pkpass "YOUR_SIGNED_URL_HERE"

# Check if it's a valid ZIP
file /tmp/downloaded.pkpass
unzip -l /tmp/downloaded.pkpass | head -20
```

If it's not a valid ZIP, the pass generation is failing.

## Step 4: Check for Missing Required Files

A valid .pkpass must contain:
- `pass.json` (the pass definition)
- `manifest.json` (file hashes)
- `signature` (certificate signature)
- At least `icon.png` and `logo.png`

Verify:

```bash
unzip -l /tmp/test.pkpass
```

Should show at least:
- pass.json
- manifest.json
- signature
- icon.png
- logo.png

## Step 5: Common Issues

### Issue 1: Pass is HTML Error Page
If the downloaded file is HTML instead of binary, there's a PHP error. Check:
```bash
tail -n 100 storage/logs/laravel.log | grep -A 20 "Apple Wallet"
```

### Issue 2: Invalid ZIP Format
The pass must be a valid ZIP file. If generation fails silently, check:
- Certificate is valid
- All required images exist
- Storage permissions are correct

### Issue 3: Wrong Content-Type
Safari is very strict. Must be exactly `application/vnd.apple.pkpass`

### Issue 4: Content-Length Missing
Safari needs Content-Length header

## Step 6: Test Pass File Manually

If you can download the file, test it:

```bash
# On Mac, try opening it
open /tmp/test.pkpass

# Or check with unzip
unzip -t /tmp/test.pkpass
```

If it opens in Wallet app, the file is valid but headers might be wrong.
If it doesn't open, the file is corrupted or invalid.
