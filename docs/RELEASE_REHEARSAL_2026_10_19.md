# Production release preparation — 19 October 2026

Status: prepared and rehearsed locally; NOT deployed or approved for production.

## Baseline and scope

- Live baseline: `71cebdb8008f43fa2456e2dc3c3fe8ff64b434ec`.
- Tested application baseline: `758c5a79fa921df5cce895af23aa402eadb04ce8`.
- The release candidate adds only the backup helpers, production worker template,
  recovery integrity tests and this runbook. Its exact SHA is in the package manifest.
- No server configuration, provider configuration or live data was changed.
- Do not copy testing's database, `.env`, APP_KEY, Stripe keys or Wallet identity.
- Existing data includes users, stores, cards and a subscription; preserve it.

## Rehearsal evidence

Using the existing production database backup dated 2026-10-19 02:15 UTC:

- Restored into a fresh, disposable local MySQL 8.0 container.
- Baseline: 20 tables, 761 total rows including 41 migration records.
- Applied all five outstanding migrations successfully (41 -> 46).
- Counts and hashes of the original columns in every existing non-migration table remained unchanged.
- Restored the original dump into a second database. All baseline counts matched.
- Booted the actual old release against that restored database and inspected its migration status.
- Removed the disposable container and its volume after the drill.
- Downloaded 114 artwork objects from the actual production assets disk using read-only
  access; credentials passed through memory only. Excluded `backups/` objects.
- Archived, extracted and verified all 114 SHA-256 hashes.
- Downloaded the existing production frontend build for rollback.
- Full local PHP suite: 527 passed, 2 skipped (Apple certificate/pass integration tests).
- Production worker template: slow/retry/failure isolation probes passed using local SQLite,
  log mail and the exact queue command arguments. This is not production inbox proof.
- Local frontend build passed. Ops failure-injection tests and artwork corruption tests passed.

Private backups and detailed logs are outside the repository in
`../../output/release-2026-10-19/`. Do not commit or publish them. This drill is not
permission to restore them over live. Take fresh backups again at deployment time.

## Prepared fixes

1. `backup-storage.sh` snapshots the configured non-public assets disk instead of accepting
   a local public directory as a substitute. Snapshot files have a hash manifest, are verified
   before archiving and use private temporary directories. Failed snapshots stop the backup.
2. `snapshot-assets.php` streams objects to disk and refuses unsafe keys, truncated copies
   and empty snapshots. Backup objects under `backups/` are deliberately excluded.
3. `upload-to-spaces.php` reads the uploaded private backup back and compares SHA-256 before
   reporting success and allowing retention cleanup. This new remote upload/readback path
   still requires an approved hosted backup check; no backup objects were written in this drill.
4. `kawhe-production-workers.conf` separates `default` and `emails`, uses 70s/60s timeouts
   below database retry_after=90, and sets memory/recycle limits.

## Configuration change sheet — not applied

Preserve all production identities and credentials. Set only:

```ini
MAIL_WELCOME_SYNC=false
MAIL_TIMEOUT=15
DB_QUEUE_RETRY_AFTER=90
```

In `/etc/kawhe-ops.env`, retain production alert destination and backup credentials;
set `OPS_SUPERVISOR_SERVICES="kawhe-queue kawhe-emails kawhe-reverb"` and an explicit
`OPS_STORAGE_SPACES_PREFIX=backups/production/storage`. Keep DB backup prefix unchanged
unless separately approved. Set mode 0600 on this private ops file.

Replace the existing Supervisor kawhe-queue stanza with the prepared production
worker template. Do not duplicate stanzas; retain the existing production Reverb
configuration. During the maintenance window, stop and disable the duplicate
`kawhe-queue.service` systemd unit. Preserve its prior enabled/active state for recovery.
Do not restart all Supervisor services or shared PHP-FPM.

The new snapshot reads every non-backup object on the selected asset disk. Confirm
that the source bucket remains the intended application bucket before each run.
Also preserve local public files and private signing/configuration material separately.

## Before authorizing production

1. Promote this exact candidate to testing with its locally built frontend and check it.
   The new operational changes are currently local only.
2. Verify the new private backup upload/readback path and confirm receipt of a labelled
   production alert in the expected channel. Do not mistake HTTP acceptance for human receipt.
