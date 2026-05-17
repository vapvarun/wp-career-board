# WP Career Board — QA Release Checklist

> **The final gate before tagging a release.** Every row must pass, no exceptions.
> This is the backend counterpart to `PRE_RELEASE_SMOKE.md` (frontend/browser).
> Together they guarantee: code quality + feature behavior + safe packaging.

**Target time:** 45 minutes end-to-end (plus the 90-min browser smoke).

> Pair plugin: `wp-career-board-pro` ships in lockstep — every row that mentions a version, gate, or zip applies to both. Fail one, both are blocked.

---

## 0 — Branch hygiene

- [ ] On a named release branch (`release/<version>`), NOT on `main`
- [ ] `git status` clean — no uncommitted changes
- [ ] `git pull` on the branch — up to date with origin
- [ ] `main` merged into release branch (or rebased) — no stale base
- [ ] No `.DS_Store`, `.idea/`, `.vscode/`, `node_modules/`, `vendor/` staged for commit

```bash
cd /Users/varundubey/Local\ Sites/forums/app/public/wp-content/plugins/wp-career-board
git status
git fetch origin
git log --oneline origin/main..HEAD | head -20   # what's shipping
```

## 1 — Version triangulation

WP Career Board keeps the version in multiple places. Every place must match.

- [ ] `wp-career-board.php` header `Version:` comment equals `<version>`
- [ ] `wp-career-board.php` `define( 'WCB_VERSION', '<version>' );` matches
- [ ] `readme.txt` `Stable tag: <version>` matches (once a readme.txt is added — currently the plugin has no .org readme)
- [ ] `package.json` `version` matches
- [ ] `composer.json` `version` matches (if/when composer.json is added — free has none today)
- [ ] `CHANGELOG.md` has a `## <version> — YYYY-MM-DD` entry with real release notes
- [ ] `wp-career-board-pro` version (header + `WCBP_VERSION`) also equals `<version>` (lockstep)
- [ ] `wp-career-board-pro` version constant matches free at runtime — `wp eval 'echo WCB_VERSION . " " . WCBP_VERSION;'` prints two identical values

Fast check:
```bash
grep -rE "Version:|define.*WCB_VERSION|define.*WCBP_VERSION|Stable tag" \
  /Users/varundubey/Local\ Sites/forums/app/public/wp-content/plugins/wp-career-board \
  /Users/varundubey/Local\ Sites/forums/app/public/wp-content/plugins/wp-career-board-pro \
  | grep -v vendor | grep -v node_modules
```

Every printed line should show the same `<version>`.

## 2 — Static analysis

Run from each plugin's root.

### WPCS (WordPress Coding Standards)

- [ ] `composer phpcs` (or `vendor/bin/phpcs --standard=phpcs.xml`) — 0 errors, 0 warnings on changed files (free)
- [ ] Same for Pro
- [ ] No new suppressions (`// phpcs:ignore`) added this release without a comment explaining why

```bash
vendor/bin/phpcs --standard=phpcs.xml --report=summary
```

### PHPStan

- [ ] `composer phpstan` — level clean per `phpstan.neon`, or only entries already in the baseline
- [ ] Baseline not grown this release (or the diff is documented in CHANGELOG)

```bash
vendor/bin/phpstan analyse --memory-limit=2G
diff <(git show HEAD:phpstan-baseline.neon 2>/dev/null || echo "") phpstan-baseline.neon
```

### PHP lint (syntax)

```bash
find . -name "*.php" \
  -not -path "*/vendor/*" -not -path "*/node_modules/*" -not -path "*/build/*" \
  -exec php -l {} \; 2>&1 | grep -v "No syntax errors"
```

- [ ] No output (means no syntax errors in any PHP file)

### Plugin Check (PCP)

- [ ] `wp plugin check wp-career-board --format=json` — no errors
- [ ] `wp plugin check wp-career-board-pro --format=json` — no errors (Pro will skip the .org-only checks; flag any base64_decode warnings with a phpcs:ignore + reason)

## 3 — Tests

### PHPUnit

- [ ] `composer test` — all tests pass (free)
- [ ] Same for Pro (when Pro acquires its own suite — currently free has `tests/` directory; Pro does not)
- [ ] PHP matrix covers the declared floor (`Requires PHP: 8.1`) AND current stable (8.3 / 8.4 minimum)
- [ ] WP matrix covers declared `Requires at least: 6.9` AND current stable AND `latest`

```bash
composer test
```

### Jest / JS tests (if present)

- [ ] `npm test` — all pass
- [ ] No `.only` / `.skip` left in test files

```bash
grep -rE "\.only\(|\.skip\(" tests/ assets/ 2>/dev/null
```

## 4 — Security sweep

