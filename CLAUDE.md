# WP Career Board — Claude Code Rules

> **READ FIRST:** Read [`audit/manifest.summary.json`](audit/manifest.summary.json) (≤6 KB index) before opening the full [`audit/manifest.json`](audit/manifest.json). The summary is schema v2.2, last refreshed by hand on 2026-10-13 and stamped plugin version 1.8.0, `db_version` 1.4.3 (matches `Install::DB_VERSION`); the code is at 1.8.2 on branch `1.8.3`, so **a refresh is due** (`/wp-plugin-onboard --refresh`). Counts as the summary records them: 56 REST routes · 273 hook firings (189 unique) · 3 tables · 13 capabilities · 17 blocks · 2 shortcode call sites · 5 WP-CLI commands · 9 admin pages · 11 cron hooks · 45 services · 0 AJAX handlers (REST-only). The live REST catalogue, [`docs/api/openapi.json`](docs/api/openapi.json) (regenerated and diffed by `composer openapi-fresh`), lists 59 paths / 84 route+method entries, so the manifest's REST count already lags it. Static-analysis findings recorded in the summary: 1 REST↔JS contract flag, **verified FALSE POSITIVE, not a bug** (T27): `assets/js/admin.js:183` `data.reason` is the OUTBOUND POST body for `/jobs/{id}/reject`, not a read of the `/employers/{id}/jobs` GET response, and the check's 50-line proximity heuristic conflates the two nearby route references; do not "fix" it. Otherwise 0 dead listeners, 0 grid-track overflow risks, 11 rest-hang risks (admin-only `admin.js`/`wizard.js`/`emails.js`/`application-detail.js` apiFetch/fetch calls; every block `view.js` fetch goes through the `@wcb/fetch` helper with a 15s AbortController), see [`audit/wppqa-baseline-2026-05-17/SUMMARY.md`](audit/wppqa-baseline-2026-05-17/SUMMARY.md). Cross-plugin coupling at [`audit/derived/cross-plugin-coupling.json`](audit/derived/cross-plugin-coupling.json) (computed 2026-05-17): Pro consumes 41 Free hooks. See also [`audit/FEATURE_AUDIT.md`](audit/FEATURE_AUDIT.md), [`audit/CODE_FLOWS.md`](audit/CODE_FLOWS.md), [`audit/ROLE_MATRIX.md`](audit/ROLE_MATRIX.md).

## QA artifacts — single index (no searching required)

Everything QA-related lives in three directories. Touch nothing else for routine release work.

