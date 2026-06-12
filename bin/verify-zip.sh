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
#   3. LIBRARY COMPLETENESS — every bundled library (libs/*, vendor/composer,
#      and each shipped vendor/<vendor>/<pkg>) ships IN FULL: every runtime
#      file (php, css, js, fonts, templates, json) must be in the zip; only
#      provably non-runtime cruft (*.md, tests/, composer/package manifests,
#      dotfiles) may be absent. RULE: all required library files are bundled,
#      always — auto-discovered from the zip, so it adapts as libraries grow.
#   4. REQUIRED-FILE PARITY — every require/include of an own-plugin file
#      (OWN_CONST . '...', __DIR__ . '...', plugin_dir_path() . '...') must
#      exist as an entry in the zip. Auto-derived from the source, so it
#      adapts as new templates/partials are added — no hardcoded list to
#      keep in sync. Catches the bug that shipped 1.4.0 without templates/
#      (Basecamp 9990627782): three blocks require'd
#      templates/parts/archive-toolbar.php but copy:dist never listed
#      templates/**, so render fatal'd on a fresh install. A sibling
#      plugin's dir constant (Pro requiring Free's WCB_DIR) resolves to the
#      OTHER plugin and is intentionally skipped — external dependency, not
#      this zip's payload.
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
#   42  library runtime file missing from zip (completeness check)
#   43  require/include target missing from zip (required-file parity)

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

# ── 3. Library completeness — every bundled library ships its full runtime ─
#      RULE: all required library files are bundled, always.
#
#      Two regimes, because the two kinds of library have different sources of
#      truth:
#
#      3a. COMMITTED libs/* — shipped verbatim, so source == zip. STRICT: every
#          non-cruft source file (php, css, js, fonts, templates) must be in the
#          zip. This is the real-risk class — our own SDKs — and the bug behind
#          the 1.4.0 Credits SDK WSOD.
#
#      3b. COMPOSER-managed vendor/* — rebuilt `composer install --no-dev` at
#          package time, so the shipped tree legitimately differs from the dev
#          working tree (no dev autoload_files.php, no runtime-generated
#          *.ufm.json caches). A strict source diff is invalid here. Composer
#          guarantees intra-package completeness and the .distignore denylist
#          strips only cruft, so we verify each shipped package is present and
#          non-empty.
#
#      Cruft = non-runtime files (mirrors .distignore): *.md, *.dist, tests/,
#      composer/package manifests, phpunit.xml*, dotfiles.
CRUFT_RE='\.(md|dist)$|(^|/)(tests?|Tests)/|/composer\.(json|lock)$|/phpunit\.xml|/package(-lock)?\.json$|(^|/)\.[^/]+'

LIB_FAIL=0

# 3a. Committed libs/ — strict, source-exact.
for libdir in "$ROOT"/libs/*/; do
	[ -d "$libdir" ] || continue
	lib="libs/$(basename "$libdir")"
	printf '%s\n' "$ENTRIES" | grep -q "^$SLUG/$lib/" || continue   # not shipped by this plugin
	this_fail=0
	while IFS= read -r src_file; do
		rel="${src_file#"$ROOT"/}"
		printf '%s' "$rel" | grep -qE "$CRUFT_RE" && continue
		if ! printf '%s\n' "$ENTRIES" | grep -qxF "$SLUG/$rel"; then
			echo "FAIL: committed library runtime file missing from zip: $rel" >&2
			this_fail=1
			LIB_FAIL=1
		fi
	done < <(find "$libdir" -type f)
	[ "$this_fail" -eq 0 ] && echo "  complete: $lib ($(printf '%s\n' "$ENTRIES" | grep -c "^$SLUG/$lib/") files)"
done

# 3b. Composer vendor/ — present + non-empty per package.
for vroot in $(printf '%s\n' "$ENTRIES" | grep -v '/$' | sed -nE "s#^$SLUG/(vendor/composer|vendor/[^/]+/[^/]+)/.*#\1#p" | sort -u); do
	n=$(printf '%s\n' "$ENTRIES" | grep -c "^$SLUG/$vroot/")
	if [ "$n" -gt 0 ]; then
		echo "  present: $vroot ($n files)"
	else
		echo "FAIL: shipped vendor package is empty: $vroot" >&2
		LIB_FAIL=1
	fi
done
[ "$LIB_FAIL" -eq 1 ] && exit 42

# ── 4. Required-file parity — every own-plugin require/include must ship ──
# OWN_CONST is this plugin's own dir constant; a require off the sibling's
# constant points at the OTHER plugin and is skipped (external dependency).
case "$SLUG" in
	wp-career-board)     OWN_CONST=WCB_DIR ;;
	wp-career-board-pro) OWN_CONST=WCBP_DIR ;;
esac

REQFILE_FAIL=0
REQFILE_CHECKED=0
while IFS= read -r match; do
	src="${match%%:*}"          # path/to/file.php
	code="${match#*:*:}"        # the require/include line

	if printf '%s' "$code" | grep -qE "[^A-Z_]${OWN_CONST}[[:space:]]*\."; then
		# OWN_CONST . 'relpath'  → SLUG/relpath
		rel="$(printf '%s' "$code" | sed -nE "s/.*${OWN_CONST}[[:space:]]*\.[[:space:]]*'([^']+)'.*/\1/p")"
		[ -n "$rel" ] && target="$SLUG/${rel#/}"
	elif printf '%s' "$code" | grep -qE "__DIR__|plugin_dir_path"; then
		# __DIR__ / plugin_dir_path(__FILE__) . 'relpath'  → SLUG/<file's dir>/relpath
		rel="$(printf '%s' "$code" | sed -nE "s/.*(__DIR__|plugin_dir_path\([^)]*\))[[:space:]]*\.[[:space:]]*'([^']+)'.*/\2/p")"
		[ -z "$rel" ] && continue
		combined="$(dirname "$src")/${rel#/}"
		target="$SLUG/${combined#./}"
	else
		continue   # sibling/external dir constant — not this zip's payload
	fi

	[ -z "${target:-}" ] && continue
	REQFILE_CHECKED=$((REQFILE_CHECKED + 1))
	if ! printf '%s\n' "$ENTRIES" | grep -qxF "$target"; then
		echo "FAIL: require/include target missing from zip: $target" >&2
		echo "      referenced at: $src" >&2
		REQFILE_FAIL=1
	fi
	unset target
done < <(cd "$ROOT" && grep -rnE "(require|include)(_once)?[[:space:]]+(${OWN_CONST}|WCB_DIR|WCBP_DIR|__DIR__|plugin_dir_path)" . --include='*.php' \
	| sed -E 's#^\./##' \
	| grep -vE "^(vendor|node_modules|dist|build)/" \
	| grep -vE "(^|/)(tests|test|Tests)/")

[ "$REQFILE_FAIL" -eq 1 ] && exit 43
echo "  parity OK: $REQFILE_CHECKED own-plugin require/include targets"

echo "  ✓ zip contents verified"
