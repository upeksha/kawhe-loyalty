#!/usr/bin/env bash

set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR/.."

environment="${1:-}"
target_commit="${2:-}"
case "$environment" in
  testing) app_dir=/var/www/kawhe-testing;;
  production) app_dir=/var/www/kawhe;;
  *) echo "Usage: $0 <testing|production> <full-commit-sha>" >&2; exit 1;;
esac
if [[ ! "$target_commit" =~ ^[0-9a-f]{40}$ ]]; then
  echo "An exact 40-character commit SHA is required." >&2
  exit 1
fi

archive="$PWD/output/releases/$target_commit.tar.gz"
if [[ ! -s "$archive" || ! -s "$archive.sha256" ]]; then
  echo "No tested local release package exists for $target_commit." >&2
  exit 1
fi
checksum="$(cat "$archive.sha256")"
if [[ ! "$checksum" =~ ^[0-9a-f]{64}$ ]] || [[ "$(shasum -a 256 "$archive" | awk '{print $1}')" != "$checksum" ]]; then
  echo "Local release package checksum failed." >&2
  exit 1
fi

if [[ "$environment" == production ]]; then
  testing_commit="$(ssh -o BatchMode=yes kawhe-loyalty 'git -C /var/www/kawhe-testing rev-parse HEAD')"
  if [[ "$testing_commit" != "$target_commit" ]]; then
    echo "Production requires the exact commit already deployed to testing." >&2
    exit 1
  fi
fi

remote_stage="/tmp/kawhe-release-$target_commit"
ssh -o BatchMode=yes kawhe-loyalty "mkdir -p -m 700 '$remote_stage'"
scp -q "$archive" "$SCRIPT_DIR/deploy-release.sh" "$SCRIPT_DIR/alert.sh" "kawhe-loyalty:$remote_stage/"
ssh -o BatchMode=yes kawhe-loyalty \
  "APP_DIR='$app_dir' PREBUILT_ASSET_TAR='$remote_stage/$target_commit.tar.gz' PREBUILT_ASSET_SHA256='$checksum' bash '$remote_stage/deploy-release.sh' '$target_commit'"

echo "Deployed $target_commit to $environment with locally built assets."
