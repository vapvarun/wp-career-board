---
id: employer-cant-edit-other-job
priority: critical
personas: employer.figma, employer.vercel
requires: mu:autologin, seed:jobs
last_verified: 2026-05-09
needs: cli
---

# Employer cannot edit another employer's job via PATCH

**Why this journey exists:** horizontal privilege escalation on job editing — employer A must not be able to modify employer B's job post. The `update_item_permissions_check` on the jobs endpoint must verify post authorship (or company ownership), not just the `wcb_post_jobs` capability.

## Steps

1. Find a job owned by employer.vercel (user 49): `wp post list --post_type=wcb_job --post_author=49 --post_status=publish --field=ID --posts_per_page=1` → capture as `<vercel-job-id>`
2. Read the current title: `wp post get <vercel-job-id> --field=post_title` → capture as `<original-vercel-title>`
3. As `employer.figma` (user 50), navigate to `/?autologin=employer.figma` → expect HTTP 200, employer.figma is logged in
4. Attempt to edit employer.vercel's job: PATCH `/wp-json/wcb/v1/jobs/<vercel-job-id>` with body `{"title": "HIJACKED by figma smoke journey"}` → expect HTTP 403, response `code` is `rest_forbidden` or `wcb_not_authorized`
5. Verify DB is unchanged: `wp post get <vercel-job-id> --field=post_title` → expect still `<original-vercel-title>`
6. Attempt DELETE as employer.figma: DELETE `/wp-json/wcb/v1/jobs/<vercel-job-id>` → expect HTTP 403 (delete is also blocked for non-owners)
7. Verify the job still exists: `wp post get <vercel-job-id> --field=post_status` → expect `publish` (not deleted, not trashed)
8. As `employer.vercel`, PATCH the same job with a legitimate edit: navigate to `/?autologin=employer.vercel` → PATCH `/wp-json/wcb/v1/jobs/<vercel-job-id>` with body `{"title": "Vercel Legitimate Edit"}` → expect HTTP 200 (owner can edit own job)
9. Restore: PATCH `/wp-json/wcb/v1/jobs/<vercel-job-id>` with body `{"title": "<original-vercel-title>"}` as employer.vercel → expect HTTP 200
10. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

```bash
wp post update <vercel-job-id> --post_title="<original-vercel-title-captured-in-step-2>"
```

## Notes

- The update endpoint is `PUT/PATCH /wcb/v1/jobs/(?P<id>\\d+)` with permission `update_item_permissions_check` per manifest.
- The ownership check should compare the job's `post_author` with the current user, OR compare the linked `_wcb_company_id` with the employer's company. Read `JobsEndpoint::update_item_permissions_check` to confirm the exact mechanism.
- User IDs: employer.figma = 50, employer.vercel = 49 on job-portal.local.
