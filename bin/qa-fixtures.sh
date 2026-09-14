#!/usr/bin/env bash
# QA persona fixtures for WP Career Board — idempotent.
#
# The role ladder (wp-card-qa §1.5) needs every persona to exist before the first
# card of a session is walked. This script is the executable half of the
# `personas` contract in docs/qa/qa-config.json: it VERIFIES the ladder, and
# delegates creation to bin/seed-qa-fixtures.php, which already owns user and
# content creation. Two scripts creating users would drift.
#
#   bash bin/qa-fixtures.sh          # verify, seeding once if anything is missing
#   bash bin/qa-fixtures.sh --list   # report state, change nothing
#   bash bin/qa-fixtures.sh --seed   # force a reseed even if the ladder is complete
#
# Run once per environment at the start of a QA session, NOT once per card.
set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONFIG="${PLUGIN_DIR}/docs/qa/qa-config.json"
SEEDER="${PLUGIN_DIR}/bin/seed-qa-fixtures.php"

[[ -f "${CONFIG}" ]] || { echo "✗ missing ${CONFIG}"; exit 1; }
[[ -f "${SEEDER}" ]] || { echo "✗ missing ${SEEDER}"; exit 1; }

# Derive the WP root from the plugin's own location; never trust a stored path.
WP_PATH="${PLUGIN_DIR%/wp-content/plugins/*}"
[[ -f "${WP_PATH}/wp-load.php" ]] || { echo "✗ no wp-load.php above ${PLUGIN_DIR}"; exit 1; }

# Plain wp-cli. If the local PHP carries broken extensions whose startup warnings
# pollute output (a Local bundle beside a Homebrew PHP, say), export
# WP_CLI_PHP_ARGS before running — wp-cli passes it through to PHP:
#   WP_CLI_PHP_ARGS='-d error_reporting=0 -d mysqli.default_socket=/path/to/mysqld.sock'
wp() { command wp --path="${WP_PATH}" "$@"; }

# personas is a MAP (key -> login) and persona_roles a sibling map of expected
# roles. bash 3.2 on macOS has no associative arrays, so flatten to TSV.
LADDER_TSV="$(python3 -c '
import json,sys
d=json.load(open(sys.argv[1]))
people=d.get("personas",{}) or {}
roles=d.get("persona_roles",{}) or {}
for key,login in people.items():
    if login:
        print("\t".join([key, login, roles.get(key,"")]))
' "${CONFIG}")"

[[ -n "${LADDER_TSV}" ]] || { echo "✗ no personas in ${CONFIG} — see wp-card-qa Step 0"; exit 1; }

missing=0
report() {
  printf '%-18s %-22s %-22s %s\n' KEY LOGIN EXPECTED-ROLE STATUS
  while IFS=$'\t' read -r key login want; do
    if id="$(wp user get "${login}" --field=ID 2>/dev/null)"; then
      have="$(wp user get "${login}" --field=roles 2>/dev/null || echo '?')"
      if [[ -n "${want}" && "${have}" != *"${want}"* ]]; then
        printf '%-18s %-22s %-22s ✗ role is "%s"\n' "${key}" "${login}" "${want}" "${have}"
        missing=$((missing+1))
      else
        printf '%-18s %-22s %-22s ok (ID %s)\n' "${key}" "${login}" "${want}" "${id}"
      fi
    else
      printf '%-18s %-22s %-22s ✗ absent\n' "${key}" "${login}" "${want}"
      missing=$((missing+1))
    fi
  done <<< "${LADDER_TSV}"
}

case "${1:-}" in
  --list)
    report
    exit 0
    ;;
  --seed)
    wp eval-file "${SEEDER}"
    missing=0
    report
    ;;
  *)
    report
    if (( missing > 0 )); then
      echo
      echo "→ ${missing} persona(s) missing or mis-roled; running bin/seed-qa-fixtures.php once."
      wp eval-file "${SEEDER}"
      missing=0
      echo
      report
    fi
    ;;
esac

echo
if (( missing > 0 )); then
  echo "✗ ladder incomplete after seeding — fix bin/seed-qa-fixtures.php or the personas map."
  exit 1
fi

# §1.5: a permission bug is invisible with a single account, because the owner
# can always see their own item. Fail loudly rather than let a QA session start
# on a ladder that cannot detect that class of bug.
for pair in employer:employer_other candidate:candidate_other; do
  a="${pair%%:*}"; b="${pair##*:}"
  grep -q "^${b}	" <<< "${LADDER_TSV}" || {
    echo "✗ ${a} has no second same-role persona (${b}) — see wp-card-qa §1.5."
    exit 1
  }
done

echo "✓ role ladder complete — two same-role members present for employer and candidate."
