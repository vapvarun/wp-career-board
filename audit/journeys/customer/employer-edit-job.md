---
id: employer-edit-job
priority: high
personas: employer.figma
requires: mu:autologin, seed:jobs
last_verified: 2026-05-09
needs: cli
---

# Employer edits a job; changes propagate to public listing, REST response, and search index

**Why this journey exists:** editing a job is a routine employer action; a PATCH that returns 200 but leaves the public listing stale (cache not invalidated, search index not updated) is invisible to the employer but breaks the candidate experience.

## Steps

1. As `employer.figma`, navigate to `/?autologin=employer.figma` → expect HTTP 200, employer is logged in (user ID 50)
2. Find a `wcb_job` owned by employer.figma: `wp post list --post_type=wcb_job --post_author=50 --post_status=publish --field=ID --posts_per_page=1` → capture as `<job-id>`
3. Read the current job title: `wp post get <job-id> --field=post_title` → capture as `<original-title>`
4. PATCH `/wp-json/wcb/v1/jobs/<job-id>` with body `{"title": "Smoke Edit - <original-title>", "description": "Updated description for smoke journey test", "salary_max": 99999}` → expect HTTP 200, response body contains `id` equal to `<job-id>`
5. Verify title persisted in DB: `wp post get <job-id> --field=post_title` → expect `Smoke Edit - <original-title>`
6. Verify salary meta persisted: `wp post meta get <job-id> _wcb_salary_max` → expect `99999`
7. As anonymous, GET `/wp-json/wcb/v1/jobs/<job-id>` → expect HTTP 200, response `title` field equals `Smoke Edit - <original-title>` and `salary_max` equals `99999` (confirming REST response is not stale)
8. GET `/wp-json/wcb/v1/search?q=Smoke+Edit` → expect HTTP 200, response array includes an entry with `id` equal to `<job-id>` (search index reflects the edit)
9. Restore the original title: PATCH `/wp-json/wcb/v1/jobs/<job-id>` with body `{"title": "<original-title>"}` → expect HTTP 200
10. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

```bash
# Restore title in case step 9 was not reached
wp post update <job-id> --post_title="<original-title>"
```

## Notes

- Only employer.figma (the job owner) should be able to PATCH this job. Cross-employer edit protection is verified in `security/employer-cant-edit-other-job.md`.
- The search endpoint is `GET /wcb/v1/search` per manifest. If the search index is cached, the result may not reflect immediately — allow up to one cache-busting request via `wp option update wcb_jobs_cache_v $(($(wp option get wcb_jobs_cache_v) + 1))`.
