# Google Wallet Permissions Setup (Correct Method)

## Important: Google Wallet Doesn't Use IAM Roles

Google Wallet uses a **permissions system** managed through the Wallet Objects API, not traditional IAM roles. You need to add your service account to the Issuer's permissions list.

## Step 1: Get Your Service Account Email

```bash
# On your server, get the service account email from the JSON file
cat storage/app/private/google-wallet/service-account.json | grep client_email
```

Copy the email address (format: `something@project-id.iam.gserviceaccount.com`)

## Step 2: Add Service Account to Issuer Permissions

You need to use the Google Wallet API to add your service account to the permissions list. There are two ways:

### Method A: Using Google Wallet API Console (Easier)

1. Go to [Google Wallet API Console](https://pay.google.com/business/console)
2. Sign in with the account that created the Issuer ID
3. Look for **Settings** or **Permissions** section
4. Add your service account email with **WRITER** or **OWNER** role

### Method B: Using API Call (If Console Doesn't Have UI)

You'll need to use the API to update permissions. Here's a PHP script to do it:

```php
// Run this in tinker or create a temporary route
php artisan tinker
```

```php
use Google_Client;
use Google_Service_Walletobjects;

// Initialize client
$client = new Google_Client();
$client->setApplicationName('Kawhe Loyalty');
$client->setScopes('https://www.googleapis.com/auth/wallet_object.issuer');
$client->setAuthConfig(storage_path('app/private/google-wallet/service-account.json'));

$service = new Google_Service_Walletobjects($client);

// Get your Issuer ID
$issuerId = config('services.google_wallet.issuer_id');

// Get service account email
$credentials = json_decode(file_get_contents(storage_path('app/private/google-wallet/service-account.json')), true);
$serviceAccountEmail = $credentials['client_email'];

// Get current permissions
try {
    $permissions = $service->permissions->get($issuerId);
    echo "Current permissions:\n";
    print_r($permissions);
} catch (\Exception $e) {
    echo "Error getting permissions: " . $e->getMessage() . "\n";
    echo "This might mean permissions don't exist yet.\n";
}

// Update permissions to add service account
try {
    $permissions = new \Google_Service_Walletobjects_Permissions();
    
    // Add service account with WRITER role
    $permission = new \Google_Service_Walletobjects_Permission();
    $permission->setEmailAddress($serviceAccountEmail);
    $permission->setRole('WRITER'); // or 'OWNER'
    
    $permissions->setIssuerId($issuerId);
    $permissions->setPermissions([$permission]);
    
    $result = $service->permissions->update($issuerId, $permissions);
    echo "✅ Permissions updated successfully!\n";
    print_r($result);
} catch (\Exception $e) {
    echo "❌ Error updating permissions: " . $e->getMessage() . "\n";
    echo "Full error: " . $e->getTraceAsString() . "\n";
}
```

## Step 3: Alternative - Use Your Personal Google Account

If service account permissions are too complex, you can temporarily use your personal Google account:

1. **Get OAuth credentials** instead of service account
2. **Use your personal Google account** that has access to Google Wallet API
3. This is easier for testing but not recommended for production

## Step 4: Verify Permissions

After setting permissions, test:

```php
$account = \App\Models\LoyaltyAccount::with(['store', 'customer'])->first();
$service = new \App\Services\Wallet\GoogleWalletPassService();
$url = $service->generateSaveLink($account);
echo "✅ URL: " . $url . "\n";
```

## Common Issues

### "Permission denied" after adding permissions
- Wait 2-5 minutes for permissions to propagate
- Verify service account email is correct
- Check Issuer ID matches exactly

### "Issuer not found"
- Verify Issuer ID is correct
- Make sure you're using the Issuer ID from Google Wallet API Console
- Not the project ID or service account email

### Permissions API returns 404
- The Issuer might not be fully set up
- Go to Google Wallet API Console and complete setup
- Some accounts need approval before permissions can be set

## Quick Fix: Use Owner Role Temporarily

If you just want to test, you can:

1. In Google Cloud Console → IAM & Admin → Service Accounts
2. Edit your service account
3. Add role: **Owner** (temporary, for testing only)
4. Test if it works
5. Then set up proper Wallet permissions later

## Recommended: Contact Google Support

If permissions setup is confusing, contact Google Wallet API Support:
- They can help set up permissions correctly
- They can verify your Issuer account is configured properly
- They can grant necessary access
