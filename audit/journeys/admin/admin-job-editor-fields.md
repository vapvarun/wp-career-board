---
id: admin-job-editor-fields
priority: high
personas: varundubey
requires: mu:autologin
last_verified: 2026-10-13
needs: cli
bug_ref: 10389803878, 10389801810, 10389500513, 10389802926
---

# Admin job editor has Apply URL and Apply Email, follows the site currency, takes any salary value, and handles a blank deadline

**Why this journey exists:** the wp-admin Job Details box saved its own values with its own rules: currency fell back to a hardcoded USD, salary inputs had `step=1000`, there were no Apply URL or Apply Email fields (admin-created jobs could not route applications), and a blank deadline made the job never expire. It now saves through the same `JobsMeta` sanitizers as REST, the forms and the importers.

## Steps

1. Set the site currency: `wp option patch update wcb_settings salary_currency GBP`. Open `$WCB_SITE/wp-admin/post-new.php?post_type=wcb_job` → `#wcb_salary_currency` is preselected to GBP (not USD). On an existing job with no currency meta it is GBP too; on a job that has `_wcb_salary_currency` EUR it stays EUR.
2. Salary accepts any value: `#wcb_salary_min` and `#wcb_salary_max` have `step="any"`; enter `85000.50` and `120000` → no HTML validation bubble; after Update `wp post meta get <id> _wcb_salary_min` = `85000.5` and `_wcb_salary_max` = `120000`. Set the input value to `-5` or `abc` via devtools and Update → the stored value is an empty string (`wp post meta get <id> _wcb_salary_min` prints nothing; `sanitize_amount` rejects negatives and non-numbers). Emptying a saved salary deletes the row.
3. Apply fields: `#wcb_apply_url` (type=url, placeholder "https://") and `#wcb_apply_email` (type=email) exist in the box. Save `https://example.com/apply?ref=1` and `jobs@example.com` → `_wcb_apply_url` and `_wcb_apply_email` hold them; on the public job page the Apply button routes to the URL (or the email, per `job-apply-routing-email-or-url`). Enter `javascript:alert(1)` as the URL → stored empty (`esc_url_raw` drops the `javascript:` scheme), never as a script URL; enter `not-an-email` → stored empty (`sanitize_email`).
4. Clearing the fields deletes the meta: save with both empty → `wp post meta get <id> _wcb_apply_url` returns nothing (row deleted, not an empty string).
5. Blank deadline = board default: leave `#wcb_deadline` empty and untick "No expiry", Update → `_wcb_deadline` = today + Settings > Jobs > Default listing length (30 by default), or the board's own length with Pro (`JobDeadline::default_end_for_board`). The help text under the field reads "Leave empty to use the board's listing length."
6. Deliberate evergreen: tick **No expiry (stays open until closed)** (`name=wcb_no_expiry`), Update → `_wcb_deadline` is empty and the job never expires (run `wp cron event run wcb_check_job_expiry`; the job stays `publish`). Reopen the editor: the box is ticked for a saved job with no deadline, and NOT ticked for a brand-new job (`auto-draft`).
7. Explicit date still wins: set `#wcb_deadline` to a date 20 days out with No expiry unticked → stored exactly. Ticking No expiry while a date is typed clears the deadline (the checkbox wins).
8. Currency tamper: submit the editor form with `wcb_salary_currency=xx` (devtools, a value not in the catalog) → `_wcb_salary_currency` is stored as the site currency (`GBP`), never `xx` (`JobsMeta::sanitize_currency`).
8a. **No untitled job goes live, and the salary range stays low-high (card:10412612838, card:10417402598).** Career Board > Jobs > Add New, leave the title empty, click Publish: the screen reloads as a Draft with the error "Add a title before publishing. It was saved as a draft." Same for Add Board (Settings > Boards) and Add New Resume. A company in the block editor with body text but no title shows "Publishing failed. Add a title before publishing." In a titled job, enter Salary Min 90000 and Max 50000 and Update: the saved pair reads 50000 / 90000. Regression test: `tests/test-write-validation.php`.
9. tail debug.log diff → expect ZERO new fatal/warning lines.

## Teardown

```bash
wp post delete <job ids> --force
wp option patch update wcb_settings salary_currency USD
```

## Notes

- Save path: `AdminMetaBoxes::save_job_meta()` loops `salary_currency, salary_type, salary_min, salary_max, apply_url, apply_email` through `update_post_meta()`, which applies the sanitizers `JobsMeta` registers per key.
- The site-currency helper is `JobsMeta::default_currency()`; every former hardcoded `'USD'` fallback (job page, listings, dashboards, REST, search, wizard samples, candidate and settings endpoints) now calls it.