| What | Where | When to use |
|---|---|---|
| **App parity gate (member-facing)** | [`docs/APP-PARITY.md`](docs/APP-PARITY.md) → `careerboard-app/docs/FEATURE-COVERAGE.md` | **Before shipping any member-facing change, and before tagging.** Every capability a member has on the website mapped to its app surface, gated at zero `Missing`. Canonical copy lives in the app repo on the fleet-wide path so every Wbcom app's status is findable in the same place; this repo carries a pointer plus the rules for when plugin work touches it. |
| **Common use-case catalog (Pareto pre-release checklist)** | [`docs/qa/COMMON_USE_CASES.md`](docs/qa/COMMON_USE_CASES.md) | **START HERE before every release.** The 80–90% of flows real users hit, grouped by actor (Job Seeker / Job Poster / Admin), tiered by frequency, each row mapped to its journey. Refresh procedure + 🆕-since-last-release flags live in the doc. Pro delta mirrors it. |
| **Docs-level walkthroughs (20, by actor)** | [`docs/qa/walkthroughs/`](docs/qa/walkthroughs/) | Human-runnable end-to-end walkthroughs, one per catalog use case: `seeker/` (7), `poster/` (5), `admin/` (8). The *how* behind the catalog's *what*. No script parses them (`bin/check-qa-portability.sh` only checks they pin no machine). Regression sentinels stay in `audit/journeys/`. |
| **Feature walkthroughs (25 + README)** | [`docs/journeys/`](docs/journeys/) | One browser walkthrough per feature through every role (anonymous, candidate, employer, admin). Read by people and agents only; nothing in `bin/` or `composer` reads them and the route gate does not cover them. |
| **Plugin facts for the smoke skill** | [`docs/qa/qa-config.json`](docs/qa/qa-config.json) | Edit when slug / version constant / personas / fixture-cleanup SQL / debug-log whitelist change. Read by the global `/wp-plugin-smoke` skill. |
| **Sonnet+Playwright runbook (sections A–H + 129 D-rows)** | [`docs/qa/AGENT_SMOKE_RUNBOOK.md`](docs/qa/AGENT_SMOKE_RUNBOOK.md) | Per-release walk (A fresh install, B upgrade, C core flows, D regression guards, H empty-state and adversarial data, E Pro extensions, F cross-browser/RTL/a11y, G post-release monitoring); describes customer contracts (not scripts). Count the D-rows with `grep -c '^\| D\.' docs/qa/AGENT_SMOKE_RUNBOOK.md`. Add a D-row in the same PR as every customer-visible bug fix. |
| **Human pre-release checklist (90 min, 4 personas)** | [`docs/qa/PRE_RELEASE_SMOKE.md`](docs/qa/PRE_RELEASE_SMOKE.md) | When a human walks the release pre-tag. |
| **15-section release gate** | [`docs/qa/QA_RELEASE_CHECKLIST.md`](docs/qa/QA_RELEASE_CHECKLIST.md) | Final gate: branch hygiene → version triangulation → static analysis → tests → security → packaging → install-in-anger → tag. |
| **Per-template surface check** | [`docs/qa/UX_AUDIT.md`](docs/qa/UX_AUDIT.md) | When a release touches UI; per-view × per-persona × per-viewport × per-theme matrix. |
| **Architectural debt + queued refactors** | [`docs/qa/REFACTOR_NEEDED.md`](docs/qa/REFACTOR_NEEDED.md) | R1–R11, each entry carries a status and the evidence for it. Done: R1, R2, R3, R4, R5, R8, R10. Open: R6, R7 (manifest refresh), R9 (partly done), R11 (policy). |
| **Open improvements** | [`docs/qa/IMPROVEMENT_BACKLOG.md`](docs/qa/IMPROVEMENT_BACKLOG.md) | The one list of work that is still open, including what was carried over from the retired remediation and long-term plans. |
| **Finished QA cycles (history, not contracts)** | [`audit/evidence/`](audit/evidence/) | Dated records: 1.5.1 bug cards / verify tracker / walk findings / remediation plan, 1.7.0 and 1.7.1 findings, CSS dedup audit, 1.8.2 hand-off, the old remediation and long-term plans. Do not update them to match new code. |
| **Customer-flow journey sentinels (98 files: 88 sentinels + 10 `walkthrough-*`)** | [`audit/journeys/`](audit/journeys/) | One folder per area: `customer/` (48, 7 of them walkthroughs), `admin/` (26, 3 walkthroughs), `security/` (16), `system/` (8). Each is a 5+ step Markdown contract; bug fixes update the matching journey in the same PR. Recount with `ls audit/journeys/*/*.md \| wc -l`. README at [`audit/journeys/README.md`](audit/journeys/README.md), template at [`audit/journeys/.template.md`](audit/journeys/.template.md). |
| **Three walkthrough layers** | `audit/journeys/` · `docs/qa/walkthroughs/` · `docs/journeys/` | `audit/journeys/` = regression sentinels, the only layer a script reads (`bin/run-journeys.sh` selects by `priority`, `bin/check-journey-routes.php` checks its routes); the release smoke (global `/wp-plugin-smoke`) is what gates a tag. `docs/qa/walkthroughs/` = human-runnable by actor, one per catalog use case. `docs/journeys/` = feature walkthroughs through every role. The `walkthrough-*.md` files in `audit/journeys/` overlap the other two on purpose, so do not delete or merge them. |
| **Presentation gate (browser, two layers)** | [`bin/presentation-gate.cjs`](bin/presentation-gate.cjs) + [`docs/qa/presentation-views.json`](docs/qa/presentation-views.json) + [`docs/qa/presentation-review.md`](docs/qa/presentation-review.md) | Layer 1, run by `bin/local-ci.sh`: fails on overflow, stray left edges, mid-word breaks, clipping, contrast and wp-admin markup at 1440/390 × light/dark, after self-testing each check on `tests/presentation/planted.html`. Layer 2, a Sonnet judgment review of its screenshots per the rubric: Ship / Polish / Rework, with suggestions for site owners. This is the one sanctioned Playwright script in the repo (owner decision 2026-10-13); add a view whenever you add a frontend surface. Its views need the repo's own data: seed with `wp eval-file bin/seed-demo-data.php` and `wp eval-file bin/seed-qa-fixtures.php` first (`seed-demo-data.php teardown` removes the demo set). **Entry points:** `bin/local-ci.sh` runs it (when node, `npm install` and a reachable site exist); `bin/build-release.sh` runs `composer ci:no-journeys`, which does NOT include it, so a release does not run the presentation gate unless `bin/local-ci.sh` was run. |
| **Drift-only coverage gate** | [`audit/qa-coverage.json`](audit/qa-coverage.json) | Auto-generated by `composer qa-coverage`. Pre-commit hook fails if `uncovered_total` grows. |
| **Pre-commit + pre-push hooks** | [`.githooks/pre-commit`](.githooks/pre-commit) + [`.githooks/pre-push`](.githooks/pre-push) | Activate once per clone: `composer install-hooks`. Both only do the cheap pass by default (see Local CI below). |
| **Test-runner scripts** | [`bin/run-journeys.sh`](bin/run-journeys.sh), [`bin/coding-rules-check.sh`](bin/coding-rules-check.sh), [`bin/qa-coverage-check.php`](bin/qa-coverage-check.php), [`bin/qa-stub-gen.php`](bin/qa-stub-gen.php), [`bin/seed-qa-fixtures.php`](bin/seed-qa-fixtures.php) | Run via composer scripts (below) — direct invocation is for debugging. |
| **Smoke + ci entry points** | [`composer.json`](composer.json) — see "Local CI pipeline" section below | `composer ci`, `composer ci:no-journeys`, `composer ci:quick`, `composer journeys`, `composer journeys:routes`, `composer qa-coverage`, `composer install-hooks`; plus `bin/local-ci.sh`. |
| **Smoke skill (global, NOT plugin-scoped)** | `/wp-plugin-smoke` (in `~/.claude/skills/`) | One skill across the Wbcom portfolio. Reads this plugin's `qa-config.json`. Trigger: ask Claude Code to "run /wp-plugin-smoke combo mode for wp-career-board". |
| **Bug tracking** | Basecamp project `46502739` (WP Career Board) — Bugs column `9691964821`, Ready for Testing `9681375659`, In Testing `9681375773`, Done `9681375111` | New bug → file in Bugs. Fix landed → move to Ready for Testing. QA verified → Done. |

