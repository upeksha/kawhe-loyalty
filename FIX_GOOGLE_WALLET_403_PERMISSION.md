# Fix Google Wallet 403 Permission Denied Error

## Error
```
Google\Service\Exception: Permission denied.
code: 403
reason: permissionDenied
```

## What This Means

The service account doesn't have the correct permissions to create Google Wallet objects. This is a **Google Cloud permissions issue**, not a code issue.

## Solution Steps

### Step 1: Verify Service Account Has Correct Role

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Navigate to **IAM & Admin** → **Service Accounts**
3. Find your service account (the one you downloaded the JSON key from)
4. Click on it to view details
5. Check the **Roles** tab

**Required Role**: `Wallet Object Issuer` or `Wallet Objects Issuer`

### Step 2: Add the Role (If Missing)

1. In the Service Account details page, click **Edit**
2. Click **ADD ANOTHER ROLE**
3. Search for: `Wallet Object Issuer`
4. Select it
5. Click **SAVE**

### Step 3: Verify Google Wallet API is Enabled

1. Go to **APIs & Services** → **Enabled APIs**
2. Search for "Google Wallet API"
3. Make sure it shows **ENABLED**
4. If not, go to **APIs & Services** → **Library**
5. Search "Google Wallet API" and click **Enable**

### Step 4: Check API Restrictions

1. Go to **APIs & Services** → **Credentials**
2. Find your service account
3. Click on it
4. Check **API restrictions** section
5. Make sure **Google Wallet API** is allowed (or "Don't restrict key")

### Step 5: Verify Issuer ID

1. Go to [Google Wallet API Console](https://pay.google.com/business/console)
2. Verify your **Issuer ID** matches what's in your `.env`
3. Make sure you're using the correct Issuer ID for your account

### Step 6: Check Service Account Email

The service account email must match the one associated with your Google Wallet API account.

1. In Google Cloud Console, check your service account email
2. Make sure it's the same account that has access to Google Wallet API

## Common Issues

### Issue 1: Wrong Project

Make sure your service account is in the **same Google Cloud Project** where Google Wallet API is enabled.

### Issue 2: Service Account Not Linked to Wallet API

The service account needs to be explicitly granted access to Google Wallet API.

### Issue 3: API Not Enabled

Google Wallet API must be enabled in the project.

### Issue 4: Wrong Issuer ID

The Issuer ID in `.env` must match your Google Wallet API Console Issuer ID.

## Quick Checklist

- [ ] Service account has "Wallet Object Issuer" role
- [ ] Google Wallet API is enabled in the project
- [ ] Service account is in the same project as the API
- [ ] Issuer ID in `.env` matches Google Wallet API Console
- [ ] Service account JSON key is from the correct account

## Test After Fixing

```bash
php artisan tinker
```

```php
$account = \App\Models\LoyaltyAccount::with(['store', 'customer'])->first();
$service = new \App\Services\Wallet\GoogleWalletPassService();
$url = $service->generateSaveLink($account);
echo "✅ URL: " . $url . "\n";
```

If you still get 403, check:
1. Service account email matches the one with Wallet API access
2. Try creating a new service account with the role from the start
3. Wait a few minutes after adding roles (permissions can take time to propagate)
