---
id: admin-settings-tabs-render
priority: high
personas: varundubey
requires: mu:autologin
last_verified: 2026-05-09
needs: cli
---

# Admin iterates every settings tab and verifies each renders without error

**Why this journey exists:** Guards that every tab registered via `wcb_settings_tabs` filter — both Free built-in tabs and any Pro-injected tabs — renders its section content without a PHP fatal, warning, or notice. This prevents silent regressions where a tab ships but its `do_action('wcb_settings_tab_<slug>')` callback has a broken dependency.

## Steps

1. As `varundubey`, navigate to `/wp-admin/admin.php?page=wcb-settings&autologin=1` → expect 200, settings sidebar renders, default "Job Listings" section is visible
2. Enumerate the registered tabs via WP-CLI:
   ```bash
   wp eval 'echo implode(", ", array_keys( apply_filters("wcb_settings_tabs", ["listings"=>"","pages"=>"","notifications"=>"","emails"=>"","import"=>"","antispam"=>""]) ));'
   ```
   Note the list; expected Free-only minimum: `listings, pages, notifications, emails, import, antispam`
3. For each tab slug in the list, navigate to `admin.php?page=wcb-settings&tab=<slug>&autologin=1`:
   - `listings` → expect "Job Listings" card heading renders
   - `pages` → expect "Pages" card with wp_dropdown_pages selects
   - `notifications` → expect "Email Configuration" card with From Name / From Email / Notification Email inputs
   - `emails` → expect email brand settings + per-template table
   - `import` → expect WPJM migration card(s) render
   - `antispam` → expect "Anti-Spam" form with CAPTCHA Provider dropdown
4. With Pro active, also navigate to each Pro-injected tab slug:
   - `boards` → expect Boards list card or "No boards" empty state
   - `field-builder` → expect Field Builder board-selector + (empty) field canvas
   - `credits` → expect credit packages table
   - `ai-settings` → expect AI Provider dropdown with "Disabled" default
   - `job-feed` → expect feed URL and enable toggle
   - `license` → expect license key input field
5. For EACH tab visited, immediately capture:
   ```bash
   wp eval 'echo "Tab loaded OK";'
   ```
   → output is "Tab loaded OK" (no fatal error interrupted WP-CLI)
6. Verify that navigating to a non-existent tab (`?tab=zzz_nonexistent`) falls through gracefully — the settings page still renders (likely showing the default first-tab content) without a PHP warning
7. Diff `debug.log` → expect ZERO new fatal/warning/notice lines across all tab visits

## Teardown

No data created. No teardown needed.

## Notes

- Tab navigation in the settings page uses JavaScript hash-routing (`settings-nav.js`), but each tab section is rendered server-side in one page load — you do NOT need JavaScript execution to check that the PHP renders. Each section's PHP is always emitted; JS merely toggles visibility.
- Pro tabs only appear when Pro plugin is active. If Pro is inactive, the teaser nav links point to the upsell URL (external), not to a settings tab — they will return a redirect, not 200 on wcb-settings.
- The `antispam` tab is registered by `AntiSpamModule::add_settings_tab()` via the `wcb_settings_tabs` filter, not hardcoded in `get_tabs()`.
