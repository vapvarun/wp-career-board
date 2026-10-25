---
id: job-page-view-is-counted
priority: high
personas: anonymous, candidate, employer, admin
requires: seed:jobs, pro:analytics
last_verified: 2026-10-23
needs: browser, cli
bug_ref: 10403606771
---

# Opening a job page counts a view, and the owner sees it in Analytics

**Why this journey exists:** views were recorded only by `GET /wcb/v1/jobs/<id>` (the app), so a website visitor never added a row to `wp_wcb_job_views` and Analytics > Job Views (30 days) stayed at 0. The job page now sends `POST /wcb/v1/jobs/<id>/view` from the browser once it loads, which a full-page cache cannot skip.

## Steps

1. `SELECT COUNT(*) FROM wp_wcb_job_views` → note N.
2. As a guest, open a published job → the browser sends `POST /wp-json/wcb/v1/jobs/<id>/view` → 204; the count is N+1.
3. As a candidate, open the same job → 204; N+2.
4. As the job's employer, and as an admin, open it → no `/view` request; the count does not change. The app path agrees: `GET /wcb/v1/jobs/<id>` as the job's author adds no row, as a candidate adds one.
5. `curl -X POST .../jobs/<expired or draft id>/view` and `.../jobs/1/view` (not a job) → 404, no row. With `-A "Googlebot/2.1"` on a live job → 204, no row.
6. Admin > Career Board > Settings > Analytics at 1440 and 390 → "Job Views (30 days)" shows the count and the job appears in "Top jobs by views".
6a. **Website visits count as job views (card:10403606771).** As a guest, open a published job, then as `varundubey` open Settings > Analytics → "Job Views (30 days)" is above 0 and the job is in Top jobs by views, at 1440 and 390.
7. Browser console on the job page → no errors.

## Teardown

`DELETE FROM wp_wcb_job_views WHERE viewed_at > <start of the run>`