3. Confirm Google Wallet public issuer approval; current app configuration says UNDER_REVIEW.
4. Confirm Stripe live price/webhook delivery and legacy subscription price compatibility.
   Live price is NZD 10/month and webhook is enabled; no payment was performed in this drill.
5. Schedule a maintenance window and retain enough disk/memory for the release and backup.
   Current 1 GB shared host must not run npm build or parallel tests.
6. Obtain approval for the exact candidate SHA, configuration delta and live smoke-test actions.

## Deployment sequence — execute only after approval

Use a guarded operator session with stop-on-error; do not run a generic git pull or
blindly invoke the old production deployment script. The script performs in-place
checkout and does not implement this maintenance/backup/worker orchestration.

1. Recheck both checkout SHAs and clean status, HTTP /up, queue counts, memory and disk.
   Verify package SHA-256 checksums. Stop on drift or a failed preflight.
2. Stage the exact candidate and prebuilt `public/build` outside the running app.
   Verify composer.lock matches the rehearsed baseline (it currently does). Reuse the
   compatible vendor tree; do not run composer update. If dependencies drift, re-rehearse.
3. Enter production maintenance mode, with a private operator bypass for smoke checks.
   Stop live queue consumers gracefully: Supervisor kawhe-queue and systemd
   kawhe-queue.service; also stop production Reverb. Do not touch testing programs.
   Coordinate the production scheduler so it cannot execute during in-place replacement;
   preserve its crontab and temporarily suspend only the production schedule entry.
4. Take fresh database, assets, local public files, private certificate/configuration,
   frontend and old-release backups. Back up Supervisor/systemd/cron/ops configuration.
   Verify compression, artwork hashes and off-server readback. Record timestamps and SHA.
   Do not proceed if a backup or alert check fails.
5. Install the approved SHA and its exact prebuilt frontend. Apply only the reviewed env
   changes and rebuild config cache BEFORE migrations (needed for environment guards).
   Run `php artisan migrate --force`; then rebuild route/event/view caches. Verify the
   expected 46 migrations, unless a newly reviewed baseline says otherwise.
6. Install the reviewed worker stanzas without starting all workers globally. Disable
   the duplicate systemd queue unit; update only kawhe-queue and kawhe-emails after caches
   and migrations succeed. Restart only production Reverb. Restore the production scheduler.
7. Run Laravel health, verify services and bypassed HTTP health/login/assets, then perform
   controlled owner/staff/customer/email/Wallet/API checks. A failed check leaves maintenance
   on and triggers the rollback decision below. Do not report success because checkout passed.
8. Lift maintenance only when critical checks pass. Confirm ordinary public requests work,
   inspect errors/queues, and monitor. No automatic database rollback.

## Rollback decision and sequence

- Before migration or customer writes: keep maintenance on, stop workers and scheduler,
  restore old code/build/configuration and verify. Do not restore a database unnecessarily.
- After migration: all five migrations are additive, but code-only compatibility is NOT
  proven for new staff accounts, changed card styles, sessions or queued new job classes.
  Prefer a forward fix. Do not use `migrate:rollback` as an automatic recovery mechanism.
- For a full rollback: freeze writes, preserve a second incident-state database/artwork
  snapshot, then restore the fresh pre-release dump into a NEW recovery database. Validate
  table counts and artwork hashes before an explicitly approved database switch. Preserve
  the failed-release database for reconciliation. Never silently discard post-backup activity.
- Restore artwork into a separate verified snapshot/prefix first. Do not bulk-delete live
  bucket contents; overwrites must be limited to the approved inventory, with current versions
  retained. Recheck any object URL/path implications before switching storage configuration.
- Restore old code, frontend and matching configuration. Old code can boot on the original
  restored schema (rehearsed), but reopening restores its older known security limitations.
  If unsafe, remain in maintenance and fix forward instead.
- Restore service/scheduler configuration consistently; never run old workers against a new
  database or replay new-format jobs with the old code. Verify boot, migrations, health and
  controlled requests before reopening.
- Stripe charges/subscription changes, delivered emails and issued Wallet passes are external
  effects. A database rollback cannot undo them. Reconcile provider state explicitly.

## Deliberate limits

This is a local restoration/migration/worker rehearsal, not a hosted cutover rehearsal.
No production restore, live payment, alert, email or physical Wallet update was performed.
A real source snapshot can change while read; take the final paired backups while writes
and workers are paused. The current iPhone build still targets testing and is not a live app.
