#!/usr/bin/env bash
# php-floor-lint.sh - compile every PHP file on the oldest PHP the plugin supports.
#
# PHPStan and PHPCS run on whatever PHP the machine has, so syntax newer than
# the plugin's "Requires PHP" (a standalone `true` type needs 8.2) passes them
# and then fatals on the oldest supported version. This runs `php -l` with that
# version: a local `php<ver>` binary, else the `php:<ver>-cli` Docker image.
# A gate that cannot run must not pass, so neither being available is a failure.
#
# Usage: bin/php-floor-lint.sh [plugin-dir | release.zip]   (default: this plugin)
# Exit:  0 all files compile | 1 a file fails | 2 no PHP <ver> available

set -euo pipefail

TARGET="${1:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
WORK=""
cleanup() { if [ -n "$WORK" ]; then rm -rf "$WORK"; fi; }
trap cleanup EXIT

if [ -f "$TARGET" ] && [ "${TARGET##*.}" = "zip" ]; then
	WORK="$(mktemp -d)"
	unzip -q "$TARGET" -d "$WORK"
	DIR="$WORK"
	MAIN="$(grep -l "Requires PHP:" "$WORK"/*/*.php 2>/dev/null | head -1)"
else
	DIR="$(cd "$TARGET" && pwd)"
	MAIN="$(grep -l "Requires PHP:" "$DIR"/*.php 2>/dev/null | head -1)"
fi
VER="$(grep -m1 "Requires PHP:" "$MAIN" | grep -oE '[0-9]+\.[0-9]+')"
[ -n "$VER" ] || { echo "php-floor-lint: no Requires PHP header found in $TARGET" >&2; exit 2; }

# Source dirs skip dev-only trees; a zip is linted whole (its vendor/ is runtime).
FILES="$(mktemp)"
SKIP=( -not -path '*/node_modules/*' )
[ -z "$WORK" ] && SKIP+=( -not -path './vendor/*' -not -path './tests/*' )
( cd "$DIR" && find . -name '*.php' "${SKIP[@]}" ) > "$FILES"

if command -v "php$VER" >/dev/null 2>&1; then
	RUN=( "php$VER" )
	cd "$DIR"
elif command -v docker >/dev/null 2>&1 && docker image inspect "php:$VER-cli" >/dev/null 2>&1 || docker pull -q "php:$VER-cli" >/dev/null 2>&1; then
	RUN=( docker run --rm -i -v "$DIR:/src:ro" -v "$FILES:/files.txt:ro" -w /src "php:$VER-cli" )
else
	echo "php-floor-lint: needs php$VER or Docker with php:$VER-cli; refusing to pass without it." >&2
	rm -f "$FILES"
	exit 2
fi

LIST="$FILES"
[ "${RUN[0]}" = "docker" ] && LIST="/files.txt"
# php -l prints "No syntax errors detected in ..." for a good file; anything else is a failure.
OUT="$( "${RUN[@]}" sh -c "xargs -n 1 -P 4 php -l < $LIST 2>&1 | grep -v '^No syntax errors detected' | grep -v '^$'" || true )"
COUNT="$(wc -l < "$FILES" | tr -d ' ')"
rm -f "$FILES"
if [ -n "$OUT" ]; then
	echo "php-floor-lint: files that do not compile on PHP $VER:" >&2
	echo "$OUT" >&2
	exit 1
fi
echo "php-floor-lint OK: $COUNT PHP files compile on PHP $VER."
