#!/usr/bin/env bash

set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR/.."

if [[ "$PWD" == /var/www/* ]]; then
  echo "Release packages must be built off the droplet." >&2
  exit 1
fi

target_commit="$(git rev-parse --verify HEAD)"
if [[ -n "${1:-}" && "$(git rev-parse --verify "${1}^{commit}")" != "$target_commit" ]]; then
  echo "Build the checked-out commit, then promote that same commit to testing and live." >&2
  exit 1
fi

if ! git diff --quiet || ! git diff --cached --quiet; then
  echo "Commit tracked changes before packaging so the assets match an exact SHA." >&2
  exit 1
fi

bundled_node="/Users/isuru/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node"
if [[ -z "${KAWHE_NODE_BIN:-}" && -x "$bundled_node" ]]; then
  KAWHE_NODE_BIN="$bundled_node"
fi
KAWHE_NODE_BIN="${KAWHE_NODE_BIN:-$(command -v node)}"
export PATH="$(dirname "$KAWHE_NODE_BIN"):$PATH"
node_version="$(node -p 'process.versions.node')"
if ! node -e 'const [major, minor] = process.versions.node.split(".").map(Number); process.exit(major > 22 || (major === 22 && minor >= 12) || (major === 20 && minor >= 19) ? 0 : 1)'; then
  echo "Node $node_version is too old for this Vite build; use Node 20.19+ or 22.12+." >&2
  exit 1
fi

if command -v php >/dev/null 2>&1; then
  php -d memory_limit=512M vendor/bin/pest
elif command -v docker >/dev/null 2>&1; then
  docker run --rm --user "$(id -u):$(id -g)" \
    --mount "type=bind,src=$PWD,dst=/app" -w /app \
    "${KAWHE_TEST_IMAGE:-kawhe-php84-test:latest}" php -d memory_limit=512M vendor/bin/pest
else
  echo "Local PHP or Docker is required to run the test suite." >&2
  exit 1
fi

npm ci
# Reverb's public key is supplied by each app environment at request time.
# Keep local VITE_* settings out of assets promoted to both testing and live.
VITE_REVERB_APP_KEY= VITE_REVERB_HOST= VITE_REVERB_PORT= VITE_REVERB_SCHEME= npm run build

if [[ ! -s public/build/manifest.json ]]; then
  echo "Vite did not produce public/build/manifest.json." >&2
  exit 1
fi

release_dir="$PWD/output/releases"
mkdir -p "$release_dir"
stage="$(mktemp -d "${TMPDIR:-/tmp}/kawhe-package.XXXXXX")"
trap 'rm -rf "$stage"' EXIT
printf '%s\n' "$target_commit" > "$stage/release-commit.txt"
cp -R public/build "$stage/build"

archive="$release_dir/$target_commit.tar.gz"
tar -C "$stage" -czf "$archive.tmp" release-commit.txt build
mv "$archive.tmp" "$archive"
checksum="$(shasum -a 256 "$archive" | awk '{print $1}')"
printf '%s\n' "$checksum" > "$archive.sha256"

echo "Local tests and frontend build passed."
echo "Commit: $target_commit"
echo "Archive: $archive"
echo "SHA-256: $checksum"
