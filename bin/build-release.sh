#!/usr/bin/env bash
#
# build-release.sh — package the plugin for release.
#
# Pipeline:
#   1. Read version from main plugin file
#   2. Lockstep check (Pro only — version must match Free)
#   3. Working-tree-clean check (skipable with --allow-dirty)
#   4. composer ci pipeline (skipable with --skip-ci, NOT for customer releases)
#   5. Smoke-report gate (skipable with --skip-browser-smoke, NOT for customer releases)
#   6. Build clean staging dir
#   7. composer install --no-dev (for runtime-only vendor when require exists)
#   8. rsync source applying .distignore
#   9. Replace vendor/ with the no-dev install
#  10. Zip
#
# Exit codes:
#    0  built ok
#   10  cannot read VERSION
#   11  lockstep mismatch (Pro)
#   12  uncommitted changes
#   13  composer ci failed
#   30  smoke gate failed
#    2  unknown flag

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

SLUG="$(basename "$ROOT")"
OUTPUT_DIR="$ROOT/dist"
SKIP_BROWSER_SMOKE=0
SKIP_CI=0
ALLOW_DIRTY=0

while [[ $# -gt 0 ]]; do
	case "$1" in
		--output) OUTPUT_DIR="$2"; shift 2 ;;
		--skip-browser-smoke) SKIP_BROWSER_SMOKE=1; shift ;;
		--skip-ci) SKIP_CI=1; shift ;;
		--allow-dirty) ALLOW_DIRTY=1; shift ;;
		-h|--help)
			echo "Usage: $0 [--output DIR] [--skip-browser-smoke] [--skip-ci] [--allow-dirty]"
			exit 0
			;;
		*) echo "Unknown flag: $1" >&2; exit 2 ;;
	esac
done

# 1. Read version
case "$SLUG" in
	wp-career-board)     VAR=WCB_VERSION ;;
	wp-career-board-pro) VAR=WCBP_VERSION ;;
	*) echo "FAIL: unknown plugin slug '$SLUG'" >&2; exit 10 ;;
