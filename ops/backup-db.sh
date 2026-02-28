#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck disable=SC1091
source "$SCRIPT_DIR/lib.sh"

BACKUP_DIR="${OPS_BACKUP_DIR:-$APP_DIR/storage/backups}"
DB_BACKUP_DIR="$BACKUP_DIR/db"
RETENTION_DAYS="${OPS_DB_BACKUP_RETENTION_DAYS:-14}"

ensure_dir "$DB_BACKUP_DIR"

if [[ "${DB_CONNECTION:-}" != "mysql" ]]; then
  alert "ERROR" "DB backup skipped" "Unsupported DB_CONNECTION=${DB_CONNECTION:-unset}. Only mysql is supported by backup-db.sh."
  exit 1
fi

STAMP="$(date -u +%Y%m%d_%H%M%S)"
OUT_FILE="$DB_BACKUP_DIR/${DB_DATABASE}_$STAMP.sql.gz"
TMP_FILE="$DB_BACKUP_DIR/.${DB_DATABASE}_$STAMP.sql"

export MYSQL_PWD="${DB_PASSWORD:-}"

mysqldump \
  --host="${DB_HOST}" \
  --port="${DB_PORT:-3306}" \
  --user="${DB_USERNAME}" \
  --single-transaction \
  --quick \
  --routines \
  --triggers \
  --set-gtid-purged=OFF \
  "${DB_DATABASE}" > "$TMP_FILE"

gzip -f "$TMP_FILE"
mv "${TMP_FILE}.gz" "$OUT_FILE"

find "$DB_BACKUP_DIR" -type f -name '*.sql.gz' -mtime +"$RETENTION_DAYS" -delete

SIZE="$(du -h "$OUT_FILE" | awk '{print $1}')"
alert "INFO" "DB backup completed" "File: $OUT_FILE"$'\n'"Size: $SIZE"
