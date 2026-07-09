---
id: walkthrough-admin-setup-and-settings
priority: high
personas: varundubey
requires: mu:autologin
last_verified: 2026-06-29
---

# Walkthrough: Admin Setup Wizard & Settings — run the first-run wizard (create pages + sample data), then configure every Settings tab

**Why this journey exists:** This is the end-to-end walkthrough of how a site owner stands up WP Career
Board. It traces the full happy path: open the REST-powered Setup Wizard, create the required pages,
optionally install sample data, complete the wizard, then walk into the Settings screen and configure
each tab — Job Listings (moderation defaults), Pages (block-page assignments), Notifications/Emails
(sender + admin alerts) — and confirm the "Run Setup Wizard" / sample-data controls. The whole
admin setup-and-configure functionality is browser-coverable in one pass.

## Steps

1. As `varundubey`, navigate to `http://jobboard.local/wp-admin/admin.php?page=wcb-setup&wcb_rerun=1&autologin=varundubey` → expect HTTP 200 and the wizard shell `.wcb-wizard-wrap` with `#wcb-wizard-steps` containing two `.wcb-wizard-step` cards; step 1 (`data-step="1"` `data-key="create-pages"`) carries the `active` class. (Without `wcb_rerun=1` an already-configured site renders `admin/views/setup-wizard-complete.php` — the "Setup Already Completed" card with a "Re-run Setup Wizard" link — instead of the steps; `wcb_rerun=1` forces the steps via the `wcb_wizard_force_render` filter.)

2. **Step 1 (Create Pages)** — click the primary button `#wcb-create-pages` (`[data-wcb-wizard-action="create-pages"]`, label "Create Pages & Continue") → expect a single `POST http://jobboard.local/wp-json/wcb/v1/wizard/create-pages` (sent by `wp.apiFetch`, carrying header `X-WP-Nonce`) returning HTTP 200 with a JSON map of `setting_key => page_id` for any page that was newly created (empty map if all six pages already exist — both are success). The required pages are Employer Registration, Employer Dashboard, Candidate Dashboard, Find Jobs, Companies, and Post a Job.

3. Expect the wizard to auto-advance: step 1 loses `active` and step 2 (`data-step="2"` `data-key="sample-data"`, title "Sample Data") gains `active` (the `wcb-wizard-step-complete` CustomEvent → `showStep(2)`). The "Demo Content" toggle `#wcb-install-sample` is rendered **checked** by default.

4. **Step 2 (Sample Data)** — leave `#wcb-install-sample` checked and click `#wcb-finish-wizard` (`[data-wcb-wizard-action="sample-data"]`, label "Finish Setup") → expect `POST http://jobboard.local/wp-json/wcb/v1/wizard/sample-data` with JSON body `{ install_sample: 1 }` returning HTTP 200 `{ installed: true }` (seeds 3 companies, 8 published jobs, and the category/job-type/location/experience/tag taxonomy terms; stamps `wcb_sample_data_installed`).

5. Expect a follow-up `POST http://jobboard.local/wp-json/wcb/v1/wizard/complete` returning HTTP 200 `{ redirect: "http://jobboard.local/wp-admin/admin.php?page=wp-career-board" }`, the browser to land on the WP Career Board dashboard, and `get_option('wcb_setup_complete')` to be `true`.

6. As `varundubey`, navigate to `http://jobboard.local/wp-admin/admin.php?page=wcb-settings&autologin=varundubey` → expect HTTP 200, the settings shell `.wcb-settings-wrap` with the sidebar `.wcb-settings-sidebar` listing nav items `a.wcb-settings-nav-item[data-section]` (Job Listings/`listings`, Pages/`pages`, Notifications/`notifications`, Emails/`emails`, Import/`import`, Integrations/`integrations`), and the page header action `a.wcb-btn` "Run Setup Wizard" pointing at `admin.php?page=wcb-setup`.

