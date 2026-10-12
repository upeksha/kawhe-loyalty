# Store staff access

Verified cafe owners can open **Staff access** from the merchant navigation. Each invitation applies to one store and grants **Stamp cards**, **Redeem rewards**, or both. An owner can resend or cancel a pending invitation, change a joined member's permissions, or remove access.

Invitation links expire after seven days and can be accepted once. Resending replaces the old link. Only a SHA-256 token hash is stored in the invitation table. New staff create their own login through the emailed link and go directly to the scanner, without creating a store or choosing a billing plan. Existing accounts must sign in with the invited, verified email address.

Staff access does not confer ownership. Staff-only accounts cannot open merchant settings, customer lists/exports, staff management, onboarding, or billing. Existing merchants invited to another store retain their own stores but receive only scanner permissions at the invited store. Both browser and API scanner actions check the current store membership and action permission; existing sessions and API tokens lose those permissions when membership is revoked. Stamp and redemption ledgers retain the acting user ID.

## Release and validation

Run `2026_10_12_000002_create_store_staff_tables` with the release. It adds `users.is_staff_account` and the `store_staff` and `store_staff_invitations` tables. Existing users remain owner-capable by default. Invitations use the configured mail queue, so the environment's queue worker must be running.

Regression coverage is in `tests/Feature/StoreStaffPermissionsTest.php`. Test against an isolated database, never the deployed application's database. Verify invitation acceptance, stamping, redemption, action-specific permissions, owner-only routes, and revocation in the hosted testing environment before promoting the exact tested commit. Invitation mail delivery to a real inbox requires a separate recipient-controlled check.
