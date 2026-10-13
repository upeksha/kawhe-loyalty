#!/usr/bin/env bash

set -Eeuo pipefail

failed_release() {
  local status=$?
  echo "Deployment FAILED (exit $status). Do not promote this release; inspect the checkout and migration state." >&2
  if [[ -n "${OPS_ENV_FILE:-}" ]]; then
    bash "$SCRIPT_DIR/alert.sh" ERROR "Kawhe deployment failed" "App: $APP_DIR. Exit: $status. Inspect release state before retrying." || true
  fi
  exit "$status"
}
trap failed_release ERR

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR_DEFAULT="$(cd "$SCRIPT_DIR/.." && pwd)"

APP_DIR="${APP_DIR:-$APP_DIR_DEFAULT}"
REMOTE_NAME="${REMOTE_NAME:-origin}"
TARGET_REF="${1:-}"
RUN_NPM_BUILD="${RUN_NPM_BUILD:-1}"
RUN_MIGRATIONS="${RUN_MIGRATIONS:-1}"
ALLOW_DIRTY="${ALLOW_DIRTY:-0}"
WEB_USER="${WEB_USER:-www-data}"

if [[ -z "$TARGET_REF" ]]; then
  echo "Usage: $0 <git-ref>"
  echo "Example:"
  echo "  APP_DIR=/var/www/kawhe-testing $0 codex/prod-hardening"
  echo "  APP_DIR=/var/www/kawhe $0 1a5e99cbfcfa8c6e15730f61689c79271d50edf3"
  exit 1
fi

case "$APP_DIR" in
  /var/www/kawhe-testing) DEPLOY_HEALTH_URL="${DEPLOY_HEALTH_URL:-https://testing.kawhe.shop/up}";;
  /var/www/kawhe) DEPLOY_HEALTH_URL="${DEPLOY_HEALTH_URL:-https://app.kawhe.shop/up}";;
esac
: "${DEPLOY_HEALTH_URL:?Set DEPLOY_HEALTH_URL for this deployment}"

cd "$APP_DIR"

if [[ ! -f artisan ]]; then
  echo "artisan not found in $APP_DIR"
  exit 1
fi

if [[ "$ALLOW_DIRTY" != "1" ]]; then
  if [[ -n "$(git status --porcelain)" ]]; then
    echo "Refusing to deploy from a dirty working tree in $APP_DIR"
    git status --short
    exit 1
  fi
fi

echo "== Kawhe deploy =="
echo "App dir: $APP_DIR"
echo "Remote: $REMOTE_NAME"
echo "Target ref: $TARGET_REF"

php artisan about >/dev/null
php artisan health:check --no-interaction
curl --fail --silent --show-error --connect-timeout 5 --max-time 20 "$DEPLOY_HEALTH_URL" >/dev/null

current_commit="$(git rev-parse HEAD)"
echo "Current commit: $current_commit"

git fetch "$REMOTE_NAME" --tags

target_commit="$(git rev-parse "$TARGET_REF")"
echo "Target commit:  $target_commit"

if [[ "$current_commit" = "$target_commit" ]]; then
  echo "Already at target commit."
else
  git checkout --detach "$target_commit"
fi

composer install --no-dev --optimize-autoloader --no-interaction

if [[ "$RUN_NPM_BUILD" = "1" && -f package.json ]]; then
  npm run build
fi

if [[ "$RUN_MIGRATIONS" = "1" ]]; then
  php artisan migrate --force
fi

php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache

if [[ "$(id -u)" = "0" ]] && id "$WEB_USER" >/dev/null 2>&1; then
  chown -R "$WEB_USER:$WEB_USER" storage bootstrap/cache
fi

php artisan about >/dev/null
echo "Laravel boot check: OK"

php artisan health:check --no-interaction
echo "Health check: OK"

if [[ -n "${DEPLOY_HEALTH_URL:-}" ]]; then
  curl --fail --silent --show-error --connect-timeout 5 --max-time 20 "$DEPLOY_HEALTH_URL" >/dev/null
  echo "HTTP health check: OK"
fi

echo "Deployed commit: $(git rev-parse HEAD)"
