---
id: orphan-application-shows-job-removed
priority: critical
personas: wcbp_p5_candidate
requires: mu:autologin
last_verified: 2026-05-12
bug_ref: session-2026-05-12 follow-up to Basecamp 9871740742
---

# Candidate's "My Applications" row stays readable when the linked job has been deleted

**Why this journey exists:** A candidate's apply history is theirs — when the employer or admin deletes the job they applied to, the application row must still tell them what they applied to and when. Pre-fix the row rendered as a blank line because the endpoint returned empty strings for `jobTitle` / `company`.

## Steps

1. As `wcbp_p5_candidate`, apply to any published job → confirm the new application appears in `/candidate-dashboard/` → My Applications, status "Submitted".
2. As a site admin, permanently delete the job post (Trash → Delete Permanently — `wp_trash_post` alone does not trigger the lifecycle hook by design).
3. Confirm the `_wcb_status` meta on the application post is now `job_removed` → `wp post meta get <app-id> _wcb_status` returns `job_removed`.
4. Confirm the status log gained an entry → `wp post meta get <app-id> _wcb_status_log` includes an entry with `to: job_removed` and `reason: job_deleted`.
5. As `wcbp_p5_candidate`, reload `/candidate-dashboard/` → click My Applications tab.
6. The row corresponding to the deleted job must:
   - Render `jobTitle` text "Job no longer available" (or the title snapshot if the application was created post-snapshot-meta release).
   - Render the date the application was submitted (NOT the deletion date).
   - Render a status badge labelled "Job Removed" with `data-status="job_removed"` and the muted-grey styling.
   - NOT render a working anchor — the `<a>` href is empty.
7. tail debug.log diff → expect ZERO new fatal/warning lines.

## Teardown

```bash
# Hard-delete the test application so the journey is rerunnable.
wp post delete <app-id> --force
```

## Notes

Future applications created against still-live jobs will have `_wcb_job_title_snapshot` + `_wcb_company_name_snapshot` meta from apply-time, so step 6's title fallback reads the snapshot before falling through to "Job no longer available". The 35 orphan applications backfilled during the 1.1.1 session don't have snapshots and will always render the localised fallback string.
