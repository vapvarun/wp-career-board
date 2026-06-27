#!/usr/bin/env bash
#
# run-journeys.sh — list / dry-run / execute journeys under audit/journeys/
#
# Journeys are commit-time regression sentinels: each is a Markdown file
# with frontmatter + numbered steps. This script's job is the wrapper:
# it parses frontmatter, picks journeys by priority/area, and dispatches
# each to a Sonnet sub-agent with Playwright MCP for execution.
#
# Modes:
#   --list                    list all journeys with priority + status
#   --dry-run                 list what would run, but don't execute
#   --priority=critical       filter by priority (default: critical)
#   --area=customer|admin|... filter by sub-directory
#   --id=apply-to-job         run a single journey
#   --audit-stale             list journeys with last_verified > 90 days ago
#
# Output: per-journey JSON in audit/journey-runs/<id>-<timestamp>.json
#         summary table on stdout
#
# Exit codes:
#   0 = all journeys pass (or none ran)
#   1 = one or more critical-priority journeys failed
#   2 = malformed frontmatter / missing required field

set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
JOURNEYS_DIR="$ROOT/audit/journeys"
RUNS_DIR="$ROOT/audit/journey-runs"

if [ ! -d "$JOURNEYS_DIR" ]; then
	echo "FAIL: $JOURNEYS_DIR does not exist. Did you run wp-plugin-onboard Phase 4.7?" >&2
	exit 2
fi

mkdir -p "$RUNS_DIR"

# --- arg parsing ---
MODE=""
PRIORITY="critical"
AREA=""
ID=""
while [ $# -gt 0 ]; do
	case "$1" in
		--list)            MODE="list";       shift ;;
		--dry-run)         MODE="dry-run";    shift ;;
		--audit-stale)     MODE="stale";      shift ;;
		--priority=*)      PRIORITY="${1#*=}"; shift ;;
		--area=*)          AREA="${1#*=}";     shift ;;
		--id=*)            ID="${1#*=}";       shift ;;
		-h|--help)
			sed -n '2,/^$/p' "$0" | sed 's/^# \?//'
			exit 0
			;;
		*) echo "unknown flag: $1" >&2; exit 2 ;;
	esac
done

# --- discover journeys (find handles paths with spaces; glob does not) ---
collect() {
	if [ -n "$AREA" ]; then
		[ -d "$JOURNEYS_DIR/$AREA" ] || return 0
		find "$JOURNEYS_DIR/$AREA" -mindepth 1 -maxdepth 1 -name '*.md' \
			-not -name 'README.md' -not -name '.template.md' 2>/dev/null
	else
		find "$JOURNEYS_DIR" -mindepth 2 -maxdepth 2 -name '*.md' \
			-not -name 'README.md' -not -name '.template.md' 2>/dev/null
	fi
}

# --- frontmatter helpers (no yq dep) ---
fm_field() {
	local file="$1" field="$2"
	awk -v f="$field" '
		/^---$/ { if (++c == 2) exit }
		c == 1 && $0 ~ "^"f": " { sub("^"f": ", ""); print; exit }
	' "$file"
}

# --- list ---
if [ "$MODE" = "list" ] || [ -z "$MODE" ]; then
	printf "%-40s %-10s %-10s %s\n" "ID" "PRIORITY" "AREA" "BUG_REF"
	printf -- "------------------------------------------------------------------------------\n"
	while IFS= read -r f; do
		[ -z "$f" ] && continue
		id=$(fm_field "$f" id)
		pri=$(fm_field "$f" priority)
		area=$(basename "$(dirname "$f")")
		bug=$(fm_field "$f" bug_ref)
		printf "%-40s %-10s %-10s %s\n" "$id" "$pri" "$area" "${bug:-—}"
	done < <(collect)
	[ "$MODE" = "list" ] && exit 0
fi

