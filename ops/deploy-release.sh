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
RUN_NPM_BUILD="${RUN_NPM_BUILD:-0}"
RUN_MIGRATIONS="${RUN_MIGRATIONS:-1}"
ALLOW_DIRTY="${ALLOW_DIRTY:-0}"
WEB_USER="${WEB_USER:-www-data}"
PREBUILT_ASSET_TAR="${PREBUILT_ASSET_TAR:-}"
PREBUILT_ASSET_SHA256="${PREBUILT_ASSET_SHA256:-}"

if [[ -z "$TARGET_REF" ]]; then
  echo "Usage: $0 <git-ref>"
  echo "Example:"
  echo "  APP_DIR=/var/www/kawhe-testing $0 codex/prod-hardening"
  echo "  APP_DIR=/var/www/kawhe $0 1a5e99cbfcfa8c6e15730f61689c79271d50edf3"
  exit 1
fi

if [[ "$RUN_NPM_BUILD" != "0" ]]; then
  echo "On-server npm builds are disabled. Build and test locally, then provide a prebuilt release archive." >&2
  exit 1
fi

if [[ -z "$PREBUILT_ASSET_TAR" || -z "$PREBUILT_ASSET_SHA256" || ! -f "$PREBUILT_ASSET_TAR" ]]; then
  echo "A prebuilt release archive and its SHA-256 are required (PREBUILT_ASSET_TAR and PREBUILT_ASSET_SHA256)." >&2
  exit 1
fi

printf '%s  %s\n' "$PREBUILT_ASSET_SHA256" "$PREBUILT_ASSET_TAR" | sha256sum --check --status || {
  echo "Prebuilt release archive checksum does not match." >&2
  exit 1
}

release_stage="$(mktemp -d "${TMPDIR:-/tmp}/kawhe-release.XXXXXX")"
trap 'rm -rf "$release_stage"' EXIT

# Only accept the two paths produced by package-release-local.sh. Reject an
# unexpected archive before tar extracts anything on the server.
while IFS= read -r entry; do
  case "$entry" in
    /*|*..*) echo "Unsafe path in prebuilt release archive: $entry" >&2; exit 1;;
    release-commit.txt|build|build/|build/*) ;;
    *) echo "Unexpected path in prebuilt release archive: $entry" >&2; exit 1;;
  esac
done < <(tar -tzf "$PREBUILT_ASSET_TAR")
tar -xzf "$PREBUILT_ASSET_TAR" -C "$release_stage" --no-same-owner
if [[ -n "$(find "$release_stage" -type l -print -quit)" || ! -s "$release_stage/build/manifest.json" || ! -s "$release_stage/release-commit.txt" ]]; then
  echo "Prebuilt release archive is incomplete or contains symlinks." >&2
  exit 1
fi

php -r '
  $base = $argv[1];
  $manifest = json_decode(file_get_contents($base."/manifest.json"), true, 512, JSON_THROW_ON_ERROR);
  if (!is_array($manifest) || !$manifest) exit(1);
  foreach ($manifest as $entry) {
    foreach (array_merge(isset($entry["file"]) ? [$entry["file"]] : [], $entry["css"] ?? [], $entry["assets"] ?? []) as $asset) {
      if (!is_string($asset) || str_contains($asset, "..") || !is_file($base."/".$asset)) exit(1);
    }
  }
' "$release_stage/build" || { echo "Prebuilt Vite manifest references missing assets." >&2; exit 1; }

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

if [[ "$(cat "$release_stage/release-commit.txt")" != "$target_commit" ]]; then
  echo "Prebuilt assets were made for a different commit; refusing deployment." >&2
  exit 1
fi

current_lock_hash="$(sha256sum composer.lock | awk '{print $1}')"
target_lock_hash="$(git show "$target_commit:composer.lock" | sha256sum | awk '{print $1}')"
if [[ "$current_lock_hash" != "$target_lock_hash" || ! -s vendor/autoload.php ]]; then
  echo "PHP dependencies changed or vendor is missing. Prepare a reviewed Linux vendor artifact off-server; Composer is disabled on this droplet." >&2
  exit 1
fi

if [[ "$current_commit" = "$target_commit" ]]; then
  echo "Already at target commit."
else
  git checkout --detach "$target_commit"
fi

# Hashed assets can be copied ahead of the manifest. Keep old hashes available
# for in-flight pages and rollback; replace the manifest only after all assets
# and PHP dependencies are installed.
mkdir -p public/build
rsync -a --exclude=manifest.json "$release_stage/build/" public/build/

if [[ "$RUN_MIGRATIONS" = "1" ]]; then
  php artisan migrate --force
fi

php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache

cp "$release_stage/build/manifest.json" public/build/manifest.json.next
mv -f public/build/manifest.json.next public/build/manifest.json

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
