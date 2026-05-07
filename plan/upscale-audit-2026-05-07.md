# WP Career Board Free + Pro — Upscale-Model Audit
**Date:** 2026-05-07 · **Target:** long-term-stable plugin pair for 100K users · **Cross-plugin scope: this doc is the single source for both plugins**

> **Read order:** Free is the foundation. Pro extends Free via the documented filter API in `wp-career-board/docs/HOOKS.md` and the coordination class at `wp-career-board/core/class-pro-coordination.php`. The dependency arrow is **Pro → Free, never Free → Pro** (per `plan/1.2.0-stability.md:18`).

---

## TL;DR

The upscale model is **mostly intact**. Pro hooks 20+ Free filters/actions cleanly via `core/class-free-coordination.php`; there is **zero block-name collision**, **zero duplicate CPT registration**, **zero Pro CSS-tokens file** (Pro inherits from Free), and **zero Pro REST namespace fork** (Pro adds routes onto `wcb/v1`). The `bin/check-pro-decoupling.sh` CI gate exists.

Real findings worth fixing before claiming 100K-stable status:

| # | Finding | Severity | Plugin | Effort |
|---|---|---|---|---|
| F1 | One Free→Pro decoupling violation in `class-install.php:166` (direct `is_plugin_active` check) | P1 | Free | S |
| F2 | Pro modifies Free's `wcb_settings` option at runtime in resume migration | P2 | Pro | S |
| F3 | 5 dead/legacy `wcb_settings` sub-keys read but never written | P2 | Free | S |
| F4 | 2 mega-CSS files (1774 + 1356 LOC) in Free dashboards | P1 | Free | M |
| F5 | Manifest 9 days stale; Free self-flags 17 unaccounted hooks | P2 | Free | S |
| F6 | REST `get_item_schema()` deferred on Free + Pro endpoints | P2 | Both | M |
| F7 | `wcb_rest_prepare_*` filters missing on most prepared resources | P2 | Both | S |
| F8 | A-16 perf risk: 144 wpdb queries per listing render at per_page=50 | P0 | Free | L |
| F9 | Pro write-after-listener races on `wcb_settings` (resume migration + setup wizard both write same key) | P2 | Pro | S |
| F10 | Pro option-key proliferation: 25+ `wcbp_*` options without inventory | P3 | Pro | M |

`P0` = blocker for 100K · `P1` = should fix this release · `P2` = next release · `P3` = backlog.

---

## What's working well (the upscale model done right)

These patterns should be **preserved** and **documented as the contract** — they are why the plugin pair is in good shape:

1. **Single REST namespace** — Both plugins register routes under `wcb/v1`. Pro endpoints extend `WCB\Pro\Api\Pro_REST_Controller` which itself extends Free's `WCB\Api\RestController`. New endpoints inherit license + ability gating for free.
2. **Pro reuses every Free CPT** — `wcb_job`, `wcb_company`, `wcb_application`, `wcb_resume`, `wcb_board` are all owned by Free. Pro adds zero CPTs and one taxonomy (`wcb_resume_skill`).
3. **Single options key model** — `wcb_settings` is the canonical Free option (post 1.2.0 consolidation). Sanitizer enumerates 19 keys in 3 tab groups (`listings`, `pages`, `notifications`).
4. **Filter-driven currency catalog** — `wcb_currency_catalog` filter lets Pro add JPY/BRL/MXN/etc. without forking the catalog. Free reads filtered output everywhere via `AdminSettings::get_currency_catalog()`.
5. **Pro coordination class** — `core/class-free-coordination.php` is the single file where Pro registers all `add_filter`/`add_action` calls Free declared. No scattered hooking. Currently hooks 13 filters + 2 actions there.
6. **Block namespace unification** — Both plugins register blocks under `wcb/*`. 15 Free + 16 Pro = 31 blocks, **zero name collision**. Pro additions are entirely new feature families (resume-builder, application-kanban, ai-chat-search, credit-balance, board-switcher).
7. **Field-schema filters** — `wcb_job_form_fields`, `wcb_application_form_fields_groups`, `wcb_company_form_fields`, `wcb_candidate_form_fields`, `wcb_resume_form_fields` all use a uniform group/field shape. Pro extends without forking templates.
8. **Lifecycle action set** — Free fires 14+ lifecycle actions (`wcb_job_created`, `wcb_application_status_changed`, `wcb_deadline_reminder`, `wcb_featured_expired`, etc.) that Pro hooks for credits/notifications/cleanup.
9. **Decoupling CI gate** — `bin/check-pro-decoupling.sh` blocks any PR that puts `WCB\Pro\`, `wcbp_*()`, `WCBP_`, or `wp-career-board-pro` in Free runtime code.
10. **Hook namespace contract** — `wcb_*` = customer-facing, stable for 5 years. `wcbp_*` = Pro-internal, may change. Codified in `docs/HOOKS.md:152`.

**Don't break these in any future refactor.** They are the upscale model.

---

## Findings

### F1 — Direct `is_plugin_active` in Free (decoupling violation)

**Where:** `wp-career-board/core/class-install.php:166`

```php
$pro_active = is_plugin_active( 'wp-career-board-pro/wp-career-board-pro.php' );
$settings['resume_archive_enabled'] = (bool) $pro_active;
```

**Issue:** Free hardcodes the Pro plugin path. Violates the "zero references to Pro" rule from `plan/1.2.0-stability.md:9`. Defendable as a one-time install migration when Pro can't have hooked yet, but the rule has no carve-out. CI gate `check-pro-decoupling.sh` catches this pattern by name.

**Fix:** Replace with the documented filter:
```php
$pro_active = (bool) apply_filters( 'wcb_pro_active', false );
```
The migration runs at `init` priority 10+; Pro's `class-free-coordination.php` filters are registered at boot, so the filter is available. If the migration must run earlier than Pro can hook (truly synchronous boot-time), keep the direct check but add a `// phpcs:ignore` annotation citing this plan and document the carve-out in `plan/1.2.0-stability.md`.

**Effort:** 1-line change + decision on whether migration timing requires the carve-out.

---

### F2 — Pro writes Free's `wcb_settings` at runtime

**Where:** `wp-career-board-pro/modules/resume/class-resume-module.php:374`

```php
$settings = (array) get_option( 'wcb_settings', array() );
foreach ( array( 'max_resumes', 'resume_archive_page' ) as $key ) {
    if ( ! isset( $settings[ $key ] ) && isset( $legacy[ $key ] ) ) {
        $settings[ $key ] = $legacy[ $key ];
    }
}
update_option( 'wcb_settings', $settings );
delete_option( 'wcbp_resume_settings' );
```

**Issue:** Pro is reaching into Free's option storage to seed two keys (`max_resumes`, `resume_archive_page`). It's wrapped in a `wcbp_resume_settings_migrated` guard so it runs once, but:

1. The two keys (`max_resumes`, `resume_archive_page`) are written here by Pro yet are NOT in Free's sanitizer key list (`admin/class-admin-settings.php:172-194`). When Free's settings page saves, the sanitizer rebuilds `$output` from `$existing`; these Pro-seeded keys survive only because the sanitize logic preserves `$existing` for non-submitted tabs. Fragile.
2. Pro writing Free's option breaks the dependency arrow. The owner of `wcb_settings` should always be Free.

**Fix:** Two paths.

- **Cleanest (recommended):** move `max_resumes` and `resume_archive_page` keys to Pro's own option (`wcbp_resume_settings_v2` or a Pro-namespaced sub-array under a `wcb_settings_pro` filter that Free merges into `get_currency_catalog`-style accessors). Pro then reads its own option, doesn't write to `wcb_settings`. Free declares two getter filters (`wcb_max_resumes`, `wcb_resume_archive_page_id`) and Pro returns its values.
- **Pragmatic:** Free's sanitizer adds `max_resumes` and `resume_archive_page` to its key list (defaults: 0/0) so Pro can write them safely. Move the migration into Free as a tombstone, run once, never written again from Pro after migration completes.

