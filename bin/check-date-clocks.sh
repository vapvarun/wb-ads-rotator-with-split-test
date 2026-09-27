#!/usr/bin/env bash
# Delegates to PRO's bin/check-date-clocks.sh - one date rule, shared by
# both plugins (same pattern as check-retired-vocab-in-i18n.sh). See that
# script and docs/standards/dates.md.
#
# Usage: bash bin/check-date-clocks.sh <dir> [<dir> ...]

set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PRO_GUARD="$SCRIPT_DIR/../../wb-ad-manager-pro/bin/check-date-clocks.sh"

if [ ! -f "$PRO_GUARD" ]; then
	echo "ERROR: Cannot locate PRO's check-date-clocks.sh. Expected sibling of $SCRIPT_DIR/../.. named 'wb-ad-manager-pro'." >&2
	exit 2
fi

exec bash "$PRO_GUARD" "$@"