7. **Job Listings tab (moderation)** — the first section `#section-listings` is active by default. Set the "Auto-Publish Jobs" toggle `input[name="wcb_settings[auto_publish_jobs]"]` to **off** (so new jobs are held as Pending for review), set `#wcb-jobs-per-page` (`name="wcb_settings[jobs_per_page]"`) to `12`, and `#wcb-jobs-expire-days` (`name="wcb_settings[jobs_expire_days]"`) to `45` → click the section's "Save Changes" submit (`#section-listings form[action="options.php"]` → `submit.wcb-btn--primary`) → expect the WP Settings API redirect back with the `settings-updated=true` "Settings saved." notice and the toggle persisting as off / fields as 12 and 45 on reload.

8. **Pages tab** — click `a.wcb-settings-nav-item[data-section="pages"]` → expect `#section-pages` to become the visible section (hash becomes `#pages`). Each canonical page dropdown is present and pre-selected to the wizard-created page: `#wcb-page-jobs_archive_page`, `#wcb-page-employer_dashboard_page`, `#wcb-page-candidate_dashboard_page`, `#wcb-page-company_archive_page`, `#wcb-page-post_job_page`, `#wcb-page-employer_registration_page` (all `name="wcb_settings[<key>]"`), each followed by a "View Page →" link to the assigned page. Click this section's "Save Changes" → expect `settings-updated=true` and assignments preserved.

9. **Notifications tab** — click `a.wcb-settings-nav-item[data-section="notifications"]` → `#section-notifications` shows. Set `#wcb-from-name` (`name="wcb_settings[from_name]"`) to `Careers Team`, `#wcb-from-email` (`name="wcb_settings[from_email]"`) to `careers@jobboard.local`, and the required `#wcb-notification-email` (`name="wcb_settings[notification_email]"`) to `admin@jobboard.local` → click "Save Changes" → expect `settings-updated=true` and the sender values persisting.

10. **Emails tab** — click `a.wcb-settings-nav-item[data-section="emails"]` → expect `#section-emails` to show the email-template UI rendered by the `wcb_settings_tab_emails` action (a list of editable notification templates with merge-tag support). Expect HTTP 200 with no missing-section / blank-panel state.

11. **Sample-data control on Settings** — back on `#section-listings`, because sample data now exists (`SetupWizard::has_sample_data()` is true), expect the `#wcb-sample-data-block` card with the "Remove Sample Data" button `#wcb-remove-sample-data`. Click it, confirm the modal, and expect `POST http://jobboard.local/wp-json/wcb/v1/wizard/remove-sample-data` returning HTTP 200 with counts `{ jobs, companies, candidates, terms }`; the card is then replaced by the `#wcb-install-sample-block` "Install Sample Data" form (which posts `action=wcb_install_demo` to `admin-post.php` with the `wcb_install_demo` nonce). (Skip the click if the run must leave seeded data in place.)

12. Click the page-header "Run Setup Wizard" button (`a.wcb-btn` → `admin.php?page=wcb-setup`) → expect HTTP 200 and, since setup is already complete and `wcb_rerun` is absent, the "Setup Already Completed" card with the "Re-run Setup Wizard" link (`admin.php?page=wcb-setup&wcb_rerun=1`).

13. tail `wp-content/debug.log` diff over the whole run → expect ZERO new fatal/warning lines.

## Teardown
```bash
SITE='/Users/varundubey/Local Sites/jobboard/app/public'

# Remove wizard-seeded sample data cleanly (idempotent; mirrors SetupWizard::remove_sample_data()).
wp eval '(new \WCB\Admin\SetupWizard())->remove_sample_data();' --path="$SITE" 2>/dev/null || true

# If step 11's "Remove Sample Data" was clicked instead, the above is a no-op.

# Reset the settings touched in steps 7-9 back to defaults (optional — only the keys this walkthrough changed).
wp eval '
$s = (array) get_option("wcb_settings", array());
unset($s["jobs_per_page"], $s["jobs_expire_days"], $s["from_name"], $s["from_email"]);
$s["auto_publish_jobs"] = 0;
update_option("wcb_settings", $s);
' --path="$SITE" 2>/dev/null || true

# Note: the six required pages created in step 2 are intentionally left in place —
# they are the canonical Career Board pages, not throwaway fixtures.
```

