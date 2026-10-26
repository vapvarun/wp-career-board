---
id: admin-applications-export
priority: medium
personas: varundubey, employer.figma
requires: mu:autologin, seed:applications
last_verified: 2026-10-26
needs: cli
---

# Admin exports applications as CSV (all matching, ticked rows, one job); file is complete and formula-safe

**Why this journey exists:** the applications CSV is how HR teams pull candidate data out of the plugin (`WCB\Core\ApplicationsCsv`, `core/class-applications-csv.php`); an empty file, a wrong Content-Type, rows the caller may not see, or a cell that opens as a spreadsheet formula are all customer-visible failures. This journey was previously aimed at Pro's credits ledger CSV; that export is covered by Pro's own journeys (`wp-career-board-pro/audit/journeys/admin/walkthrough-analytics.md`).

## Steps

1. Seed two applications on a published job, one from a guest whose name starts with `=` to prove the formula guard:
   ```bash
   JOB_ID=$(wp post list --post_type=wcb_job --post_status=publish --field=ID --posts_per_page=1)
   A1=$(wp post create --post_type=wcb_application --post_title='CSV smoke guest' --post_status=publish --post_author=1 --porcelain)
   wp post meta update $A1 _wcb_job_id $JOB_ID; wp post meta update $A1 _wcb_status submitted
   wp post meta update $A1 _wcb_guest_name '=HYPERLINK("http://example.test","x")'; wp post meta update $A1 _wcb_guest_email csv-guest@example.test
   A2=$(wp post create --post_type=wcb_application --post_title='CSV smoke guest two' --post_status=publish --post_author=1 --porcelain)
   wp post meta update $A2 _wcb_job_id $JOB_ID; wp post meta update $A2 _wcb_status shortlisted
   wp post meta update $A2 _wcb_guest_name 'Mei Tanaka'; wp post meta update $A2 _wcb_guest_email mei@example.test
   ```
2. As `varundubey` (admin), navigate to `/wp-admin/admin.php?page=wcb-applications&autologin=1` → expect HTTP 200, the list renders and an **Export all matching to CSV** button is visible (it only renders when at least one row matches the current filters)
3. Click **Export all matching to CSV** (a link to `admin.php?page=wcb-applications&wcb_export=all&_wpnonce=...`) → expect a download named `wcb-applications-<timestamp>.csv`, response header `Content-Type: text/csv; charset=utf-8`, body starts with a UTF-8 BOM
4. Verify the header row: `ID, Job ID, Job title, Applicant name, Applicant Email, Status, Submitted, Cover letter, Resume URL, Screening Answers`
5. Verify both seeded rows are present with status labels `Submitted` and `Shortlisted`, the job title of `$JOB_ID`, and that the first guest's name cell is `'=HYPERLINK(...)` (a leading apostrophe) so a spreadsheet shows text, not a formula
6. Filter then export: open `admin.php?page=wcb-applications&app_status=shortlisted`, click **Export all matching to CSV** → expect only shortlisted rows (Mei Tanaka present, the `=HYPERLINK` row absent)
7. Ticked rows: on the unfiltered list tick only the `CSV smoke guest two` row, choose bulk action **Export to CSV**, Apply → expect a CSV with exactly that one data row
8. Tampered nonce: request the step 3 URL with `_wpnonce=bad` → expect the WordPress "link expired" screen (403), no CSV body
9. Per-job export for an employer: as `employer.figma` (owner of `$JOB_ID`'s company), GET `/wp-json/wcb/v1/jobs/$JOB_ID/applications/export` → expect HTTP 200 `text/csv` containing the same job's rows. As a different employer (not the owner) the same request returns a REST permission error (401/403), never the CSV
10. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

```bash
wp post delete $A1 $A2 --force
```

## Notes

- The admin button and the bulk action share one streamer (`ApplicationsCsv::stream()`); the REST route `GET /wcb/v1/jobs/{id}/applications/export` (`ApplicationsEndpoint`) is the same streamer behind an owner-or-`wcb/manage-settings` check.
- The Applications admin page needs the `wcb_manage_settings` capability (`admin/class-admin.php`).
- Rows are read in batches of 500 ids, so "export all" does not load every application at once.
