#!/usr/bin/env bash
#
# Build three zips - two shipped, one internal:
#   1. wb-ads-rotator-with-split-test-<version>.zip   RELEASE ASSET (free only)
#   2. wb-ad-manager-pro-<version>.zip                RELEASE ASSET (pro only)
#   3. wb-ad-manager-combo-<version>.zip              QA ONLY - never attached
#                                                     to a GitHub release
#
# The combo is a convenience for testing the matched pair in one install. No
# customer channel serves it: free ships from the store and wp.org, Pro from
# EDD. It rode along on the 3.1.0 release by accident, which is also how it
# ended up advertising its version twice ("3.1.0+3.1.0"). Build it, test with
# it, do not upload it.
#
# All respect the free plugin's .distignore. The pro folder adds its own
# exclusions on top, defined once in PRO_EXCLUDES and shared by the
# standalone and combo builds.
#
# Packaging is gated on a green browser smoke report — see docs/qa/.

set -euo pipefail

# --- flags ---------------------------------------------------------
SKIP_BROWSER_SMOKE=0
for arg in "$@"; do
	case "$arg" in
		--skip-browser-smoke) SKIP_BROWSER_SMOKE=1 ;;
		-h|--help)
			echo "Usage: $0 [--skip-browser-smoke]"
			echo "  --skip-browser-smoke  Package without a green smoke report."
			echo "                        Internal builds only, never a customer release."
			exit 0
			;;
		*) echo "Unknown option: $arg" >&2; exit 2 ;;
	esac
done

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FREE_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
PLUGINS_DIR="$(cd "$FREE_DIR/.." && pwd)"
PRO_DIR="$PLUGINS_DIR/wb-ad-manager-pro"
BUILD_DIR="$FREE_DIR/build"
DIST_DIR="$FREE_DIR/dist"

# Extract versions from plugin headers.
grep_version() {
	local file="$1"
	grep -i "^ \* Version:" "$file" | head -1 | sed 's/.*Version:[[:space:]]*//;s/[[:space:]]*$//'
}

FREE_VERSION="$(grep_version "$FREE_DIR/wb-ads-rotator-with-split-test.php")"
PRO_VERSION=""
if [ -f "$PRO_DIR/wb-ad-manager-pro.php" ]; then
	PRO_VERSION="$(grep_version "$PRO_DIR/wb-ad-manager-pro.php")"
fi

echo "Free version: $FREE_VERSION"
echo "Pro version:  ${PRO_VERSION:-<not found>}"

# ------------------------------------------------------------------
# Browser-smoke gate — refuses to package without a fresh green report.
# No release ships unless a run of docs/qa/AGENT_SMOKE_RUNBOOK.md reported
# zero failures and zero debug.log entries. Version-matched so yesterday's
# green report cannot wave through today's code.
# ------------------------------------------------------------------
SMOKE_REPORT="$FREE_DIR/docs/qa/.last-smoke-pass.json"
if [ "$SKIP_BROWSER_SMOKE" -eq 1 ]; then
	echo "WARN: browser smoke gate skipped (--skip-browser-smoke). Not for customer releases."
elif [ ! -f "$SMOKE_REPORT" ]; then
	echo "FAIL: no browser smoke report at $SMOKE_REPORT" >&2
	echo "      Run /wp-plugin-smoke combo to generate it." >&2
	echo "      Emergency only: rerun with --skip-browser-smoke." >&2
	exit 30
else
	REPORT_VERSION="$(grep -oE '"release_version"[[:space:]]*:[[:space:]]*"[^"]+"' "$SMOKE_REPORT" | head -1 | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' || true)"
	if [ "$REPORT_VERSION" != "$FREE_VERSION" ]; then
		echo "FAIL: smoke report version ($REPORT_VERSION) does not match release version ($FREE_VERSION)" >&2
		echo "      Re-run /wp-plugin-smoke combo against HEAD before packaging." >&2
		exit 30
	fi
	if grep -qE '"failures"[[:space:]]*:[[:space:]]*\[[[:space:]]*\{' "$SMOKE_REPORT"; then
		echo "FAIL: smoke report has failures. Fix them before packaging." >&2
		exit 30
	fi
	if grep -qE '"debug_log_issues"[[:space:]]*:[[:space:]]*\[[[:space:]]*\{' "$SMOKE_REPORT"; then
		echo "FAIL: smoke report recorded debug.log entries during the walk. Fix before packaging." >&2
		exit 30
	fi
	echo "    smoke report OK ($REPORT_VERSION)"
