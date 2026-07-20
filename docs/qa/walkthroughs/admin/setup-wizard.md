---
id: walkthrough-admin-setup-wizard
priority: high
personas: varundubey
requires: mu:autologin
last_verified: 2026-07-08
covers: admin/admin-setup-wizard-end-to-end, admin/walkthrough-admin-setup-and-settings (wizard portion)
---

# Walkthrough: First-Run Setup Wizard — create the required pages and install sample data end to end

**Why this journey exists:** The Setup Wizard is the first thing a site owner sees. It must render its REST-driven steps, create the six canonical Career Board pages (persisting their IDs into `wcb_settings`), optionally seed sample jobs/companies/terms, and set `wcb_setup_complete` so the wizard does not relaunch on the next admin visit. This walkthrough is the human-runnable end-to-end for the `admin-setup-wizard-end-to-end` sentinel.

## Steps

1. As `varundubey`, force a clean wizard state so the steps render: `wp option update wcb_setup_complete 0 --path=/Users/varundubey/Local Sites/jobboard/app/public` → expect `Success`.
2. As `varundubey`, navigate to `http://jobboard.local/wp-admin/admin.php?page=wcb-setup&wcb_rerun=1&autologin=varundubey` → expect HTTP 200 and the wizard shell `.wcb-wizard-wrap` with `#wcb-wizard-steps` holding two `.wcb-wizard-step` cards; step 1 (`[data-step="1"][data-key="create-pages"]`) carries the `active` class. (The page is registered under parent `options.php`, cap `wcb_manage_settings`, at `admin/class-setup-wizard.php`; `?wcb_rerun=1` feeds the `wcb_wizard_force_render` filter so an already-configured site still shows the steps instead of `admin/views/setup-wizard-complete.php`.)
3. Confirm the wizard localization is present: view source and assert the `wcbWizard` JS object exposes `restUrl` (= `rest_url('wcb/v1/wizard')`) and `steps` (localized in `enqueue_wizard_assets()`, `assets/js/wizard.js`) → expect both keys present.
4. **Step 1 (Create Pages)** — click `#wcb-create-pages` (`[data-wcb-wizard-action="create-pages"]`, label "Create Pages & Continue") → expect a single `POST http://jobboard.local/wp-json/wcb/v1/wizard/create-pages` (sent by `wp.apiFetch` with header `X-WP-Nonce`) returning HTTP 200 with a JSON `setting_key => page_id` map (empty map is also success if all six pages already exist). The six pages are Employer Registration, Employer Dashboard, Candidate Dashboard, Find Jobs, Companies, and Post a Job (`create_required_pages()`; canonical slugs in `admin/class-pages.php`).
5. Verify the page IDs persisted into `wcb_settings`: `wp option get wcb_settings --format=json` → expect `jobs_archive_page` and `employer_dashboard_page` both `> 0`.
6. Expect the wizard to auto-advance: step 1 loses `active`, step 2 (`[data-step="2"][data-key="sample-data"]`, title "Sample Data") gains `active`, and the "Demo Content" toggle `#wcb-install-sample` renders **checked** by default (`admin/views/wizard-steps/sample-data.php`).
7. **Step 2 (Sample Data)** — leave `#wcb-install-sample` checked and click `#wcb-finish-wizard` (`[data-wcb-wizard-action="sample-data"]`, label "Finish Setup") → expect `POST http://jobboard.local/wp-json/wcb/v1/wizard/sample-data` body `{ install_sample: 1 }` returning HTTP 200 `{ installed: true }`. This seeds 3 companies, 8 published jobs, and the category/job-type/location/experience/tag terms, and stamps `wcb_sample_data_installed` (`install_sample_data()`).
8. Expect a follow-up `POST http://jobboard.local/wp-json/wcb/v1/wizard/complete` returning HTTP 200 `{ redirect: "http://jobboard.local/wp-admin/admin.php?page=wp-career-board" }`, and the browser to land on the Career Board dashboard.
9. Verify completion persisted: `wp option get wcb_setup_complete` → expect `1`.
10. Verify the wizard does NOT relaunch: navigate to `http://jobboard.local/wp-admin/admin.php?page=wp-career-board&autologin=varundubey` → expect the Career Board dashboard (title "WP Career Board"), NOT a redirect back into the wizard.
11. Re-open the wizard without the rerun flag: navigate to `http://jobboard.local/wp-admin/admin.php?page=wcb-setup&autologin=varundubey` → expect HTTP 200 and the "Setup Already Completed" card (`admin/views/setup-wizard-complete.php`) with a "Re-run Setup Wizard" link to `admin.php?page=wcb-setup&wcb_rerun=1` (since `is_setup_complete()` is now true and `wcb_rerun` is absent).
12. tail `wp-content/debug.log` diff over the whole run → expect ZERO new fatal/warning lines.

## Teardown

```bash
SITE='/Users/varundubey/Local Sites/jobboard/app/public'

# Remove wizard-seeded sample data cleanly (idempotent; mirrors SetupWizard::remove_sample_data()).
wp eval '(new \WCB\Admin\SetupWizard())->remove_sample_data();' --path="$SITE" 2>/dev/null || true

# Restore the completion flag to a configured-site state.
wp option update wcb_setup_complete 1 --path="$SITE" 2>/dev/null || true

# The six canonical Career Board pages are intentionally left in place — they are
# the real product pages, not throwaway fixtures.
```

## Notes

- Wizard REST routes (`create-pages`, `sample-data`, `complete`, `remove-sample-data`) register in `SetupWizard::register_routes()`, all gated by `wizard_permission_check()` → ability `wcb/manage-settings`. `sample-data` takes an `absint` arg `install_sample`.
- `is_setup_complete()` returns true when `wcb_setup_complete` is set OR any core page id is mapped in `wcb_settings` — so deleting only the flag will not reopen the wizard if pages are still assigned; the `?wcb_rerun=1` force-render path exists precisely for that case.
- Re-running is safe: page creation skips keys that already resolve to a page, and the sample-data seeder re-tracks existing sample IDs (idempotent).
- Pro can inject additional steps via the `wcb_wizard_steps` filter; with Pro active the step count is higher — enumerate via `wcbWizard.steps`.
- No 1.5.1-new surface in this walkthrough (setup flow unchanged in 1.5.1).
- Seed needs: none beyond `mu:autologin` — the walkthrough creates the pages and sample data itself.