## Notes
- **Wizard page + force-render:** `admin/class-setup-wizard.php:128-141` registers `admin.php?page=wcb-setup` (parent `options.php`, cap `wcb_manage_settings`). `render()` (L359-382) shows `setup-wizard-complete.php` once `is_setup_complete()` is true unless the `wcb_wizard_force_render` filter (fed by `?wcb_rerun=1`, L361/373) is true. `is_setup_complete()` (L46-57) = `wcb_setup_complete` option OR any core page id mapped in `wcb_settings`.
- **Wizard steps + selectors:** `admin/views/setup-wizard.php` (`.wcb-wizard-wrap`, `#wcb-wizard-steps`, `.wcb-wizard-step[data-step][data-key]`, first card `active`). Step partials: `admin/views/wizard-steps/create-pages.php` (`#wcb-create-pages`) and `sample-data.php` (`#wcb-install-sample` checked, `#wcb-finish-wizard`).
- **Wizard JS + REST:** `assets/js/wizard.js` — create-pages POST (L101-114), sample-data POST with `{install_sample}` (L118-133), `completeWizard()` POST `/complete` → `response.redirect` (L45-54). `restUrl = rest_url('wcb/v1/wizard')` localized in `enqueue_wizard_assets()` (L201-209). Routes registered in `register_routes()` (L219-264): `POST /wcb/v1/wizard/create-pages`, `/sample-data` (arg `install_sample` absint), `/complete`, `/remove-sample-data`; all gated by `wizard_permission_check()` → ability `wcb/manage-settings` (L273-278).
- **Pages created + slugs:** `create_required_pages()` (L392-487) inserts the six pages with block content; canonical slugs in `admin/class-pages.php:42-50` (`post_job_page=>post-a-job`, `employer_dashboard_page=>employer-dashboard`, `candidate_dashboard_page=>candidate-dashboard`, `jobs_archive_page=>find-jobs`, `company_archive_page=>find-companies`, `employer_registration_page=>employer-registration`, plus Pro-only `resume_archive_page=>find-resumes`). IDs persisted into `wcb_settings`.
- **Sample data:** `install_sample_data()` (L499-857) seeds 3 companies + 8 jobs + terms, sets `wcb_sample_data_ids` / `wcb_sample_data_installed`, fires `wcb_sample_data_installed`. `has_sample_data()` (L920-968) / `remove_sample_data()` (L982-1126) back the Settings card.
- **Settings shell + nav:** `admin/class-admin-settings.php` — page `admin.php?page=wcb-settings` registered in `admin/class-admin.php:215-220`; tabs from `get_tabs()` (L371-394: listings/pages/notifications/emails/import/integrations); sidebar nav `a.wcb-settings-nav-item[data-section]` (L785) → sections `#section-<slug>` (L823/971/1051/1090/1110). Section toggle + `?tab=`/`#hash` deep-link in `assets/js/admin/settings-nav.js` (L13-93). "Run Setup Wizard" header button at L762.
- **Listings/moderation fields:** `auto_publish_jobs` toggle L837, `jobs_per_page` `#wcb-jobs-per-page` L848, `jobs_expire_days` `#wcb-jobs-expire-days` L855 — each section is its own `<form action="options.php">` under `settings_fields('wcb_settings_group')`. `auto_publish_jobs` default OFF means jobs land as Pending (matches the employer-post-job moderation path).
- **Pages dropdowns:** `wp_dropdown_pages()` ids `#wcb-page-<key>` / names `wcb_settings[<key>]`, pre-selected via `Pages::get_id()` (L1015-1041), "View Page →" link L1034.
- **Notifications fields:** `#wcb-from-name`/`#wcb-from-email`/`#wcb-notification-email` (L1063/1070/1077). **Emails tab:** `#section-emails` content via `do_action('wcb_settings_tab_emails', $settings)` (L1098), rendered by `admin/class-email-settings.php`.
- **Seed needs:** none beyond `mu:autologin` — this walkthrough *creates* the pages and sample data itself. Re-running is safe: page creation skips existing keys, sample seeding re-tracks existing IDs (idempotent).
