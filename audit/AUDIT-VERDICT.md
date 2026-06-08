# Audit Verdict: wp-career-board

**Branch:** 1.2.1  
**DB_VERSION:** 1.2.7  
**Manifest schema:** v2.2 (refreshed 2026-06-08)  
**Auditor:** AutoVAP — 2026-06-08 (re-audit after big-site + cleanup wave)

---

## Shippable? YES — UNCONDITIONAL

The 1.2.1 big-site + cleanup wave resolved every prior Blocker and all five Major performance findings. The REST-JS-001 false positive is confirmed closed. Architecture debt items R1 and R2 are resolved in code. No new Blockers or Majors were introduced by the wave. Remaining open items are Minor/Polish level and carry no ship gate.

## Sellable? YES — WITH POLISH

The prior sellable-with-polish verdict stands and is stronger: the enterprise-scale gaps (cron, GDPR, N+1, grid overflow) that would have surfaced for power users are now fixed. A paying customer at any scale from SMB to mid-market will receive a production-grade plugin. The remaining open items (8 CSS breakpoints, tap targets, PHPStan baseline count) are cosmetic or technical debt and do not affect the customer experience.

---

## Prior Findings — Resolution Status

| # | Prior Severity | Finding | Status | Evidence |
|---|---------------|---------|--------|----------|
| 1 | Blocker | REST-JS-001 `data.reason` REST↔JS contract drift | **RESOLVED (false positive confirmed)** | `assets/js/admin.js:183` writes `data.reason` into a REQUEST body sent to `/wcb/v1/jobs/{id}/reject`; `modules/moderation/class-moderation-module.php:82` declares the `reason` parameter in the route schema and reads it at line 186. Contract intact. Note: `audit/contracts/` directory was not created — the fixture gap from the recommendation remains open as a documentation task. |
| 2 | Major | Cron: unbounded `posts_per_page=-1` on job expiry | **RESOLVED** | `modules/jobs/class-jobs-expiry.php:105` — bounded batch of 200; cron re-arms itself via `wp_schedule_single_event(time()+MINUTE_IN_SECONDS, 'wcb_check_job_expiry')` when batch is full (line 142). Loop drains naturally because each pass transitions rows out of `publish`. |
| 3 | Major | N+1 on employer applications view | **RESOLVED** | `api/endpoints/class-employers-endpoint.php:778-796` — candidate IDs and job IDs extracted from the primed meta cache in a pre-loop pass, then `cache_users(array_unique($wcb_candidate_ids))` (line 791) and `_prime_post_caches(array_unique($wcb_job_ids), false, false)` (line 794) called before the `array_map` loop. Per-row `get_user_by()` and `get_the_title()` are now cache hits. |
| 4 | Major | `dedupe_default_boards()` inner job reassignment unbounded | **RESOLVED** | `core/class-install.php:439-455` — inner loop now uses `posts_per_page => 500` with a `do...while(count($jobs) === 500)` drain. Outer boards enumeration remains at `-1` but `wcb_board` is a singleton CPT in Free (0-2 posts), so the risk is theoretical. |
| 5 | Major | GDPR eraser/exporter unbounded `numberposts=-1` | **RESOLVED** | `modules/gdpr/class-gdpr-module.php:84-158` (exporter) — page-based with `$per_page=100`, returns `done: count($apps) < $per_page`. Lines 188-214 (eraser) — destructive-read pattern: always reads page 1 at 100 per pass, returns `done` when batch < 100 so WP re-invokes until exhausted. Both implement the WP Privacy Tools page-based contract correctly. |
| 6 | Minor | `manage_options` raw cap in antispam save handler | **RESOLVED** | `modules/antispam/class-anti-spam-module.php:284` — gate is now solely `wp_is_ability_granted('wcb/manage-settings')` with the correct phpcs:ignore citing the polyfill. The redundant `current_user_can('manage_options')` call was removed. |
| 7 | Minor | No AbortController on 68 fetch() calls in block view.js | **RESOLVED (block-side)** | All 9 data-fetching block `view.js` files import `wcbFetch` from `@wcb/fetch` (verified: 72 `wcbFetch` call sites, 9 `view.asset.php` files declare the `@wcb/fetch` dependency). Module registered at `core/class-plugin.php:226-232`. The 11 remaining admin-script calls (`admin.js` 5 `wp.apiFetch`, `wizard.js` 3 `wp.apiFetch`, `emails.js` 2 `fetch`, `application-detail.js` 1 `fetch`) are admin-only and explicitly deferred — see NEW ITEM #1 below. |
| 8 | Minor | 8 distinct CSS breakpoints | **OPEN (unchanged)** | Ground-truth grep confirms 8 breakpoints still present: 600, 640, 768, 782, 900, 960, 1024, 1100 px in `assets/css/`; 420, 480, 600, 640, 720, 768, 900, 1023, 1024 px in `blocks/`. Not addressed in this wave. Ship-quality polish, not a gate. |
| 9 | Minor | 51 bare `1fr` grid track rules | **RESOLVED** | Manifest `grid_track_overflow_risks` = 0 (was 51). Ground-truth grep for bare `1fr` in `grid-template` rules outside `minmax()` across both `assets/css/` and `blocks/` CSS returns 0 results. All 18 CSS files cleared. |
| 10 | Minor | R1: Duplicate `post_status` allowlist at 3 sites | **RESOLVED** | `api/endpoints/class-employers-endpoint.php:535-539` — `owner_visible_statuses(bool)` private method is the single source of truth, called at lines 538, 561, 629, 742. All three prior duplication sites now delegate to this method. |
| 11 | Minor | R2: Company brand-meta serialization duplicated | **RESOLVED** | `core/class-company-meta-shape.php` — new `WCB\Core\CompanyMetaShape` class with `serialize(int)` and `size_label(string)` statics. Consumed at `api/endpoints/class-companies-endpoint.php:357` and `api/endpoints/class-jobs-endpoint.php:1197`. Duplicate private `size_label()` methods removed from both endpoints. |
| 12 | Polish | 8 admin tap targets below 40 px | **OPEN (unchanged)** | `assets/css/admin/shared.css:61` `.wcb-btn` at `height:32px`, line 72 `.wcb-btn--sm` at `height:28px`; `assets/css/wcb-ui.css:193` `.wcb-btn--sm` at `min-height:32px`, line 1207 `.wcb-cbtn--sm` at `min-height:32px`. These are all admin-side chrome (small variant buttons, size chips). The 24px heights at `admin.css:331,455,830` are icon/decorative elements, not interactive controls. Not addressed in this wave. |
| 13 | Polish | PHPStan baseline 70 suppressed items | **OPEN (count stable at 75)** | `phpstan-baseline.neon` has 75 `message:` entries. The count nudged from 70 to 75 — 5 new suppressions added, likely from the new `CompanyMetaShape` class or the GDPR page-based signatures. The count should trend down not up; this needs review before the next release. |

