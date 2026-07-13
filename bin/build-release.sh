#!/usr/bin/env bash
#
# build-release.sh — package the plugin for release.
#
# Pipeline:
#   1. Read version from main plugin file
#   2. Lockstep check (Pro only — version must match Free)
#   3. Working-tree-clean check (skipable with --allow-dirty)
#   4. composer ci pipeline (skipable with --skip-ci, NOT for customer releases)
#   5. Smoke-report gate (skipable with --skip-browser-smoke, NOT for customer releases)
#   6. Package via bin/package-dist.sh (the single packaging routine, also
#      used by `grunt dist`): rsync + .distignore + no-dev vendor + verify-zip
#      gate. Dev build and customer release are byte-for-byte identical.
#
# Exit codes:
#    0  built ok
#   10  cannot read VERSION
#   11  lockstep mismatch (Pro)
#   12  uncommitted changes
#   13  composer ci failed
#   30  smoke gate failed
#   40  zip content verification failed
#    2  unknown flag

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

SLUG="$(basename "$ROOT")"
OUTPUT_DIR="$ROOT/dist"
SKIP_BROWSER_SMOKE=0
SKIP_CI=0
ALLOW_DIRTY=0

while [[ $# -gt 0 ]]; do
	case "$1" in
		--output) OUTPUT_DIR="$2"; shift 2 ;;
		--skip-browser-smoke) SKIP_BROWSER_SMOKE=1; shift ;;
		--skip-ci) SKIP_CI=1; shift ;;
		--allow-dirty) ALLOW_DIRTY=1; shift ;;
		-h|--help)
			echo "Usage: $0 [--output DIR] [--skip-browser-smoke] [--skip-ci] [--allow-dirty]"
			exit 0
			;;
		*) echo "Unknown flag: $1" >&2; exit 2 ;;
	esac
done

# 1. Read version
case "$SLUG" in
	wp-career-board)     VAR=WCB_VERSION ;;
	wp-career-board-pro) VAR=WCBP_VERSION ;;
	*) echo "FAIL: unknown plugin slug '$SLUG'" >&2; exit 10 ;;
esac
VERSION=$(grep -oE "define\(\s*'${VAR}',\s*'[0-9.]+'" "$ROOT/$SLUG.php" | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' | head -1)
[ -z "$VERSION" ] && { echo "FAIL: cannot read $VAR from $SLUG.php" >&2; exit 10; }
echo "Building $SLUG v$VERSION"

# 2. Lockstep (Pro only)
if [ "$SLUG" = "wp-career-board-pro" ]; then
	FREE_FILE="$ROOT/../wp-career-board/wp-career-board.php"
	[ ! -f "$FREE_FILE" ] && { echo "FAIL: cannot find Free plugin at $FREE_FILE for lockstep check" >&2; exit 11; }
	FREE_VERSION=$(grep -oE "define\(\s*'WCB_VERSION',\s*'[0-9.]+'" "$FREE_FILE" | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' | head -1)
	if [ "$VERSION" != "$FREE_VERSION" ]; then
		echo "FAIL: lockstep mismatch — Pro $VERSION but Free $FREE_VERSION" >&2
		exit 11
	fi
	echo "  lockstep OK ($FREE_VERSION == $VERSION)"
fi

# 3. Working tree clean
if [ "$ALLOW_DIRTY" -eq 0 ] && ! git -C "$ROOT" diff --quiet; then
	echo "FAIL: working tree has uncommitted changes. Use --allow-dirty to bypass." >&2
	exit 12
fi

# 4. composer ci pipeline
if [ "$SKIP_CI" -eq 1 ]; then
	echo "WARN: composer ci skipped (--skip-ci). Not for customer releases."
else
	composer ci:no-journeys || { echo "FAIL: composer ci:no-journeys failed" >&2; exit 13; }
fi

# 4b. Surface-reachability gates — catch the "orphaned route" + "dead deprecated
#     class" bug classes that walk-the-UI journeys/smoke structurally miss (they
#     go UI-forward; these scan code-backward). A registered REST route with no
#     caller, or a @deprecated class with no callers, blocks the release. These
#     are part of `composer ci:no-journeys` (so dev CI + step 4 already ran
#     them); run them directly only when CI was skipped, so a release NEVER
#     ships an orphaned route / dead class regardless of flags.
if [ "$SKIP_CI" -eq 1 ]; then
	echo "Running reachability + dead-code gates (CI was skipped)..."
	php "$ROOT/bin/check-route-callers.php" "$ROOT" || { echo "FAIL: orphaned REST route(s). Wire a caller or baseline in bin/route-callers-allowlist.txt." >&2; exit 14; }
	php "$ROOT/bin/check-dead-code.php" "$ROOT"      || { echo "FAIL: dead @deprecated class(es). Remove them." >&2; exit 14; }
fi

# 5. Smoke-report gate (per docs/qa/SCAFFOLDING.md paste-block)
# Pro reads its own supplement file; Free reads the combo file.
if [ "$SLUG" = "wp-career-board-pro" ]; then
	SMOKE_REPORT="$ROOT/docs/qa/.last-smoke-pass-pro.json"
else
	SMOKE_REPORT="$ROOT/docs/qa/.last-smoke-pass.json"
fi
if [ "$SKIP_BROWSER_SMOKE" -eq 1 ]; then
	echo "WARN: browser smoke gate skipped (--skip-browser-smoke). Not for customer releases."
elif [ ! -f "$SMOKE_REPORT" ]; then
	echo "FAIL: no smoke report at $SMOKE_REPORT" >&2
	echo "      Run the /wp-plugin-smoke skill first to generate it." >&2
	echo "      Emergency only: rerun with --skip-browser-smoke." >&2
	exit 30
else
	REPORT_VERSION="$(grep -oE '"release_version"[[:space:]]*:[[:space:]]*"[^"]+"' "$SMOKE_REPORT" | head -1 | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' || true)"
	if [ "$REPORT_VERSION" != "$VERSION" ]; then
		echo "FAIL: smoke report version ($REPORT_VERSION) doesn't match release version ($VERSION)" >&2
		echo "      Re-run /wp-plugin-smoke against HEAD before packaging." >&2
		exit 30
	fi
	if grep -qE '"failures"[[:space:]]*:[[:space:]]*\[[[:space:]]*\{' "$SMOKE_REPORT"; then
		echo "FAIL: smoke report has failures. Fix them before packaging." >&2
		exit 30
	fi
	if grep -qE '"debug_log_issues"[[:space:]]*:[[:space:]]*\[[[:space:]]*\{' "$SMOKE_REPORT"; then
		echo "FAIL: smoke report recorded debug.log entries. Fix before packaging." >&2
		exit 30
	fi
	echo "  smoke report OK ($REPORT_VERSION, no failures, no debug_log_issues)"
fi

# 6. Package via the single packaging routine — rsync + .distignore + no-dev
#    vendor + verify-zip gate. The SAME script `grunt dist` runs, so the dev
#    build and this customer release are byte-for-byte identical.
echo
bash "$ROOT/bin/package-dist.sh" --output "$OUTPUT_DIR"
