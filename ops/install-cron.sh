#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

TMP_CRON="$(mktemp)"
trap 'rm -f "$TMP_CRON"' EXIT

cat > "$TMP_CRON" <<CRON
MAILTO=""
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

* * * * * cd $APP_DIR && php artisan schedule:run >> /dev/null 2>&1
*/5 * * * * $APP_DIR/ops/health-check.sh >> /var/log/kawhe-health.log 2>&1
15 2 * * * $APP_DIR/ops/backup-db.sh >> /var/log/kawhe-backup.log 2>&1
30 2 * * * $APP_DIR/ops/backup-storage.sh >> /var/log/kawhe-backup.log 2>&1
CRON

crontab "$TMP_CRON"
echo "Installed cron entries:"
crontab -l
