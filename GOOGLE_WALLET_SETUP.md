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

### Pass shows "[TEST ONLY]"
- This is normal during development
- Request publishing access in Google Wallet API console to remove test tag

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