**Effort:** S either way. Migration code can stay; the runtime read path needs to flip.

---

### F3 — Dead/legacy `wcb_settings` sub-keys

**Issue:** Code reads 5 sub-keys that the sanitizer NEVER writes:

| Key | Probable replacement | Action |
|---|---|---|
| `auto_publish` | `auto_publish_jobs` | grep + replace; remove old reads |
| `currency` | `salary_currency` | same |
| `moderation_mode` | unclear; possibly `pending_review` or `auto_publish_jobs` | investigate intent |
| `pending_review` | `auto_publish_jobs == false` | derived; replace reads |
| `per_page` | `jobs_per_page` | same |

**Why it matters:** any read against a key the sanitizer doesn't persist returns the default fallback. So the feature using it is effectively running on the fallback, not the user's choice. Looks like it works but doesn't.

**Fix:** Audit each read site; replace with the canonical key, or add the legacy key to the sanitizer if the feature is still wanted. Then delete the dead reads.

**Effort:** S. ~10–20 grep hits to walk.

---

### F4 — Mega-CSS files in Free dashboards (P1)

**Where:**
- `wp-career-board/blocks/employer-dashboard/style.css` — **1774 LOC**
- `wp-career-board/blocks/candidate-dashboard/style.css` — **1356 LOC**
- `wp-career-board/assets/css/frontend-components.css` — 900 LOC
- `wp-career-board/blocks/job-single/style.css` — 878 LOC
- `wp-career-board/blocks/job-listings/style.css` — 758 LOC

**Issue:** Two block style files exceed 1.7K and 1.3K LOC respectively. At current growth rate they'll cross 3K within 2 releases. Single mega-files break the editor experience (slow IDE, painful diff review, merge conflicts), make tree-shaking impossible, and mix concerns from dashboard tabs that should be CSS-isolated.

**Fix:** Split each block's `style.css` by feature/tab:

```
blocks/employer-dashboard/
  style.css           (was 1774 LOC → ~200 — block layout, header, footer, shared)
  styles/
    overview.css      (overview tab)
    jobs.css          (my jobs tab)
    applications.css  (applications tab)
    candidates.css    (saved candidates tab — Pro only)
    settings.css      (settings tab)
    credits.css       (credits widget — Pro)
    notifications.css (notifications bell — Pro)
```

Concatenate via the build (Grunt or `@import` cascade) so the runtime asset is unchanged but source files are bounded at ~200 LOC each. The Gruntfile already does asset bundling — wire it to glob `blocks/{block}/styles/*.css` after `style.css`.

**Same pattern for candidate-dashboard.** `frontend-components.css` (900 LOC) is a shared library — leave for now but watch.

**Effort:** M per block. Two blocks = 2 PRs of ~half-day each. Worth it for long-term sanity.

---

### F5 — Manifest is 9 days stale, self-flags incomplete coverage

**Where:** `wp-career-board/audit/manifest.json` (`generated.at: 2026-04-29`)

The Free manifest's own `refresh_notes` says: *"ground-truth grep finds 89 wcb_* hooks fired vs 72 in this manifest — 17 hooks predate this manifest gen and are not yet enumerated; flagged for next full audit-only re-run."*

**Issue:** Manifests are the discovery tool. A 23%-undercovered manifest sends future agents (and the cross-plugin coupling index) down the wrong path. We just shipped 3 commits today (resume default, currency propagation × 2) that touched hooks/filters — those need to be in the index.

**Fix:** Run `/wp-plugin-onboard --refresh` on both plugins after this audit's quick-wins land. The skill's diff-driven refresh will only re-scan changed categories, so it's cheap.

**Effort:** S (15 min for both plugins).

---

### F6 — REST `get_item_schema()` deferred plugin-wide

