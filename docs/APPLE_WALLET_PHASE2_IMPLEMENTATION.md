# Apple Wallet Phase 2: Pass Web Service Implementation

## Overview

This document describes the Phase 2 implementation of Apple Wallet Pass Web Service, enabling automatic pass updates via Apple Push Notification Service (APNs).

## Features Implemented

1. **Device Registration**: Devices can register to receive pass updates
2. **Device Unregistration**: Devices can unregister from updates
3. **Pass Retrieval**: Apple Wallet can fetch updated pass files
4. **304 Not Modified**: Efficient caching with conditional requests
5. **Updated Serials List**: Device can query for updated passes
6. **Logging Endpoint**: Apple Wallet can send diagnostic logs
7. **APNs Push Notifications**: Automatic push when stamps change

## Database Schema

### `apple_wallet_registrations` Table

```sql
CREATE TABLE apple_wallet_registrations (
    id BIGINT UNSIGNED PRIMARY KEY,
    device_library_identifier VARCHAR(255) NOT NULL,
    push_token VARCHAR(255) NOT NULL,
    pass_type_identifier VARCHAR(255) NOT NULL,
    serial_number VARCHAR(255) NOT NULL,
    loyalty_account_id BIGINT UNSIGNED NULLABLE,
    active BOOLEAN DEFAULT TRUE,
    last_registered_at TIMESTAMP NULLABLE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE KEY device_pass_serial_unique (device_library_identifier, pass_type_identifier, serial_number),
    FOREIGN KEY (loyalty_account_id) REFERENCES loyalty_accounts(id) ON DELETE CASCADE
);
```

## API Endpoints

All endpoints are under `/wallet/v1/` and require authentication via `Authorization: ApplePass <token>` header.

### 1. Register Device

**POST** `/wallet/v1/devices/{deviceLibraryIdentifier}/registrations/{passTypeIdentifier}/{serialNumber}`

**Request Body:**
```json
{
  "pushToken": "device-push-token-here"
}
```

**Response:**
- `201 Created` - New registration created
- `200 OK` - Registration already exists (idempotent)
- `404 Not Found` - Pass not found
- `400 Bad Request` - Invalid pass type

**Example:**
```bash
curl -X POST https://your-domain.com/wallet/v1/devices/device123/registrations/pass.com.kawhe.loyalty/kawhe-1-2 \
  -H "Authorization: ApplePass your-web-service-token" \
  -H "Content-Type: application/json" \
  -d '{"pushToken": "abc123def456"}'
```

### 2. Unregister Device

**DELETE** `/wallet/v1/devices/{deviceLibraryIdentifier}/registrations/{passTypeIdentifier}/{serialNumber}`

**Response:**
- `200 OK` - Always (idempotent)

**Example:**
```bash
curl -X DELETE https://your-domain.com/wallet/v1/devices/device123/registrations/pass.com.kawhe.loyalty/kawhe-1-2 \
  -H "Authorization: ApplePass your-web-service-token"
```

### 3. Get Updated Pass

**GET** `/wallet/v1/passes/{passTypeIdentifier}/{serialNumber}`

**Headers:**
- `If-Modified-Since` (optional) - For 304 Not Modified responses

**Response:**
- `200 OK` - Pass file (binary .pkpass)
- `304 Not Modified` - Pass hasn't changed since If-Modified-Since
- `404 Not Found` - Pass not found
- `400 Bad Request` - Invalid pass type

**Response Headers:**
- `Content-Type: application/vnd.apple.pkpass`
- `Cache-Control: no-store`
- `Last-Modified: <HTTP date>`

**Example:**
```bash
curl -X GET https://your-domain.com/wallet/v1/passes/pass.com.kawhe.loyalty/kawhe-1-2 \
  -H "Authorization: ApplePass your-web-service-token" \
  -H "If-Modified-Since: Mon, 15 Jan 2024 10:00:00 GMT" \
  --output pass.pkpass
```

### 4. Get Updated Serials

**GET** `/wallet/v1/devices/{deviceLibraryIdentifier}/registrations/{passTypeIdentifier}?passesUpdatedSince=<timestamp>`

**Query Parameters:**
- `passesUpdatedSince` (optional) - Unix timestamp or HTTP date string

**Response:**
```json
{
  "lastUpdated": 1705320000,
  "serialNumbers": ["kawhe-1-2", "kawhe-1-3"]
}
```

