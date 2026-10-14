#!/usr/bin/env bash
# Generate the POT translation template from the plugin's PHP, block.json and
# JavaScript. Requires WP-CLI with the i18n command on PATH.
#
# Usage: bin/make-pot.sh [plugin-dir] [--check]   (default: this plugin)
# Pro runs it as ../wp-career-board/bin/make-pot.sh . - one script, two plugins.
# --check writes nothing and exits 1 when the committed POT is missing a string
# the code emits, or still lists one the code no longer has.
#
# bin/ and theme.json are excluded: bin/ never ships, and theme.json is merged
# raw (Plugin::register_theme_json_defaults), so its preset labels never pass
# through this text domain - listing them would only send translators dead work.
set -euo pipefail

CHECK=0
DIR=""
for arg in "$@"; do
	case "$arg" in
		--check) CHECK=1 ;;
		*) DIR="$arg" ;;
	esac
done

ROOT="$(cd "${DIR:-$(dirname "${BASH_SOURCE[0]}")/..}" && pwd)"
cd "$ROOT"

DOMAIN="$(basename "$ROOT")"
POT="languages/${DOMAIN}.pot"
OUT="$POT"
[ "$CHECK" -eq 1 ] && OUT="$(mktemp).pot"

wp i18n make-pot . "$OUT" \
	--domain="${DOMAIN}" \
	--exclude=node_modules,vendor,libs,build,dist,audit,tests,docs,bin,plan,theme.json \
	--quiet

if [ "$CHECK" -eq 0 ]; then
	echo "== Done: ${POT} ($(grep -c '^msgid ' "$POT") entries incl. header) =="
	exit 0
fi

# Compare message keys (context + msgid), ignoring references and dates.
keys() { msgcat --no-location --no-wrap --sort-output "$1" | grep -E '^(msgctxt|msgid) ' ; }
if ! diff <(keys "$POT") <(keys "$OUT") > /dev/null; then
	echo "${POT} is stale; run bin/make-pot.sh. Differences (< committed, > code):"
	diff <(keys "$POT") <(keys "$OUT") | grep -E '^[<>]' | sed -n '1,20p'
	rm -f "$OUT"
	exit 1
fi
rm -f "$OUT"
echo "${POT} matches the code."