**Issue:** Both plans (`PLAN-1.2.0.md`) explicitly defer schema declarations because "partial schema is worse than no schema." For 100K users this becomes a problem because:

1. Third-party API consumers can't introspect the surface
2. WordPress's `rest_validate_value_from_schema` doesn't gate inputs — every endpoint hand-writes validation
3. OpenAPI docs can't be generated

**Fix:** Phase the schema in. Start with high-traffic endpoints (`/jobs`, `/applications`, `/companies`). Define `get_item_schema()` and let WP validate inputs from it. Drop hand-written validators where schema covers them. Track in PLAN-1.2.0.md or open a 1.3.0 ticket.

**Effort:** M per controller. Realistic budget: 4 endpoints per release.

---

### F7 — `wcb_rest_prepare_*` filters missing on most prepared resources

**Issue:** Only `wcb_job_response` filters the prepared response. The other resources (`wcb_application`, `wcb_company`, `wcb_candidate`, `wcb_resume`, `wcb_board`) prepare their REST response in code without an extension hook. Pro can't decorate them; third-party themes can't either.

**Fix:** Apply the `wcb_job_response` pattern uniformly:
```php
return apply_filters( 'wcb_rest_prepare_application', $prepared, $application, $request );
```
…in every `prepare_item_for_response_array()`. Document in `docs/HOOKS.md`.

**Effort:** S. ~6 endpoints, one filter call each.

---

### F8 — A-16 perf risk: 144 wpdb queries per listing render

**Where:** `customer-experience-baseline.md:249` flags this. The job-listings block at `per_page=50` runs ~144 cumulative wpdb queries per render today and is on track to hit ~4500+ at 1000 jobs.

**Issue:** This is the single biggest 100K-user blocker. A site with 5K active job listings and one bot-request per second to `/jobs` archive at default per_page generates ~720K queries/minute. Every WP host caps you well before that.

**Fix:** Multi-step:
1. Wire `update_meta_cache()` and `update_object_term_cache()` in `class-jobs-endpoint.php::get_items()` after `WP_Query` runs. Saves ~84 queries per page on the meta side alone.
2. Replace per-row `wp_get_object_terms()` calls with a single bulk call (already cached by step 1, but the API call itself is per-post). Use `get_the_terms()` on the cached set.
3. Add a `wcb_jobs_archive_cache_v` option that increments on `save_post_wcb_job` (already exists per `class-jobs-endpoint.php:101`). Use it as a transient namespace for the prepared response.

**Effort:** L. Real benchmark required after each step. Pair with a `wp wcb scale benchmark` WP-CLI command that the wp-plugin-onboard skill's Phase 4.7.7 prescribes — without measured timings against a 100K-row dataset, "100K-ready" is theory.

---

### F9 — Pro write-after-listener races on `wcb_settings`

**Where:** Pro has 4 sites that write `wcb_settings`:
- `core/class-pro-install.php:111` (install)
- `admin/class-pro-setup-wizard.php:380` (wizard)
- `modules/resume/class-resume-module.php:374` (legacy migration — see F2)

**Issue:** Pro's setup wizard saves `wcb_settings` at `:380`. If a user has Free's settings page open in another tab and saves, Free's sanitizer fires too. Both are `update_option` calls that race; whichever runs second wins. With multi-tab admin use this can lose the wizard's writes.

**Fix:** Wizard should call Free's sanitizer (`AdminSettings::sanitize`) instead of writing the option directly. Or the wizard should write a Pro-namespaced staging option that Free merges into `wcb_settings` on the next sanitize. Either way the write path needs to funnel through Free.

**Effort:** S per site. 3 sites total.

---

### F10 — Pro option-key proliferation

