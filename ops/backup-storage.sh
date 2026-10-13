#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck disable=SC1091
source "$SCRIPT_DIR/lib.sh"
trap backup_failed ERR

if [[ "${ASSET_DISK:-public}" != "public" && -z "${OPS_STORAGE_TARGET_DIR:-}" ]]; then
  alert ERROR "Artwork backup requires configuration" "ASSET_DISK is not public; configure a complete artwork snapshot before backing up."
  exit 1
fi

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


REMOTE_PREFIX="${OPS_STORAGE_SPACES_PREFIX:-backups/storage}"
if [[ -n "${OPS_BACKUP_SPACES_BUCKET:-${ASSETS_BUCKET:-}}" && -n "${OPS_BACKUP_SPACES_ENDPOINT:-${ASSETS_ENDPOINT:-}}" ]]; then
  php "$SCRIPT_DIR/upload-to-spaces.php" "$OUT_FILE" "$REMOTE_PREFIX/$(basename "$OUT_FILE")"
fi

SIZE="$(du -h "$OUT_FILE" | awk '{print $1}')"
alert "INFO" "Storage backup completed" "File: $OUT_FILE"$'\n'"Size: $SIZE"

find "$STORAGE_BACKUP_DIR" -type f -name '*.tar.gz' -mtime +"$RETENTION_DAYS" -delete
