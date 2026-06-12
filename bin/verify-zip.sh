#!/usr/bin/env bash
#
# verify-zip.sh — zip-content release gate.
#
# Asserts the built release zip actually contains the runtime payloads the
# shipped code requires, and none of the dev-only trees. Catches the class
# of bug that broke 1.4.0: a packaging glob silently matched nothing after
# the Credits SDK moved from /vendor to /libs, so the zip shipped without
# the SDK and Settings white-screened (Basecamp card 9989743422). The
# check-class-paths.php gate cannot catch this — it intentionally skips
# third-party namespaces like \Wbcom\Credits.
#
# Checks:
#   1. REQUIRED ENTRIES — slug-specific anchor files that must exist in the
#      zip (main file, SDK loaders, the exact class that fatal'd, etc.)
#   2. FORBIDDEN ENTRIES — dev-only trees that must never ship
#   3. SDK PARITY — every committed SDK .php file in /libs (tests excluded)
#      must appear in the zip, so the gate auto-adapts as the SDK grows
#
# Usage: bin/verify-zip.sh [path/to/zip]
#        (defaults to dist/<slug>-<version>.zip, version read like
#        build-release.sh does)
#
# Wired into BOTH packaging paths — Gruntfile `dist` task and
# bin/build-release.sh — so whichever one builds the zip, this gate runs.
#
# Exit codes:
#    0  zip verified
#   10  cannot resolve zip / version
#   40  required entry missing
#   41  forbidden entry shipped
#   42  SDK file missing from zip (parity check)

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SLUG="$(basename "$ROOT")"

case "$SLUG" in
	wp-career-board)     VAR=WCB_VERSION ;;
	wp-career-board-pro) VAR=WCBP_VERSION ;;
	*) echo "FAIL: unknown plugin slug '$SLUG'" >&2; exit 10 ;;
esac

if [ $# -ge 1 ]; then
	ZIP="$1"
else
	VERSION=$(grep -oE "define\(\s*'${VAR}',\s*'[0-9.]+'" "$ROOT/$SLUG.php" | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' | head -1)
	[ -z "$VERSION" ] && { echo "FAIL: cannot read $VAR from $SLUG.php" >&2; exit 10; }
	ZIP="$ROOT/dist/$SLUG-$VERSION.zip"
fi
[ -f "$ZIP" ] || { echo "FAIL: zip not found at $ZIP" >&2; exit 10; }

ENTRIES="$(unzip -Z1 "$ZIP")"
echo "Verifying $(basename "$ZIP") ($(printf '%s\n' "$ENTRIES" | grep -vc '/$') files)"

# ── 1. Required entries ──────────────────────────────────────────────────
REQUIRED=(
	"$SLUG/$SLUG.php"
	"$SLUG/readme.txt"
	"$SLUG/uninstall.php"
)
if [ "$SLUG" = "wp-career-board-pro" ]; then
	REQUIRED+=(
		"$SLUG/libs/wbcom-credits-sdk/wbcom-credits-sdk.php"
		"$SLUG/libs/wbcom-credits-sdk/src/Adapters/AdapterRegistry.php"
		"$SLUG/libs/wbcom-credits-sdk/src/Credits.php"
		"$SLUG/vendor/autoload.php"
		"$SLUG/admin/class-pro-admin.php"
		"$SLUG/blocks/credit-balance/block.json"
	)
else
	REQUIRED+=(
		"$SLUG/libs/edd-sl-sdk/edd-sl-sdk.php"
		"$SLUG/blocks/candidate-dashboard/block.json"
		"$SLUG/theme.json"
	)
fi

MISSING=0
for entry in "${REQUIRED[@]}"; do
	if ! printf '%s\n' "$ENTRIES" | grep -qxF "$entry"; then
		echo "FAIL: required entry missing from zip: $entry" >&2
		MISSING=1
	fi
done
[ "$MISSING" -eq 1 ] && exit 40

# ── 2. Forbidden entries ─────────────────────────────────────────────────
FORBIDDEN_REGEX=(
	"/node_modules/"
	"/\.git/"
	"^$SLUG/tests/"
	"^$SLUG/bin/"
	"^$SLUG/dist/"
	"libs/[^/]+/tests/"
)
SHIPPED_JUNK=0
for pattern in "${FORBIDDEN_REGEX[@]}"; do
	if printf '%s\n' "$ENTRIES" | grep -qE "$pattern"; then
		echo "FAIL: forbidden entry shipped (pattern: $pattern):" >&2
		printf '%s\n' "$ENTRIES" | grep -E "$pattern" | head -3 >&2
		SHIPPED_JUNK=1
	fi
done
[ "$SHIPPED_JUNK" -eq 1 ] && exit 41

# ── 3. SDK parity — every committed libs/ SDK .php must ship ─────────────
PARITY_FAIL=0
for sdk_dir in "$ROOT"/libs/*/; do
	[ -d "$sdk_dir" ] || continue
	sdk_name="$(basename "$sdk_dir")"
	SDK_FAIL=0
	while IFS= read -r src_file; do
		rel="${src_file#"$ROOT"/}"
		if ! printf '%s\n' "$ENTRIES" | grep -qxF "$SLUG/$rel"; then
			echo "FAIL: SDK file in source but missing from zip: $rel" >&2
			SDK_FAIL=1
			PARITY_FAIL=1
		fi
	done < <(find "$sdk_dir" -name '*.php' -not -path '*/tests/*')
	[ "$SDK_FAIL" -eq 0 ] && echo "  parity OK: libs/$sdk_name"
done
[ "$PARITY_FAIL" -eq 1 ] && exit 42

echo "  ✓ zip contents verified"
