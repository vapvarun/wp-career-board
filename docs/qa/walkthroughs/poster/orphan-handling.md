---
id: poster-orphan-handling
priority: high
personas: employer.figma, wcbp_p5_candidate, varundubey
requires: mu:autologin
last_verified: 2026-07-08
bug_ref: 9976738869
covers: blocks/employer-dashboard, POST /wcb/v1/employers, GET /wcb/v1/employers/me/jobs, blocks/candidate-dashboard, application status job_removed
---

# Walkthrough: Orphan Handling — a job posted before the company is adopted on company create, and an application to a deleted job reads "Job Removed"

**Why this journey exists:** Two data-integrity edge cases the plugin must handle gracefully. (1) An employer can post a job *before* saving a company profile — that job never got `_wcb_company_id` (JobsEndpoint only stamps it when a company already exists), so once My Jobs queries by company the job would be invisible forever; creating the company must backfill the link (guards Basecamp 9976738869). (2) When the employer/admin deletes a job a candidate applied to, the candidate's "My Applications" row must stay readable — status flips to `job_removed` and the row still shows what they applied to and when. Consolidates `customer/orphan-job-adopted-on-company-create` and `customer/orphan-application-shows-job-removed`.

## Steps

### A. Orphan job adopted on company create

1. As `employer.figma` in a state with NO company (`_wcb_company_id` user meta unset — clear it for the test: `wp user meta delete 50 _wcb_company_id`), create a `wcb_job` authored by them (via the Post-a-Job form, or `wp post create --post_type=wcb_job --post_author=50 --post_status=pending --post_title='Orphan Adopt Test'`) → capture `<job-id>` and confirm the orphan state: `wp post meta get <job-id> _wcb_company_id` is empty.

2. Save a company profile: `POST http://jobboard.local/wp-json/wcb/v1/employers` `{"name":"Adopt Test Co"}` → expect HTTP 200; `_wcb_company_id` user meta is now set (capture `<company-id>`). Backfill lives in `EmployersEndpoint::create_item()` → `backfill_orphan_jobs()` (idempotent, bounded to the author's own jobs).

3. Verify the backfill: `wp post meta get <job-id> _wcb_company_id` → equals `<company-id>`, and `wp post meta get <job-id> _wcb_company_name` → equals the company title "Adopt Test Co".

4. `GET http://jobboard.local/wp-json/wcb/v1/employers/me/jobs` → the previously-orphaned job now appears in the list (before the fix it was invisible because My Jobs queries by company id).

5. Verify the stale-list refresh half: after posting via the dashboard Post-a-Job form, switching to My Jobs refetches (the `_needsJobsRefresh` cross-store flag — job-form `view.js` sets it; employer `switchToJobs()` refetches, `blocks/employer-dashboard/render.php:327`), so a newly posted job appears without a manual reload.

### B. Orphan application shows "Job Removed"

6. As `wcbp_p5_candidate`, apply to any published job → confirm the new application appears in `http://jobboard.local/candidate-dashboard/` → My Applications, status "Submitted". Capture `<app-id>`.

7. As `varundubey` (admin), permanently delete that job post (Trash → Delete Permanently — `wp_trash_post` alone does NOT trigger the lifecycle hook by design; use `wp post delete <job-id> --force`).

8. Confirm the application's status flipped: `wp post meta get <app-id> _wcb_status` → `job_removed`; `wp post meta get <app-id> _wcb_status_log` → includes an entry with `to: job_removed` and `reason: job_deleted`.

9. As `wcbp_p5_candidate`, reload `http://jobboard.local/candidate-dashboard/` → click My Applications. The row for the deleted job must:
   - Render `jobTitle` text "Job no longer available" (or the title snapshot if the application post-dates the snapshot-meta release).
   - Render the date the application was submitted (NOT the deletion date).
   - Render a status badge labelled "Job Removed" with `data-status="job_removed"` and muted-grey styling.
   - NOT render a working anchor — the `<a>` href is empty.

10. tail `wp-content/debug.log` diff over the whole run → expect ZERO new fatal/warning lines.

## Teardown

```bash
# Section A: remove the adopt-test job + company; restore employer.figma's original company link if needed.
wp post delete <job-id> --force
wp post delete <company-id> --force

# Section B: hard-delete the test application so the walkthrough is re-runnable.
wp post delete <app-id> --force
```

## Notes

- **Backfill is idempotent and owner-scoped** — `EmployersEndpoint::create_item()` → `backfill_orphan_jobs()` only touches the current author's own jobs, so re-running section A is safe.
- **Snapshot fallback** — applications created against still-live jobs carry `_wcb_job_title_snapshot` + `_wcb_company_name_snapshot` (written at apply time), so step 9's title reads the snapshot before falling through to the localised "Job no longer available" string. Older orphan applications backfilled in the 1.1.1 session have no snapshot and always render the fallback.
- **`job_removed` is system-only** — it is intentionally excluded from the employer-actionable status allowlist (`modules/applications/class-application-status.php`), so it can only be set by the job-deletion lifecycle hook, never via `PATCH /applications/{id}/status`.
- **Not 1.5.1-new** — both edge cases are pre-existing (Basecamp 9976738869 landed pre-1.5.1) and covered by regression sentinels under `audit/journeys/customer/`. This file is the human-runnable consolidation.
- **`employer.figma` is user ID 50** on the reference site; resolve on other machines with `wp user get employer.figma --field=ID`.
