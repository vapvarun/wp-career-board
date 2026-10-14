---
id: employer-applicants-list
priority: high
personas: employer-a
requires: mu:autologin, seed:jobs, seed:applications
last_verified: 2026-10-13
bug_ref: 10375223031
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
8. Scale (1.8.0): with 120+ applications on one job, open Applications and select that job → 50 rows load, the status pills show the job's full totals (they equal `ApplicationStatus::counts('job', <job>)`), and **Load more applicants** brings 100, then all, with no duplicate rows; the button then disappears. Same in Board layout.
9. Click the **Rejected** pill → the list is fetched from the server (`?status=rejected`) and shows every rejected applicant, not only those already loaded. Change one to Hired → Rejected drops by 1 and Hired rises by 1 without a reload.
10. **Zero-count status filter keeps the controls (1.8.1, Basecamp 10375223031).** As `employer.figma` open `$WCB_SITE/employer-dashboard/#applications`, pick a job (`.wcb-apps-job-item`) that has applicants but none Rejected, then click `.wcb-filter-pill[data-wcb-filter="rejected"]` (count 0) → `.wcb-apps-filter-bar` (all pills), `.wcb-apps-layout-toggle` (List/Board) and `a.wcb-apps-export` (Export CSV) all stay visible, the list is empty, and the message "No applications with this status." shows in `.wcb-empty-state--toggle`. The "No applications yet for this job." message must NOT show.
11. Click the **All** pill → the rows return, `.wcb-filter-active` is back on All, the chrome never disappeared in between (no layout jump).
12. Negative: a job with zero applications total shows ONLY "No applications yet for this job." with the filter bar, layout toggle and Export hidden (the chrome follows the job's total via `state.jobHasApplications`, not the filtered page).
13. Switch job while a status filter is active: with the Rejected pill selected, click another job tile (`actions.switchAppsJob`) → the filter resets to All (the All pill is `.wcb-filter-active`) and the new job's full list is shown, not a zero-count empty state carried over from the previous job.
14. REST sanity for step 10: `GET /wp-json/wcb/v1/jobs/<job>/applications?status=rejected` as the owner → 200 with an empty list, not an error.
15. tail debug.log diff → expect ZERO new fatal/warning lines.

## Teardown

None — read-only journey (no data created).

## Notes

- Endpoint `GET /wcb/v1/employers/(?P<id>\\d+)/applications`, permission `get_applications_permissions_check`, handler `get_applications` (404 when `<id>` is not a `wcb_company`).
- Passing a **user** id instead of the company post id returns 403/404 — resolve the company id first.
- The deeper security guard lives in `security/employer-cant-see-other-applications.md`.
