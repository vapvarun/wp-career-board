---
id: walkthrough-gdpr-export-erase
priority: high
personas: varundubey
requires: mu:autologin
last_verified: 2026-06-29
---

# Walkthrough: GDPR Export & Erase — admin exports then erases a candidate's WP Career Board data via WP core privacy tools

**Why this journey exists:** This is the end-to-end walkthrough of the GDPR Export & Erase feature. WP Career Board does not ship its own export/erase UI — it registers a data exporter and eraser with WordPress core's built-in Tools > Export/Erase Personal Data workflow (`wp_privacy_personal_data_exporters` / `wp_privacy_personal_data_erasers`, `modules/gdpr/class-gdpr-module.php:34-35`). This tour traces the full admin path: confirm registration, request an export, run it (page-based, no unbounded load), then request and run an erase that deletes applications + resume + bookmark meta and writes an audit-log row.

## Steps

1. As `varundubey`, navigate to `http://jobboard.local/wp-admin/export-personal-data.php?autologin=varundubey` → expect HTTP 200 and the core "Export Personal Data" screen with an "Add Data Export Request" email field (this is the page WCB's exporter plugs into via `register_exporter`, `class-gdpr-module.php:46-52`).

2. In the "Username or email address" field enter candidate `sarah.chen`'s email and submit "Send Request" → expect a new request row in the list table with status "Pending" (no confirmation email needed for an admin-initiated request — admin can click "Next Steps" once created).

3. Confirm WCB is one of the registered exporters: navigate to `http://jobboard.local/wp-admin/admin.php?page=export_personal_data` is NOT used — instead assert on the request the "WP Career Board" exporter group runs. Trigger the export from the request row's action link → expect the generated archive/preview to contain a **"Job Applications"** group (`group_id` `wcb-applications`, `group_label` from `class-gdpr-module.php:130-131`).

4. Inspect the exported "Job Applications" group → expect each item labelled `application-<ID>` to carry exactly the three fields **Job**, **Status**, **Submitted** (`class-gdpr-module.php:134-145`), with `Job` resolved to the linked job title (via `_wcb_job_id` lookup) and `Status` from `_wcb_status` meta. Applications are matched to the candidate by `_wcb_candidate_id` meta = the user ID (`class-gdpr-module.php:105-110`).

5. Verify the export is page-based (big-site safe): the exporter returns at most 100 items per page (`$per_page = 100`, `paged` increments) and reports `done` only when a partial page comes back (`class-gdpr-module.php:93-150`) → expect the export to complete across pages for a candidate with >100 applications without a single unbounded query. Assert no PHP timeout/fatal during the run.

6. Confirm the export writes an audit-log row on completion: query `wp_wcb_gdpr_log` → expect a new row with `action = 'export'`, `user_id` = sarah.chen's ID, and `ip_hash` a 64-char SHA-256 hash (IP is hashed, never stored plaintext — `class-gdpr-module.php:152` + `log_action()` 245-263). The row is logged once, on the final (`done`) page only.

7. Navigate to `http://jobboard.local/wp-admin/erase-personal-data.php?autologin=varundubey` → expect HTTP 200 and the core "Erase Personal Data" screen with an "Add Data Erasure Request" email field (the page WCB's eraser plugs into via `register_eraser`, `class-gdpr-module.php:62-68`).

8. Enter sarah.chen's email, submit "Send Request" → expect a new erasure request row with status "Pending"; then trigger "Force Erase Personal Data" / the erase action on that row → expect the WCB eraser to run.

9. Verify the eraser deletes applications in destructive batches: it reads page 1 of up to 100 `wcb_application` posts scoped by `_wcb_candidate_id` and `wp_delete_post(..., true)` (force-delete) each (`class-gdpr-module.php:188-212`), re-invoked by WP until a partial batch signals `done` → expect the response to report `items_removed` > 0 and `items_retained` = 0.

10. Verify profile-level meta is cleared once on the final batch: after `done`, the eraser calls `delete_user_meta($user, '_wcb_resume_data')` and `delete_user_meta($user, '_wcb_bookmark')` (`class-gdpr-module.php:216-221`) → expect both meta keys gone for sarah.chen (resume data + all bookmark rows), cleared exactly once (no double-run on multi-page erase).

11. Confirm the erase writes its own audit-log row: query `wp_wcb_gdpr_log` → expect a new row with `action = 'erase'`, `user_id` = sarah.chen's ID, hashed `ip_hash`, written once on the final batch (`class-gdpr-module.php:223`).

12. Confirm idempotency / multi-actor safety: re-run the erase for the same email (already-erased candidate) → expect `items_removed = 0`, `done = true`, no fatal (the `get_posts` returns empty, the `foreach` is a no-op). A request for a non-existent email returns the empty/`done` shape immediately (`class-gdpr-module.php:178-186`).

13. tail debug.log diff → expect ZERO new fatal/warning lines across the entire export + erase run.

## Teardown

```bash
# Remove the export/erase request posts created during the walkthrough (WP stores
# privacy requests as the core 'user_request' CPT). Safe + re-runnable.
wp post list --post_type=user_request --field=ID 2>/dev/null \
  | xargs -r -n1 wp post delete --force 2>/dev/null || true

# The GDPR audit-log rows are an intentional permanent record — do NOT delete them.
# (No application/resume/bookmark restore: erase is destructive by design. Re-seed
# candidate fixtures if a later journey needs sarah.chen's applications back.)
```

## Notes

- Grounded in real code:
  - `modules/gdpr/class-gdpr-module.php:33-36` — `boot()` hooks `wp_privacy_personal_data_exporters` + `wp_privacy_personal_data_erasers`.
  - `class-gdpr-module.php:46-68` — exporter/eraser registration keyed `wp-career-board`, friendly name "WP Career Board".
  - `class-gdpr-module.php:84-159` — page-based exporter (`group_id` `wcb-applications`, fields Job/Status/Submitted, `_wcb_candidate_id`/`_wcb_job_id`/`_wcb_status` meta, `_prime_post_caches` to kill the per-row N+1).
  - `class-gdpr-module.php:177-232` — destructive page-1 eraser, force-deletes applications, clears `_wcb_resume_data` + `_wcb_bookmark` on `done`.
  - `class-gdpr-module.php:245-263` + `core/class-install.php:206-216` — `log_action()` inserts into `{$prefix}wcb_gdpr_log` (`user_id`, `action`, `ip_hash` SHA-256, `created_at`).
- The export/erase **admin URLs are WordPress core**, not WCB pages: `/wp-admin/export-personal-data.php` and `/wp-admin/erase-personal-data.php` (Tools menu). WCB contributes only the exporter/eraser callbacks, so there is no `page=wcb-*` admin screen for this feature.
- No seed flag required beyond the candidate persona, but a meaningful export/erase needs sarah.chen to have ≥1 `wcb_application` (with `_wcb_candidate_id` set) and ideally `_wcb_resume_data` / `_wcb_bookmark` meta; seed those first if the install is empty, else steps 4/10 assert against an empty set.
- Free-only feature — no Pro module required.
