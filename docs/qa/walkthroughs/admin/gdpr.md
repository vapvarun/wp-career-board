---
id: walkthrough-admin-gdpr
priority: high
personas: varundubey
requires: mu:autologin
last_verified: 2026-07-08
covers: admin/walkthrough-gdpr-export-erase
---

# Walkthrough: GDPR Export & Erase — export then erase a candidate's Career Board data via WordPress core privacy tools

**Why this journey exists:** WP Career Board ships no export/erase UI of its own — it registers a data exporter and eraser with WordPress core's built-in Tools > Export/Erase Personal Data workflow (`GdprModule::boot()`, `modules/gdpr/class-gdpr-module.php:33-36`). This walkthrough traces the full admin path: confirm registration, request + run an export (page-based, big-site safe), then request + run a destructive erase that removes applications + resume + bookmark meta and writes a hashed-IP audit row. It is the human-runnable form of the GDPR sentinel.

## Steps

1. As `varundubey`, navigate to `http://jobboard.local/wp-admin/export-personal-data.php?autologin=varundubey` → expect HTTP 200 and the core "Export Personal Data" screen with an "Add Data Export Request" email field (the page WCB's exporter plugs into via `register_exporter()`, keyed `wp-career-board`, friendly name "WP Career Board" — `modules/gdpr/class-gdpr-module.php:46-52`).
2. Enter candidate `sarah.chen`'s email, submit "Send Request" → expect a new request row with status "Pending" (admin-initiated; no confirmation email needed).
3. Trigger the export from the request row's action → expect the generated archive/preview to contain a **"Job Applications"** group (`group_id` `wcb-applications`, `group_label` "Job Applications", `modules/gdpr/class-gdpr-module.php:130-131`).
4. Inspect the group → expect each item labelled `application-<ID>` to carry exactly three fields **Job**, **Status**, **Submitted** (`modules/gdpr/class-gdpr-module.php:134-145`), with `Job` resolved from the linked job title (`_wcb_job_id` lookup) and `Status` from `_wcb_status`. Applications are matched to the candidate by `_wcb_candidate_id` = the user ID (`:105-110`).
5. Confirm the exporter is page-based (big-site safe): it returns at most 100 items per page (`$per_page = 100`, `paged` increments) and reports `done` only when a partial page comes back (`modules/gdpr/class-gdpr-module.php:93-158`) → a candidate with >100 applications completes across pages with no unbounded query and no PHP timeout/fatal.
6. Confirm the export writes ONE audit row on completion: `wp db query "SELECT action, user_id, CHAR_LENGTH(ip_hash) AS hlen FROM wp_wcb_gdpr_log ORDER BY id DESC LIMIT 1" --skip-column-names --path=/Users/varundubey/Local Sites/jobboard/app/public` → expect `action = export`, `user_id` = sarah.chen's ID, and `hlen = 64` (the IP is stored only as a SHA-256 hash, never plaintext — `log_action()`, `modules/gdpr/class-gdpr-module.php:245-263`). Logged once, on the final (`done`) page only (`:150-153`).
7. Navigate to `http://jobboard.local/wp-admin/erase-personal-data.php?autologin=varundubey` → expect HTTP 200 and the core "Erase Personal Data" screen with an "Add Data Erasure Request" email field (WCB's eraser plugs in via `register_eraser()`, `modules/gdpr/class-gdpr-module.php:62-68`).
8. Enter sarah.chen's email, submit "Send Request" → expect a "Pending" erasure row; trigger "Force Erase Personal Data" on that row → expect the WCB eraser to run.
9. Verify the eraser deletes applications in destructive batches: it reads page 1 of up to 100 `wcb_application` posts scoped by `_wcb_candidate_id` and force-deletes each (`wp_delete_post(..., true)`), re-invoked by WP until a partial batch signals `done` (`modules/gdpr/class-gdpr-module.php:188-214`) → expect the response to report `items_removed > 0` and `items_retained = 0`.
10. Verify profile-level meta is cleared exactly once, on the final batch: after `done`, the eraser calls `delete_user_meta($user, '_wcb_resume_data')` and `delete_user_meta($user, '_wcb_bookmark')` (`modules/gdpr/class-gdpr-module.php:216-221`) → both meta keys are gone for sarah.chen (resume data + all bookmark rows), not double-run on a multi-page erase.
11. Confirm the erase writes its own audit row: `wp db query "SELECT action, user_id, CHAR_LENGTH(ip_hash) FROM wp_wcb_gdpr_log ORDER BY id DESC LIMIT 1" --skip-column-names` → expect `action = erase`, sarah.chen's `user_id`, and a 64-char hash, written once on the final batch (`:223`).
12. Confirm idempotency / multi-actor safety: re-run the erase for the same (now-erased) email → expect `items_removed = 0`, `done = true`, no fatal (the `get_posts` returns empty, the `foreach` is a no-op). A non-existent email returns the empty/`done` shape immediately (`modules/gdpr/class-gdpr-module.php:178-186`).
13. tail `wp-content/debug.log` diff over the whole export + erase run → expect ZERO new fatal/warning lines.

## Teardown

```bash
SITE='/Users/varundubey/Local Sites/jobboard/app/public'
# Remove the privacy request posts created during the walk (core 'user_request' CPT). Safe + re-runnable.
wp post list --post_type=user_request --field=ID --path="$SITE" 2>/dev/null \
  | xargs -r -n1 -I{} wp post delete {} --force --path="$SITE" 2>/dev/null || true

# The wp_wcb_gdpr_log rows are an intentional permanent audit record — do NOT delete them.
# Erase is destructive by design; re-seed sarah.chen's applications/resume/bookmarks if a later
# journey needs them back.
```

## Notes
- The export/erase admin URLs are **WordPress core** (`/wp-admin/export-personal-data.php`, `/wp-admin/erase-personal-data.php`, under the Tools menu) — WCB contributes only the exporter/eraser callbacks, so there is no `page=wcb-*` screen for this feature.
- A meaningful run needs sarah.chen to have >= 1 `wcb_application` (with `_wcb_candidate_id` set) and ideally `_wcb_resume_data` / `_wcb_bookmark` meta; seed those first, else steps 4/10 assert against an empty set.
- The `wp_wcb_gdpr_log` table (`user_id`, `action`, `ip_hash`, `created_at`) is created by `core/class-install.php`; `ip_hash` is always SHA-256 (64 hex chars) — the plaintext IP is never persisted.
- Free-only feature — no Pro module required. No 1.5.1-new surface in this walkthrough.
