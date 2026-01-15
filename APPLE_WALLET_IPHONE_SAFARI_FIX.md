# Fix "Safari Cannot Download" on iPhone - Final Steps

## Changes Made

1. **Changed Content-Disposition back to `attachment`** - Safari on iPhone actually requires `attachment` for .pkpass files
2. **Removed `Content-Transfer-Encoding: binary`** - This can cause Safari to reject the file
3. **Added ZIP validation** - Ensures the pass is a valid ZIP before sending
4. **Added `X-Content-Type-Options: nosniff`** - Prevents Safari from sniffing the content type

## Test on Server

After pulling the latest changes:

```bash
cd /var/www/kawhe
git pull
php artisan config:clear
php artisan config:cache
```

## Debugging Steps

### Step 1: Check if Signed URL is the Issue

Temporarily test without signed middleware to see if that's causing Safari to fail:

1. Edit `routes/web.php` and comment out the signed middleware:

```php
Route::get('/wallet/apple/{public_token}/download', [App\Http\Controllers\WalletController::class, 'downloadApplePass'])
    ->name('wallet.apple.download');
    // ->middleware('signed'); // Temporarily disabled for testing
```

2. Update the button in `resources/views/card/show.blade.php` to use a regular route:

```php
<a href="{{ route('wallet.apple.download', ['public_token' => $account->public_token]) }}" 
```

3. Test on iPhone Safari
4. **IMPORTANT**: Re-enable signed middleware after testing!

### Step 2: Check Safari Console

On iPhone:
1. Connect iPhone to Mac
2. Open Safari on Mac
3. Go to Develop > [Your iPhone] > [Your Site]
4. Check Console for errors

### Step 3: Verify Pass File Structure

Run this on the server to verify the pass.json structure:

```bash
php artisan tinker
```

```php
use App\Models\LoyaltyAccount;
use App\Services\Wallet\AppleWalletPassService;
use ZipArchive;

$account = LoyaltyAccount::where('public_token', 'Y6S1PZh6WRL2cukm5BozjqmyGG2HHUzuWsAwzOZI')->first();
$service = new AppleWalletPassService();
$pkpass = $service->generatePass($account);
file_put_contents('/tmp/test.pkpass', $pkpass);

$zip = new ZipArchive();
if ($zip->open('/tmp/test.pkpass') === TRUE) {
    $passJson = $zip->getFromName('pass.json');
    $pass = json_decode($passJson, true);
    
    // Check required fields
    $required = ['formatVersion', 'passTypeIdentifier', 'teamIdentifier', 'organizationName', 'serialNumber', 'description', 'barcode', 'storeCard'];
    foreach ($required as $field) {
        echo isset($pass[$field]) ? "✓ $field\n" : "✗ MISSING: $field\n";
    }
    
    // Validate barcode
    if (isset($pass['barcode'])) {
        echo "Barcode format: " . ($pass['barcode']['format'] ?? 'missing') . "\n";
        echo "Barcode message: " . ($pass['barcode']['message'] ?? 'missing') . "\n";
    }
    
    $zip->close();
}
```

### Step 4: Test Direct Download

Try downloading the pass directly (bypassing the web page):

```bash
# Generate a fresh signed URL
php artisan tinker --execute="
use Illuminate\Support\Facades\URL;
use App\Models\LoyaltyAccount;

\$account = LoyaltyAccount::where('public_token', 'Y6S1PZh6WRL2cukm5BozjqmyGG2HHUzuWsAwzOZI')->first();
\$url = URL::temporarySignedRoute('wallet.apple.download', now()->addMinutes(30), ['public_token' => \$account->public_token]);
echo \$url . PHP_EOL;
"
```

Then open that URL directly in Safari on iPhone (not through the button).

### Step 5: Check Server Logs

Check for any errors when Safari tries to download:

```bash
tail -f storage/logs/laravel.log
```

Then try to download on iPhone and watch for errors.

## Common Issues

### Issue 1: Signed URL Expiration
Signed URLs expire. Make sure you're generating a fresh URL each time.

### Issue 2: HTTPS Required
Safari on iPhone requires HTTPS for .pkpass files. Verify your site uses HTTPS.

### Issue 3: Pass Structure Invalid
The pass.json must have all required fields. Use Step 3 to verify.

### Issue 4: Certificate Issues
If the pass isn't properly signed, Safari will reject it. Verify certificates are correct.

### Issue 5: Safari Cache
Safari might be caching an error response. Try:
- Clear Safari cache
- Use Private Browsing mode
- Try a different iPhone

## If Still Not Working

1. **Test on Mac Safari first** - If it works on Mac Safari, the issue is iPhone-specific
2. **Check if pass opens in Wallet app on Mac** - Double-click the downloaded .pkpass file
3. **Verify MIME type** - The response must have `Content-Type: application/vnd.apple.pkpass`
4. **Check file size** - Pass files should be at least 1KB (yours is 3.4KB, which is good)

## Alternative: Use Data URI (Last Resort)

If nothing works, we can try embedding the pass as a data URI, but this has size limitations and isn't recommended for production.
