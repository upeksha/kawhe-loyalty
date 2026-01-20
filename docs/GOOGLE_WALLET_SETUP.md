# Google Wallet Setup Guide

This guide explains how to set up Google Wallet integration for Kawhe loyalty cards.

## Prerequisites

1. **Google Cloud Project** with billing enabled
2. **Google Wallet API** enabled in your project
3. **Service Account** with Wallet Object Issuer role
4. **Issuer ID** from Google Wallet API

## Step 1: Enable Google Wallet API

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Select or create a project
3. Navigate to **APIs & Services** → **Library**
4. Search for "Google Wallet API"
5. Click **Enable**

## Step 2: Create Service Account

1. In Google Cloud Console, go to **IAM & Admin** → **Service Accounts**
2. Click **Create Service Account**
3. Enter name: `kawhe-wallet-issuer`
4. Click **Create and Continue**
5. Grant role: **Wallet Object Issuer**
6. Click **Continue** → **Done**

## Step 3: Create Service Account Key

1. Click on the service account you just created
2. Go to **Keys** tab
3. Click **Add Key** → **Create new key**
4. Choose **JSON** format
5. Download the JSON file
6. **Important**: Store this file securely (never commit to git)

## Step 4: Get Your Issuer ID

1. Go to [Google Wallet API Console](https://pay.google.com/business/console)
2. Sign in with your Google account
3. Your **Issuer ID** will be displayed (format: `3388000000022XXXXXXXXX`)
4. Copy this ID

## Step 5: Configure Environment Variables

Add these to your `.env` file:

```env
# Google Wallet Configuration
GOOGLE_WALLET_ISSUER_ID=your_issuer_id_here
GOOGLE_WALLET_CLASS_ID=loyalty_class_kawhe
GOOGLE_WALLET_SERVICE_ACCOUNT_KEY=storage/app/private/google-wallet/service-account.json
GOOGLE_WALLET_REVIEW_STATUS=UNDER_REVIEW  # Use 'UNDER_REVIEW' for testing, 'APPROVED' for production (after Google approval)
```

**Important**: 
- Replace `your_issuer_id_here` with your actual Issuer ID
- Place the service account JSON file at the path specified in `GOOGLE_WALLET_SERVICE_ACCOUNT_KEY`
- The path is relative to your Laravel project root

## Step 6: Store Service Account Key

1. Create the directory:
   ```bash
   mkdir -p storage/app/private/google-wallet
   ```

2. Copy your downloaded service account JSON file:
   ```bash
   cp ~/Downloads/your-service-account-key.json storage/app/private/google-wallet/service-account.json
   ```

3. Set proper permissions:
   ```bash
   chmod 600 storage/app/private/google-wallet/service-account.json
   ```

## Step 7: Install Dependencies

```bash
composer require google/apiclient
```

## Step 8: Test the Integration

1. **Create a test loyalty account** in your system
2. **Visit the customer card page** (`/c/{public_token}`)
3. **Click "Add to Google Wallet"** button
4. You should be redirected to Google Wallet save page
5. **Add the pass** to your Google Wallet

## Troubleshooting

### "Service account key not found"
- Verify the path in `GOOGLE_WALLET_SERVICE_ACCOUNT_KEY` is correct
- Ensure the file exists and has proper permissions (600)

### "Invalid issuer ID"
- Verify `GOOGLE_WALLET_ISSUER_ID` matches your Google Wallet API console
- Ensure no extra spaces or quotes in `.env`

### "Permission denied" errors
- Verify service account has **Wallet Object Issuer** role
- Check that Google Wallet API is enabled in your project

### JWT signing errors
- Ensure OpenSSL extension is enabled in PHP
- Verify service account JSON file is valid JSON

### "This pass is only for testing - ask your administrator to give you access"

This error occurs because your Google Wallet account is in **Demo Mode** (testing mode). You have two options:

#### Option 1: Add Test Users (Quick Fix for Testing)

1. Go to [Google Wallet API Console](https://pay.google.com/business/console)
2. Navigate to **Settings** → **Test Users**
3. Click **Add Test User**
4. Enter the email address of users who should be able to add passes
5. Click **Save**

**Note**: Only users added as test users can add passes in demo mode.

#### Option 2: Request Publishing Access (For Production)

To allow any user to add passes:

1. **Complete Business Profile**:
   - Go to [Google Wallet API Console](https://pay.google.com/business/console)
   - Fill out all required business information (legal entity, address, contact info)

2. **Request Publishing Access**:
   - Navigate to **Settings** → **Get Publishing Access**
   - Submit your request
   - Google will review your setup (usually takes a few days)

3. **After Approval**:
   - Update `.env`: `GOOGLE_WALLET_REVIEW_STATUS=APPROVED`
   - Clear config cache: `php artisan config:clear`
   - The "[TEST ONLY]" tag will be removed from passes

### Pass shows "[TEST ONLY]"
- This is normal during development (Demo Mode)
- Request publishing access to remove the test tag (see above)

## Security Notes

- **Never commit** service account JSON files to git
- Add `storage/app/private/google-wallet/` to `.gitignore`
- Use environment variables for all sensitive configuration
- Rotate service account keys periodically

## Next Steps

Once basic integration works:
1. Customize pass design (logos, colors, text)
2. Set up pass updates when stamps/rewards change
3. Request publishing access to remove "[TEST ONLY]" tag
4. Add location-based notifications (optional)
