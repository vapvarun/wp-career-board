---
id: public-company-jobs-hide-applicant-counts
priority: high
personas: anonymous, employer.figma, employer.stripe, varundubey
requires: mu:autologin, seed:jobs, seed:applications
last_verified: 2026-10-13
needs: cli
bug_ref: 10355318941
---

# Applicant counts are visible to the company owner and admins only, never in the public company job list

**Why this journey exists:** `GET /wcb/v1/employers/<company-id>/jobs` is public (it backs the company profile's Open Positions) but returned `appCount` and `appLabel` for every job, telling competitors how many people applied. Since 1.8.1 only the owner and admins get those keys.

## Steps

1. Resolve a company with published jobs that have applications: `wp post list --post_type=wcb_company --post_author=<figma-uid> --field=ID --posts_per_page=1` → `<company-id>`.
2. Anonymous: `curl -s "$WCB_SITE/wp-json/wcb/v1/employers/<company-id>/jobs" | jq '.jobs[0] | has("appCount"), has("appLabel")'` → `false` and `false`; the other job fields (`id`, `title`, `status`, `statusLabel`) are still present.
3. As another employer (`employer.stripe`, not the owner) → same: no `appCount` / `appLabel` on any item.
4. As the owner (`employer.figma`) → every item has `appCount` (integer) and `appLabel`; the numbers equal `wp post list --post_type=wcb_application --meta_key=_wcb_job_id --meta_value=<job> --format=count` per job.
5. As admin (`varundubey`, holds `wcb/manage-settings`) → keys present, and the list also includes the owner-visible statuses (pending/draft) as before.
6. The company profile page `/companies/<slug>/` Open Positions cards show no applicant count text for a visitor.
7. tail debug.log diff → expect ZERO new fatal/warning lines.

## Teardown

None - read-only journey.

## Notes

- Only `EmployersEndpoint::get_jobs()` (the company-scoped public route) strips the keys for non-owners. The owner dashboard's own job list is a separate code path the 1.8.1 diff does not touch; its "N applicants" labels must still show (walk `customer/employer-applicants-list`).
