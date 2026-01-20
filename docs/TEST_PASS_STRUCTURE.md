# Test Pass Structure

Run this on your server to verify the pass structure:

```bash
php artisan tinker
```

```php
use App\Models\LoyaltyAccount;
use App\Services\Wallet\AppleWalletPassService;
use ZipArchive;

$account = LoyaltyAccount::first();
$service = new AppleWalletPassService();
$pkpass = $service->generatePass($account);

// Save to temp file
file_put_contents('/tmp/test.pkpass', $pkpass);

// Extract and check structure
$zip = new ZipArchive();
if ($zip->open('/tmp/test.pkpass') === TRUE) {
    echo "✓ Pass is a valid ZIP\n\n";
    
    // Check required files
    $required = ['pass.json', 'manifest.json', 'signature'];
    foreach ($required as $file) {
        $exists = $zip->locateName($file) !== false;
        echo ($exists ? "✓" : "✗") . " $file " . ($exists ? "exists" : "MISSING") . "\n";
    }
    
    // Check pass.json structure
    $passJson = $zip->getFromName('pass.json');
    if ($passJson) {
        $pass = json_decode($passJson, true);
        echo "\nPass.json structure:\n";
        
        $requiredFields = [
            'formatVersion',
            'passTypeIdentifier',
            'teamIdentifier',
            'organizationName',
            'serialNumber',
            'description',
            'barcode',
            'storeCard'
        ];
        
        foreach ($requiredFields as $field) {
            $exists = isset($pass[$field]);
            echo ($exists ? "✓" : "✗") . " $field: " . ($exists ? "exists" : "MISSING") . "\n";
        }
        
        // Check barcode
        if (isset($pass['barcode'])) {
            echo "\nBarcode:\n";
            echo "  format: " . ($pass['barcode']['format'] ?? 'MISSING') . "\n";
            echo "  message: " . ($pass['barcode']['message'] ?? 'MISSING') . "\n";
            echo "  messageEncoding: " . ($pass['barcode']['messageEncoding'] ?? 'MISSING') . "\n";
        }
        
        // Check storeCard
        if (isset($pass['storeCard'])) {
            echo "\nStoreCard:\n";
            echo "  primaryFields: " . (isset($pass['storeCard']['primaryFields']) ? "exists" : "MISSING") . "\n";
            echo "  secondaryFields: " . (isset($pass['storeCard']['secondaryFields']) ? "exists" : "MISSING") . "\n";
        }
        
        // Check for errors in JSON
        $jsonError = json_last_error();
        if ($jsonError !== JSON_ERROR_NONE) {
            echo "\n✗ JSON Error: " . json_last_error_msg() . "\n";
        } else {
            echo "\n✓ JSON is valid\n";
        }
    } else {
        echo "\n✗ Could not read pass.json from ZIP\n";
    }
    
    // Check signature
    $signature = $zip->getFromName('signature');
    if ($signature) {
        echo "\n✓ Signature exists (" . strlen($signature) . " bytes)\n";
    } else {
        echo "\n✗ Signature MISSING\n";
    }
    
    $zip->close();
} else {
    echo "✗ Pass is NOT a valid ZIP\n";
}
```
