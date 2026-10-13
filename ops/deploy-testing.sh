#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
export APP_DIR="${APP_DIR:-/var/www/kawhe-testing}"

export OPS_ENV_FILE="${OPS_ENV_FILE:-/etc/kawhe-testing-ops.env}"

"$SCRIPT_DIR/deploy-release.sh" "${1:?Usage: deploy-testing.sh <git-ref-or-commit>}"
