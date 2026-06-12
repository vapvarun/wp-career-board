#!/usr/bin/env bash
#
# package-dist.sh — THE single packaging routine. One contract: .distignore.
#
# Builds dist/<slug>-<version>.zip by shipping EVERYTHING except the
# .distignore denylist (rsync), pruning a shipped vendor/ to runtime-only
# (composer --no-dev), then gating the result with verify-zip.sh.
#
# This is the ONLY place that decides what goes in the zip. Both `grunt dist`
# and bin/build-release.sh call it, so the dev build and the customer release
# are byte-for-byte identical. Adding a new folder needs ZERO build-config
# changes — it ships unless .distignore excludes it.
#
# Replaces the old Gruntfile copy:dist allowlist, whose drift from reality
# caused the missing-file bugs (templates/ dropped, stale build/ shipped): an
# allowlist needs a human to remember every new folder; a denylist does not.
#
# Usage: bin/package-dist.sh [--output DIR]
# Exit:  0 ok | 2 bad flag | 10 cannot resolve version | 40 verify-zip failed

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"
SLUG="$(basename "$ROOT")"
OUTPUT_DIR="$ROOT/dist"

while [[ $# -gt 0 ]]; do
	case "$1" in
		--output) OUTPUT_DIR="$2"; shift 2 ;;
		-h|--help) echo "Usage: $0 [--output DIR]"; exit 0 ;;
		*) echo "Unknown flag: $1" >&2; exit 2 ;;
	esac
done

# Version — read the plugin's own constant.
case "$SLUG" in
	wp-career-board)     VAR=WCB_VERSION ;;
	wp-career-board-pro) VAR=WCBP_VERSION ;;
	*) echo "FAIL: unknown plugin slug '$SLUG'" >&2; exit 10 ;;
esac
VERSION=$(grep -oE "define\(\s*'${VAR}',\s*'[0-9.]+'" "$ROOT/$SLUG.php" | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' | head -1)
[ -z "$VERSION" ] && { echo "FAIL: cannot read $VAR from $SLUG.php" >&2; exit 10; }
echo "Packaging $SLUG v$VERSION"

# Clean staging dir.
mkdir -p "$OUTPUT_DIR"
STAGE="$OUTPUT_DIR/$SLUG"
rm -rf "$STAGE"
mkdir -p "$STAGE"

# Does this plugin ship vendor/?  .distignore is the contract: if it excludes
# /vendor the plugin ships none (e.g. Free); otherwise vendor ships and its dev
# dependencies must be pruned to runtime-only.
SHIPS_VENDOR=1
grep -qxE '/vendor' "$ROOT/.distignore" 2>/dev/null && SHIPS_VENDOR=0

BUILD_VENDOR=""
if [ "$SHIPS_VENDOR" -eq 1 ] && [ -f "$ROOT/composer.json" ] && \
   python3 -c "import json,sys; sys.exit(0 if json.load(open('$ROOT/composer.json')).get('require') else 1)" 2>/dev/null; then
	echo "  installing runtime-only vendor (composer install --no-dev)"
	BUILD_VENDOR="$(mktemp -d -t "${SLUG}-vendor-XXXXXX")"
	cp "$ROOT/composer.json" "$BUILD_VENDOR/"
	[ -f "$ROOT/composer.lock" ] && cp "$ROOT/composer.lock" "$BUILD_VENDOR/"
	(cd "$BUILD_VENDOR" && composer install --no-dev --optimize-autoloader --quiet --no-interaction --no-scripts)
fi

# Stage source — ship everything EXCEPT the .distignore denylist. When vendor is
# rebuilt below, skip copying the working (dev) vendor here.
RSYNC_EXCLUDES=()
if [ -f "$ROOT/.distignore" ]; then
	while IFS= read -r line; do
		line="${line%$'\r'}"
		[ -z "$line" ] && continue
		case "$line" in '#'*) continue ;; esac
		RSYNC_EXCLUDES+=("--exclude=$line")
	done < "$ROOT/.distignore"
fi
[ -n "$BUILD_VENDOR" ] && RSYNC_EXCLUDES+=("--exclude=/vendor")

rsync -a "${RSYNC_EXCLUDES[@]}" "$ROOT/" "$STAGE/"

# Drop in the runtime-only vendor — through the SAME .distignore excludes, so
# library cruft (*.md, tests/, composer.json …) is stripped uniformly.
if [ -n "$BUILD_VENDOR" ] && [ -d "$BUILD_VENDOR/vendor" ]; then
	rsync -a "${RSYNC_EXCLUDES[@]}" "$BUILD_VENDOR/vendor/" "$STAGE/vendor/"
	rm -rf "$BUILD_VENDOR"
	echo "  vendor pruned to runtime-only"
fi

# Zip.
ZIP="$OUTPUT_DIR/$SLUG-$VERSION.zip"
rm -f "$ZIP"
(cd "$OUTPUT_DIR" && zip -rq "$ZIP" "$SLUG")

# Gate — required runtime payloads present, libraries complete, dev junk absent.
bash "$ROOT/bin/verify-zip.sh" "$ZIP" || { echo "FAIL: zip content verification failed" >&2; exit 40; }

echo "  ✓ Built: $ZIP ($(du -h "$ZIP" | cut -f1))"
