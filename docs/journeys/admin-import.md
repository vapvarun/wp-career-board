---
feature: admin page — Import (WP Job Manager migration)
roles: admin
surface: admin page (wcb-settings#import) + REST (/import/status, /import/run)
last_walked: 2026-06-26
---

# Import — full browser walkthrough

**What it is:** A one-click, idempotent migration from WP Job Manager into Career Board. The Jobs card is Free; the Resumes card is a Pro-gated teaser.
**Where it lives:** `wp-admin/admin.php?page=wcb-settings#import` (Import item in the settings sidebar, upload icon). Rendered by `AdminImport::render()` via `AdminSettings::render_import_tab()`. Batches run over REST (`/import/run`); counts come from `/import/status`.

## As admin
1. `?autologin=1` → `wp-admin/admin.php?page=wcb-settings#import` → expect the lead note "Each migration is safe to run multiple times — already-imported records are automatically skipped."
2. **WP Job Manager → Jobs card:** if `job_listing` post type exists, a green "Plugin active" badge shows; otherwise "Plugin not active" and a not-installed notice (no import button). The Fields-covered list enumerates what migrates (title, location, salary, company, taxonomies, etc.).
3. With WPJM active, the card shows three live stats from `/import/status`: **found**, **already imported**, **remaining**. If found > 0, an **Import All Jobs** primary button appears.
4. Click **Import All Jobs** → the progress bar reveals and the JS loops `POST /wcb/v1/import/run {type:'wpjm-jobs', offset, limit}` one batch at a time, updating the bar + log until done; already-migrated `job_listing` posts are skipped, so a re-run is safe.
5. After completion, **already imported** rises and **remaining** drops to 0; reload → counts persist (migrated jobs now live under Career Board → Jobs).
6. **Resumes card:** without Pro it renders locked with a Pro badge and a "Get Pro to Unlock" button → `store.wbcomdesigns.com/wp-career-board-pro/`. With Pro active it mirrors the Jobs card (`wpjm-resumes` type). Pro injects a CSV importer card via `wcb_import_extra_cards`.

## As candidate / employer
- No access — the page is under `wcb_manage_settings`; `/import/run` and `/import/status` require the same ability.

## Themes & states
- Admin-only — 1440px + 390px; cards stack and the progress bar stays full-width on mobile.
- Empty state: WPJM not installed → notice instead of buttons; 0 records found → "No jobs found in WP Job Manager."
- Known caveat (audit): the `wcb_settings_tab_import` listener exists but no matching `do_action` fires it — verify the Import panel actually renders from the sidebar before walking (`audit/FEATURE_AUDIT.md` §14 dead listener).

## Contracts guarded
- Idempotency: re-running an import skips already-migrated records (no duplicates) — the core safety promise.
- Batched REST: `/import/run` processes one bounded batch per call so large sites don't time out.
- Resume import is Pro-only — Free shows the locked teaser, never a broken/active resume importer.
