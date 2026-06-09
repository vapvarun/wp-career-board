# QA scaffolding — wp-career-board

This document tracks what the `wp-plugin-release-qa` skill scaffolded into this plugin and what remains for a human to wire in. Audited against v1.1.0 source 2026-05-09.

## What's in place

- `docs/qa/PRE_RELEASE_SMOKE.md` — 90-min human walkthrough
- `docs/qa/AGENT_SMOKE_RUNBOOK.md` — Sonnet + Playwright deterministic runbook (A–F, all 14 Pro modules in E with verified file/hook counts + admin slugs); D contains 14 real regression rows — 7 original (9871740742, 9866553120, 9818132111, 9872024322, T3.5, F-1, abilities-init-hook) + 6 added in 1.2.0 (9895205013, 9891012864, 9890815047, 9890919239, 9890885030, 9891577445) + D.pwa-icon-404 (936c04a)
- `docs/qa/UX_AUDIT.md` — per-template surface check
- `docs/qa/QA_RELEASE_CHECKLIST.md` — release gate (PHPUnit, PHPStan, WPCS, versions, packaging)
- `bin/seed-qa-fixtures.php` — idempotent reseeder (uses verified v1.1.0 meta keys + Pro table schemas; not normally needed once a dev install has sample jobs / applications / companies / resumes seeded)
- `docs/qa/qa.config.json` — plugin facts consumed by the global `/wp-plugin-smoke` skill (slug, version constant, site URL, personas, basecamp IDs, fixture-cleanup SQL, debug-log whitelist). Free + Pro each have their own.
- `wp-career-board-pro/docs/qa/AGENT_SMOKE_RUNBOOK.md` — Pro-only supplement (P1 lockstep, P2 dependency guard, P3 license, P4 module presence with verified admin-surface mapping, P5 updater, P6 Pro DB)
- `wp-career-board-pro/docs/qa/QA_RELEASE_CHECKLIST.md` — Pro-only release supplement
- `wp-career-board-pro/docs/qa/qa.config.json` — Pro counterpart of the config file (declares `extends: wp-career-board` so the smoke skill knows to walk the combo runbook)

> The smoke skill itself is **global** (`/wp-plugin-smoke`) — one skill for every plugin in the Wbcom portfolio. There is no per-plugin smoke `SKILL.md` in `.claude/skills/` (the prior `wp-career-board-smoke` skill was retired during the 2026-05-09 consistency cleanup so the dispatch pattern lives in one place).

## Verified facts (v1.2.0 audit)

