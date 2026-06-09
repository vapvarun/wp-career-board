---
id: orphan-job-adopted-on-company-create
priority: high
personas: employer.figma
requires: mu:autologin
last_verified: 2026-06-09
bug_ref: 9976738869
---

# A job posted before the company exists is adopted when the company is created

**Why this journey exists:** an employer can post a job before saving a company profile. That job never received `_wcb_company_id` (JobsEndpoint only stamps it when the user already has a company), so once My Jobs queries by company the job was invisible forever. Guards Basecamp 9976738869 (comment 1): creating the company must backfill the link onto the author's orphaned jobs.

## Steps

1. As an employer account with NO company (`_wcb_company_id` unset), create a `wcb_job` authored by them → confirm `wp post meta get <job-id> _wcb_company_id` is empty (the orphan state)
2. Save a company profile: POST `/wp-json/wcb/v1/employers` `{"name":"Adopt Test Co"}` → expect HTTP 200; `_wcb_company_id` user meta is now set
3. Verify backfill: `wp post meta get <job-id> _wcb_company_id` → equals the new company id, and `_wcb_company_name` is the company title
4. GET `/wp-json/wcb/v1/employers/me/jobs` → the previously-orphaned job now appears in the list
5. Separately, the stale-list path: after posting via the dashboard Post-a-Job form, switching to My Jobs refetches (the `_needsJobsRefresh` flag) so the new job appears without a manual reload
6. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

```bash
wp post delete <job-id> --force
wp post delete <company-id> --force
```

## Notes

- Backfill lives in `EmployersEndpoint::create_item()` → `backfill_orphan_jobs()`; idempotent, bounded to one author's own jobs.
- The refresh half is covered by the `_needsJobsRefresh` cross-store flag (job-form view.js sets it; employer `switchToJobs()` refetches).
