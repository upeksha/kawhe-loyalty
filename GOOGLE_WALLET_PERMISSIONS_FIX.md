# Fix Google Wallet 403 Permission Denied - Setup Permissions

## The Problem

The service account can't set its own permissions (403 error). You need to use your **personal Google account** (the one that created the Issuer ID) to add the service account to the permissions list.

## Solution: Use Your Personal Google Account

### Step 1: Get OAuth Credentials for Your Personal Account

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Navigate to **APIs & Services** → **Credentials**
3. Click **+ CREATE CREDENTIALS** → **OAuth client ID**
4. Choose **Web application**
5. Add authorized redirect URI: `http://localhost` (just for setup)
6. Download the JSON file (or copy Client ID and Secret)

### Step 2: Use OAuth to Set Permissions

You need to authenticate with your personal Google account (not the service account) to set permissions.

**Option A: Use Google Wallet API Console (Easier)**

1. Go to [Google Wallet API Console](https://pay.google.com/business/console)
2. Sign in with the **personal Google account** that created the Issuer ID
3. Look for **Settings** → **Permissions** or **Service Accounts**
4. Add this email: `kawhe-wallet@kawhe-484722.iam.gserviceaccount.com`
5. Grant it **WRITER** or **OWNER** role

**Option B: Use OAuth in Code (If Console Doesn't Work)**

Create a temporary script to authenticate with your personal account and set permissions.

### Step 3: Quick Fix - Grant Owner Role Temporarily

If the above is too complex, grant Owner role in Google Cloud IAM:

1. Go to **Google Cloud Console** → **IAM & Admin** → **Service Accounts**
2. Find: `kawhe-wallet@kawhe-484722.iam.gserviceaccount.com`
3. Click **Edit**
4. Add role: **Owner**
5. Click **SAVE**

This gives full permissions temporarily. Test if Google Wallet works, then we can set up proper permissions later.

### Step 4: Test After Granting Owner Role

```bash
php artisan tinker
```

```php
$account = \App\Models\LoyaltyAccount::with(['store', 'customer'])->first();
$service = new \App\Services\Wallet\GoogleWalletPassService();
$url = $service->generateSaveLink($account);
echo "✅ URL: " . $url . "\n";
```

## Alternative: Use Your Personal Account's OAuth

If you want to use your personal Google account instead of service account:

1. Create OAuth 2.0 credentials for your personal account
2. Use those credentials instead of service account
3. This is simpler for testing but not recommended for production

## Recommended: Contact Google Support

Since setting up permissions requires the account that created the Issuer, and the console might not have a UI for it, you can:

1. **Contact Google Wallet API Support** - They can help set up permissions
2. **Request they add your service account** to the Issuer permissions
3. Provide them:
   - Issuer ID: `3388000000023072141`
   - Service Account Email: `kawhe-wallet@kawhe-484722.iam.gserviceaccount.com`
   - Request: Add with WRITER role

## Quick Test: Grant Owner Role

For now, the fastest way to test:

1. **Google Cloud Console** → **IAM & Admin** → **Service Accounts**
2. Edit: `kawhe-wallet@kawhe-484722.iam.gserviceaccount.com`
3. Add role: **Owner**
4. Save
5. Test Google Wallet

This will work immediately. You can restrict permissions later once everything is working.
