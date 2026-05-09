---
id: employer-cant-see-other-applications
priority: critical
personas: employer.figma, employer.vercel
requires: mu:autologin, seed:applications
last_verified: 2026-05-09
needs: cli
---

# Employer cannot read another employer's applications list

**Why this journey exists:** the applications endpoint scoped to an employer (`GET /wcb/v1/employers/<id>/applications`) must enforce ownership — employer A must receive 403 when requesting employer B's list. A missing ownership check leaks candidate personal data and is a GDPR/privacy violation.

## Steps

1. Verify seed data: `wp post list --post_type=wcb_application --meta_key=_wcb_job_id --field=ID` and confirm applications exist for jobs owned by employer.vercel (user 49)
2. As `employer.figma` (user 50), navigate to `/?autologin=employer.figma` → expect HTTP 200, employer.figma is logged in
3. Attempt to read employer.vercel's applications: GET `/wp-json/wcb/v1/employers/49/applications` → expect HTTP 403, response body contains an error `code` (NOT 200, NOT an empty array that hides the auth failure)
4. Verify the response does NOT silently return an empty array masking a permission failure: the HTTP status must be 403, not 200 with `[]`
5. As `employer.figma`, GET `/wp-json/wcb/v1/employers/50/applications` → expect HTTP 200 (own applications are accessible)
6. As `employer.vercel` (user 49), navigate to `/?autologin=employer.vercel` → GET `/wp-json/wcb/v1/employers/50/applications` → expect HTTP 403 (symmetric: vercel cannot read figma's applications either)
7. As `varundubey` (admin, user 1), GET `/wp-json/wcb/v1/employers/49/applications` → expect HTTP 200 (admin can read any employer's applications — admin privilege is not blocked by the ownership check)
8. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

None — read-only journey.

## Notes

- The endpoint is `GET /wcb/v1/employers/(?P<id>\\d+)/applications` with permission `get_applications_permissions_check` per manifest.
- The permission check must verify that the currently authenticated user's user ID equals the `<id>` param, OR the user has `wcb_manage_settings` (admin).
- User IDs on job-portal.local: employer.figma = 50, employer.vercel = 49, employer.stripe = 48, varundubey = 1.
