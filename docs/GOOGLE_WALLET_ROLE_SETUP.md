# Google Wallet API - Service Account Role Setup

## Issue: "Wallet Object Issuer" Role Not Found

If you don't see "Wallet Object Issuer" in the role list, here are alternative ways to set it up:

## Option 1: Enable API First, Then Role Appears

The role might only appear after the API is enabled:

1. **Enable Google Wallet API**:
   - Go to [Google Cloud Console](https://console.cloud.google.com/)
   - Navigate to **APIs & Services** → **Library**
   - Search for "Google Wallet API"
   - Click **Enable**
   - Wait 2-3 minutes for it to fully enable

2. **Then check roles again**:
   - Go back to **IAM & Admin** → **Service Accounts**
   - Edit your service account
   - Click **ADD ANOTHER ROLE**
   - Search for: `wallet` (the role might appear now)

## Option 2: Use Custom Role or Owner Role (Temporary)

If the role still doesn't appear:

1. **Grant "Owner" role temporarily** (for testing):
   - In Service Account → **Edit**
   - Add role: **Owner**
   - This gives full permissions (can be restricted later)

2. **Or create a custom role** with these permissions:
   - `walletobjects.loyaltyobjects.create`
   - `walletobjects.loyaltyobjects.update`
   - `walletobjects.loyaltyclasses.create`
   - `walletobjects.loyaltyclasses.update`

## Option 3: Use Project-Level Permissions

Instead of service account role, grant at project level:

1. Go to **IAM & Admin** → **IAM**
2. Find your service account email
3. Click **Edit** (pencil icon)
4. Add role: **Service Account User** (if not already there)
5. Then enable Google Wallet API in the project

## Option 4: Check API-Specific Permissions

Some APIs require you to grant permissions through the API console:

1. Go to [Google Wallet API Console](https://pay.google.com/business/console)
2. Check if there's a section for **Service Accounts** or **API Access**
3. Add your service account email there

## Option 5: Alternative Role Names to Search For

Try searching for these variations:
- `Wallet Objects Issuer`
- `Wallet Object Creator`
- `Wallet API User`
- `Service Account Token Creator` (plus Wallet API access)

## Option 6: Use OAuth Instead (If Service Account Doesn't Work)

If service account roles don't work, you might need to:
1. Use OAuth 2.0 instead of service account
2. Or request access through Google Wallet API support

## Recommended Steps (In Order)

1. **First, enable Google Wallet API**:
   ```bash
   # Go to: APIs & Services → Library → Search "Google Wallet API" → Enable
   ```

2. **Wait 2-3 minutes** for API to fully enable

3. **Then check for roles**:
   - Search for: `wallet`
   - Search for: `loyalty`
   - Search for: `object`

4. **If still not found, use Owner role temporarily**:
   - Add "Owner" role to service account
   - Test if it works
   - Then we can create a custom role with minimal permissions

5. **Verify in Google Wallet API Console**:
   - Go to https://pay.google.com/business/console
   - Check if your service account email is listed
   - Some APIs require explicit approval there

## Test After Each Step

```bash
php artisan tinker
```

```php
$account = \App\Models\LoyaltyAccount::with(['store', 'customer'])->first();
$service = new \App\Services\Wallet\GoogleWalletPassService();
try {
    $url = $service->generateSaveLink($account);
    echo "✅ SUCCESS: " . $url . "\n";
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
```

## If Nothing Works

Contact Google Wallet API Support:
- They may need to enable the role for your account
- Or provide alternative authentication method
- Some accounts need special approval for Wallet API
