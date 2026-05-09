---
id: employer-applicants-list
priority: high
personas: employer.figma
requires: mu:autologin, seed:jobs, seed:applications
last_verified: 2026-05-09
needs: cli
---

# Employer sees applicants for her own jobs only (cross-employer isolation)

**Why this journey exists:** the employer applications list must be strictly scoped to jobs owned by the requesting employer; returning applications for a different employer's jobs is a data-isolation bug. Verifies both the "own apps are visible" contract and the "other employer's apps are not visible" guard.

## Steps

1. As `employer.figma` (user 50), navigate to `/?autologin=employer.figma` → expect HTTP 200, employer is logged in
2. GET `/wp-json/wcb/v1/employers/50/applications` → expect HTTP 200, response is a JSON array; capture response
3. Verify isolation: for every item in the response, resolve the linked job via `job_id` field → `wp post meta get <job_id> _wcb_company_id` → expect the `post_author` of that job is 50 (employer.figma). Zero items authored by employer.vercel (ID 49) or employer.stripe (ID 48) may appear
4. Verify that employer.vercel's applications are NOT in the response: find a job owned by employer.vercel: `wp post list --post_type=wcb_job --post_author=49 --field=ID --posts_per_page=1` → extract one application for that job: `wp post list --post_type=wcb_application --meta_key=_wcb_job_id --meta_value=<vercel-job-id> --field=ID --posts_per_page=1` → assert that `<vercel-app-id>` does NOT appear in the step 2 response
5. GET `/wp-json/wcb/v1/employers/49/applications` as `employer.figma` → expect HTTP 403 (figma cannot read vercel's application list — cross-employer isolation at the route level)
6. Navigate to `/employer-dashboard/?autologin=employer.figma` → expect HTTP 200, the applicant panel shows only applications for employer.figma's own jobs
7. Verify application count: count of items from step 2 response ≥ 1 (seed must have at least one application for figma's jobs)
8. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

None — read-only journey (no data created).

## Notes

- The applications endpoint is `GET /wcb/v1/employers/(?P<id>\\d+)/applications` with permission `get_applications_permissions_check` per manifest.
- User IDs on job-portal.local: employer.figma = 50, employer.vercel = 49, employer.stripe = 48.
- Step 5 directly tests the security contract; it is complemented by `security/employer-cant-see-other-applications.md` which goes deeper.