> **Pair plugin:** `wp-career-board-pro` ships in lockstep at the same version. Its CLAUDE.md mirrors this index for Pro-side artifacts. Both share Basecamp project `46502739`.

## MANDATORY: WPCS MCP Before Every Commit

Use MCP tools — do NOT run phpcs manually:

```
1. mcp__wpcs__wpcs_fix_file        → auto-fix each changed file
2. mcp__wpcs__wpcs_check_staged    → gate: must return 0 errors
3. mcp__wpcs__wpcs_phpstan_check   → must return 0 errors
4. mcp__wpcs__wpcs_quality_check   → full quality pass
5. git commit only after all pass
```

**Zero WPCS errors = required to close any task. No exceptions.**

---

## Plan Adherence

- Plan: `docs/PLAN.md`
- Build only what the current task defines — nothing more
- Every file created/modified must appear in the task's `Files:` section
- Mark task `🔄` on start, `✅` when both spec + quality reviews pass
- Update progress tracker table in `docs/PLAN.md` on completion

---

## Architecture Rules

### Prefix
All globals use `wcb_` — functions, options, hooks, meta keys, CPTs, taxonomies, DB tables.
Namespace: `WCB\` (maps via `spl_autoload_register`).
REST namespace: `wcb/v1`.

### Permissions — Abilities API only

Ability slugs use the WP 6.9 `namespace/slug` format. Free uses the `wcb/`
namespace; Pro extends the same namespace (no `wcbp/` fork). The role cap
underneath stays snake_case (`wcb_post_jobs` in `class-roles.php`) — that's
a separate namespace from abilities.

```php
// Define
wp_register_ability( 'wcb/post-jobs', array(
    'category'            => 'wcb',
    'label'               => __( 'Post Jobs', 'wp-career-board' ),
    'description'         => __( 'Allows an employer to submit job listings.', 'wp-career-board' ),
    'permission_callback' => static function (): bool {
        $user = wp_get_current_user();
        return $user && ( $user->has_cap( 'wcb_post_jobs' ) || $user->has_cap( 'manage_options' ) );
    },
    'execute_callback'    => static function (): bool {
        return true;
    },
) );

