# Grandfathering Implementation - Subscription Cancellation Handling

## Overview

This document describes the implementation of **grandfathering** for loyalty cards when a merchant cancels their Pro subscription. Cards created during the Pro subscription period remain active (grandfathered), while new card creation is limited to the free plan limit (50 cards).

## Implementation Details

### Core Logic

1. **Grandfathered Cards**: Cards created **before** subscription cancellation (`ends_at` date) remain active forever
2. **Non-Grandfathered Cards**: Cards created **after** subscription cancellation count toward the 50-card free limit
3. **Active Subscription**: All cards work, unlimited creation
4. **Cancelled Subscription**: Grandfathered cards work, but new creation limited to 50 non-grandfathered cards

### Key Changes

#### 1. `UsageService` Updates

**New Methods:**
- `cardsCountForUser($user, $includeGrandfathered = true)`: Counts cards, optionally excluding grandfathered ones
- `grandfatheredCardsCount($user)`: Returns count of grandfathered cards

**Updated Methods:**
- `canCreateCard($user)`: Now checks only non-grandfathered cards against the limit
- `getUsageStats($user)`: Returns additional stats:
  - `non_grandfathered_count`: Cards created after cancellation
  - `grandfathered_count`: Cards created before cancellation
  - `has_cancelled_subscription`: Boolean flag

**Logic:**
```php
// If subscription cancelled (has ends_at), only count cards created AFTER ends_at
if ($subscription && $subscription->ends_at) {
    $query->where('created_at', '>=', $subscription->ends_at);
}
```

#### 2. UI Updates

**Dashboard (`dashboard.blade.php`):**
- Shows grandfathered count in card display
- Displays warning banner for cancelled subscriptions with grandfathered cards
- Progress bar uses non-grandfathered count

**Billing Page (`billing/index.blade.php`):**
- Shows grandfathered count
- Usage stats reflect non-grandfathered cards
- Clear messaging about grandfathered cards

**Profile Page (`profile/partials/subscription-details.blade.php`):**
- Shows grandfathered count
- Displays info message about grandfathered cards
- Limit checks use non-grandfathered count

#### 3. JoinController Updates

- Updated logging to include grandfathered vs non-grandfathered counts
- Limit enforcement uses `canCreateCard()` which respects grandfathering

### Webhook Handling

Laravel Cashier's `WebhookController` automatically handles subscription cancellation:

1. **`customer.subscription.updated`**: Updates subscription status and `ends_at` when cancelled
2. **`customer.subscription.deleted`**: Marks subscription as deleted

The `ends_at` field is automatically set by Cashier when:
- Subscription is cancelled (immediate or at period end)
- Subscription expires

Our `isSubscribed()` method correctly returns `false` for cancelled subscriptions (checks for 'active' or 'trialing' status only).

### Example Scenarios

#### Scenario 1: Merchant with 100 cards cancels subscription
- **Before cancellation**: 100 cards, all active, unlimited creation
- **After cancellation**: 
  - 100 cards remain active (all grandfathered)
  - Cannot create new cards (non-grandfathered count = 0, but limit is 50)
  - Wait, this is wrong... Let me recalculate

Actually, if they have 100 cards and cancel:
- All 100 cards created BEFORE cancellation → all grandfathered
- Non-grandfathered count = 0
- Can create up to 50 new cards (0 < 50)

#### Scenario 2: Merchant cancels, then creates 60 new cards
- **After cancellation**: 0 grandfathered, 0 non-grandfathered
- **Creates 50 cards**: 0 grandfathered, 50 non-grandfathered (at limit)
- **Tries to create 51st**: Blocked (50 >= 50)
- **Grandfathered cards from before**: Still work (if any existed)

#### Scenario 3: Merchant resubscribes
- All cards work (grandfathered + non-grandfathered)
- Unlimited creation restored
- Grandfathered status becomes irrelevant (all cards work)

### Testing Checklist

- [ ] Merchant with active subscription can create unlimited cards
- [ ] Merchant cancels subscription → `ends_at` is set
- [ ] Cards created before cancellation remain active (grandfathered)
- [ ] Cards created after cancellation count toward 50 limit
- [ ] Cannot create new cards if non-grandfathered count >= 50
- [ ] Can create new cards if non-grandfathered count < 50
- [ ] Dashboard shows grandfathered count
- [ ] Billing page shows correct usage stats
- [ ] Profile page shows grandfathered info
- [ ] Resubscribing removes restrictions

### Database Schema

No new migrations required. Uses existing fields:
- `subscriptions.ends_at`: Set by Cashier when subscription cancelled
- `loyalty_accounts.created_at`: Used to determine if card is grandfathered

### Backward Compatibility

✅ **Fully backward compatible:**
- Existing functionality unchanged
- No breaking changes to API
- All existing cards continue to work
- Only affects new card creation logic

### Future Considerations

1. **Resubscription**: When merchant resubscribes, all cards work (no need to track grandfathering)
2. **Multiple Cancellations**: If merchant cancels and resubscribes multiple times, only the most recent `ends_at` matters
3. **Edge Cases**: 
   - What if subscription is cancelled but then reactivated before `ends_at`? → `isSubscribed()` returns true, all cards work
   - What if `ends_at` is in the future? → Cards created now are grandfathered (created before ends_at)

## Files Modified

1. `app/Services/Billing/UsageService.php` - Core grandfathering logic
2. `app/Http/Controllers/JoinController.php` - Updated logging
3. `resources/views/dashboard.blade.php` - UI updates
4. `resources/views/billing/index.blade.php` - UI updates
5. `resources/views/profile/partials/subscription-details.blade.php` - UI updates

## Notes

- Grandfathering is automatic based on `created_at` vs `ends_at` comparison
- No manual intervention needed
- Webhook handling is automatic via Cashier
- All existing functionality preserved