esac
VERSION=$(grep -oE "define\(\s*'${VAR}',\s*'[0-9.]+'" "$ROOT/$SLUG.php" | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' | head -1)
[ -z "$VERSION" ] && { echo "FAIL: cannot read $VAR from $SLUG.php" >&2; exit 10; }
echo "Building $SLUG v$VERSION"

# 2. Lockstep (Pro only)
if [ "$SLUG" = "wp-career-board-pro" ]; then
	FREE_FILE="$ROOT/../wp-career-board/wp-career-board.php"
	[ ! -f "$FREE_FILE" ] && { echo "FAIL: cannot find Free plugin at $FREE_FILE for lockstep check" >&2; exit 11; }
	FREE_VERSION=$(grep -oE "define\(\s*'WCB_VERSION',\s*'[0-9.]+'" "$FREE_FILE" | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' | head -1)
	if [ "$VERSION" != "$FREE_VERSION" ]; then
		echo "FAIL: lockstep mismatch — Pro $VERSION but Free $FREE_VERSION" >&2
		exit 11
	fi
	echo "  lockstep OK ($FREE_VERSION == $VERSION)"
fi

# 3. Working tree clean
if [ "$ALLOW_DIRTY" -eq 0 ] && ! git -C "$ROOT" diff --quiet; then
	echo "FAIL: working tree has uncommitted changes. Use --allow-dirty to bypass." >&2
	exit 12
fi

# 4. composer ci pipeline
if [ "$SKIP_CI" -eq 1 ]; then
	echo "WARN: composer ci skipped (--skip-ci). Not for customer releases."
else
	composer ci:no-journeys || { echo "FAIL: composer ci:no-journeys failed" >&2; exit 13; }
fi

# 5. Smoke-report gate (per docs/qa/SCAFFOLDING.md paste-block)
SMOKE_REPORT="$ROOT/docs/qa/.last-smoke-pass.json"
if [ "$SKIP_BROWSER_SMOKE" -eq 1 ]; then
	echo "WARN: browser smoke gate skipped (--skip-browser-smoke). Not for customer releases."
elif [ ! -f "$SMOKE_REPORT" ]; then
	echo "FAIL: no smoke report at $SMOKE_REPORT" >&2
	echo "      Run the /wp-plugin-smoke skill first to generate it." >&2
	echo "      Emergency only: rerun with --skip-browser-smoke." >&2
	exit 30
else
	REPORT_VERSION="$(grep -oE '"release_version"[[:space:]]*:[[:space:]]*"[^"]+"' "$SMOKE_REPORT" | head -1 | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' || true)"
	if [ "$REPORT_VERSION" != "$VERSION" ]; then
		echo "FAIL: smoke report version ($REPORT_VERSION) doesn't match release version ($VERSION)" >&2
		echo "      Re-run /wp-plugin-smoke against HEAD before packaging." >&2
		exit 30
	fi
	if grep -qE '"failures"[[:space:]]*:[[:space:]]*\[[[:space:]]*\{' "$SMOKE_REPORT"; then
		echo "FAIL: smoke report has failures. Fix them before packaging." >&2
		exit 30
	fi
	if grep -qE '"debug_log_issues"[[:space:]]*:[[:space:]]*\[[[:space:]]*\{' "$SMOKE_REPORT"; then
		echo "FAIL: smoke report recorded debug.log entries. Fix before packaging." >&2
		exit 30
	fi
	echo "  smoke report OK ($REPORT_VERSION, no failures, no debug_log_issues)"
fi

# 6. Clean staging dir
mkdir -p "$OUTPUT_DIR"
STAGE="$OUTPUT_DIR/$SLUG"
rm -rf "$STAGE"
mkdir -p "$STAGE"

# 7. composer install --no-dev (only when require has runtime deps)
BUILD_VENDOR=""
if [ -f "$ROOT/composer.json" ] && python3 -c "import json,sys; d=json.load(open('$ROOT/composer.json')); sys.exit(0 if d.get('require') else 1)" 2>/dev/null; then
	echo "  installing runtime-only vendor (composer install --no-dev)"
	BUILD_VENDOR="$(mktemp -d -t "${SLUG}-vendor-XXXXXX")"
	cp "$ROOT/composer.json" "$BUILD_VENDOR/"
	[ -f "$ROOT/composer.lock" ] && cp "$ROOT/composer.lock" "$BUILD_VENDOR/"
	(cd "$BUILD_VENDOR" && composer install --no-dev --optimize-autoloader --quiet --no-interaction --no-scripts)
fi

# 8. rsync source with .distignore exclusions
RSYNC_EXCLUDES=()
if [ -f "$ROOT/.distignore" ]; then
	while IFS= read -r line; do
		line="${line%$'\r'}"
		[ -z "$line" ] && continue
		case "$line" in '#'*) continue ;; esac
		RSYNC_EXCLUDES+=("--exclude=$line")
	done < "$ROOT/.distignore"
fi

rsync -a "${RSYNC_EXCLUDES[@]}" "$ROOT/" "$STAGE/"

# 9. Replace vendor/ with the no-dev install, preserving committed SDKs
#    Some runtime SDKs (wbcom-credits-sdk, edd-sl-sdk) are committed to
#    vendor/ directly rather than declared in composer.json — composer
#    install --no-dev would not include them. Snapshot them before swap
#    and restore after.
if [ -n "$BUILD_VENDOR" ] && [ -d "$BUILD_VENDOR/vendor" ]; then
	# Snapshot any committed SDK dirs that are NOT in composer.json
	COMMITTED_VENDOR_TMP="$(mktemp -d -t "${SLUG}-committed-vendor-XXXXXX")"
	for sdk in wbcom-credits-sdk edd-sl-sdk; do
		if [ -d "$STAGE/vendor/$sdk" ]; then
			cp -R "$STAGE/vendor/$sdk" "$COMMITTED_VENDOR_TMP/"
			echo "  preserving committed SDK: vendor/$sdk"
		fi
	done

	rm -rf "$STAGE/vendor"
	cp -R "$BUILD_VENDOR/vendor" "$STAGE/vendor"

	# Restore committed SDKs
	for sdk_path in "$COMMITTED_VENDOR_TMP"/*; do
		[ -d "$sdk_path" ] || continue
		cp -R "$sdk_path" "$STAGE/vendor/"
	done
	rm -rf "$COMMITTED_VENDOR_TMP"
	rm -rf "$BUILD_VENDOR"
	echo "  vendor pruned to runtime-only (committed SDKs preserved)"
fi

# 10. Zip
ZIP="$OUTPUT_DIR/$SLUG-$VERSION.zip"
rm -f "$ZIP"
(cd "$OUTPUT_DIR" && zip -rq "$ZIP" "$SLUG")

echo
echo "  ✓ Built: $ZIP ($(du -h "$ZIP" | cut -f1))"
echo "  Source: $STAGE"