// Check (uses our polyfill at core/abilities-api-polyfill.php — wraps
// wp_get_ability($name)->check_permissions() until WP core ships the helper)
if ( ! wp_is_ability_granted( 'wcb/post-jobs' ) ) { ... }

// FORBIDDEN
current_user_can( 'manage_options' );  // never
current_user_can( 'edit_posts' );      // never
```

Note: WPCS does not yet recognize `wp_is_ability_granted` as a permission
function. Until a custom sniff is configured, gating sites get a single
`phpcs:ignore` comment citing the polyfill.

### REST API
- All endpoints extend `WCB\Api\REST_Controller`
- Every route: `permission_callback`, `validate_callback`, `sanitize_callback`
- Return `WP_Error` — never throw exceptions
- Schema in `get_item_schema()` — no ad-hoc arrays
- Two documented carve-outs (setup wizard + moderation module) call `register_rest_route()` directly while still extending the base — see `docs/HOOKS.md` § REST controller carve-outs. New routes should still ship as Endpoint classes.

### Database
- Table creation via `dbDelta()` only
- All queries via `$wpdb->prepare()`

### Frontend — Interactivity API only
- `@wordpress/interactivity` store + directives (`data-wp-*`)
- Zero jQuery, zero `admin-ajax.php`, zero page reloads
- All data from `/wp-json/wcb/v1/` REST endpoints

### Blocks
```
blocks/{name}/
├── block.json    # apiVersion: 3, interactivity support
├── render.php    # wp_interactivity_state()
├── view.js       # Interactivity API store
└── style.css
```
- `register_block_type_from_metadata()` only

### Generated assets - never hand-kept
- `-rtl.css` twins are written by `npm run rtl` (`grunt rtl`, rtlcss) for every stylesheet with direction-dependent rules, and again inside `bin/package-dist.sh`, so a release zip always carries fresh ones. They are gitignored; `core/class-rtl.php` switches a style to its twin only when the file exists, so a style with no twin uses the LTR file and nothing 404s.
- Never register `rtl` per style with `wp_style_add_data()` and never commit a `-rtl.css` or `.min.css/.js` (vendored third-party files excepted). `bin/coding-rules-check.sh` Rule 11 fails on a tracked one.
- To test RTL locally, run `npm run rtl`, then load the site in an RTL language.

### Escaping
```php
esc_html_e( 'string', 'wp-career-board' );  // output
esc_attr( $value );                          // attributes
wp_kses_post( $html );                       // rich content
// Never: echo $raw; or _e() without esc_
```

### PHP
- PHP 8.1+ — typed properties, enums, named args, match expressions
- Type declarations on all public methods
- `declare( strict_types=1 )` in every file

### File Header (every PHP file)
```php
<?php
/**
 * {Description}
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\{Sub\Namespace};

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
```

---

## Code Quality — No AI Vibe Coding

This codebase must read like it was written by a senior WordPress engineer, not generated by AI.

- **No over-engineering** — don't add abstractions, helpers, or utilities for one-time operations
- **No defensive bloat** — don't validate things that can't go wrong; trust internal guarantees
- **No filler comments** — only comment where the logic is non-obvious; never restate what the code does
- **No trailing summaries** — don't add "end of file" comments or changelog-style code comments
- **Consistent patterns only** — every new method/class must follow the same pattern as adjacent existing code
- **Minimal surface area** — if three lines of code work, don't extract them into a method
- **Pro must mirror Free** — `wp-career-board-pro/` must use identical architecture: same namespace convention (`WCBPro\`), same WPCS rules, same Interactivity API patterns, same REST base class, same file/hook naming conventions

**Before writing any new code, read 2–3 adjacent existing files first** to match style exactly.

---

## Do NOT Build

- Anything beyond current task scope
- jQuery or classic AJAX (`admin-ajax.php`)
- Inline `<style>` or `<script>` in PHP — use `wp_enqueue_*`
- Hardcoded strings without i18n wrapper (text domain: `wp-career-board`)
- Raw SQL without `$wpdb->prepare()`
- Pro features — those live in `wp-career-board-pro/`

---

## Commit Format

```
feat(wcb): T{N} — {description}
fix(wcb): T{N} — {description}
```

---

## Plugin Info

- **Slug:** `wp-career-board`  |  **Prefix:** `wcb_`  |  **Namespace:** `WCB\`
- **Min WP:** 6.9  |  **Min PHP:** 8.1
- **Plan:** `docs/PLAN.md`  |  **Spec:** `docs/DESIGN-SPEC.md`
- **Basecamp project:** `46502739` ("WP Career Board") — Bugs column `9691964821`

---

## Local CI pipeline

Self-contained gates; none depend on GitHub Actions (Free's workflows are disabled).

```bash
composer install-hooks    # one-time per clone - activates .githooks/pre-commit + .githooks/pre-push
composer ci               # composer ci:no-journeys, then composer journeys (journeys only print, see below)
composer ci:no-journeys   # every static and runtime check below; what bin/build-release.sh runs
composer ci:quick         # PHPStan + coding-rules only (tight loop)
bin/local-ci.sh           # php-lint, WPCS, PHPStan, size-limit, i18n gates, qa-portability, rest-reachability + openapi-fresh (needs a live WP), presentation gate (needs node + a reachable site)
```

What `composer ci:no-journeys` actually runs (read `composer.json`; stage order):

| Stage | Script | Catches |
|---|---|---|
| 1 | `phpstan` | static type errors (baseline-aware) |
| 2 | `phpcs` | WordPress coding standards |
| 3 | `coding-rules` (`bin/coding-rules-check.sh --full`) | inline JS/CSS, em-dash i18n, hardcoded JS strings, `current_user_can` outside chokepoint, ability-slug format, raw `$wpdb->query`, tracked `-rtl.css` / `.min` files |
| 4 | `ignore-budget` | growth of `phpcs:ignore` / `@phpstan-ignore` suppressions past `audit/ignore-budget.json` |
| 5 | `route-callers` | registered REST route with no caller (allowlist `bin/route-callers-allowlist.txt`) |
| 6 | `dead-code` | `@deprecated` classes nothing calls |
| 7 | `manifest-consistency` | `audit/manifest.json` disagreeing with itself or the code |
| 8 | `qa-coverage` | manifest-driven coverage drift; `uncovered_total` can shrink, never grow |
| 9 | `qa-portability` | machine-pinned paths or hostnames in walkthroughs and journeys |
| 10 | `pro-decoupling` | Free code reading Pro identifiers, or raw `get_option('wcb_settings')` outside the accessor (documented exceptions in the script header) |
| 11 | `rest-reachability` | every GET route dispatches without a 5xx and every route has a `permission_callback` (needs a live WP install) |
| 12 | `openapi-fresh` | `docs/api/openapi.json` regenerated and unchanged (needs a live WP install) |
| 13 | `i18n-pot-fresh` | the POT is current (`bash bin/make-pot.sh --check`, needs WP-CLI) |

`composer journeys` (`bin/run-journeys.sh --priority=critical`) selects the critical journeys, prints them and writes `manual_required` reports to the git-ignored `audit/journey-runs/`; it does not drive a browser and does not fail the build. `bin/check-pro-decoupling.sh` (Free stays Pro-blind, settings read through the accessor) runs as `composer pro-decoupling`, inside `ci:no-journeys` and `bin/local-ci.sh`; its header lists the documented exceptions.

**Hooks.** `.githooks/pre-commit` runs `php -l` on staged PHP files only; the full gate (PHPStan, WPCS, coding-rules, qa-coverage drift) runs when `FULL_CI=1 git commit`. `.githooks/pre-push` does nothing by default and runs `bin/local-ci.sh` (else `composer ci:no-journeys`) only when `FULL_CI=1 git push`. Full CI otherwise runs once per release in `bin/build-release.sh` step 4 (`composer ci:no-journeys`).

**Bypass for emergencies only:** `SKIP_LOCAL_CI=1 git push` (pre-push), `COVERAGE_SKIP=1 git commit ...` (pre-commit coverage gate), `git commit --no-verify`.

## Customer journeys

Bug fixes that survive a refactor are journey-covered. See [`audit/journeys/README.md`](audit/journeys/README.md) for the schema and the three walkthrough layers. When a new bug is fixed, add or update the journey that would have caught it. The journey IS the regression test.

```bash
composer journeys:list      # what's available
composer journeys:dry-run   # what would run at priority=critical
composer journeys:stale     # journeys with last_verified > 90 days
composer journeys:routes    # every /wcb/v1 path in audit/journeys resolves to a registered route (needs wp)
composer journeys           # print the critical journeys and write manual_required reports
```

Critical journeys today: 23 (`grep -l 'priority: critical' -r audit/journeys`; the template and README also match the text, ignore them). Among them `apply-to-job`, `employer-post-job`, `paid-board-job-charged-once`, `admin-settings-jobs-tab-save` and `walkthrough-find-jobs-and-apply`. `anonymous-can-apply-cleanly` is `high`, not critical. A journey's `last_verified` moves only when someone walks it against the code; run `composer journeys:stale` for the ones overdue.

## Pre-release smoke

Use the global `/wp-plugin-smoke` skill - one skill across the Wbcom portfolio, no per-plugin skill duplication. It reads `docs/qa/qa-config.json` (plugin facts) and `docs/qa/AGENT_SMOKE_RUNBOOK.md` (sections A-H, with the Pro supplement in the Pro repo). It writes `docs/qa/.last-smoke-pass.json` for combo mode and `docs/qa/.last-smoke-pass-free.json` for free-only mode; the Pro supplement report `.last-smoke-pass-pro.json` is written in the Pro repo's `docs/qa/`. `bin/build-release.sh` reads `.last-smoke-pass.json` (Free) or `.last-smoke-pass-pro.json` (Pro) and blocks packaging when it is missing, for another version, older than the code, or lists failures; the `-free` report is not read by the gate. Trigger: ask Claude Code to "run /wp-plugin-smoke combo mode for wp-career-board".
