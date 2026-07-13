---
id: poster-manage-applicants
priority: critical
personas: employer.figma
requires: mu:autologin, seed:jobs, seed:applications
last_verified: 2026-07-08
covers: blocks/employer-dashboard (wcb/employer-dashboard), GET /wcb/v1/employers/me/jobs, GET /wcb/v1/jobs/{id}/applications, PATCH /wcb/v1/applications/{id}/status
---

# Walkthrough: Manage Applicants — open the dashboard, view applicants for a job, and move one through the status pipeline

**Why this journey exists:** After posting, the employer's core daily action is triaging applicants. This walkthrough traces the full happy path — the `wcb/employer-dashboard` block renders, My Jobs lists the employer's own jobs (scoped by company), the Applications tab loads a job's applicants, the employer opens one and changes its status (submitted → reviewing → shortlisted), and the change persists to `_wcb_status` + the status log. Cross-employer isolation (an employer sees only their own applicants) is asserted at the route level. Consolidates `customer/walkthrough-employer-manage-applications`, `customer/employer-applicants-list`, and `customer/employer-application-status-change`.

## Steps

1. As `employer.figma`, navigate to `http://jobboard.local/employer-dashboard/?autologin=employer.figma` → expect HTTP 200 and the dashboard shell renders with tabs `#wcb-tab-jobs` "My Jobs", `#wcb-tab-postjob` "Post a Job", and `#wcb-tab-apps` "Applications" present (`blocks/employer-dashboard/render.php:327-336`). NOT a "sign in as an employer" gate.

2. On the default My Jobs view (`#wcb-panel-jobs`, render.php:530), observe the list load → a `GET http://jobboard.local/wp-json/wcb/v1/employers/me/jobs` fires (header `X-WP-Nonce`) → expect HTTP 200 returning an envelope `{ jobs:[…], total, … }` where each job row has `id`, `title`, `status`, `statusLabel`, `permalink`, `editUrl`, `appCount`, `appLabel` (`api/endpoints/class-employers-endpoint.php:611-677`). The list is scoped to the employer's own company (their `_wcb_company_id`), so only their jobs appear.

3. Click the Applications tab `#wcb-tab-apps` (`data-wp-on--click="actions.switchToApplications"`, render.php:336) → expect panel `#wcb-panel-apps` visible (`state.isViewApplications`) with heading "Applications" (render.php:588-590; view.js `switchToApplications`).

4. In the job selector `.wcb-apps-selector`, click a job tile `.wcb-apps-job-item` (carries `data-wcb-job-id`, `data-wp-on--click="actions.switchAppsJob"`, render.php:598-602) → observe `GET http://jobboard.local/wp-json/wcb/v1/jobs/<jobId>/applications` → expect HTTP 200 returning `{ applications:[…] }`, each row with `id`, `applicant_name`, `status` (route `api/endpoints/class-jobs-endpoint.php:91-94` `get_applications`; perm `wcb/view-applications` at :1196; envelope :1125). The `.wcb-applicant-list` populates.

5. **Cross-employer isolation (negative):** as `employer.figma`, request another company's list `GET http://jobboard.local/wp-json/wcb/v1/employers/<companyB>/applications` → expect HTTP 403 (access requires `get_current_user_id() === company.post_author` + `wcb/view-applications`, or admin). Resolve `<companyB>` as any `wcb_company` NOT authored by employer.figma. Deeper guard: `security/employer-cant-see-other-applications`.

6. Click a status filter pill `.wcb-filter-pill[data-wcb-filter="submitted"]` (label "New", `data-wp-on--click="actions.setAppsFilter"`, render.php:624-627) → expect the list to narrow to `status === 'submitted'`. Reset to the "All" pill `.wcb-filter-pill[data-wcb-filter="all"]`, then click an applicant row `.wcb-applicant-row` (`data-wcb-app-id`, `actions.selectApplicant`) → expect `.wcb-applicant-detail` showing `.wcb-detail-name`, `.wcb-detail-email`, cover letter, and `.wcb-status-select` reflecting the current status (render.php:661-707).

