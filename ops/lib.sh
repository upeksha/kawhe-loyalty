#!/usr/bin/env bash

set -Eeuo pipefail
umask 077

APP_DIR="${APP_DIR:-/var/www/kawhe}"
APP_ENV_FILE="${APP_ENV_FILE:-$APP_DIR/.env}"
if [[ "$APP_DIR" != "/var/www/kawhe" && -z "${OPS_ENV_FILE:-}" ]]; then
  echo "OPS_ENV_FILE is required outside production to prevent shared backup/health configuration." >&2
  exit 1
fi
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
  local payload message webhook_url json_message

  message="[$level] $title"
  if [[ -n "$body" ]]; then
    message="$message"$'\n'"$body"
  fi

  log "$message"

  webhook_url="${OPS_ALERT_WEBHOOK_URL:-}"
  if [[ -z "$webhook_url" ]]; then
    log "Alert webhook is not configured" >&2
    return 1
  fi
  if [[ -n "$webhook_url" ]]; then
    json_message="$(printf '%s' "$message" | python3 -c 'import json,sys; print(json.dumps(sys.stdin.read())[1:-1])')"
    if [[ "$webhook_url" == *"discord.com/api/webhooks/"* ]]; then
      payload=$(printf '{"content":"%s"}' "$json_message")
    else
      payload=$(printf '{"text":"%s"}' "$json_message")
    fi
    if ! curl -fsS --connect-timeout 5 --max-time 20 -X POST -H "Content-Type: application/json" -d "$payload" "$webhook_url" >/dev/null 2>&1; then
      log "Webhook alert delivery failed" >&2
      return 1
    fi
  fi
}

backup_failed() {
  local status=$?
  trap - ERR
  alert "ERROR" "Kawhe backup failed" "Environment: $APP_DIR. Exit status: $status. Inspect private backup logs." || true
  exit "$status"
}