### Abilities API + nonce + capability hot-check

The plugin's `CLAUDE.md` mandates Abilities API for permissions. New code must obey this.

- [ ] Every new REST route registered this release calls `wp_is_authorized( 'wcb_<ability>' )` in its `permission_callback` (NOT `current_user_can()` directly per `CLAUDE.md`)
- [ ] Every new admin form calls `wp_verify_nonce()` + capability check on POST handler
- [ ] Every new form output includes `wp_nonce_field()`

Hot-check:
```bash
git diff origin/main...HEAD -- '*.php' | grep -E "^\+.*register_rest_route" | head -20
git diff origin/main...HEAD -- '*.php' | grep -E "^\+.*current_user_can\(" | head -20  # should be EMPTY (Abilities API only)
```

### Escape on output

- [ ] Every echoed variable passes through an escape function (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`)
- [ ] Translations via `esc_html__`, `esc_attr__`, `esc_html_e` (not bare `__` in output context)

```bash
git diff origin/main...HEAD -- '*.php' | grep -E "^\+.*echo \\\$" | grep -v "esc_"
```

### SQL

- [ ] No string concatenation in `$wpdb` queries — all use `$wpdb->prepare()`
- [ ] Table names use `$wpdb->prefix` — no hardcoded `wp_`

### File operations

- [ ] No `file_get_contents` on user-supplied paths
- [ ] Resume / attachment uploads use `wp_handle_upload()` with explicit MIME / size validation
- [ ] No dynamic-code execution functions called with user-supplied data

## 5 — Translations (i18n)

- [ ] `.pot` file regenerated and matches current strings
- [ ] No em-dashes (`—`) inside any `__()`, `_e()`, `_x()`, `_n()`, `esc_html__()` (reads as AI-generated)
- [ ] Text domain consistent: `wp-career-board` (free) / `wp-career-board-pro` (pro)
- [ ] `_n()` used for pluralizable strings (not runtime `if ($count === 1)`)

```bash
grep -rE "(__|_e|_x|_n|esc_html__|esc_attr__|esc_html_e|esc_attr_e)\\([^)]*—" \
  . | grep -v vendor
```

Regenerate pot:
```bash
wp i18n make-pot . languages/wp-career-board.pot
```

## 6 — Readme + Docs

### WordPress.org readme (when applicable)

- [ ] `readme.txt` validates at https://wordpress.org/plugins/developers/readme-validator/
- [ ] `Requires at least: 6.9`, `Tested up to: <current>`, `Requires PHP: 8.1` current
- [ ] `Stable tag` matches `<version>`
- [ ] Changelog entry written for `<version>`
- [ ] Upgrade notice written for `<version>` if behavior changes

### Internal docs

- [ ] `CHANGELOG.md` updated (human-readable, customer-facing)
- [ ] `docs/qa/AGENT_SMOKE_RUNBOOK.md` section D updated with any new regression guards from this release
- [ ] `docs/PLAN.md` updated (per CLAUDE.md plan-adherence rule)
- [ ] Any new public hooks / filters documented in `docs/hooks.md` (or equivalent)

## 7 — Browser smoke gate (external dependency)

- [ ] `wp-career-board/docs/qa/.last-smoke-pass.json` exists
- [ ] Report `release_version` equals `<version>` (and `pro_version` equals `<version>` when combo)
- [ ] Report `ran_at` within the last 24 hours
- [ ] `failures[]` is empty (or only `for`-origin entries documented)
- [ ] `debug_log_issues[]` is empty (no fatals/warnings during walk)
- [ ] `manual_required[]` reviewed — Firefox / Safari iOS flows verified separately by a human

If the report is missing or stale, run the `wp-plugin-smoke` skill (lives in `wp-career-board-pro/.claude/skills/wp-plugin-smoke/`).

## 8 — Packaging dry-run

- [ ] `bin/build-release.sh --output /tmp` (or equivalent) succeeds end-to-end (NOTE: this script doesn't exist yet — Section 8 is gated on adding one; see scaffold instructions in `docs/qa/SCAFFOLDING.md` once added)
- [ ] Resulting zip has NO: `.git/`, `node_modules/`, `tests/`, `.github/`, `bin/`, `phpunit.xml.dist`, `phpcs.xml.dist`, `composer.json` (unless required), `composer.lock`, `package.json`, `package-lock.json`, `.DS_Store`, `Gruntfile.js`, `phpstan.neon*`
- [ ] Resulting zip HAS: `wp-career-board.php`, `readme.txt` (when added), `languages/*.pot`, all module directories under `modules/`, `core/`, `api/`, `admin/`, `assets/`, `blocks/`, `vendor/` (if runtime deps)
- [ ] Zip extracts to a folder named exactly `wp-career-board/` (not `wp-career-board-<version>/`)
- [ ] Zip size reasonable (flag if >2× previous release)

## 9 — Install-in-anger

On a **second clean** Local site (not the development site):

- [ ] Install the generated zip via `wp plugin install /tmp/wp-career-board-<version>.zip --activate`
- [ ] Activation succeeds — no fatal, no PHP warning in debug.log
- [ ] Front-end job-board route (first request after activation) returns HTTP 200
- [ ] DB tables created (see `A.db.tables-and-version` in the agent runbook)
- [ ] Install `wp-career-board-pro` zip — activates, no fatal, Pro-specific features appear
- [ ] Deactivate `wp-career-board` → `wp-career-board-pro` shows the "requires WP Career Board" notice, doesn't fatal

## 10 — Upgrade-in-anger

On a **third clean** site with the **previous stable version** installed + real data:

- [ ] Upload the new zip via "Replace plugin" or WP admin update flow
- [ ] Upgrade succeeds — no fatal
- [ ] DB version option updates (`wp option get wcb_db_version`, `wp option get wcb_pro_db_version`)
- [ ] Pre-existing data still renders on every surface (don't just check one page)
- [ ] Settings preserved (no defaults overwritten)
- [ ] No new `debug.log` entries during the upgrade request
- [ ] Cron events re-registered cleanly (`wp cron event list | grep wcb`)

## 11 — Release metadata

- [ ] Git tag created: `v<version>` (annotated — `git tag -a v<version> -m "..."`)
- [ ] Tag points at the release-branch commit (not `main` yet)
- [ ] GitHub Release drafted with changelog copied from `CHANGELOG.md`
- [ ] Release zip attached to GitHub Release
- [ ] Matching tag on `wp-career-board-pro` repo with same `v<version>`

## 12 — Post-tag checks (first push)

- [ ] CI on the tag is green (PHPUnit matrix, PHPStan, WPCS, Lint)
- [ ] Release branch merged back to `main` (per repo convention)
- [ ] `main` branch protection rules intact (no accidental direct push)

## 13 — Customer-facing publish

Only once sections 0–12 are ticked:

- [ ] Wbcom store product page updated with new version + changelog
- [ ] Docs website synced (via `mcp__wbcom-docs__publish_product_docs`, `sync_to_live: true`)
- [ ] Customer update email drafted (with the real changelog, not marketing fluff) — optional per release
- [ ] Internal Slack post to `#releases` with zip link + changelog link + smoke report link

## 14 — Post-release monitor (first 24h)

- [ ] `wp-content/debug.log` on your own production site clean of new warnings/notices/fatals
- [ ] Zoho Desk / Crisp — no "broke after update" tickets in first 24h
- [ ] Basecamp Bugs column — no new cards matching the release
- [ ] Analytics / activity signal continues (no "zero events" sign of breakage)

If any post-release signal is red → open a `<version>.1` patch cycle immediately.

---

## Failure protocol

If ANY row in sections 0–11 fails:

1. **Stop.** Do not tag or publish.
2. Fix in the release branch.
3. Re-run from Section 0 (branch hygiene) — a fix can regress earlier sections.
4. Only tag after the entire checklist is green in one continuous run.

## Emergency patch

For a genuinely emergency patch (security CVE, dataloss bug reaching production):

- The `--skip-browser-smoke` flag on `build-release.sh` (once added) is allowed
- But sections 0–6 and 8–11 are still non-negotiable
- Document the skipped browser smoke in the release notes with a reason

## Version-specific additions

Append a section below for every release with the specific extra checks added that cycle. After 2 clean releases of a row, graduate it into the main checklist above.

### 1.2.0 — 2026-05-15

- [ ] `POST /wcb/v1/admin/emails/test` returns `{sent: bool, to: string, logged: int}` — verify with curl or REST client as admin.
- [ ] `wp_wcb_notifications_log` test row has `status` = `sent_test` (not `sent`) and `payload` JSON has `"is_test": true`.
- [ ] `GET /wcb/v1/jobs?meta__wcb_<custom_key>=<val>` returns filtered results without `wcb_jobs_allowed_meta_filters` hook registered.
- [ ] `WCB_VERSION` constant = `1.2.0` in `wp-career-board.php`; `Stable tag: 1.2.0` in `readme.txt`; `package.json` `version` = `1.2.0`.
- [ ] languages/wp-career-board.pot `Project-Id-Version` = `1.2.0`; string count ~ 1172.
- [ ] docs/HOOKS.md lists `wcb_board_options_for_employer` and `wcb_page_needs_frontend_assets` with correct signatures.
- [ ] docs/HOOKS.md `wcb_jobs_allowed_meta_filters` row notes the `_wcb_*` default-allow behavior.
