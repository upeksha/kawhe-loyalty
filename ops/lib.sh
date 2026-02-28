#!/usr/bin/env bash

set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/kawhe}"
APP_ENV_FILE="${APP_ENV_FILE:-$APP_DIR/.env}"
OPS_ENV_FILE="${OPS_ENV_FILE:-/etc/kawhe-ops.env}"

load_env_file() {
  local file="$1"
  if [[ -f "$file" ]]; then
    set -a
    # shellcheck disable=SC1090
    source "$file"
    set +a
  fi
}

load_env_file "$APP_ENV_FILE"
load_env_file "$OPS_ENV_FILE"

timestamp() {
  date -u +"%Y-%m-%dT%H:%M:%SZ"
}

log() {
  printf '[%s] %s\n' "$(timestamp)" "$*"
}

ensure_dir() {
  mkdir -p "$1"
}

alert() {
  local level="$1"
  local title="$2"
  local body="${3:-}"
  local payload message

  message="[$level] $title"
  if [[ -n "$body" ]]; then
    message="$message"$'\n'"$body"
  fi

  log "$message"

  if [[ -n "${OPS_ALERT_WEBHOOK_URL:-}" ]]; then
    payload=$(printf '{"text":"%s"}' "$(printf '%s' "$message" | python3 -c 'import json,sys; print(json.dumps(sys.stdin.read())[1:-1])')")
    curl -fsS -X POST -H "Content-Type: application/json" -d "$payload" "$OPS_ALERT_WEBHOOK_URL" >/dev/null || log "Webhook alert delivery failed"
  fi
}
