#!/usr/bin/env bash
#
# WordPress.org gate: run Plugin Check on exactly the files that ship
# (git archive HEAD, then .distignore, the same tree scripts/build-release.mjs
# zips) and fail on any ERROR. Warnings are printed but do not fail: the
# directory blocks on errors, and the legacy WPCS style warnings are tracked
# separately.
#
# Env overrides: WBAM_GATE_WP_PHP, WBAM_GATE_WP_CLI, WBAM_GATE_WP_SITE (any
# WordPress with the plugin-check plugin; this tree is checked by path).
#
# libs/ is skipped: it holds MaxMind's own maxmind-db-reader (Apache-2.0),
# shipped unmodified, whose exception messages and file reads Plugin Check
# reports as errors. Findings there belong upstream, as with Pro's libs/.
#
# Exit 0 = no errors. 1 = errors found. 2 = could not run.

set -uo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SLUG="wb-ads-rotator-with-split-test"

WP_PHP="${WBAM_GATE_WP_PHP:-$(ls /Applications/Local.app/Contents/Resources/extraResources/lightning-services/php-*/bin/*/bin/php 2>/dev/null | sort | tail -1)}"
WP_CLI="${WBAM_GATE_WP_CLI:-/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/wp-cli.phar}"
WP_SITE="${WBAM_GATE_WP_SITE:-}"
if [ -z "$WP_SITE" ]; then
	for cand in "$HOME/Local Sites"/*/app/public; do
		[ -d "$cand/wp-content/plugins/plugin-check" ] && [ -f "$cand/wp-load.php" ] && { WP_SITE="$cand"; break; }
	done
fi
if [ -z "$WP_PHP" ] || [ ! -f "$WP_CLI" ] || [ -z "$WP_SITE" ]; then
	echo "Plugin Check gate could not run: set WBAM_GATE_WP_PHP / WBAM_GATE_WP_CLI / WBAM_GATE_WP_SITE."
	exit 2
fi

# Real path: macOS mktemp returns /var/..., which Plugin Check trims wrongly.
STAGE="$(cd "$(mktemp -d)" && pwd -P)"
trap 'rm -rf "$STAGE"' EXIT
mkdir -p "$STAGE/archive" "$STAGE/$SLUG"
git -C "$ROOT_DIR" archive --format=tar HEAD | tar -x -C "$STAGE/archive"
rsync -a --exclude-from="$ROOT_DIR/.distignore" "$STAGE/archive/" "$STAGE/$SLUG/"

"$WP_PHP" "$WP_CLI" plugin activate plugin-check --path="$WP_SITE" >/dev/null 2>&1
"$WP_PHP" "$WP_CLI" plugin check "$STAGE/$SLUG" --path="$WP_SITE" --format=csv --exclude-directories=libs > "$STAGE/pcp.csv" 2>/dev/null

ERRORS="$(grep -c ',ERROR,' "$STAGE/pcp.csv" || true)"
WARNINGS="$(grep -c ',WARNING,' "$STAGE/pcp.csv" || true)"
if [ "${ERRORS:-0}" -gt 0 ]; then
	echo "Plugin Check: ${ERRORS} error(s), ${WARNINGS} warning(s) on the shipped files:"
	grep ',ERROR,' "$STAGE/pcp.csv" | sed 's/^/  /'
	exit 1
fi
echo "Plugin Check: 0 errors (${WARNINGS} warning(s)) on the shipped files."
