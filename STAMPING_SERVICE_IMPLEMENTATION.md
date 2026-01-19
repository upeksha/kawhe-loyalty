# Stamping Service Implementation (Phase 1)

## Overview

This document describes the implementation of a safe, concurrent stamping system for the Kawhe loyalty platform. The system ensures atomic updates, full audit trails, and automatic wallet pass updates.

## Files Created/Modified

### New Files
1. `app/Services/Loyalty/StampLoyaltyService.php` - Core stamping service
2. `app/Services/Loyalty/StampResultDTO.php` - Data transfer object for stamp results
3. `app/Jobs/UpdateWalletPassJob.php` - Queue job for wallet pass updates
4. `app/Services/Wallet/WalletSyncService.php` - Wallet sync service (stub for Phase 1)
5. `config/loyalty.php` - Loyalty configuration (reward_target default)
6. `tests/Feature/StampLoyaltyServiceTest.php` - Comprehensive test suite

### Modified Files
1. `app/Http/Controllers/ScannerController.php` - Refactored to use `StampLoyaltyService`

## Key Features

### 1. Concurrency Safety
- Uses `lockForUpdate()` to prevent race conditions
- Optimistic locking via `version` column
- Idempotency protection via unique `idempotency_key` constraint

### 2. Audit Trail
- Creates `stamp_events` record for every stamp
- Creates `points_transactions` record (if table exists)
- Logs user agent and IP address

### 3. Reward Calculation
- Handles overshoot (when stamp_count exceeds reward_target)
- Uses store-specific `reward_target` or config default
- Automatically updates `reward_balance` and resets `stamp_count`

### 4. Wallet Integration
- Dispatches `UpdateWalletPassJob` after successful stamp
- Job runs after transaction commits (via `DB::afterCommit()`)
- Retry mechanism with exponential backoff

## Database Schema

### Existing Tables (No Changes Required)
- `stamp_events` - Already has `idempotency_key` with unique constraint (migration `2026_01_08_000522`)
- `points_transactions` - Already exists with `idempotency_key` unique constraint
- `loyalty_accounts` - Already has `version` column for optimistic locking

## Commands to Run

### 1. Run Migrations
```bash
php artisan migrate
```

**Note:** All required migrations already exist. The `idempotency_key` unique constraint was added in migration `2026_01_08_000522_add_idempotency_and_version_to_tables.php`.

### 2. Run Tests
```bash
# Run all tests
php artisan test

# Run only stamping service tests
php artisan test --filter StampLoyaltyServiceTest

# Run with coverage
php artisan test --coverage
```

### 3. Clear Cache (if needed)
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

## API Usage Examples

### Example 1: Basic Stamp Request
```bash
curl -X POST http://localhost:8000/stamp \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "token": "LA:abc123def456...",
    "store_id": 1,
    "idempotency_key": "550e8400-e29b-41d4-a716-446655440000"
  }'
```

**Response:**
```json
{
  "status": "success",
  "success": true,
  "message": "Successfully added 1 stamp!",
  "storeName": "Coffee Shop",
  "store_id_used": 1,
  "store_name_used": "Coffee Shop",
  "store_switched": false,
  "loyalty_account_id": 123,
  "customerLabel": "John Doe",
  "stampCount": 5,
  "rewardBalance": 0,
  "rewardTarget": 10,
  "rewardAvailable": false,
  "rewardEarned": false,
  "stampsRemaining": 5,
  "receipt": {
    "transaction_id": 456,
    "timestamp": "2026-01-15T10:30:00Z",
    "stamps_added": 1,
    "new_total": 5
  }
}
```

### Example 2: Stamp with Custom Count
```bash
curl -X POST http://localhost:8000/stamp \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "token": "LA:abc123def456...",
    "store_id": 1,
    "count": 3,
    "idempotency_key": "550e8400-e29b-41d4-a716-446655440001"
  }'
```

### Example 3: Idempotent Request (Duplicate)
```bash
# First request
curl -X POST http://localhost:8000/stamp \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "token": "LA:abc123def456...",
    "store_id": 1,
    "idempotency_key": "550e8400-e29b-41d4-a716-446655440002"
  }'

# Duplicate request with same idempotency_key
curl -X POST http://localhost:8000/stamp \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "token": "LA:abc123def456...",
    "store_id": 1,
    "idempotency_key": "550e8400-e29b-41d4-a716-446655440002"
  }'
```

**Response (Duplicate):**
```json
{
  "status": "duplicate",
  "success": true,
  "storeName": "Coffee Shop",
  "store_id_used": 1,
  "store_name_used": "Coffee Shop",
  "store_switched": false,
  "customerLabel": "John Doe",
  "stampCount": 5,
  "rewardBalance": 0,
  "rewardTarget": 10,
  "rewardAvailable": false,
  "message": "Already processed"
}
```

