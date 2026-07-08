#!/usr/bin/env bash
# WP Career Board — JS i18n gate.
#
# Greps blocks/ + assets/js for user-facing English string literals that are
# NOT read from a localized i18n source. eslint's @wordpress/i18n-* rules only
# validate strings ALREADY wrapped in __(); no @wordpress rule catches a bare
# literal (the widely-cited `no-unlocalized-strings` is not a real rule in
# @wordpress/eslint-plugin). This grep is that missing gate.
#
# Allowed (localized with a defensive fallback):
#   • foo.textContent = t( 'cancel', 'Cancel' );
#   • foo.textContent = si( 'cancel', 'Cancel' );
#   • foo.textContent = state.i18n?.cancel || 'Cancel';
#   • foo.textContent = data.i18n.cancel || 'Cancel';
#   • wp.i18n.__( 'Cancel', 'wp-career-board' )         (editor index.js)
#
# Disallowed (ships English to translators):
#   • foo.textContent = 'Cancel';
#   • alert( 'Failed!' );  confirm( 'Sure?' );  prompt( 'URL:' );
#
# Exits 1 if any bare literal is found.

set -euo pipefail
cd "$(dirname "$0")/.."

SUSPECT_PROPS='textContent|innerText|innerHTML|placeholder|title'
FAILED=0

check() {
	local regex="$1"
	while IFS= read -r line; do
		# Skip lines already reading from an i18n source or a helper on the SAME line.
		if echo "$line" | grep -Eq "(\.i18n[?]?\.[a-zA-Z]|\bt\( *'|\bsi\( *'|state\.i18n|wp\.i18n\.__|__\( *')"; then
			continue
		fi
		# Skip translator-comment markers / data-attribute template strings.
		if echo "$line" | grep -Eq '(translators:|data-wp-|aria-label.*\$\{)'; then
			continue
		fi
		echo "$line"
		FAILED=1
	done < <(grep -rEn "$regex" blocks/ assets/js/ \
		--include='*.js' 2>/dev/null | grep -v '\.min\.js' || true)
}

# 1. Assignments to UI-bearing DOM properties.
check "\.($SUSPECT_PROPS)\s*=\s*['\"][A-Z][a-zA-Z][^'\"]{1,60}['\"]"

# 2. Blocking modal/native calls with a literal body.
check "\b(alert|confirm|prompt)\s*\(\s*['\"][A-Z][a-zA-Z][^'\"]{1,60}['\"]"

if [ "$FAILED" -ne 0 ]; then
	echo ""
	echo "FAILED: hardcoded user-facing string(s) in blocks/ or assets/js."
	echo "Localize via t( 'key', 'English' ) (seed the key in render.php's i18n"
	echo "array) or wp.i18n.__() in editor scripts. See docs/standards/i18n.md."
	exit 1
fi

echo "OK: no hardcoded user-facing strings in blocks/ or assets/js."
