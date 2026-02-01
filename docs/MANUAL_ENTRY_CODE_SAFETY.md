# Manual entry code (4-char) – safety and store isolation

This doc explains how we ensure stamping and redeeming never affect the wrong store or wrong card when using the 4-character manual entry code (e.g. `A3CX`).

## Design: code is unique per store

- **`manual_entry_code`** is 4 characters (e.g. `A3CX`), unique **per store** (same code can exist at different stores).
- Lookup is always **`(store_id, manual_entry_code)`** when the input is 4 characters and a store is selected.
- So the **selected store** (from the scanner dropdown) fully determines which store’s cards we consider. We never mix stores.

## Stamp flow

1. **4-char + store selected:** Look up account by `(requested_store_id, manual_entry_code)`. Only accounts at that store are considered. If found, we then call `StampLoyaltyService->stamp(account, staff)`, which calls **`validateStaffAccess(account, staff)`**: staff must own the account’s store (or be super admin). So:
   - Correct store + correct code → stamp the right account.
   - Wrong store (e.g. Store B selected, card is at Store A) → lookup `(Store B, code)` finds no account (code is at Store A) → "invalid or not found".
   - Attacker sends another merchant’s `store_id` + code → we find that store’s account, then `validateStaffAccess` fails (staff doesn’t own that store) → 422, no stamp.
2. **4-char without store:** We do **not** use 4-char lookup (we require `store_id` for it). We fall back to `public_token` lookup; no account has a 4-char `public_token` → "invalid or not found".
3. **Full token (e.g. `LA:xxxx…`):** Lookup by `public_token` only. Then `validateStaffAccess` ensures the staff owns the account’s store. So another store’s card cannot be stamped unless the staff owns that store.

Result: **no stamp on the wrong store or wrong card** other than user error (merchant selects wrong store in the UI).

## Redeem flow

1. **`store_id` is required** for redeem. We resolve 4-char to account by `(store_id, manual_entry_code)` and then set `token = account->redeem_token` for the rest of the flow. So we only ever redeem an account that belongs to the **requested store**.
2. If merchant selects Store A and enters a code that only exists at Store B → lookup `(Store A, code)` finds nothing → invalid/expired code, no redeem.
3. Redeem is always scoped to the store the merchant selected; we never redeem another store’s card.

Result: **no redeem on the wrong store or wrong card**.

## Preview flow

- Preview uses the **same** 4-char lookup: `(store_id, manual_entry_code)` when token length is 4 and `store_id` is present. Same store isolation as stamp/redeem.
- If store is wrong, we don’t find the card (or we find a different store’s card for that store), and we also verify the user has access to the account’s store.

## Tests (ManualEntryCodeSafetyTest)

The test file **`tests/Feature/ManualEntryCodeSafetyTest.php`** encodes the above guarantees:

| Test | What it guarantees |
|------|--------------------|
| Stamp with 4-char + correct store | Stamps the right account only; stamp_count and stamp_events are correct. |
| Stamp with 4-char + wrong store | Does not find the card; returns 422; no stamp on the other store’s account. |
| Stamp with 4-char without store_id | Does not use 4-char path; returns 422; no stamp. |
| Stamp with 4-char + other merchant’s store_id | validateStaffAccess denies; 422 "do not have access"; no stamp. |
| Redeem with 4-char + correct store | Redeems the right account only; reward_balance and stamp_events correct. |
| Redeem with 4-char + wrong store | Does not redeem other store’s card; 422; both accounts’ reward_balance unchanged. |
| Full token stamp, other merchant | validateStaffAccess denies; 422; no stamp (regression test). |

Run:

```bash
php artisan test tests/Feature/ManualEntryCodeSafetyTest.php
```

## Summary

- **4-char code is scoped by store:** Lookup is always `(store_id, manual_entry_code)` when input is 4 chars and store is selected.
- **Stamp:** Same store scope + `validateStaffAccess` so we never stamp another store’s card unless the staff owns that store.
- **Redeem:** store_id required; 4-char resolved only for that store; no cross-store redeem.
- **Preview:** Same 4-char lookup and store checks.
- **Tests:** ManualEntryCodeSafetyTest covers these cases so we don’t accidentally stamp or redeem the wrong store or wrong card.
