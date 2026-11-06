# Permanent account deletion and archiving

This change is prepared locally. It has not been deployed to production.

## Local development preview

The change is installed in `kawhe-local-testing` at `http://127.0.0.1:8010/profile`. Sign in, expand **Review account deletion**, and use the password-confirmed **Delete Account** action. Account deletion is a web-only feature; no mobile-app changes are included.

The local MySQL database has both required migrations. `kawhe-local-scheduler` runs `php artisan schedule:work` against the same local checkout, database and file storage, with Docker's `unless-stopped` restart policy. After deliberately stopping the containers, restart the development environment with:

```bash
docker start kawhe-local-mysql kawhe-local-mailpit kawhe-local-web kawhe-local-scheduler
```

This setup does not restore hosted testing or deploy anything to the production droplet.

## Supported deletion flow

The password-confirmed **Delete Account** action calls `AccountDeletionService`. Billing must be settled first; existing subscription, invoice and checkout guards still apply. Do not use a raw SQL user deletion as a substitute: it bypasses the cleanup intent.

In one database transaction, the service records a durable file-cleanup request, removes the owner's active and archived stores/cards and dependent loyalty records, removes related support records and recovery links, revokes tokens/sessions/reset links, and deletes the user. Customer identities survive if another café still has a card or valid pending enrolment for them. Staff identities belonging to other people survive. Activity at another café keeps its counts/history while the deleted actor ID and technical identifiers are cleared.

No files are deleted inside that transaction. A database failure rolls back both the records and the cleanup request without deleting images.

## File cleanup

`accounts:cleanup-files` runs every minute through the existing scheduler. New requests wait five minutes for in-flight requests to settle. The cleanup inventory includes:

- Current legacy logo, Wallet logo, hero/photo and custom stamp files from all owned stores/cards, including archived ones.
- New uploads under `{logos,pass-logos,pass-heroes,card-designs}/accounts/{owner_id}/`, including replaced uploads.
- All versions under the deleted programs' `wallet/programs/{program_id}/` directories.
- Generated Google stamp strips for the deleted stores/customer cards.
- Owner-scoped card artwork under `wallet/card-styles-v2/accounts/{owner_id}/`.
- Current legacy `wallet/card-styles-v1/{hash}/` artwork, unless another active or archived store/card still uses the same design.
- Server-side Apple `.pkpass` files and pass-generation directories for the current and legacy card serials. Shared signing certificates are excluded.

Files still referenced by another active or archived store/card are retained. Missing files are harmless. A failed listing/read/delete remains pending and retries with bounded backoff. Exact paths are persisted before deletion, so retries work after a partial cleanup. Completed requests discard their file inventory. The record contains no email address, password or access token.

The existing `health:check` reports pending cleanup requests older than one hour as a failure, allowing the current production health-alert script to flag them after deployment.

## Archive remains separate

Store and non-default card Archive/Restore actions retain their records, balances, history and images. They do not create a permanent file-cleanup request. Deleting the entire owner account also deletes that owner's archived stores/cards; Archive is not a protection against an explicit account deletion.

## Boundaries

- This is forward account-deletion handling, not an automatic sweep of past deleted accounts. Old, already-unreferenced uploads from before ownership namespaces were introduced cannot reliably be attributed to an owner. Audit those separately; never guess ownership or bulk-delete the shared bucket.
- Backups, server/application logs, provider-retained billing records and copies already downloaded/cached on customer devices are separate. This feature does not rewrite backups, purge a CDN or remotely erase screenshots/Wallet passes. Deleted card links and authenticated operations no longer resolve to a live account.
- Original uploads currently referenced by a store/card are covered even if they predate the change. Replaced historical uploads made after this change are attributable through their owner namespace.
- Storage should remain on the same configured disk through a pending cleanup; migrate inventory deliberately if the asset disk changes.

## Release and rollback

1. Build/test locally. Use a disposable MySQL database to verify the foreign-key migration, not the live database.
2. Take and verify the normal production backup during an explicitly approved release window.
3. Apply both new migrations before enabling the new deletion handler. `stamp_events.user_id` becomes nullable with `SET NULL` on actor deletion, preserving other cafés' history.
4. Deploy the reviewed code and restart long-running workers through the normal controlled workflow. The existing scheduler must continue running every minute.
5. Verify a labelled QA-only merchant deletion, storage cleanup, archive/restore and another merchant's unchanged records. Check pending cleanup counts and health alerts.
6. Prefer a forward fix for failures. Rolling back code alone does not undo a completed deletion. Do not drop the cleanup table with pending requests; its down migration refuses this. The actor migration refuses to restore a required actor column when anonymised history exists. Preserve these additive migrations and the cleanup worker during any code rollback.

No production account deletion or storage sweep is authorized merely by preparing this implementation.