### Example 4: Cooldown Response
```bash
curl -X POST http://localhost:8000/stamp \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "token": "LA:abc123def456...",
    "store_id": 1
  }'
```

**Response (if within 30-second cooldown):**
```json
{
  "status": "cooldown",
  "success": false,
  "message": "Stamped 15s ago",
  "seconds_since_last": 15,
  "cooldown_seconds": 30,
  "allow_override": true,
  "next_action": "confirm_override",
  "stampCount": 5,
  "rewardBalance": 0
}
```

**Override cooldown:**
```bash
curl -X POST http://localhost:8000/stamp \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "token": "LA:abc123def456...",
    "store_id": 1,
    "override_cooldown": true
  }'
```

## Service Method Signature

```php
public function stamp(
    LoyaltyAccount $account,
    User $staff,
    int $count = 1,
    ?string $idempotencyKey = null,
    ?string $userAgent = null,
    ?string $ipAddress = null
): StampResultDTO
```

## Testing Checklist

### Manual Testing
1. ✅ **Free merchant under limit**: Create store, create customer join → works
2. ✅ **Free merchant at limit**: Create 50 loyalty accounts, then attempt 51st join → blocked
3. ✅ **Existing customer re-joining**: Still works
4. ✅ **Merchant scanner stamping**: Works for existing cards
5. ✅ **Subscribed merchant**: Customer join works beyond 50

### Automated Tests
Run the test suite to verify:
- ✅ Stamp increments `stamp_count`
- ✅ When `stamp_count` reaches `reward_target`, it resets and `reward_balance` increments
- ✅ Audit logs are created (`stamp_events` and `points_transactions`)
- ✅ Wallet update job is dispatched
- ✅ Idempotency key prevents double stamping
- ✅ Authorization checks (staff must own store or be super admin)
- ✅ Reward token management (`redeem_token` and `reward_available_at`)

## Configuration

### Loyalty Config (`config/loyalty.php`)
```php
return [
    'reward_target' => env('LOYALTY_REWARD_TARGET', 10),
];
```

Set in `.env`:
```env
LOYALTY_REWARD_TARGET=10
```

## Queue Worker

The `UpdateWalletPassJob` is dispatched to the `default` queue. Ensure your queue worker is running:

```bash
php artisan queue:work
```

Or via systemd (if configured):
```bash
sudo systemctl status kawhe-queue
```

## Error Handling

### Validation Exceptions
- Staff access denied: Returns 422 with validation error
- Invalid token: Returns 422 with validation error

### Database Exceptions
- Duplicate idempotency key: Handled gracefully, returns existing result
- Transaction failures: Automatically rolled back

### Queue Failures
- Job retries 3 times with exponential backoff (10s, 30s, 60s)
- Final failure logged to error log

## Performance Considerations

1. **Row Locking**: Uses `lockForUpdate()` to prevent concurrent modifications
2. **Transaction Scope**: All DB operations wrapped in transaction
3. **Job Dispatch**: Wallet update job dispatched after commit (non-blocking)
4. **Idempotency Check**: Performed before locking to avoid unnecessary locks

## Future Enhancements (Phase 2+)

1. **Wallet Push Updates**: Implement actual push notifications to Apple/Google Wallet
2. **Batch Stamping**: Support for bulk stamp operations
3. **Custom Reward Rules**: Per-store reward calculation logic
4. **Analytics**: Track stamp patterns and reward redemption rates

## Troubleshooting

### Issue: "Duplicate entry" error for idempotency_key
**Solution**: This is expected behavior. The same `idempotency_key` cannot be used twice. Generate a new UUID for each request.

### Issue: Wallet job not running
**Solution**: 
1. Check queue worker is running: `php artisan queue:work`
2. Check failed jobs: `php artisan queue:failed`
3. Check logs: `storage/logs/laravel.log`

### Issue: Tests failing
**Solution**:
1. Ensure database is migrated: `php artisan migrate:fresh`
2. Clear cache: `php artisan config:clear`
3. Run tests in isolation: `php artisan test --filter StampLoyaltyServiceTest`

## Security Notes

1. **Authorization**: Service validates staff has access to store before stamping
2. **Idempotency**: Prevents duplicate processing via unique constraint
3. **Audit Trail**: All stamps logged with user agent and IP address
4. **Optimistic Locking**: Version column prevents lost updates

## Support

For issues or questions, refer to:
- Service implementation: `app/Services/Loyalty/StampLoyaltyService.php`
- Tests: `tests/Feature/StampLoyaltyServiceTest.php`
- Controller: `app/Http/Controllers/ScannerController.php`