**Example:**
```bash
curl -X GET "https://your-domain.com/wallet/v1/devices/device123/registrations/pass.com.kawhe.loyalty?passesUpdatedSince=1705320000" \
  -H "Authorization: ApplePass your-web-service-token"
```

### 5. Log Endpoint

**POST** `/wallet/v1/log`

**Request Body:**
```json
{
  "logs": [
    {
      "level": "info",
      "message": "Pass update received"
    }
  ]
}
```

**Response:**
- `200 OK`

**Example:**
```bash
curl -X POST https://your-domain.com/wallet/v1/log \
  -H "Authorization: ApplePass your-web-service-token" \
  -H "Content-Type: application/json" \
  -d '{"logs": [{"level": "info", "message": "Test log"}]}'
```

## Configuration

### Environment Variables

Add these to your `.env` file:

```env
# Apple Wallet Web Service Authentication
APPLE_WALLET_WEB_SERVICE_AUTH_TOKEN=your-secure-random-token-here

# Apple Push Notification Service (APNs)
WALLET_APPLE_PUSH_ENABLED=true
APPLE_APNS_KEY_ID=ABC123XYZ
APPLE_APNS_TEAM_ID=DEF456UVW
APPLE_APNS_AUTH_KEY_PATH=apns/AuthKey_ABC123XYZ.p8
APPLE_APNS_TOPIC=pass.com.kawhe.loyalty
APPLE_APNS_PRODUCTION=true
```

### Generating Web Service Auth Token

Generate a secure random token:

```bash
# Using OpenSSL
openssl rand -hex 32

# Or using PHP
php -r "echo bin2hex(random_bytes(32));"
```

### APNs Configuration

1. **Create APNs Key**:
   - Go to Apple Developer Portal → Certificates, Identifiers & Profiles
   - Create a new Key with "Apple Push Notifications service (APNs)" enabled
   - Download the `.p8` file
   - Note the Key ID and Team ID

2. **Store the Key**:
   ```bash
   mkdir -p storage/app/private/apns
   cp AuthKey_ABC123XYZ.p8 storage/app/private/apns/
   chmod 600 storage/app/private/apns/AuthKey_ABC123XYZ.p8
   ```

3. **Set Environment Variables**:
   ```env
   APPLE_APNS_KEY_ID=ABC123XYZ
   APPLE_APNS_TEAM_ID=YOUR_TEAM_ID
   APPLE_APNS_AUTH_KEY_PATH=apns/AuthKey_ABC123XYZ.p8
   APPLE_APNS_TOPIC=pass.com.kawhe.loyalty
   APPLE_APNS_PRODUCTION=true  # false for sandbox
   ```

## Testing

### Run Tests

```bash
php artisan test --filter AppleWalletWebServiceTest
```

### Manual Testing with cURL

1. **Create a test account**:
   ```bash
   php artisan tinker
   ```
   ```php
   $user = \App\Models\User::factory()->create();
   $store = \App\Models\Store::factory()->create(['user_id' => $user->id]);
   $customer = \App\Models\Customer::factory()->create();
   $account = \App\Models\LoyaltyAccount::factory()->create([
       'store_id' => $store->id,
       'customer_id' => $customer->id,
   ]);
   $serialNumber = "kawhe-{$store->id}-{$customer->id}";
   echo "Serial Number: {$serialNumber}\n";
   ```

2. **Register device**:
   ```bash
   curl -X POST http://localhost:8000/wallet/v1/devices/test-device-123/registrations/pass.com.kawhe.loyalty/kawhe-1-2 \
     -H "Authorization: ApplePass test-auth-token-123" \
     -H "Content-Type: application/json" \
     -d '{"pushToken": "test-push-token-456"}'
   ```

3. **Get pass**:
   ```bash
   curl -X GET http://localhost:8000/wallet/v1/passes/pass.com.kawhe.loyalty/kawhe-1-2 \
     -H "Authorization: ApplePass test-auth-token-123" \
     --output test-pass.pkpass
   ```

4. **Test 304 Not Modified**:
   ```bash
   # First request
   curl -X GET http://localhost:8000/wallet/v1/passes/pass.com.kawhe.loyalty/kawhe-1-2 \
     -H "Authorization: ApplePass test-auth-token-123" \
     -v
   
   # Note the Last-Modified header, then:
   curl -X GET http://localhost:8000/wallet/v1/passes/pass.com.kawhe.loyalty/kawhe-1-2 \
     -H "Authorization: ApplePass test-auth-token-123" \
     -H "If-Modified-Since: <Last-Modified-value>" \
     -v
   ```

