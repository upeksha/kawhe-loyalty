#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="${APP_DIR:-/var/www/kawhe-testing}"

"$SCRIPT_DIR/deploy-release.sh" "${1:?Usage: deploy-testing.sh <git-ref-or-commit>}"
