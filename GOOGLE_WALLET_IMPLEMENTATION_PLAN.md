# Google Wallet Integration Plan

## Overview

Google Wallet uses a REST API approach (different from Apple Wallet's .pkpass files). We'll implement it alongside Apple Wallet without breaking existing functionality.

## Key Differences

| Feature | Apple Wallet | Google Wallet |
|---------|--------------|---------------|
| Format | .pkpass ZIP bundle | JSON via REST API |
| Signing | PKCS#12 certificate | Google service account credentials |
| Delivery | Direct file download | JWT-based "Save to Google Wallet" link |
| Pass Type | storeCard | LoyaltyObject |

## Implementation Steps

### Phase 1: Setup & Configuration

1. **Google Cloud Setup**:
   - Create Google Cloud Project
   - Enable Google Wallet API
   - Create Service Account
   - Download service account JSON key

2. **Environment Variables**:
   ```env
   GOOGLE_WALLET_ISSUER_ID=your_issuer_id
   GOOGLE_WALLET_SERVICE_ACCOUNT_KEY=path/to/service-account.json
   GOOGLE_WALLET_CLASS_ID=loyalty_class_kawhe
   ```

3. **Package Installation**:
   - Use Google's PHP client library: `google/apiclient`
   - Or implement REST API calls directly

### Phase 2: Service Implementation

1. **Create `GoogleWalletPassService`**:
   - Similar structure to `AppleWalletPassService`
   - Methods:
     - `createLoyaltyClass()` - Create pass template (one-time setup)
     - `createLoyaltyObject()` - Create pass for customer
     - `updateLoyaltyObject()` - Update pass when stamps change
     - `generateSaveLink()` - Generate JWT "Save to Google Wallet" link

2. **Loyalty Object Structure**:
   - Account ID (stable identifier)
   - Account Name (customer name)
   - Loyalty Points (stamp count)
   - Secondary Points Label (rewards)
   - Barcode (QR code with public_token)
   - Images (logo, hero image)

### Phase 3: Controller & Routes

1. **Extend `WalletController`**:
   - Add `downloadGooglePass()` method
   - Returns JWT link or redirects to Google Wallet

2. **Routes**:
   ```php
   Route::get('/wallet/google/{public_token}/save', [WalletController::class, 'saveGooglePass'])
       ->name('wallet.google.save')
       ->middleware('signed');
   ```

### Phase 4: Frontend Integration

1. **Update Card View**:
   - Add "Add to Google Wallet" button
   - Detect device/platform (show appropriate button)
   - Or show both buttons

2. **Button Implementation**:
   - Use Google's "Save to Google Wallet" button
   - Or custom button that opens JWT link

## Files to Create/Modify

### New Files:
- `app/Services/Wallet/GoogleWalletPassService.php`
- `config/google-wallet.php` (optional)
- `resources/views/card/partials/google-wallet-button.blade.php` (optional)

### Modified Files:
- `app/Http/Controllers/WalletController.php` - Add Google Wallet method
- `routes/web.php` - Add Google Wallet route
- `resources/views/card/show.blade.php` - Add Google Wallet button
- `composer.json` - Add Google API client (if needed)
- `.env.example` - Add Google Wallet config

## Security Considerations

- Use signed URLs (same as Apple Wallet)
- Store service account key securely
- Validate public_token before creating/updating passes
- Rate limiting on pass creation/updates

## Testing Strategy

1. Test pass creation in Google Wallet API
2. Test "Save to Google Wallet" link generation
3. Test on Android device
4. Verify barcode scanning works
5. Test pass updates when stamps change

## Future Enhancements (Phase 2)

- Push updates when stamps/rewards change
- Location-based notifications
- Rotating barcodes for security