## Integration Flow

### When a Stamp is Added

1. `StampLoyaltyService::stamp()` is called
2. Transaction commits
3. `UpdateWalletPassJob` is dispatched (after commit)
4. Job calls `WalletSyncService::syncLoyaltyAccount()`
5. Service calls `ApplePushService::sendPassUpdatePushes()`
6. APNs push notifications sent to all registered devices
7. Apple Wallet receives push and calls GET `/wallet/v1/passes/...`
8. Server returns updated .pkpass file
9. Pass updates on user's device

### Serial Number Mapping

Serial numbers follow the format: `kawhe-{store_id}-{customer_id}`

This ensures:
- Stable serial numbers (same account = same serial)
- Easy mapping to `LoyaltyAccount`
- No collisions between stores/customers

## Nginx Configuration

If using Nginx, ensure these routes are accessible:

```nginx
location /wallet/v1/ {
    try_files $uri $uri/ /index.php?$query_string;
    
    # Allow POST/DELETE without CSRF
    # (Laravel handles this via bootstrap/app.php)
}
```

## Troubleshooting

### Issue: 401 Unauthorized

**Solution**: Check that:
1. `APPLE_WALLET_WEB_SERVICE_AUTH_TOKEN` is set in `.env`
2. Authorization header format is correct: `ApplePass <token>`
3. Token matches exactly (no extra spaces)

### Issue: APNs Push Not Working

**Solution**: Check that:
1. `WALLET_APPLE_PUSH_ENABLED=true` in `.env`
2. APNs key file exists and is readable
3. Key ID, Team ID, and Topic are correct
4. Queue worker is running: `php artisan queue:work`
5. Check logs: `storage/logs/laravel.log`

### Issue: 304 Not Modified Not Working

**Solution**: 
- Ensure `If-Modified-Since` header is in HTTP date format
- Check that `loyalty_accounts.updated_at` is being updated correctly
- Verify timezone settings

### Issue: Pass Generation Fails

**Solution**: 
- Check existing Apple Wallet setup (certificates, config)
- Verify `AppleWalletPassService::generatePass()` works independently
- Check logs for specific error messages

## Security Notes

1. **Web Service Auth Token**: Use a strong, random token (32+ bytes)
2. **HTTPS Required**: Apple Wallet requires HTTPS in production
3. **Token Storage**: Never commit tokens to git
4. **APNs Key**: Store `.p8` file securely (600 permissions)
5. **Rate Limiting**: Consider adding rate limiting to endpoints

## Files Created/Modified

### New Files
- `database/migrations/xxxx_xx_xx_create_apple_wallet_registrations_table.php`
- `app/Models/AppleWalletRegistration.php`
- `app/Http/Middleware/ApplePassAuthMiddleware.php`
- `app/Http/Controllers/Wallet/AppleWalletController.php`
- `app/Services/Wallet/Apple/ApplePassService.php`
- `app/Services/Wallet/Apple/ApplePushService.php`
- `config/wallet.php`
- `tests/Feature/AppleWalletWebServiceTest.php`

### Modified Files
- `app/Services/Wallet/WalletSyncService.php` - Added Apple push integration
- `routes/web.php` - Added `/wallet/v1/*` routes
- `bootstrap/app.php` - Excluded wallet routes from CSRF

## Commands

### Run Migration
```bash
php artisan migrate
```

### Run Tests
```bash
php artisan test --filter AppleWalletWebServiceTest
```

### Clear Cache
```bash
php artisan config:clear
php artisan route:clear
php artisan cache:clear
```

## Next Steps

1. **Configure APNs**: Set up APNs key and environment variables
2. **Test Registration**: Register a test device
3. **Test Push**: Add a stamp and verify push notification is sent
4. **Monitor Logs**: Watch for errors in `storage/logs/laravel.log`
5. **Production**: Enable `APPLE_APNS_PRODUCTION=true` for production

## Support

For issues:
1. Check logs: `storage/logs/laravel.log`
2. Verify configuration: `php artisan config:show wallet`
3. Test endpoints manually with cURL
4. Check queue worker is running