- **CPTs (6):** `wcb_job`, `wcb_application`, `wcb_resume`, `wcb_company`, `wcb_board`, `wcb_credit_package`
- **DB tables (12 total):** 3 Free-owned (`wcb_notifications_log`, `wcb_job_views`, `wcb_gdpr_log`) + 9 Pro-owned (`wcb_notifications`, `wcb_credit_ledger`, `wcb_credit_gateway_log`, `wcb_field_groups`, `wcb_field_definitions`, `wcb_field_values`, `wcb_job_boards`, `wcb_job_alerts`, `wcb_application_stages`, `wcb_ai_vectors`)
- **DB version options:** `wcb_db_version` (free) + `wcbp_db_version` (pro) — note `wcbp_` not `wcb_pro_`
- **REST namespace:** `wcb/v1` shared by both plugins (Pro extends, doesn't fork)
- **Custom roles:** `wcb_employer`, `wcb_candidate`
- **Ability slugs:** namespaced format `wcb/post-jobs` (NOT `wcb_post_jobs`) per WP 6.9+ Abilities API; role caps underneath stay snake_case
- **Application stage values (allowlist):** `submitted`, `reviewing`, `shortlisted`, `rejected` (NOT `applied/hired` — those were placeholders in the seeder, fixed)
- **Pro module status:** `complete` = resume; `partial` = ai, alerts, analytics, boards, credits, feed, fields, maps, notifications-bell, notifications-pro; `stub` = migration, pipeline, pwa

## What's still missing

### 1. `bin/build-release.sh` (both plugins)

Neither `wp-career-board` nor `wp-career-board-pro` has a release packaging script today. The smoke gate is wired into the runbook + skill, but until a `bin/build-release.sh` exists the gate runs only as a discipline (manual review of `.last-smoke-pass.json` before tagging).

When you add `bin/build-release.sh`, paste the following block **after** the PHP boot-smoke step and **before** the zip step, and add `--skip-browser-smoke` to the argv loop:

```bash
# argv flag (add to existing flag loop)
SKIP_BROWSER_SMOKE=0
# ...
case "$1" in
    # ... existing flags ...
    --skip-browser-smoke) SKIP_BROWSER_SMOKE=1; shift ;;
esac

# Browser smoke gate — refuses to package unless a fresh green smoke report
# exists. Protects first-hand customer experience: no release ships unless
# a run of docs/qa/AGENT_SMOKE_RUNBOOK.md (dispatched to Sonnet via the
# wp-plugin-smoke skill) reported zero failures and zero
# debug_log_issues.
SMOKE_REPORT="$ROOT/docs/qa/.last-smoke-pass.json"
if [ "$SKIP_BROWSER_SMOKE" -eq 1 ]; then
    echo "WARN: browser smoke gate skipped (--skip-browser-smoke). Not for customer releases."
elif [ ! -f "$SMOKE_REPORT" ]; then
    echo "FAIL: no browser smoke report at $SMOKE_REPORT" >&2
    echo "      Run the wp-plugin-smoke skill first to generate it." >&2
    echo "      Emergency only: rerun with --skip-browser-smoke." >&2
    exit 30
else
    REPORT_VERSION="$(grep -oE '"release_version"[[:space:]]*:[[:space:]]*"[^"]+"' "$SMOKE_REPORT" | head -1 | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' || true)"
    if [ "$REPORT_VERSION" != "$VERSION" ]; then
        echo "FAIL: smoke report version ($REPORT_VERSION) doesn't match release version ($VERSION)" >&2
        echo "      Rerun the wp-plugin-smoke skill against HEAD before packaging." >&2
        exit 30
    fi
    if grep -qE '"failures"[[:space:]]*:[[:space:]]*\[[[:space:]]*\{' "$SMOKE_REPORT"; then
        echo "FAIL: smoke report has failures. Fix them before packaging." >&2
        exit 30
    fi
    if grep -qE '"debug_log_issues"[[:space:]]*:[[:space:]]*\[[[:space:]]*\{' "$SMOKE_REPORT"; then
        echo "FAIL: smoke report recorded debug.log entries during the walk. Fix before packaging." >&2
        exit 30
    fi
    echo "    smoke report OK"
fi
```

The Pro counterpart should also check `WCB_VERSION == WCBP_VERSION` lockstep before zipping (mirror jetonomy/jetonomy-pro/bin/build-release.sh sections 2 + 7b).

### 2. Basecamp wiring (resolved)

Project ID `46502739` ("WP Career Board") is baked into the runbook + smoke skill. Card-table columns:

| Column | ID |
|---|---|
| Triage | `9681375106` |
| Bugs | `9691964821` |
| UI issues | `9696299448` |
| Ready for Testing | `9681375659` |
| In Testing | `9681375773` |
| Done | `9681375111` |

Smoke-walk failure drafts file into the **Bugs** column (`9691964821`). Verified-real drafts are filed by the calling Opus session, never directly by Sonnet.

### 3. Section D — regression guards

D has 14 real rows. Original 7: 9871740742, 9866553120, 9818132111, 9872024322, T3.5, F-1, abilities-init-hook. Added in 1.2.0 (2026-05-15): D.test-email-bridge (9895205013), D.meta-filter-default-allow (9891012864), D.setup-wizard-centering (9890815047), D.company-cards-alignment (9890919239), D.active-filter-spacing (9890885030), D.public-chevron-lucide (9891577445), D.pwa-icon-404 (936c04a). Every customer-visible fix from now on adds a D row in the same PR. After 2 clean releases a row graduates into a numbered C / E step.

### 4. `readme.txt`

The free plugin already ships a `readme.txt` (16k bytes). Pro also has one (10k bytes). The QA checklist's `Stable tag` section is live and applies on the next release.

### 5. Optional: `qa-coverage` gate

The Jetonomy reference also runs a `bin/qa-coverage-check.php` tied to `audit/manifest.json`. The manifest now exists for both plugins (35 free REST endpoints, 37 pro). The gate can be adopted as a follow-on task — copy `wp-content/plugins/jetonomy/bin/qa-coverage-check.php` and tune the test-source globs (`includes/qa/`, `tests/`, `cli/`).

## Reference implementation

When in doubt, mirror Jetonomy:
- `wp-content/plugins/jetonomy/docs/qa/AGENT_SMOKE_RUNBOOK.md`
- `wp-content/plugins/jetonomy/bin/build-release.sh` (sections 6b, 7, 7b)
- `wp-content/plugins/jetonomy-pro/.claude/skills/jetonomy-smoke/SKILL.md`

Any difference between WP Career Board's setup and Jetonomy's should either be justified (different shape of plugin) or brought into alignment.
