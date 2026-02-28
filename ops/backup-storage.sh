#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck disable=SC1091
source "$SCRIPT_DIR/lib.sh"

BACKUP_DIR="${OPS_BACKUP_DIR:-$APP_DIR/storage/backups}"
STORAGE_BACKUP_DIR="$BACKUP_DIR/storage"
RETENTION_DAYS="${OPS_STORAGE_BACKUP_RETENTION_DAYS:-14}"
TARGET_DIR="${OPS_STORAGE_TARGET_DIR:-$APP_DIR/storage/app/public}"

ensure_dir "$STORAGE_BACKUP_DIR"

if [[ ! -d "$TARGET_DIR" ]]; then
  alert "ERROR" "Storage backup skipped" "Target directory missing: $TARGET_DIR"
  exit 1
fi

STAMP="$(date -u +%Y%m%d_%H%M%S)"
OUT_FILE="$STORAGE_BACKUP_DIR/storage_public_$STAMP.tar.gz"

tar -C "$TARGET_DIR" -czf "$OUT_FILE" .

find "$STORAGE_BACKUP_DIR" -type f -name '*.tar.gz' -mtime +"$RETENTION_DAYS" -delete

SIZE="$(du -h "$OUT_FILE" | awk '{print $1}')"
alert "INFO" "Storage backup completed" "File: $OUT_FILE"$'\n'"Size: $SIZE"