**Issue:** 25+ `wcbp_*` options exist:
```
wcbp_activation_redirect, wcbp_ai_api_key, wcbp_ai_base_url, wcbp_ai_provider,
wcbp_credit_mappings, wcbp_credit_purchase_url, wcbp_currency, wcbp_db_version,
wcbp_featured_upgrade_cost, wcbp_feed_email, wcbp_feed_enabled, wcbp_feed_version,
wcbp_flush_rewrite_rules, wcbp_license_key, wcbp_license_status,
wcbp_resume_settings_migrated, wcbp_setup_complete, ...
```

Some are correct (license, db_version, activation_redirect — runtime state Pro owns). Others are user-facing settings that should consolidate under `wcb_settings_pro` (or live in `wcb_settings` sub-arrays, like Free's `emails` and `captcha` sub-arrays).

**Fix:** Inventory each `wcbp_*` option. Categorize as:
- **Runtime state** (license, version, flush flags) → keep as own option
- **User settings** (AI provider, AI base URL, feed email, credit mappings, low_threshold) → migrate into a `wcbp_settings` umbrella option, like Free's pattern
- **Cache/transient** → convert to actual transients

**Effort:** M. Migration code per key plus one cycle of "tombstone" support.

`wcbp_currency` is already a tombstone (only `uninstall.php` references it after the recent currency fix). Good.

---

## Manifest gaps to refresh

After fixes land, run `/wp-plugin-onboard --refresh` on both plugins. Specific categories needing patch:

| Category | Why |
|---|---|
| `hooks_fired` | Free flagged 17 missing |
| `services` | New `wcb_settings` accessor methods if F2/F9 land |
| `static_analysis.cap_drift` | 2.5.3a — never run; would surface aspirational caps + permission monoculture |
| `static_analysis.adapter_undefined_calls` | 2.5.11 — never run on Pro Boards / Credits adapter slots |
| `static_analysis.registry_strategy` | 2.5.14 — Pro has SDK adapter registries (credits, notifications) — risk classification needed |
| `consumed_by` index | 2.5.10 — cross-plugin coupling index never populated; would let dead-listener detection become a `jq` query |

---

## Long-term roadmap (100K-scale checklist)

Beyond the findings above, items the plan docs already track but worth keeping visible:

- [ ] **Scale benchmark** — `wp wcb scale benchmark` CLI command per wp-plugin-onboard 4.7.7
- [ ] **REST cache priming** (F8 step 1)
- [ ] **REST cache invalidation** (F8 step 3)
- [ ] **Schema rollout** (F6) — high-traffic endpoints first
- [ ] **CSS feature-split** (F4) — employer + candidate dashboards
- [ ] **Pro options consolidation** (F10) — `wcbp_settings` umbrella
- [ ] **Manifest auto-refresh on commit** — git pre-commit hook calling `/wp-plugin-onboard --refresh` when `category_sources` files change
- [ ] **Cross-plugin contract doc** — Phase 4.5 of wp-plugin-onboard prescribes `plan/free-pro-architecture-contract.md` with 11 enforced invariants. Don't yet exist; should.
- [ ] **Customer journey suite** — Phase 4.7.3 prescribes ≥3 critical-priority journeys. Currently zero.
- [ ] **Local-CI gate** — `bin/local-ci.sh` + pre-push hook (Phase 4.7). Currently `check-pro-decoupling.sh` only.

---

## Recommended order of operations (for the team)

**This week (P0/P1 only):**
1. F1 — Replace `is_plugin_active` with `wcb_pro_active` filter (1 line, 5 min).
2. F4 — Start the CSS split. Tackle `employer-dashboard` first (the larger one).
3. F8 step 1 — Wire `update_meta_cache` + `update_object_term_cache` in `class-jobs-endpoint.php::get_items()`. Measure before + after.

**Next release (P2):**
4. F2 — Move resume migration write to Free or to Pro's own option.
5. F3 — Walk dead `wcb_settings` keys, replace or remove reads.
6. F9 — Funnel Pro's `wcb_settings` writes through Free's sanitizer.
7. F5 — Refresh manifests (cheap, do it after the above).

**Backlog (P2/P3, watch):**
8. F6 — Schema rollout phased.
9. F7 — `wcb_rest_prepare_*` filters across all prepared resources.
10. F10 — Pro options consolidation.
11. Phase 4.5 contract doc + Phase 4.7 local-CI scaffold (per wp-plugin-onboard skill).

---

## Wiring & uniformity scoreboard

This section answers "are all features wired uniformly to expert-level WordPress patterns?" The audit ran 10 targeted greps across both plugins and drilled into every signal that looked suspicious. Headline: **most of the architecture is held to the standard; 3 real drifts deserve attention.**

| # | Check | Free | Pro | Verdict |
|---|---|---|---|---|
| U1 | File header pattern (`declare(strict_types=1)` + `defined('ABSPATH')` guard) | 5 edge cases (templates + tests + auto-gen `.asset.php` + `phpstan-bootstrap.php`) | same shape | ✓ except templates need ABSPATH guard |
| U2 | Raw `_e()` uses (echoing unescaped) | **0** | **0** | ✓ |
| U3 | Abilities API vs `current_user_can()` | 13 abilities, 29 current_user_can | 5 abilities, 29 current_user_can | ✗ **58 violations** of the "Abilities API only" rule |
| U4 | jQuery in block view.js | **0** | **0** | ✓ |
| U5 | Direct `admin-ajax.php` usage | 0 (1 docblock comment) | 0 (5 docblock comments) | ✓ all references are "we used to" docs |
| U6 | Raw `$wpdb->query/get_*` calls without `prepare` on same line | 18 | 40 | ⚠ 58 sites — heuristic-overcount; needs manual review (many `$wpdb->prepare()` calls span multiple lines) |
| U7 | REST routes registered outside `RestController` base | `core/class-plugin.php` | `core/class-pro-plugin.php` | ⚠ 2 sites — bootstrap-time helper routes; should still extend or have a documented carve-out |
| U8 | Block `view.js` uses `@wordpress/interactivity` | 11/11 | 10/11 (`resume-single/view.js` doesn't) | ⚠ 1 outlier |
| U9 | Settings reads through accessor vs raw `get_option('wcb_settings')` | **48 raw sites** | **21 raw sites** | ✗ **69 violations** — no accessor pattern |
| U10 | CPT meta key prefix uniformity (`_wcb_*` for private) | clean | clean | ✓ |

### U3 — Abilities API drift (P1)

The architecture rule from `wp-career-board/CLAUDE.md` is unambiguous: *"Permissions — Abilities API only · CORRECT: `wp_is_authorized( 'wcb_post_job' )` · FORBIDDEN: `current_user_can( 'manage_options' )` // never"*.

Reality: **58 `current_user_can()` calls** across both plugins. Free has 29; Pro has 29. Only 18 abilities are registered.

**Why this matters at 100K:** capability checks scattered across handlers can't be remapped centrally when a customer asks "let editors approve jobs but not delete them." The Abilities API was introduced specifically so a single registration line + a default-roles map = consistent behavior. Every `current_user_can()` is a place that ignores that registration.

**Fix:** Audit every `current_user_can()` site. For each, either (a) it's checking a registered ability — replace with `wp_is_authorized()`; (b) it's checking a WordPress core capability that genuinely is core (`manage_options` for top-level admin, `edit_posts` for post-author) — leave with a `// phpcs:ignore` and a comment citing why; (c) it's a stale check — delete. Track in PLAN-1.2.0.md or 1.3.0.

**Effort:** M. Realistic budget: half-day to walk + decide each call, half-day to commit per plugin.

### U9 — No settings accessor (P1)

There are **69 raw `get_option('wcb_settings')` reads** across both plugins. Every block, every endpoint, every module reads the option directly and then does its own `! empty( $settings['key'] )` defaulting.

**Why this matters at 100K:**
1. **No central place to add caching.** Today every page render reads the option from the DB or autoloaded cache, then does default-merging in PHP. With 69 sites that's 69 places to harden when the option grows.
2. **No central place to enforce the schema.** The recently-fixed `apply_resume_required` bug existed precisely because the toggle UI used `! empty($settings['key'])` while the validator used `array_key_exists($key, $settings) ? ... : true`. Two readers, two interpretations of "absent key."
3. **No central place to add migrations.** When Free renames a key (post-1.2.0), every reader site must be updated separately.

**Fix:** Introduce a single class:

```php
namespace WCB\Admin;

final class Settings {
    public static function get( string $key, mixed $default = null ): mixed {
        $settings = self::all();
        return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
    }

    public static function bool( string $key, bool $default ): bool {
        $settings = self::all();
        return array_key_exists( $key, $settings ) ? ! empty( $settings[ $key ] ) : $default;
    }

    public static function all(): array {
        static $cache = null;
        return $cache ??= (array) get_option( AdminSettings::OPTION_KEY, array() );
    }
}
```

Then **every** read becomes `WCB\Admin\Settings::bool('apply_resume_required', true)` or `WCB\Admin\Settings::get('jobs_per_page', 10)`. The `array_key_exists` semantic that fixed Bug 9863100490 today becomes the default — bug class eliminated by construction.

Migration: add the class, walk the 69 sites with `Edit` (most are 2-line replacements), delete the duplicated default-merge. Pro's reads use the same class; the interface is namespaced under Free, Pro inherits.

**Effort:** M. ~1 day with mechanical replacement.

### U6 — `$wpdb` prepared-query review (P2)

58 sites where `$wpdb->query/get_*` is called and `prepare` is not on the same line. Many are likely safe (multi-line `$sql = $wpdb->prepare(...); $wpdb->query($sql);` pattern) but each one is a security audit point.

**Fix:** Walk the 58 sites once. For each, classify:
- **Static query** (no user input — `CREATE TABLE`, schema check) → annotate with `// phpcs:ignore` citing why.
- **Multi-line prepared** (`$sql = $wpdb->prepare(); $wpdb->query($sql);`) → leave as is; document with a comment if non-obvious.
- **Genuinely raw with user input** → **emergency fix**, will be SQL-injectable.

**Effort:** S to walk. PHPCS already flags these via `WordPress.DB.PreparedSQL.NotPrepared`; running `mcp__wpcs__wpcs_check_file` on each surfaces real concerns immediately.

### U7 — REST routes outside the base class (P3)

`core/class-plugin.php` and `core/class-pro-plugin.php` each register at least one REST route directly via `register_rest_route`. They're plumbing routes (plugin-meta, license check, status pings) that don't justify their own controller. Acceptable carve-out, but worth documenting.

**Fix:** Add a comment + line to `docs/HOOKS.md` or the architecture contract doc explaining the carve-out so reviewers don't get nervous.

**Effort:** XS.

### U8 — `resume-single` block doesn't use Interactivity API (P3)

**Where:** `wp-career-board-pro/blocks/resume-single/view.js`

**Issue:** Pro's resume-single block is the only block in either plugin that doesn't import `@wordpress/interactivity`. It might be a static-only block (no client-side state) — in which case it shouldn't have a `view.js` at all (the build pipeline removes view.js for static blocks). Or it is an oversight.

**Fix:** Inspect the file. If it's static — remove view.js + the registration. If it has client behavior — port to Interactivity API.

**Effort:** S.

---

## Verification — what the team can claim once everything above lands

> "WP Career Board (Free + Pro) is a clean upscale-model plugin pair. Pro extends Free via 20+ documented filters and 14+ lifecycle actions. Free has zero references to Pro classes/functions/options. There is a single REST namespace, a single options-key model, and zero block / CPT / taxonomy collisions. CI enforces the decoupling contract. Listing render is bounded at ≤30 wpdb queries regardless of dataset size; benchmarked against a 10K-row seed via `wp wcb scale benchmark`. Customer journeys cover the 5 highest-impact flows. Manifests are committed and refreshed per release."

That's the 100K-stable claim worth making.
