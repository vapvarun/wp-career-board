# Bug Fix Batch — 6 Confirmed Bugs

**Date:** 2026-04-03
**Scope:** wp-career-board (Free plugin only, except Bug 4 which touches candidate-dashboard used by Pro)

## Bug 1: SEO datePosted timezone

**File:** `modules/seo/class-seo-module.php:84`
**Root cause:** `get_the_date('c', $job)` returns the date in the site's configured timezone. Schema.org expects UTC ISO 8601.
**Fix:** Replace with `get_post_time( 'c', true, $job )`. The `true` second parameter forces GMT output. This matches the existing `gmdate()` call for `validThrough` on line 77.

## Bug 2: Bot/crawler view counting

**File:** `api/class-rest-controller.php:106-123`
**Root cause:** `record_job_view()` records every GET request with no User-Agent filtering.
**Fix:** Add a private `is_bot_request()` method to `REST_Controller`. It checks `$_SERVER['HTTP_USER_AGENT']` against a regex of known bot patterns (Googlebot, Bingbot, Slurp, DuckDuckBot, Baiduspider, YandexBot, facebookexternalhit, Twitterbot, LinkedInBot, Applebot, mj12bot, AhrefsBot, SemrushBot, DotBot, PetalBot). Returns `true` if UA is empty or matches. Called at the top of `record_job_view()` — early return if bot.

The regex approach is lightweight (no DB, no external service), covers the major bots, and is easily extensible via a `wcb_bot_ua_patterns` filter for site owners who need to add custom patterns.

## Bug 3: Setup wizard re-runnable after completion

**Files:**
- `admin/class-admin.php:452-455` — Quick Actions button
- `admin/class-setup-wizard.php` — render method

**Root cause:** Quick Actions always shows "Setup Wizard" link. The wizard page is directly accessible via URL regardless of completion state.

**Fix — two parts:**
1. **Quick Actions (class-admin.php):** Wrap the Setup Wizard button in `if ( ! get_option( 'wcb_setup_complete', false ) )`. After completion, the button disappears from Quick Actions.
2. **Wizard page (class-setup-wizard.php::render):** Check `wcb_setup_complete` before rendering. If complete, render a confirmation state: "Setup already completed" message with a "Re-run Setup Wizard" button. That button sets a transient (`wcb_wizard_rerun`) and reloads. On reload, the wizard renders normally. This prevents accidental page re-creation while allowing intentional re-runs.

## Bug 4: Resume tab missing on candidate dashboard

**File:** `blocks/candidate-dashboard/render.php:105`
**Root cause:** `resumesEnabled` checks `'' !== $wcb_resume_builder_url` which is empty when no dedicated page exists. The `$wcb_resume_builder_embedded` variable (line 45) correctly checks block registration but is ignored for tab visibility.

**Fix:** Change line 105 to:
```php
'resumesEnabled' => $wcb_resume_builder_embedded || '' !== $wcb_resume_builder_url,
```
This enables the resume tab when either: (a) the `wcb/resume-builder` block is registered (Pro inline), or (b) a dedicated resume builder page URL exists. Both are valid paths to resume functionality.

## Bug 8: Duplicate search input in job-listings

**File:** `blocks/job-listings/render.php:282-299`
**Root cause:** `job-listings` renders its own `<input type="search">` alongside the dedicated `job-search` block. When both blocks are on the same page (as the wizard creates), two search inputs appear.

**Fix:** Remove the search input from `job-listings/render.php` (lines 285-298: the `wcb-search-wrap` div containing the search icon, label, and input). Keep the sort `<select>` in the header row. The `job-search` block is the authoritative search component — `job-listings` should only display results and filtering UI (chips, sort, results count).

The `wcb-search-sort-row` div stays but only contains the sort dropdown. Rename to `wcb-sort-row` for clarity.

## Bug 9: Regex breaking block name extraction in setup wizard

**File:** `admin/class-setup-wizard.php:275`
**Root cause:** Regex `([^ \/]+)` stops at `/`, so `wp-career-board/job-search` captures as just `wp-career-board`.

**Fix:** Change from:
```php
if ( preg_match( '/<!-- wp:([^ \/]+)/', $page_data['content'], $m ) ) {
```
To:
```php
if ( preg_match( '/<!-- wp:([a-z0-9-]+(?:\/[a-z0-9-]+)?)/', $page_data['content'], $m ) ) {
```
This explicitly matches the WordPress block name format: `namespace/block-name` where the `/block-name` part is optional (for core blocks like `wp:paragraph`). More precise than a generic non-space match and self-documenting.

## Verification

After all fixes:
1. Navigate to a single job page — view source, confirm `datePosted` outputs UTC (`+00:00` or `Z`)
2. `curl -A "Googlebot" http://job-portal.local/wp-json/wcb/v1/jobs/1` — query `wcb_job_views`, confirm no row added
3. Complete the setup wizard — verify Quick Actions hides the button, verify `/wp-admin/admin.php?page=wcb-setup` shows "Setup already completed" with re-run option
4. Navigate to candidate dashboard — verify "My Resume" tab appears in sidebar
5. Navigate to Find Jobs — verify only one search input exists
6. Delete all WCB pages, re-run wizard — verify pages are created correctly with full block names