fi

rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR/free" "$BUILD_DIR/pro" "$BUILD_DIR/combo" "$DIST_DIR"

# rsync exclude list driven by .distignore.
build_exclude_args() {
	local distignore="$1"
	local args=()
	while IFS= read -r line; do
		# Skip blanks and comments.
		[ -z "$line" ] && continue
		[[ "$line" =~ ^# ]] && continue
		args+=(--exclude="$line")
	done < "$distignore"
	printf '%s\n' "${args[@]}"
}

# Guard: every literal assets/... path referenced from shipped PHP must
# exist in the build payload. Catches .distignore patterns that silently
# strip runtime files (e.g. an unanchored "vendor" once removed
# assets/vendor/lucide.min.js and 404'd every frontend icon).
verify_runtime_assets() {
	local target="$1"
	local missing=0
	local ref
	# Two exclusions, both learned the hard way when this guard was first
	# pointed at the Pro payload:
	#
	#   vendor/  — bundled dependencies resolve assets against their OWN
	#              base path, not the plugin root. The Credits SDK's
	#              assets/js/checkout.js lives at libs/wbcom-credits-sdk/
	#              and is perfectly present; looking for it at the root
	#              reports a phantom.
	#   comments — a docblock describing an enqueue that was REMOVED is not
	#              a runtime reference. Skip lines that are comment lines.
	while IFS= read -r ref; do
		if [ ! -f "$target/$ref" ]; then
			echo "ERROR: PHP references '$ref' but it is missing from the build payload." >&2
			missing=1
		fi
	done < <(grep -rhE "assets/(css|js|vendor|images)/[A-Za-z0-9@_./-]+\.(css|js|png|svg|gif|woff2?)" \
		--include='*.php' --exclude-dir=vendor --exclude-dir=libs "$target" \
		| grep -vE "^[[:space:]]*(\*|//|#)" \
		| grep -oE "assets/(css|js|vendor|images)/[A-Za-z0-9@_./-]+\.(css|js|png|svg|gif|woff2?)" \
		| sort -u)
	if [ "$missing" -ne 0 ]; then
		echo "Build aborted: runtime assets stripped from the zip (check .distignore anchoring)." >&2
		exit 1
	fi
}

# Guard: no internal artifact may reach a customer zip. .distignore and
# PRO_EXCLUDES are allow-by-omission - anything nobody thought to list ships.
# That is exactly how AUDIT-VERDICT.md went out in 3.1.0. This asserts the
# payload after the copy, so a stray file fails the build instead of a QA card.
#
# Customer zips ship readme.txt; *.md is always internal (docs, audits,
# changelogs, bundled-dependency READMEs).
verify_no_internal_artifacts() {
	local target="$1"
	local label="$2"
	local found=0
	local hit

	while IFS= read -r hit; do
		echo "ERROR: [$label] internal artifact in payload: ${hit#$target/}" >&2
		found=1
	done < <(find "$target" \
		\( -name '*.md' \
		   -o -name '.contract-audit-baseline.json' \
		   -o -name 'AUDIT-VERDICT.md' \
		   -o -name '.phpcs*' \
		   -o -name 'phpcs.xml*' \
		   -o -name 'phpstan*' \
		   -o -name 'composer.json' \
		   -o -name 'composer.lock' \
		   -o -name '.distignore' \
		   -o -name '.gitignore' \
		   -o -name '.gitattributes' \
		   -o -name '.DS_Store' \
		   -o -name '.eslint*' \
		   -o -name '.stylelintrc*' \
		   -o -name '.pa11yci' \
		   -o -name '.gitkeep' \
		   -o -name '.bundled-from' \
		   -o -path "$target/scripts" \
		\) -print | sort)

	if [ "$found" -ne 0 ]; then
		echo "Build aborted: dev/QA artifacts would ship to customers." >&2
		echo "  Add them to .distignore (free) or PRO_EXCLUDES (pro) and rebuild." >&2
		exit 1
	fi
}

# ------------------------------------------------------------------
# 1. Free-only zip
# ------------------------------------------------------------------
FREE_TARGET="$BUILD_DIR/free/wb-ads-rotator-with-split-test"
mkdir -p "$FREE_TARGET"

# Read exclude list into an array. Using a while-read loop instead of
# `mapfile -t` so the script also works on bash 3.2 (macOS default).
FREE_EXCLUDES=()
while IFS= read -r line; do
	FREE_EXCLUDES+=("$line")
done < <(build_exclude_args "$FREE_DIR/.distignore")

rsync -a "${FREE_EXCLUDES[@]}" "$FREE_DIR/" "$FREE_TARGET/"

verify_runtime_assets "$FREE_TARGET"
verify_no_internal_artifacts "$FREE_TARGET" "free"

FREE_ZIP="$DIST_DIR/wb-ads-rotator-with-split-test-${FREE_VERSION}.zip"
rm -f "$FREE_ZIP"
( cd "$BUILD_DIR/free" && zip -rq "$FREE_ZIP" "wb-ads-rotator-with-split-test" )

# ------------------------------------------------------------------
# Pro rsync rules — defined once, used by both the standalone Pro zip
# and the combo. This previously lived inline in the combo branch only,
# so any future standalone build would have drifted from it.
#
# vendor/ used to need surgery: the bundled Credits SDK lived inside it and
# MUST ship, while everything else under vendor/ is composer dev tooling
# (phpstan alone is 47MB). Keeping both meant include-before-exclude rsync
# rules, and getting them slightly wrong is how the combo went from 2.9MB to
# 18MB of static analysers, and how the 3.1.0 zip reached customers carrying
# the dev tree.
#
# Pro now bundles the SDK at libs/wbcom-credits-sdk (portfolio standard), so
# vendor/ is dev-only and excluded outright. No ordering to get wrong, and no
# dev dependency can ship by sitting next to something that must.
# ------------------------------------------------------------------
PRO_EXCLUDES=(
	--exclude=/vendor
	--exclude=/libs/wbcom-credits-sdk/docs
	--exclude=.git --exclude=.github --exclude=node_modules
	--exclude=tests --exclude=dist --exclude=docs --exclude=marketing
	--exclude=/bin --exclude=/plan --exclude=/audit
	--exclude=.contract-audit-baseline.json --exclude=.phpcs-cache
	--exclude=.distignore --exclude=.editorconfig --exclude=.gitattributes
	--exclude=.gitignore --exclude=.phpunit.result.cache
	--exclude=composer.json --exclude=composer.lock
	--exclude=package.json --exclude=package-lock.json --exclude=Gruntfile.js
	--exclude=phpunit.xml --exclude=phpunit.xml.dist
	--exclude=phpcs.xml --exclude=phpcs.xml.dist
	# Dot-prefixed variants are separate filenames, not matched by the rules
	# above. Pro's own .distignore excludes .phpcs.xml.dist, so the standalone
	# Pro zip was clean while the combo - which builds its Pro payload from
	# this list instead - shipped it.
	--exclude=.phpcs.xml.dist --exclude=.phpstan.neon --exclude=.phpunit.result.cache
	--exclude=phpstan.neon --exclude=phpstan-baseline.neon --exclude=phpstan-bootstrap.php
	--exclude='*.md' --exclude=CLAUDE.md --exclude=sales-page.html
	# Front-end tooling configs and the release script: dev only. The 3.1.1
	# zip shipped all of these.
	--exclude=/scripts --exclude=.eslintrc.json --exclude=.eslintignore
	--exclude=.stylelintrc.json --exclude=.pa11yci --exclude=.gitkeep
	--exclude=.bundled-from
)

# ------------------------------------------------------------------
# 2. Pro standalone zip (only if pro is present)
#
# Pro-only customers and the wbcom-services dist need an artifact that
# is not the combo. 3.1.0 shipped without one.
# ------------------------------------------------------------------
if [ -n "$PRO_VERSION" ]; then
	PRO_TARGET="$BUILD_DIR/pro/wb-ad-manager-pro"
	mkdir -p "$PRO_TARGET"

	rsync -a "${PRO_EXCLUDES[@]}" "$PRO_DIR/" "$PRO_TARGET/"

	verify_runtime_assets "$PRO_TARGET"
	verify_no_internal_artifacts "$PRO_TARGET" "pro"

	# The bundled Credits SDK is a runtime dependency, not a dev one.
	# Pro fatals on activation without it, and .distignore-style vendor
	# exclusions have stripped it before.
	if [ ! -f "$PRO_TARGET/libs/wbcom-credits-sdk/wbcom-credits-sdk.php" ]; then
		echo "ERROR: bundled Credits SDK missing from the Pro payload." >&2
		echo "       Pro cannot boot without libs/wbcom-credits-sdk/." >&2
		exit 1
	fi

	# The composer tree must never reach a customer. Cheap to assert, and the
	# thing that actually went wrong in 3.1.0.
	if [ -d "$PRO_TARGET/vendor" ]; then
		echo "ERROR: vendor/ present in the Pro payload — dev toolchain would ship." >&2
		exit 1
	fi

	PRO_ZIP="$DIST_DIR/wb-ad-manager-pro-${PRO_VERSION}.zip"
	rm -f "$PRO_ZIP"
	( cd "$BUILD_DIR/pro" && zip -rq "$PRO_ZIP" "wb-ad-manager-pro" )
fi

# ------------------------------------------------------------------
# 3. Combo zip - QA only, not a release asset (only if pro is present)
# ------------------------------------------------------------------
if [ -n "$PRO_VERSION" ]; then
	COMBO_FREE_TARGET="$BUILD_DIR/combo/wb-ads-rotator-with-split-test"
	COMBO_PRO_TARGET="$BUILD_DIR/combo/wb-ad-manager-pro"
	mkdir -p "$COMBO_FREE_TARGET" "$COMBO_PRO_TARGET"

	rsync -a "${FREE_EXCLUDES[@]}" "$FREE_DIR/" "$COMBO_FREE_TARGET/"
	rsync -a "${PRO_EXCLUDES[@]}" "$PRO_DIR/" "$COMBO_PRO_TARGET/"

	verify_no_internal_artifacts "$COMBO_FREE_TARGET" "combo/free"
	verify_no_internal_artifacts "$COMBO_PRO_TARGET" "combo/pro"

	# One version in the name when free and pro are in lockstep (the normal
	# case); only a skewed pair gets the "<free>+<pro>" form.
	if [ "$FREE_VERSION" = "$PRO_VERSION" ]; then
		COMBO_ZIP="$DIST_DIR/wb-ad-manager-combo-${FREE_VERSION}.zip"
	else
		COMBO_ZIP="$DIST_DIR/wb-ad-manager-combo-${FREE_VERSION}+${PRO_VERSION}.zip"
	fi
	rm -f "$COMBO_ZIP"
	( cd "$BUILD_DIR/combo" && zip -rq "$COMBO_ZIP" "wb-ads-rotator-with-split-test" "wb-ad-manager-pro" )
fi

# ------------------------------------------------------------------
# Report
# ------------------------------------------------------------------
echo
echo "== Release assets =="
for z in "$FREE_ZIP" "${PRO_ZIP:-}"; do
	[ -z "$z" ] && continue
	[ ! -f "$z" ] && continue
	size=$(wc -c < "$z")
	sha=$(shasum -a 256 "$z" | awk '{print $1}')
	printf "  %s\n    size: %s bytes\n    sha256: %s\n" "$z" "$size" "$sha"
done

if [ -n "${COMBO_ZIP:-}" ] && [ -f "$COMBO_ZIP" ]; then
	echo
	echo "== QA only - do NOT attach to a release =="
	printf "  %s\n    size: %s bytes\n" "$COMBO_ZIP" "$(wc -c < "$COMBO_ZIP")"
fi
