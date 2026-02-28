#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck disable=SC1091
source "$SCRIPT_DIR/lib.sh"

level="${1:-INFO}"
title="${2:-Kawhe ops alert}"
body="${3:-}"

alert "$level" "$title" "$body"