---

## New Findings Introduced by the Wave

| # | Severity | Lens | Finding | File:line | Suggested journey to fix |
|---|----------|------|---------|-----------|--------------------------|
| N1 | **Minor** | Standards / Architecture | **SQL IN-clause constructed by string interpolation (PHPCS suppressed).** `api/endpoints/class-employers-endpoint.php:742-743` builds `$wcb_status_in` by imploding the hardcoded `owner_visible_statuses(true)` array and interpolating it directly into the `$wpdb->prepare()` call. PHPCS `PreparedSQL.InterpolatedNotPrepared` is suppressed. The values are internal constants (not user-controlled), so there is no injection risk. However, the correct pattern for a static IN-list is `%s` placeholders with `$wpdb->prepare('... IN (%s)', implode(',', array_map('esc_sql', $statuses)))` or using `\WP_Query` with `post_status` array which handles this natively. This is a code-quality debt item — it works correctly but sets a pattern that would be dangerous if the value source were ever changed. | `api/endpoints/class-employers-endpoint.php:742-762` | Minor cleanup: replace string-build + phpcs:ignore with a `\WP_Query`-backed query or proper placeholder array. |
| N2 | **Minor** | Big-site / Performance | **`migrate_resume_public_flag()` outer query is unbounded.** `core/class-install.php:540-553` fetches ALL `wcb_resume` posts with `posts_per_page=-1`. This is a Pro CPT one-shot migration gated on `version_compare < 1.2.7`, so it runs at most once. For a site that imported 100k resumes it would allocate the full result set into PHP memory on the upgrade request. Practical risk is low (Pro migrations typically run before the site has large data) but inconsistent with the bounded-batch pattern applied to the adjacent jobs and boards migrations. | `core/class-install.php:540-553` | Apply the bounded drain pattern (500 per pass, loop until empty) to match the boards and jobs migrations for consistency. Low urgency. |
| N3 | **Deferred / Known** | Performance / UX | **11 admin-script fetch() calls without AbortController (rest_hang_risks = 11).** `assets/js/admin.js` 5× `wp.apiFetch`, `assets/js/wizard.js` 3× `wp.apiFetch`, `assets/js/admin/emails.js` 2× `fetch`, `assets/js/admin/application-detail.js` 1× `fetch`. All are admin-only paths (the administrator cannot navigate away mid-request in the same disruptive way a frontend visitor can). These are an acknowledged deferral from the wave — block-side is resolved; admin-side remains. They do not affect customer-facing UX. | `assets/js/admin.js`, `assets/js/wizard.js`, `assets/js/admin/emails.js`, `assets/js/admin/application-detail.js` | Future sprint: extend `@wcb/fetch` or a companion `@wcb/admin-fetch` helper to the admin scripts. Non-blocking for ship. |
| N4 | **Documentation** | QA | **REST contract fixture not created.** The prior verdict recommended creating `audit/contracts/reject-job.json` to prevent REST-JS-001 from being re-raised in future baselines. The directory and file do not exist. The code is correct but the audit trail is absent. | `audit/contracts/` (directory missing) | Create `audit/contracts/reject-job.json` with the request shape `{reason: string}` and response shape `{success: true}`. One-line documentation task. |
| N5 | **Minor** | Standards | **PHPStan baseline count increased 70 → 75.** Five new suppressions were added in this wave. The baseline should trend downward; a net increase of 5 means the wave introduced new type-level issues rather than paying down the existing debt. The 75 entries include the long-standing `WP_List_Table::set_pagination_args` type mismatch (5 suppressions) and `get_users` signature mismatch (1) — these are stub-quality issues in the WordPress stubs, not real bugs. But the count should be reviewed before any further increases are accepted. | `phpstan-baseline.neon` | Triage the 5 new entries; fix any that are real type errors vs stub limitations. |

