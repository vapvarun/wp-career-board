#!/usr/bin/env bash
#
# coding-rules-check.sh — plugin-specific coding rules gate
#
# These checks ride alongside WPCS / PHPStan and catch rules that those
# tools don't (or don't reliably) flag. Each rule has a one-line reason
# and an exit code so CI can route which gate failed.
#
# Rules:
#   1. No inline <script> / <style> in PHP source (race-condition + i18n trap)
#   2. No bare `current_user_can()` outside class-rest-controller.php (per CLAUDE.md Abilities API rule)
#   3. No em-dashes inside i18n callable args (reads as AI-generated)
#   4. No hardcoded user-visible JS strings without i18n routing
#   5. Ability slugs use namespaced format `wcb/post-jobs` (NOT `wcb_post_jobs` snake_case)
#   6. No raw $wpdb->query without prepare() in src changes
#
# Modes:
#   --staged   only check files staged for commit (default for pre-commit hook)
#   --full     full source-tree scan (slow, used by composer ci)
#   --quiet    suppress per-rule summary (still prints failures)
#
# Exit codes: 0 = pass, 1 = at least one rule failed

set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

MODE="staged"
QUIET=0
for arg in "$@"; do
	case "$arg" in
		--staged) MODE="staged" ;;
		--full)   MODE="full"   ;;
		--quiet)  QUIET=1       ;;
	esac
done

# Build the file list once.
if [ "$MODE" = "staged" ]; then
	PHP_FILES=$(git diff --cached --name-only --diff-filter=ACMR -- '*.php' 2>/dev/null | tr '\n' ' ')
	JS_FILES=$(git diff --cached --name-only --diff-filter=ACMR -- 'assets/js/*.js' 'blocks/*/view.js' 2>/dev/null | grep -v '\.min\.js$' | tr '\n' ' ')
else
	PHP_FILES=$(find . -name '*.php' \
		-not -path './vendor/*' -not -path './node_modules/*' -not -path './tests/*' \
		-not -path './build/*' -not -path './dist/*' 2>/dev/null | tr '\n' ' ')
	JS_FILES=$(find . -path '*/assets/js/*.js' -o -path '*/blocks/*/view.js' 2>/dev/null \
		| grep -v '\.min\.js$' | grep -v node_modules | tr '\n' ' ')
fi

FAILED=0
report() { echo "  ✗ $1"; FAILED=1; }
ok()     { [ "$QUIET" -eq 1 ] || echo "  ✓ $1"; }

