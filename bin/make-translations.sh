#!/usr/bin/env bash
# Refresh every shipped translation from the POT: update the .po files, then
# compile .mo, .l10n.php and the JavaScript JSON.
#
# Usage: bin/make-translations.sh [plugin-dir]   (default: this plugin)
# Pro runs it as ../wp-career-board/bin/make-translations.sh . - one script,
# two plugins.
#
# JSON naming: WordPress loads a script's translations from
# <domain>-<locale>-<md5 of the script path>.json. Our editor scripts are
# registered straight from source (block.json "file:./index.js", Pro's
# assets/field-builder.js), so the path in the .po is the path WordPress
# hashes and no source->build map is needed. Front-end view.js modules are
# translated through server-seeded Interactivity state, never through JSON.
set -euo pipefail

ROOT="$(cd "${1:-$(dirname "${BASH_SOURCE[0]}")/..}" && pwd)"
cd "$ROOT"

DOMAIN="$(basename "$ROOT")"
POT="languages/${DOMAIN}.pot"
[ -f "$POT" ] || { echo "Missing $POT - run bin/make-pot.sh first." >&2; exit 1; }

echo "== ${DOMAIN}: updating .po files from the POT =="
wp i18n update-po "$POT" languages/

# Compile from copies with the fuzzy entries removed. wp i18n make-mo/make-php
# compile fuzzy entries (GNU msgfmt does not), and a fuzzy entry is gettext's
# guess from a similar old string - unreviewed, often wrong - so it must never
# reach a site. Only reviewed translations are shipped.
echo "== ${DOMAIN}: compiling .mo and .l10n.php (reviewed entries only) =="
CLEAN="$(mktemp -d)"
for po in languages/"${DOMAIN}"-*.po; do
	msgattrib --no-fuzzy --no-obsolete -o "$CLEAN/$(basename "$po")" "$po"
done
wp i18n make-mo "$CLEAN" languages/
wp i18n make-php "$CLEAN" languages/

echo "== ${DOMAIN}: rebuilding JavaScript JSON (stale files purged) =="
rm -f languages/"${DOMAIN}"-*-[0-9a-f]*.json
wp i18n make-json "$CLEAN" languages/ --no-purge
rm -rf "$CLEAN"

# Every JSON must be keyed to a script that exists, or WordPress never finds it.
for json in languages/"${DOMAIN}"-*.json; do
	[ -e "$json" ] || continue
	src="$(php -r 'echo json_decode( file_get_contents( $argv[1] ) )->source ?? "";' "$json")"
	[ -f "$src" ] || { echo "JSON $json points at missing script '$src'" >&2; exit 1; }
done

echo "== ${DOMAIN}: done =="
