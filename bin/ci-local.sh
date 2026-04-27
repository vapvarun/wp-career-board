#!/usr/bin/env bash
# ci-local.sh — run the full CI battery on the local workstation.
#
# Mirrors .github/workflows/ci.yml for the Free plugin: must pass green
# here BEFORE every push to origin so GitHub's runner sees a clean tree.
# Pro plugin uses the same script (no GitHub mirror) — green here is the
# only gate.
#
# Exit code: 0 on all-green, 1 on any failure.

set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT" || exit 2

GREEN="\033[0;32m"
RED="\033[0;31m"
DIM="\033[2m"
RESET="\033[0m"
BOLD="\033[1m"

run_step() {
	local name="$1"; shift
	local cmd="$*"
	printf "  ${DIM}running${RESET} %s ... " "$name"
	local out
	if out=$(eval "$cmd" 2>&1); then
		printf "${GREEN}OK${RESET}\n"
		return 0
	else
		printf "${RED}FAIL${RESET}\n"
		printf "%s\n" "$out" | sed 's/^/      /'
		return 1
	fi
}

failed=0

printf "${BOLD}Local CI — %s${RESET}\n" "$(basename "$ROOT")"

# 1. PHP syntax lint
# `php -l` emits "No syntax errors detected …" on success; any other line is
# either a parse error or warning, which we want to surface. We collect the
# noisy output, drop the success lines, and fail iff anything remains.
run_step "php-lint" '
	out=$(find . -name "*.php" \
		-not -path "./vendor/*" \
		-not -path "./node_modules/*" \
		-not -path "./build/*" \
		-not -path "./dist/*" \
		-not -path "./.worktrees/*" \
		-print0 \
		| xargs -0 -P4 -n20 php -l 2>&1 \
		| awk "!/No syntax errors detected/")
	if [ -n "$out" ]; then printf "%s\n" "$out"; exit 1; fi
' || failed=$((failed+1))

# 2. WPCS
if [ -x "./vendor/bin/phpcs" ]; then
	run_step "wpcs" './vendor/bin/phpcs --runtime-set ignore_warnings_on_exit 1 .' || failed=$((failed+1))
else
	printf "  ${DIM}skip   ${RESET} wpcs (run \"composer install\" first)\n"
fi

# 3. PHPStan
if [ -x "./vendor/bin/phpstan" ]; then
	run_step "phpstan" './vendor/bin/phpstan analyse --no-progress --memory-limit=2G' || failed=$((failed+1))
else
	printf "  ${DIM}skip   ${RESET} phpstan (run \"composer install\" first)\n"
fi

# 4. size-limit (only when configured)
if [ -f package.json ] && grep -q '"size-limit"' package.json && [ -x "./node_modules/.bin/size-limit" ]; then
	run_step "size-limit" 'npx --no-install size-limit' || failed=$((failed+1))
else
	printf "  ${DIM}skip   ${RESET} size-limit (no config or not installed)\n"
fi

printf "\n"
if [ "$failed" -eq 0 ]; then
	printf "${GREEN}${BOLD}ALL GREEN${RESET} — safe to push\n"
	exit 0
else
	printf "${RED}${BOLD}%d step(s) failed${RESET} — fix before push\n" "$failed"
	exit 1
fi
