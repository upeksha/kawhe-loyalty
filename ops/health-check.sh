#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck disable=SC1091
source "$SCRIPT_DIR/lib.sh"

HEALTH_URL="${OPS_HEALTHCHECK_URL:-${APP_URL%/}/up}"
DISK_THRESHOLD="${OPS_DISK_WARN_PERCENT:-85}"
QUEUE_WARN_THRESHOLD="${OPS_QUEUE_WARN_THRESHOLD:-25}"

HEALTH_LOG="$(mktemp)"
trap 'rm -f "$HEALTH_LOG"' EXIT
errors=()
warnings=()

if ! php "$APP_DIR/artisan" health:check --no-interaction >"$HEALTH_LOG" 2>&1; then
  errors+=("artisan health:check failed")
fi

for service in ${OPS_SUPERVISOR_SERVICES:-kawhe-queue kawhe-reverb}; do
  if ! supervisorctl status "$service" | grep -q RUNNING; then
    errors+=("$service is not running")
  fi
done

if ! curl -fsS --max-time 10 "$HEALTH_URL" >/dev/null; then
  errors+=("HTTP health check failed: $HEALTH_URL")
fi

disk_use="$(df -P / | awk 'NR==2 {gsub(/%/, "", $5); print $5}')"
if [[ "${disk_use:-0}" -ge "$DISK_THRESHOLD" ]]; then
  warnings+=("Disk usage is ${disk_use}%")
fi

queue_pending="$(php "$APP_DIR/artisan" tinker --execute="echo Illuminate\\Support\\Facades\\DB::table('jobs')->count();" 2>/dev/null || echo QUERY_FAILED)"
failed_jobs="$(php "$APP_DIR/artisan" tinker --execute="echo Illuminate\\Support\\Facades\\DB::table('failed_jobs')->count();" 2>/dev/null || echo QUERY_FAILED)"

if [[ ! "$queue_pending" =~ ^[0-9]+$ || ! "$failed_jobs" =~ ^[0-9]+$ ]]; then
  errors+=("Queue status query failed")
  queue_pending=0
  failed_jobs=0
fi

if [[ "${queue_pending:-0}" -ge "$QUEUE_WARN_THRESHOLD" ]]; then
  warnings+=("Queue backlog is ${queue_pending}")
fi

if [[ "${failed_jobs:-0}" -gt 0 ]]; then
  errors+=("Failed jobs present: ${failed_jobs}")
fi

if (( ${#errors[@]} > 0 )); then
  message="$(
    printf 'Errors:\n'
    printf ' - %s\n' "${errors[@]}"
    if (( ${#warnings[@]} > 0 )); then
      printf '\nWarnings:\n'
      printf ' - %s\n' "${warnings[@]}"
    fi

  )"
  alert "ERROR" "Kawhe $APP_DIR health check failed" "$message"
  exit 1
fi

if (( ${#warnings[@]} > 0 )); then
  message="$(
    printf 'Warnings:\n'
    printf ' - %s\n' "${warnings[@]}"

  )"
  alert "WARN" "Kawhe $APP_DIR health warnings" "$message"
  exit 0
fi

log "Health check passed"
