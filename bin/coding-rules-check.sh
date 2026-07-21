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
#   7. Release-integrity: every WCB class autoloads + ships (no autoloader
#      dir/namespace mismatch, no class whose file .distignore strips)
#   8. Design-system contracts: (a) single canonical token namespace, no legacy
#      --wcb-accent/text/bg/warn aliases; (b) dual-context CSS keeps hex fallbacks
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
	CSS_FILES=$(git diff --cached --name-only --diff-filter=ACMR -- 'assets/css/*.css' 'assets/css/**/*.css' 'blocks/*/style.css' 'blocks/*/styles/*.css' 2>/dev/null | tr '\n' ' ')
else
	PHP_FILES=$(find . -name '*.php' \
		-not -path './vendor/*' -not -path './node_modules/*' -not -path './tests/*' \
		-not -path './build/*' -not -path './dist/*' -not -path './libs/*' 2>/dev/null | tr '\n' ' ')
	JS_FILES=$(find . -path '*/assets/js/*.js' -o -path '*/blocks/*/view.js' 2>/dev/null \
		| grep -v '\.min\.js$' | grep -v node_modules | tr '\n' ' ')
	CSS_FILES=$(find . \( -path '*/assets/css/*.css' -o -path '*/blocks/*/style.css' -o -path '*/blocks/*/styles/*.css' \) \
		-not -path './node_modules/*' -not -path './vendor/*' 2>/dev/null | tr '\n' ' ')
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
			# R10 — pre-existing 1.1.0 tech debt, queued for refactor.
			# These admin sites use wp.media (Email Settings logo upload),
			# settings-nav UI scripts that depend on i18n strings inlined
			# via esc_js(), or board-form drag-handle JS. Migrating to
			# enqueued + wp_localize_script is non-trivial and out of scope
			# for 1.1.0. Tracked in docs/qa/REFACTOR_NEEDED.md § R10.
			*admin/class-email-settings.php) continue ;;
			*admin/class-admin-meta-boxes.php) continue ;;
			*admin/class-admin-settings.php) continue ;;
			*admin/class-admin-boards.php) continue ;;
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
			*core/abilities-api-polyfill.php) continue ;; # the polyfill IS the chokepoint
			*modules/antispam/*) continue ;;            # uses wcb_manage_settings cap directly
		esac
		# Match real call sites only. grep -n on a single file outputs
		# `<linenum>:<text>` — no filename prefix. Filter out:
		#   1. phpcs:ignore lines (existing carve-out)
		#   2. lines whose text portion starts with `*` (docblock continuation),
		#      `//` (single-line comment), or `#` (rare in PHP) — they only
		#      MENTION the function, don't call it.
		#   3. 2-arg per-object meta-cap checks like
		#      current_user_can( 'edit_post', $post_id ) — Abilities API is
		#      global-scope only and doesn't replace WordPress's object-scoped
		#      meta caps (edit_post, delete_post, read_post, edit_user, etc.).
		# Match the bare function only — exclude prefixed variants like
		# `bp_current_user_can` (BuddyPress) which are integration-specific
		# and out of scope for this rule.
		hits=$(grep -nE '(^|[^a-zA-Z0-9_])current_user_can\s*\(' "$f" 2>/dev/null \
			| grep -vE '//[[:space:]]*phpcs:ignore' \
			| grep -vE '^[0-9]+:[[:space:]]*(\*|//|#)' \
			| grep -vE "current_user_can\(\s*['\"](edit|delete|read)_(post|page|user|comment|term)['\"]\s*," || true)
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

# --- Rule 7: release-integrity — every WCB class autoloads + ships ---
# Whole-tree structural check (fast): catches autoloader dir/namespace
# mismatches (a hyphenated module dir the autoloader can't find) and classes
# whose file .distignore strips from the release zip ("class not found" in
# production but fine in dev). Runs in every mode.
if INTEGRITY="$( php "$ROOT/bin/check-class-paths.php" 2>&1 )"; then
	ok "Rule 7: release-integrity (WCB classes autoload + ship)"
else
	echo "$INTEGRITY" | sed 's/^/    /'
	report "Rule 7: release-integrity — a class won't autoload or is stripped from the release zip"
fi

# --- Rule 8: design-system contracts (token namespace + dual-context fallbacks) ---
# Catches the two CSS classes of bug that shipped in 1.5.0 and that WPCS/PHPStan
# cannot see:
#   (a) parallel token namespace — the admin CSS must use the canonical
#       --wcb-primary/--wcb-contrast/--wcb-base/--wcb-warning (+ --wcb-bg-subtle/
#       --wcb-surface), never the legacy --wcb-accent/--wcb-text/--wcb-bg/--wcb-warn
#       aliases (UI-001).
#   (b) dual-context CSS — files that render OUTSIDE the .wcb-admin / :root token
#       scope (e.g. application-detail widgets in post.php meta boxes) must keep a
#       hex fallback on every token, or the colours collapse (application-detail).
if [ -n "${CSS_FILES:-}" ]; then
	# Match real syntax only — token immediately followed by ) , : or ; (a
	# var() use or a custom-property definition). A space/slash after the name is
	# prose (e.g. a comment documenting the migration) and must not trip the rule.
	LEGACY=$(grep -nE -- '--wcb-(accent|text|bg|warn|bg-secondary|bg-tertiary|accent-hover|accent-light)[),:;]' $CSS_FILES 2>/dev/null || true)
	if [ -n "$LEGACY" ]; then
		echo "$LEGACY" | sed 's/^/    /'
		report "Rule 8a: legacy parallel token name — use canonical --wcb-primary/--wcb-contrast/--wcb-base/--wcb-warning (+ --wcb-bg-subtle/--wcb-surface)"
	else
		ok "Rule 8a: single canonical token namespace"
	fi

	# Dual-context files: render where the token vars may be undefined, so a bare
	# var(--wcb-*) (no fallback) is a bug. Add files to this pattern as new
	# meta-box / shortcode-reused widgets appear.
	DUAL=$(printf '%s\n' $CSS_FILES | grep -E 'admin/application-detail\.css$' || true)
	if [ -n "$DUAL" ]; then
		NOFB=$(grep -nE 'var\(\s*--wcb-[a-z0-9-]+\s*\)' $DUAL 2>/dev/null || true)
		if [ -n "$NOFB" ]; then
			echo "$NOFB" | sed 's/^/    /'
			report "Rule 8b: dual-context CSS has a bare var(--wcb-*) — it renders outside the token scope, add a hex fallback: var(--token, #hex)"
		else
			ok "Rule 8b: dual-context CSS keeps hex fallbacks"
		fi
	fi
fi

# --- Rule 9: AbstractEmail contract frozen — no new abstract methods ---
# Pro email classes (shipped separately, updated separately) extend
# WCB\Modules\Notifications\AbstractEmail. Any abstract method added after the
# 1.5.0 contract fatals every install whose Pro copy lags behind Free — Free
# auto-updates from wp.org, Pro updates via license, so the staggered pair is
# the NORMAL case in the field (the 1.6.0 get_default_body/get_merge_tags
# additions broke exactly this way). New contract methods must be concrete
# with a safe default body.
ABSTRACT_EMAIL="modules/notifications/class-abstract-email.php"
if [ -f "$ABSTRACT_EMAIL" ]; then
	FROZEN="get_id get_title get_recipient get_default_subject boot"
	EXTRA=""
	while read -r method; do
		case " $FROZEN " in
			*" $method "*) ;;
			*) EXTRA="$EXTRA $method" ;;
		esac
	done <<-EOF
	$(grep -oE 'abstract public function [a-z_]+' "$ABSTRACT_EMAIL" | awk '{print $4}')
	EOF
	if [ -n "$EXTRA" ]; then
		echo "    new abstract method(s):$EXTRA"
		report "Rule 9: AbstractEmail gained an abstract method beyond the frozen 1.5.0 contract — older Pro builds fatal on load; ship a concrete safe default instead"
	else
		ok "Rule 9: AbstractEmail abstract contract frozen at the 1.5.0 five"
	fi
fi

[ "$FAILED" -eq 0 ] && [ "$QUIET" -eq 0 ] && echo "coding-rules: OK"
exit "$FAILED"
