# Production Ops

## Installed scripts

- `ops/backup-db.sh`: gzip MySQL dump into `storage/backups/db`
- `ops/backup-storage.sh`: archive `storage/app/public` into `storage/backups/storage`
- `ops/health-check.sh`: runs Laravel health checks, supervisor checks, HTTP `/up`, disk, queue, failed jobs
- `ops/alert.sh`: sends an alert to `OPS_ALERT_WEBHOOK_URL` when configured
- `ops/install-cron.sh`: installs the standard cron schedule

## Required server env

These are optional but recommended in `/var/www/kawhe/.env` or `/etc/kawhe-ops.env`:

- `OPS_ALERT_WEBHOOK_URL`
- `OPS_BACKUP_DIR`
- `OPS_DB_BACKUP_RETENTION_DAYS`
- `OPS_STORAGE_BACKUP_RETENTION_DAYS`
- `OPS_HEALTHCHECK_URL`
- `OPS_DISK_WARN_PERCENT`
- `OPS_QUEUE_WARN_THRESHOLD`
- `OPS_BACKUP_SPACES_ACCESS_KEY_ID`
- `OPS_BACKUP_SPACES_SECRET_ACCESS_KEY`
- `OPS_BACKUP_SPACES_REGION`
- `OPS_BACKUP_SPACES_BUCKET`
- `OPS_BACKUP_SPACES_ENDPOINT`
- `OPS_BACKUP_SPACES_PREFIX`

## Cron schedule

- every minute: `php artisan schedule:run`
- every 5 minutes: health check
- daily 02:15 UTC: DB backup
- daily 02:30 UTC: storage backup

## Notes

- Backups are currently local-to-server unless you add an off-server sync step.
- Alert delivery is a no-op until `OPS_ALERT_WEBHOOK_URL` is configured.
- DB backups can be uploaded to Spaces with private ACL by setting the `OPS_BACKUP_SPACES_*` variables.