7. In the detail panel change `.wcb-status-select` (`data-wp-on--change="actions.updateAppStatus"`, render.php:683-696) from "Submitted" to "Reviewing" → observe `PATCH http://jobboard.local/wp-json/wcb/v1/applications/<appId>/status` with header `X-WP-Nonce` and body `{"status":"reviewing"}` → expect HTTP 200 returning `{ id:<appId>, status:"reviewing" }` (route `api/endpoints/class-applications-endpoint.php:68-76`; allowlist submitted/reviewing/shortlisted/rejected/hired at :418-423; response :447-451) and `.wcb-status-msg` shows the saved confirmation.

8. Verify persistence: `wp post meta get <appId> _wcb_status` → expect `reviewing`; `wp post meta get <appId> _wcb_status_log` → expect the latest entry `{from: submitted, to: reviewing, by:<employer-id>}` (meta writes class-applications-endpoint.php:433-443; hook `wcb_application_status_changed` fired :445).

9. Repeat the change to "Shortlisted" via the same `.wcb-status-select` → expect HTTP 200 `{"status":"shortlisted"}` and the "Shortlisted" pill count `.wcb-filter-pill[data-wcb-filter="shortlisted"] .wcb-pill-count` increments by one.

10. **Invalid status rejected:** PATCH the same endpoint with a value outside the allowlist (e.g. `{"status":"applied"}` — a legacy value) → expect HTTP 400/422 (not 200) and `wp post meta get <appId> _wcb_status` unchanged at `shortlisted`.

11. tail `wp-content/debug.log` diff over the whole run → expect ZERO new fatal / warning / `wp_register_ability` notice lines.

## Teardown

```bash
# Restore the test application to 'submitted' so the walkthrough is re-runnable.
# Resolve <appId> from the GET in step 4 (first row's id), then:
wp post meta update <appId> _wcb_status submitted
wp post meta delete <appId> _wcb_status_log
```

## Notes

- **1.5.1-new: dashboard reorder filter.** 1.5.1 adds an `apply_filters` hook on the My-Jobs / applicant dashboard ordering so a site can re-sort the lists. It is flagged 🆕 in `docs/qa/COMMON_USE_CASES.md` (Job Poster Tier 1, "Employer dashboard renders" row). The list queries it wraps are the `WP_Query` args in `EmployersEndpoint::get_my_jobs()` (`class-employers-endpoint.php:627-635`, no explicit `orderby` today → default date DESC) and `get_jobs()` (:706-721). When verifying 1.5.1: confirm the new filter's default preserves today's ordering (newest first) and that a filter callback measurably reorders the returned list — this is the regression most likely to slip since it is new this cycle.
- **`me/jobs` vs `employers/{companyId}/applications`.** `GET /employers/me/jobs` resolves the caller's own company and delegates to `get_jobs()` (class-employers-endpoint.php:611-620); it needs no id. The applicant *list* endpoint takes the **`wcb_company` post id**, NOT a user id — passing a user id returns 403/404.
- **Status vocabulary** grounded in `modules/applications/class-application-status.php:28-32` (submitted, reviewing, shortlisted, rejected, hired). `withdrawn` (candidate-only) and `job_removed` (system-only) are excluded from the employer-actionable allowlist; legacy `applied` is rejected.
- REST base + nonce injected at `blocks/employer-dashboard/render.php:152-153` (`apiBase` = `rest_url('wcb/v1')`, `nonce` = `wp_create_nonce('wp_rest')`); all PATCHes route through the shared `applyStatusChange` action (`blocks/employer-dashboard/view.js:962`).
- **Seed needs:** at least one published `wcb_application` whose `_wcb_job_id` points to a job owned by `employer.figma`; without one the Applications tab shows the "No applications yet for this job" empty state (render.php:653-655) and steps 6-10 cannot run.
- Free-only feature. Pro adds AI ranking (`/ai/ranked-applications/<id>`, `.wcb-ai-rank-btn`, the `aiScore` sort in view.js:275/325) on the same list — out of scope here.
