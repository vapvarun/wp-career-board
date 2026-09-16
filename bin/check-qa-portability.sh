#!/usr/bin/env bash
#
# Fail when a QA procedure hardcodes one machine.
#
# The walkthroughs are the runnable half of the QA catalogue. When they carry
# an author's hostname and home directory, following them on any other box
# fails at the first command - and degrades quietly rather than loudly: a wrong
# --path makes wp-cli operate on the wrong site, and an empty variable produces
# a URL that loads a list page instead of erroring. 97 + 20 occurrences had
# accumulated across 22 files before anyone hit it (Basecamp 10304164491).
#
# Procedures take $WCB_SITE / $WCB_PATH, set from the preamble each one opens
# with. FINDINGS-*.md is exempt: it records one run on one machine by design.
#
# Usage: bash bin/check-qa-portability.sh
set -uo pipefail

cd "$(dirname "$0")/.."

DIRS=()
[ -d docs/qa/walkthroughs ] && DIRS+=(docs/qa/walkthroughs)
[ -d audit/journeys ] && DIRS+=(audit/journeys)

if [ ${#DIRS[@]} -eq 0 ]; then
	echo "qa-portability: no walkthrough directories, nothing to check"
	exit 0
fi

# A .local hostname, or an absolute path into someone's home directory.
# Bare hostnames too, not just http:// ones - three files named a machine
# without a scheme and slipped the first version of this check.
PATTERN='[A-Za-z0-9-]+\.local\b|/(Users|home)/[A-Za-z0-9._-]+/'

HITS=$(grep -rnE "$PATTERN" "${DIRS[@]}" --include='*.md' 2>/dev/null \
	| grep -vE 'FINDINGS-|BUG-CARDS-|WALK-FINDINGS' || true)

if [ -n "$HITS" ]; then
	echo "qa-portability: FAIL - a QA procedure hardcodes one machine."
	echo "$HITS" | sed 's/^/    /'
	echo
	echo "  Use \$WCB_SITE and \$WCB_PATH, set by the preamble each walkthrough opens with:"
	echo '    WCB_PATH="$(cd "$(git rev-parse --show-toplevel)/../../.." && pwd)"'
	echo '    WCB_SITE="$(wp --path="$WCB_PATH" option get home)"'
	exit 1
fi

echo "qa-portability: OK - no hardcoded hostnames or home paths in QA procedures"
