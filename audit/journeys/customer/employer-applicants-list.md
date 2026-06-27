---
id: employer-applicants-list
priority: high
personas: employer-a
requires: mu:autologin, seed:jobs, seed:applications
last_verified: 2026-06-27
needs: cli
---

# Employer sees applicants for her own jobs only (cross-employer isolation)

**Why this journey exists:** the employer applications list must be strictly scoped to the requesting employer's own company; returning applications for another company's jobs is a data-isolation bug. Verifies both the "own apps are visible" contract and the "other employer's apps are not visible" guard.

> **Route semantics (verified 2026-06-27):** `<id>` in `GET /wcb/v1/employers/<id>/applications` is the **`wcb_company` post id** (the dashboard passes `state.companyId`), NOT a user id. Access requires `get_current_user_id() === company.post_author` + `wcb/view-applications`, or `wcb/manage-settings` (admin).

## Steps

1. Resolve employer A's company id: `wp post list --post_type=wcb_company --post_author=<empA-uid> --field=ID --posts_per_page=1` → `<companyA>`. Resolve a second employer's company id → `<companyB>`.
2. As **employer A**, navigate to `/?autologin=<empA>` → expect HTTP 200, logged in.
3. GET `/wp-json/wcb/v1/employers/<companyA>/applications` → expect HTTP 200, response is a JSON array; capture it.
4. Verify isolation: for every item, resolve the linked job via the `job_id` field → `wp post meta get <job_id> _wcb_company_id` → expect every job's `_wcb_company_id` equals `<companyA>`. Zero items linked to `<companyB>` may appear.
5. Negative: GET `/wp-json/wcb/v1/employers/<companyB>/applications` as employer A → expect HTTP 403 (cannot read another company's application list — cross-employer isolation at the route level).
6. Navigate to `/employer-dashboard/?autologin=<empA>` → expect HTTP 200, the applicant panel shows only applications for employer A's own jobs.
7. Verify application count: items from step 3 ≥ 1 (seed must have at least one application for company A's jobs).
8. tail debug.log diff → expect ZERO new fatal/warning lines.

## Teardown

None — read-only journey (no data created).

## Notes

- Endpoint `GET /wcb/v1/employers/(?P<id>\\d+)/applications`, permission `get_applications_permissions_check`, handler `get_applications` (404 when `<id>` is not a `wcb_company`).
- Passing a **user** id instead of the company post id returns 403/404 — resolve the company id first.
- The deeper security guard lives in `security/employer-cant-see-other-applications.md`.
