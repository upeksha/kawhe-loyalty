# Fix "Safari Cannot Download" on iPhone

## Step 1: Verify Pass Structure

The pass.json must have all required fields. Test on server:

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
    file_put_contents('/tmp/test.pkpass', $pkpass);
    
    // Extract and check pass.json
    $zip = new ZipArchive();
    if ($zip->open('/tmp/test.pkpass') === TRUE) {
        $passJson = $zip->getFromName('pass.json');
        $passData = json_decode($passJson, true);
        
        echo "Pass JSON structure:\n";
        print_r($passData);
        
        // Check required fields
        $required = ['formatVersion', 'passTypeIdentifier', 'teamIdentifier', 'organizationName', 'serialNumber', 'description'];
        foreach ($required as $field) {
            if (!isset($passData[$field])) {
                echo "ERROR: Missing required field: $field\n";
            } else {
                echo "✓ $field: " . $passData[$field] . "\n";
            }
        }
        
        // Check storeCard structure
        if (!isset($passData['storeCard'])) {
            echo "ERROR: Missing storeCard\n";
        } else {
            echo "✓ storeCard exists\n";
        }
        
        // Check barcode
        if (!isset($passData['barcode'])) {
            echo "ERROR: Missing barcode\n";
        } else {
            echo "✓ barcode exists: " . ($passData['barcode']['message'] ?? 'no message') . "\n";
        }
        
        $zip->close();
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
```

## Step 2: Check Actual HTTP Response

Test what Safari actually receives:

```bash
# Generate fresh signed URL
php artisan tinker --execute="
use Illuminate\Support\Facades\URL;
use App\Models\LoyaltyAccount;

\$account = LoyaltyAccount::where('public_token', 'Y6S1PZh6WRL2cukm5BozjqmyGG2HHUzuWsAwzOZI')->first();
\$url = URL::temporarySignedRoute('wallet.apple.download', now()->addMinutes(30), ['public_token' => \$account->public_token]);
echo \$url . PHP_EOL;
"
```

Then test with curl and check if it's actually the pass file:

```bash
curl -v -L -o /tmp/safari-test.pkpass "YOUR_URL_HERE" 2>&1 | grep -E "HTTP|Content-Type|Content-Length|Location"
```

## Step 3: Common Safari iPhone Issues

### Issue 1: Pass Missing Required Fields
Apple Wallet requires specific fields. Check pass.json has:
- formatVersion
- passTypeIdentifier  
- teamIdentifier
- organizationName
- serialNumber
- description
- barcode (with message, format, messageEncoding)
- storeCard (with at least primaryFields)

### Issue 2: Invalid Certificate or Signature
The pass must be properly signed. Verify:
```bash
# Check signature exists in pass
unzip -l /tmp/test.pkpass | grep signature
```

### Issue 3: Safari Redirect or Error
Safari might be getting redirected or an error page. Check:
```bash
# Follow redirects and see final response
curl -L -v "YOUR_URL_HERE" 2>&1 | tail -30
```

### Issue 4: Signed URL Expiring
Signed URLs expire. Make sure you're using a fresh URL each time.

### Issue 5: HTTPS Required
Safari on iPhone requires HTTPS for .pkpass files. Verify your site uses HTTPS.

## Step 4: Test Pass File Validity

```bash
# On Mac, try opening the downloaded file
open /tmp/test.pkpass

# Or validate with Apple's tools (if available)
# pkpass-validator /tmp/test.pkpass
```

If it opens in Wallet app on Mac, the file is valid and the issue is with Safari's download.

## Step 5: Alternative - Direct Download Link

Try bypassing the signed URL temporarily to test:

```bash
# Temporarily remove signed middleware from route to test
# (Don't do this in production, just for testing)
```

Or test with a direct link to see if signed URLs are the issue.
