#!/usr/bin/env bash
# Delegates to PRO's bin/check-retired-vocab-in-i18n.sh — one guard, one
# allowlist, shared by both plugins (same pattern as `composer arch-checks`
# delegating to PRO's bin/architecture-checks.sh). See that script for the
# rule, the allowlist, and why each entry is there.
#
# Usage:
#     bash bin/check-retired-vocab-in-i18n.sh <dir> [<dir> ...]

set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PRO_GUARD="$SCRIPT_DIR/../../wb-ad-manager-pro/bin/check-retired-vocab-in-i18n.sh"

if [ ! -f "$PRO_GUARD" ]; then
    echo "ERROR: Cannot locate PRO's check-retired-vocab-in-i18n.sh. Expected sibling of $SCRIPT_DIR/../.. named 'wb-ad-manager-pro'." >&2
    exit 2
fi

exec bash "$PRO_GUARD" "$@"
