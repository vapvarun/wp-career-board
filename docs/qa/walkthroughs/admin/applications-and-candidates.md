---
id: walkthrough-admin-applications-and-candidates
priority: high
personas: varundubey
requires: mu:autologin, seed:jobs
last_verified: 2026-07-08
covers: admin/admin-applications-page-renders, admin/admin-applications-export, admin/admin-candidates-page-renders
---

# Walkthrough: Applications & Candidates — render the Applications list, export selected rows as CSV, and render the Candidates list

**Why this journey exists:** HR teams live in the Applications list. It must render its columns, let the admin change application status, and export selected applications to a well-formed CSV (a 500 or an empty/truncated file on export is a high-impact support ticket). The Candidates list must render and be searchable. This is the human-runnable form of the three applications/candidates sentinels.

## Steps

1. As `varundubey`, navigate to `http://jobboard.local/wp-admin/admin.php?page=wcb-applications&autologin=varundubey` → expect HTTP 200 and the Applications list table (`AdminApplications::render()`, `admin/class-admin-applications.php:64`) with column headers **Candidate, Job, Status, Change Status, Date** (`get_columns()`, `admin/class-admin-applications.php:112-120`).
2. Confirm the status views render (`get_views()`, `admin/class-admin-applications.php:279`): expect "All" plus per-status links. Application status is stored in `_wcb_status` postmeta; filtering to a status must return only that subset.
3. Seed one application per status so the export has rows: `APP1=$(wp post create --post_type=wcb_application --post_title='Smoke App A' --post_status=publish --post_author=1 --porcelain --path=/Users/varundubey/Local Sites/jobboard/app/public)` then `wp post meta update $APP1 _wcb_status submitted`; repeat for `APP2` with `_wcb_status reviewing`. Reload the list → expect both rows visible.
4. **Change status via bulk action.** Select `$APP1`, choose "Mark as Shortlisted" from the bulk dropdown (`get_bulk_actions()` offers Mark as Reviewing/Shortlisted/Rejected/Hired, Export to CSV, Move to Trash — `admin/class-admin-applications.php:141-149`), Apply → expect a redirect back to `admin.php?page=wcb-applications` and `wp post meta get $APP1 _wcb_status` → `shortlisted` (fires `wcb_application_status_changed`, `admin/class-admin-applications.php:598-616`).
5. **CSV export.** Select `$APP1` and `$APP2`, choose "Export to CSV", Apply → expect a file download, NOT a page reload (`export_applications_csv()` streams to `php://output` and exits, `admin/class-admin-applications.php:593-596,634-638`). Verify the response headers: `Content-Type: text/csv; charset=utf-8` and `Content-Disposition: attachment; filename="wcb-applications-<YYYY-MM-DD-His>.csv"`.
6. Verify the CSV body: it opens with a UTF-8 BOM (`\xEF\xBB\xBF`) and a header row of columns **ID, Job ID, Job Title, Applicant Name, Applicant Email, Status, Submitted, Cover Letter, Resume URL** (`admin/class-admin-applications.php:640-645` + docblock `:626-627`), followed by exactly one data row per selected application (2 rows here). Assert no PHP error/HTML leaked into the file.
7. Confirm the export is nonce-gated: the bulk form carries the `bulk-applications` nonce and the handler rejects a missing/invalid `_wpnonce` (`admin/class-admin-applications.php:582-585`) — a direct `?action=export_csv` without the nonce must NOT stream a file.
8. **Candidates list.** Navigate to `http://jobboard.local/wp-admin/admin.php?page=wcb-candidates&autologin=varundubey` → expect HTTP 200 and the Candidates list table (`AdminCandidates::render()`) rendering with no PHP error.
9. Seed a candidate and search: `CAND=$(wp post create --post_type=wcb_resume --post_title='Smoke Candidate AlphaTest' --post_status=publish --post_author=1 --porcelain ...)`; reload the Candidates list → expect "Smoke Candidate AlphaTest" in the table. Use the search box (`&s=AlphaTest`) → expect exactly the one matching row; search `&s=ZZZNoMatch9999` → expect the empty-state message, no rows.
10. tail `wp-content/debug.log` diff over the whole run → expect ZERO new fatal/warning lines.

## Teardown

```bash
SITE='/Users/varundubey/Local Sites/jobboard/app/public'
wp post delete "$APP1" "$APP2" "$CAND" --force --path="$SITE" 2>/dev/null || true
# Downloaded CSV: delete the local file if the browser saved one.
```

## Notes
- Applications and Candidates are CUSTOM admin pages (`admin.php?page=wcb-applications` / `?page=wcb-candidates`, rendered by `WP_List_Table` subclasses), NOT the native `edit.php?post_type=…` CPT screens that older atomic journeys reference. Menu registration: `admin/class-admin.php:93-110`.
- Application status lives in `_wcb_status` postmeta (submitted / reviewing / shortlisted / rejected / hired), not as `post_status`. The "Change Status" column offers an inline change per row; the bulk `Mark as *` actions are the multi-row path.
- The CSV export is a **Free** feature (streamed by `AdminApplications`); it is distinct from the Pro analytics `GET /wcb/v1/analytics/credits.csv` export. This walkthrough covers the Free application CSV only.
- Candidates are the `wcb_resume` CPT; if `resume_archive_enabled` is off there is no public "View" link — the walkthrough only asserts render + search.
- No 1.5.1-new surface in this walkthrough.