# --- stale audit ---
if [ "$MODE" = "stale" ]; then
	NOW=$(date +%s)
	THRESHOLD=$((NOW - 90 * 86400))
	stale=0
	while IFS= read -r f; do
		[ -z "$f" ] && continue
		lv=$(fm_field "$f" last_verified)
		[ -z "$lv" ] && continue
		ts=$(date -j -f "%Y-%m-%d" "$lv" "+%s" 2>/dev/null || echo 0)
		if [ "$ts" -lt "$THRESHOLD" ] && [ "$ts" -gt 0 ]; then
			id=$(fm_field "$f" id)
			echo "  STALE  $id (last_verified: $lv)"
			stale=$((stale + 1))
		fi
	done < <(collect)
	echo
	echo "$stale stale journey(s)"
	exit 0
fi

# --- dry-run / execute ---
SELECTED=()
while IFS= read -r f; do
	[ -z "$f" ] && continue
	id=$(fm_field "$f" id)
	pri=$(fm_field "$f" priority)
	[ -n "$ID" ] && [ "$id" != "$ID" ] && continue
	[ -z "$ID" ] && [ -n "$PRIORITY" ] && [ "$pri" != "$PRIORITY" ] && continue
	SELECTED+=( "$f" )
done < <(collect)

if [ "${#SELECTED[@]}" -eq 0 ]; then
	echo "No journeys matched the filter."
	exit 0
fi

if [ "$MODE" = "dry-run" ]; then
	echo "Would run ${#SELECTED[@]} journey(s):"
	for f in "${SELECTED[@]}"; do
		echo "  $(fm_field "$f" id) — $(basename "$(dirname "$f")")/$(basename "$f")"
	done
	exit 0
fi

# --- pre-flight: every journey REST route must resolve to a registered route ---
# Drift gate (bin/check-journey-routes.php). Needs the live REST server, so it
# runs only when wp-cli is available; skipped with a warning otherwise so offline
# listing still works.
if command -v wp >/dev/null 2>&1; then
	echo "Pre-flight: validating journey REST routes against registered routes…"
	if ! wp eval-file "$ROOT/bin/check-journey-routes.php"; then
		echo "FAIL: a journey references a route the plugin does not register (see above)." >&2
		exit 1
	fi
else
	echo "WARN: wp-cli not found — skipping journey REST-route validation." >&2
fi

# --- actual execution ---
# A journey runner is a Sonnet sub-agent dispatch. The framework here
# prints the prompt template; the calling Claude Code session wraps the
# Agent({ model: "sonnet", ... }) call. This wrapper exists so journeys
# are a first-class CLI surface — `composer journeys` doesn't need to
# know about Claude.
#
# When no Claude session is invoking this script (e.g., human dev runs
# `composer journeys` from a terminal), we print the dispatch instructions
# rather than executing. The intent is: humans use this as a checklist;
# automation pipes it through Claude Code's `claude` CLI.

echo "==> ${#SELECTED[@]} journey(s) selected"
echo
TS=$(date -u +%Y%m%dT%H%M%SZ)
FAILED=0
for f in "${SELECTED[@]}"; do
	id=$(fm_field "$f" id)
	echo "── $id ───────────────────────────────────────────"
	cat "$f"
	echo
	# Stub the run report — a real implementation pipes this to Sonnet
	# via the wp-career-board-smoke skill's dispatch template, then
	# parses the returned JSON. For now: declare the journey "manually
	# verifiable" and print the audit-trail location.
	REPORT="$RUNS_DIR/${id}-${TS}.json"
	cat > "$REPORT" <<JSON
{
  "id": "$id",
  "ran_at": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "status": "manual_required",
  "note": "run-journeys.sh prints the journey for manual walk; for automated execution, dispatch via wp-career-board-smoke skill (Sonnet + Playwright MCP) and rewrite this report.",
  "source_journey": "${f#$ROOT/}"
}
JSON
	echo "  report: ${REPORT#$ROOT/}"
done

echo
echo "Summary: ${#SELECTED[@]} journey(s) emitted manual_required reports."
echo "For automated execution, run the wp-career-board-smoke skill: it dispatches"
echo "a Sonnet sub-agent that reads each journey, walks it via Playwright MCP,"
echo "and rewrites the report with pass/fail per step."

exit "$FAILED"