# --- Rule 1: no inline <script>/<style> in PHP ---
if [ -n "$PHP_FILES" ]; then
	OFFENDERS=""
	for f in $PHP_FILES; do
		case "$f" in
			vendor/*|node_modules/*|tests/*|build/*|dist/*) continue ;;
			*templates/emails/*) continue ;; # email templates are exempt
		esac
		hits=$(grep -nE '^[[:space:]]*<(script|style)([[:space:]]|>)' "$f" 2>/dev/null \
			| grep -vE 'application/(ld\+json|json)' || true)
		[ -n "$hits" ] && OFFENDERS="$OFFENDERS$f:\n$hits\n"
	done
	if [ -n "$OFFENDERS" ]; then
		printf "%b" "$OFFENDERS" | sed 's/^/    /'
		report "Rule 1: inline <script>/<style> in PHP source — move to assets/{js,css}/ + wp_enqueue_*"
	else
		ok "Rule 1: no inline <script>/<style> in PHP"
	fi
fi

# --- Rule 2: no bare current_user_can outside the chokepoint controller ---
if [ -n "$PHP_FILES" ]; then
	OFFENDERS=""
	for f in $PHP_FILES; do
		case "$f" in
			vendor/*|node_modules/*|tests/*) continue ;;
			*api/class-rest-controller.php) continue ;; # documented carve-out
			*modules/antispam/*) continue ;;            # uses wcb_manage_settings cap directly
		esac
		hits=$(grep -nE 'current_user_can\s*\(' "$f" 2>/dev/null \
			| grep -vE '//\s*phpcs:ignore' || true)
		[ -n "$hits" ] && OFFENDERS="$OFFENDERS$f:\n$hits\n"
	done
	if [ -n "$OFFENDERS" ]; then
		printf "%b" "$OFFENDERS" | sed 's/^/    /'
		report "Rule 2: bare current_user_can() — use Abilities API (wp_is_ability_granted) per CLAUDE.md"
	else
		ok "Rule 2: Abilities API only (no bare current_user_can outside chokepoint)"
	fi
fi

# --- Rule 3: no em-dashes in i18n call sites ---
if [ -n "$PHP_FILES" ]; then
	HITS=$(grep -nE "(__|_e|_x|_n|esc_html__|esc_attr__|esc_html_e|esc_attr_e)\(\s*['\"][^)]*—" \
		$PHP_FILES 2>/dev/null | grep -v vendor || true)
	if [ -n "$HITS" ]; then
		echo "$HITS" | sed 's/^/    /'
		report "Rule 3: em-dash inside i18n callable — use ' - ' (space-hyphen-space) instead"
	else
		ok "Rule 3: no em-dashes in i18n callables"
	fi
fi

# --- Rule 4: no hardcoded user-visible JS strings ---
if [ -n "$JS_FILES" ]; then
	if [ "$MODE" = "staged" ]; then
		HITS=$(git diff --cached -- $JS_FILES 2>/dev/null \
			| grep -E '^\+[^+]' \
			| grep -vE '^\+[[:space:]]*(//|\*|/\*)' \
			| grep -vE 'i18n\.|wcb[A-Za-z]+\.i18n|\|\|' \
			| grep -E "(\.textContent|\.placeholder|\.title|\.innerText|setAttribute\(['\"]aria-label['\"]|alert\()[[:space:]]*[=,][[:space:]]*['\"][A-Z][a-z]+[ ][a-zA-Z]" || true)
	else
		HITS=$(grep -nE "(\.textContent|\.placeholder|\.title|\.innerText|setAttribute\(['\"]aria-label['\"]|alert\()[[:space:]]*[=,][[:space:]]*['\"][A-Z][a-z]+[ ][a-zA-Z]" \
			$JS_FILES 2>/dev/null \
			| grep -vE 'i18n\.|wcb[A-Za-z]+\.i18n|\|\|' || true)
	fi
	if [ -n "$HITS" ]; then
		echo "$HITS" | sed 's/^/    /'
		report "Rule 4: hardcoded user-visible JS string — route through window.wcb*.i18n with || 'English fallback'"
	else
		ok "Rule 4: no hardcoded user-visible JS strings"
	fi
fi

# --- Rule 5: ability slugs use wcb/foo namespaced format ---
if [ -n "$PHP_FILES" ]; then
	HITS=$(grep -nE "wp_register_ability\s*\(\s*'wcb_[a-z_]+'" $PHP_FILES 2>/dev/null || true)
	if [ -n "$HITS" ]; then
		echo "$HITS" | sed 's/^/    /'
		report "Rule 5: ability slug uses snake_case 'wcb_foo' — use namespaced 'wcb/foo' (WP 6.9+ Abilities API)"
	else
		ok "Rule 5: ability slugs use namespaced format"
	fi
fi

# --- Rule 6: no raw $wpdb->query without prepare in changes ---
if [ -n "$PHP_FILES" ] && [ "$MODE" = "staged" ]; then
	HITS=$(git diff --cached -- $PHP_FILES 2>/dev/null \
		| grep -E "^\+.*\\\$wpdb->(query|get_)" \
		| grep -v "prepare\|//\s*phpcs:ignore" || true)
	if [ -n "$HITS" ]; then
		echo "$HITS" | sed 's/^/    /'
		report "Rule 6: \$wpdb-> call without ->prepare() — wrap with prepare() or annotate with phpcs:ignore + reason"
	else
		ok "Rule 6: \$wpdb calls use ->prepare()"
	fi
fi

[ "$FAILED" -eq 0 ] && [ "$QUIET" -eq 0 ] && echo "coding-rules: OK"
exit "$FAILED"
