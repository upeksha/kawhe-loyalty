# Backup and release safety

Testing uses `/var/www/kawhe-testing`, an explicit private `/etc/kawhe-testing-ops.env`, local backups under `/var/backups/kawhe-testing/scheduled`, and private Spaces objects under `backups/testing/db` and `backups/testing/storage`. Never source the production ops configuration for a testing backup. Keep the ops configuration mode 0600 and outside Git.

Run each script with `APP_DIR=/var/www/kawhe-testing OPS_ENV_FILE=/etc/kawhe-testing-ops.env`. Database and artwork scripts fail on errors and send failure alerts. A missing or rejected alert webhook is itself an error. Backups use a private umask. Storage backups cover the local public disk; a different asset disk requires an explicit complete snapshot target, rather than silently backing up the wrong directory. Both backup types upload before local retention deletes old files. Off-server retention must be configured separately; these scripts do not delete remote objects.

## Isolated restoration

1. Take a database dump with `--single-transaction --quick --no-tablespaces --set-gtid-purged=OFF`; never add `--databases` or database-switch statements. Archive `storage/app/public` for the currently configured public artwork disk. Keep SQL, artwork, source commit and built frontend together with private permissions.
2. Upload private backup objects to a unique testing prefix. Download those exact objects into a new private directory and compare SHA-256 hashes before using them.
3. Create a new disposable database named `kawhe_restore_qa_<timestamp>`. Import only into that database. Never import over testing or live. Inspect the dump for database-switch statements before restoring an unfamiliar backup.
4. Check every restored table and count records. Extract the trusted artwork archive into a new private directory and compare file hashes. Do not run restored queues, send mail, or contact payment/Wallet providers.
5. Remove the disposable database after the drill. Retain the private backup and evidence under the testing backup directory; remove extracted scratch copies when no longer needed.

2026-10-13 drill: private Spaces upload/download hashes matched, 23 restored tables passed integrity checks (858 rows), and 190 artwork files matched. The disposable database was removed; neither running database or artwork directory was overwritten. The configured Discord webhook accepted the labelled test alert; human receipt is a separate check.

## Deployment gates

`deploy-release.sh` requires successful Laravel boot, application health and HTTP health checks before checkout/install. Boot, application health and HTTP checks also gate the final success message. Any failure returns nonzero. `deploy-testing.sh` exports the testing directory and ops configuration. For custom directories, supply `DEPLOY_HEALTH_URL` explicitly. The two known hosted directories have environment-specific defaults.

A failed post-deployment check does **not** roll back migrations or the checkout automatically. Stop promotion, inspect the failed release, and use the verified backup/recovery plan. These scripts use in-place checkout, not atomic releases; an error can leave changed code on the target environment. Do not automatically retry migrations or roll back a database containing newer customer transactions. Take and verify backups before release, and manage queue restarts explicitly.

Do not use `install-cron.sh` on the shared host without reviewing it: it replaces the invoking user's crontab. Testing monitoring/backup entries should use a separate cron file with explicit APP_DIR and OPS_ENV_FILE. Production schedules and production scripts are unchanged by a testing release.

Run offline regression tests with `python3 tests/Ops/test_safety.py`. These simulate boot/health/HTTP failures, failed queue queries, stopped workers, failed dumps/uploads, and missing/failed/successful webhook delivery without contacting providers or modifying an app.
