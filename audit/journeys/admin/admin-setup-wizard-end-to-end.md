---
id: admin-setup-wizard-end-to-end
priority: high
personas: varundubey
requires: mu:autologin
last_verified: 2026-09-27
needs: cli
bug_ref: Basecamp 9890815047
---

# Admin walks the setup wizard from start to finish; completion flag is set

> **Set these two first.** Every command and URL below uses them, so the
> walkthrough runs on any machine rather than the one it was written on:
>
> ```bash
> WCB_PATH="$(cd "$(git rev-parse --show-toplevel)/../../.." && pwd)"
> WCB_SITE="$(wp --path="$WCB_PATH" option get home)"
> ```
>
> `bin/qa-fixtures.sh` derives the same root the same way, so the two agree.

**Why this journey exists:** Guards the full wizard lifecycle: page renders without the admin menu (focus mode), each step's REST endpoint responds correctly, the Pages step creates every page in `Pages::definitions()` (Pro's Find Candidates and Job Map included), each settings step saves through the settings schema without wiping other keys, and the completion step sets `wcb_setup_complete = true` so the wizard does not relaunch on the next admin visit.

## Steps

1. Ensure a clean wizard state for the test:
   ```bash
   wp option update wcb_setup_complete 0
   ```
2. As `varundubey`, navigate to `/wp-admin/admin.php?page=wcb-setup&autologin=1` → expect 200, wizard UI renders; first step "Create Pages" is visible (or the welcome step if Pro adds one). At 1440px viewport, assert `.wcb-wizard-wrap` is horizontally centered (visible left and right margins). At 768px viewport, assert side-margin is ~12px with no horizontal scroll.
3. Confirm the wizard JS localization data is present by checking the page source for `wcbWizard` object containing `restUrl` and `steps` keys
4. Execute Step 1 (Create Pages) via the wizard REST endpoint as admin:
   ```bash
   NONCE=$(wp eval 'echo wp_create_nonce("wp_rest");')
   curl -s -X POST "$WCB_SITE/wp-json/wcb/v1/wizard/create-pages" \
     -H "X-WP-Nonce: $NONCE" \
     -H "Cookie: $(wp eval 'wp_set_auth_cookie(1); echo $_COOKIE[AUTH_COOKIE] ?? "";')" \
     | python3 -m json.tool
   ```
   → expect 200, JSON response contains page IDs for at least `jobs_archive_page` and `employer_dashboard_page`
5. Verify pages were persisted into `wcb_settings`:
   ```bash
   wp option get wcb_settings --format=json | python3 -c "
   import json,sys; d=json.load(sys.stdin)
   print('jobs_archive_page:', d.get('jobs_archive_page', 0))
   print('employer_dashboard_page:', d.get('employer_dashboard_page', 0))
   "
   ```
   → both values are > 0 (page IDs were created and saved)
6. Save a settings step (Sign-ups + Jobs) exactly as the wizard buttons do:
   ```bash
   curl -s -X POST "$WCB_SITE/wp-json/wcb/v1/wizard/settings" \
     -H "X-WP-Nonce: $NONCE" -H "Content-Type: application/json" \
     -d '{"settings":{"users_can_register":1,"require_email_verification":1,"jobs_expire_days":45,"salary_currency":"EUR","not_a_setting":"x"}}'
   ```
   → `{"saved": true}`; `wp option get users_can_register` = 1; `wcb_settings` has `jobs_expire_days` 45, `salary_currency` EUR, `require_email_verification` true, no `not_a_setting`, and every key present before the call (e.g. `max_resumes`, page IDs) is unchanged.
   In the browser: the stepper marks finished steps done, a finished step can be reopened from the stepper with its saved value shown, "Skip for now" advances without saving, and the CAPTCHA step shows only the chosen provider's key fields.
7. Execute the Sample Data step — skip sample data install):
   ```bash
   curl -s -X POST "$WCB_SITE/wp-json/wcb/v1/wizard/sample-data" \
     -H "X-WP-Nonce: $NONCE" \
     -H "Content-Type: application/json" \
     -d '{"install_sample": 0}' | python3 -m json.tool
   ```
   → expect `{"installed": false}`
8. Execute the completion step:
   ```bash
   curl -s -X POST "$WCB_SITE/wp-json/wcb/v1/wizard/complete" \
     -H "X-WP-Nonce: $NONCE" | python3 -m json.tool
   ```
   → expect JSON with `redirect` key pointing to `admin.php?page=wp-career-board`
9. Verify the completion flag is set:
   ```bash
   wp option get wcb_setup_complete
   ```
   → output is `1`
10. Verify the wizard does NOT relaunch: navigate to `admin.php?page=wp-career-board&autologin=1` → expect the Career Board dashboard, NOT a redirect to the wizard
11. Diff `debug.log` → expect ZERO new fatal/warning/notice lines

## Teardown

```bash
# Restore the wizard flag to whatever it was (typically true on a configured site)
wp option update wcb_setup_complete 1
# Clean up wizard-created pages if they conflict with existing setup
# Only delete if they were newly created (check by comparing with pre-test IDs)
```

## Notes

- Since 1.8.0 Free has 6 steps: `create-pages`, `registration`, `jobs`, `emails`, `anti-spam`, `sample-data`. Pro inserts `license` and `credits` ("How Employers Pay") before `sample-data` via `wcb_wizard_steps`. Enumerate via `wcbWizard.steps` in the page source.
- The "Skip" path for each step is handled in the frontend JS (user clicks "Skip" → still calls the step endpoint but ignores the response). For this journey, the REST calls simulate "Skip" behavior (steps execute with minimal side effects).
- The wizard page slug is `wcb-setup` (registered under `options.php` parent to hide from the submenu), not `wcb-setup-wizard`.
- `SetupWizard::is_setup_complete()` checks the `wcb_setup_complete` flag AND falls back to checking if any core page IDs are set. Setting the flag to 0 is enough to reopen the wizard only if no pages are configured.