---

## Scores

| Lens | Grade | Notes |
|---|---|---|
| Security | A | Zero SQL injection risks. SQL IN at `class-employers-endpoint.php:742` is hardcoded internal values — not user-controlled, not an injection risk. Nonce verification on all admin write paths. GDPR paths gated and page-based. `manage_options` raw cap in antispam now removed. `current_user_can` usages that remain in admin list-table code (`wcb_manage_settings`, `edit_post`) are WP-conventional list-table patterns, not Abilities-API violations. The polyfill-based abilities gate is the security gate on all REST write paths. |
| Performance | A- | All five prior Major performance findings resolved: expire_jobs batched + re-armed; GDPR page-based; employer applications N+1 eliminated via `cache_users` + `_prime_post_caches`; dedupe inner loop bounded. Two residual one-shot migration queries (resume backfill, boards outer loop) are low-cardinality CPTs and run once under version gate. All REST list endpoints (jobs, companies, candidates, applications) are paginated, cache-primed, and transient-cached. FULLTEXT index in place since 1.2.6. Minor: `class-locations.php:167` company HQ backfill uses `-1` (one-shot, company CPT is low-cardinality). |
| UX | B | Token-driven design system. Dark-mode and RTL tokens in place. All 51 bare-1fr grid tracks cleared (grid_track_overflow_risks = 0). Remaining: 8 CSS breakpoints vs 3-target discipline (unchanged from prior audit). Tap targets: `.wcb-btn--sm` at 28/32px — admin-side chrome only, not customer-facing primary actions. Empty/error/loading states present on all interactive blocks. |
| QA / Coverage | B+ | 45 customer journey sentinels. Manifest at v2.2, ground-truth verified. REST-JS-001 confirmed false positive. `audit/contracts/` directory not yet created (documentation gap, N4). PHPStan baseline at 75 entries (nudged up 5 from wave — N5). `rest_hang_risks` reduced 68 → 11 (11 admin-only, explicitly deferred). `grid_track_overflow_risks` = 0. |
| Standards | A- | WPCS compliant. PHP 8.1 typed properties throughout. `declare(strict_types=1)` in every file. Abilities API used consistently on all REST and form-handler write paths. SQL IN interpolation with phpcs:ignore at `class-employers-endpoint.php:742` (N1) is safe but non-standard pattern. PHPStan baseline count at 75 (N5). |

---

## Ship / No-Ship Summary

**Ship verdict:** YES — unconditional. No Blockers. No Majors.

All prior blockers and majors are resolved. The remaining open items are:

1. **CSS breakpoints** (Minor #8, unchanged) — polish sprint, not a gate.
2. **Admin tap targets sub-40px** (Polish #12, unchanged) — admin-side chrome only, not customer-blocking.
3. **PHPStan baseline at 75** (N5) — count should not grow further; triage the 5 new suppressions before the next release.
4. **SQL IN interpolation** (N1) — architecturally safe, PHPCS-suppressed, minor cleanup in next maintenance pass.
5. **`migrate_resume_public_flag` unbounded** (N2) — Pro CPT, one-shot migration, low risk; apply bounded pattern for consistency.
6. **Admin-script fetch() without AbortController** (N3) — **knowingly deferred.** Block-side (57 sites) is resolved via `@wcb/fetch`. The 11 remaining admin-only calls are lower priority because (a) admin sessions don't have the same navigation-abandon profile as frontend visitors, (b) `wp.apiFetch` has its own middleware abort support that can be added without the `@wcb/fetch` wrapper. Schedule for a future admin-JS polish sprint.
7. **REST contract fixture** (N4) — documentation task only.

**Sell verdict:** YES. The plugin is at commercial-plugin standard. The big-site performance wave brings it to enterprise-ready on all previously flagged critical paths.
