---
id: walkthrough-employer-manage-applications
priority: high
personas: employer.figma
requires: mu:autologin, seed:jobs
last_verified: 2026-06-29
---

# Walkthrough: Employer Manage Applications — view applicants for a job and move them through the status pipeline

**Why this journey exists:** This is the end-to-end walkthrough of the employer "Manage Applications"
feature. It traces the full happy path a hiring employer takes — open the dashboard, switch to the
Applications tab, pick a job, browse its applicants, filter by status, open one applicant, change the
status (new → reviewing → shortlisted/rejected/hired), and confirm via the Board (Kanban) layout — so
the whole functionality is browser-coverable in one pass.

## Steps

1. As `employer.figma`, navigate to `/employer-dashboard/?autologin=employer.figma` → expect HTTP 200 and the dashboard shell renders (tab `#wcb-tab-apps` labelled "Applications" is present). *(page slug confirmed: `wp post list` → ID 8 `employer-dashboard`; tab markup `blocks/employer-dashboard/render.php:336`)*
2. Click the Applications tab `#wcb-tab-apps` (`data-wp-on--click="actions.switchToApplications"`) → expect panel `#wcb-panel-apps` becomes visible (`state.isViewApplications`) with heading "Applications". *(render.php:336, 588-590; view.js `switchToApplications` ~line 637)*
3. In the job selector `.wcb-apps-selector`, click a job tile `.wcb-apps-job-item` (carries `data-wcb-job-id`, `data-wp-on--click="actions.switchAppsJob"`) → expect the applicant list `.wcb-applicant-list` to populate and a per-job GET to fire. *(render.php:598-602; view.js `switchAppsJob` ~line 858 → `loadApplications` ~line 875)*
4. Observe the network call `GET /wp-json/wcb/v1/jobs/<jobId>/applications` → expect HTTP 200 returning `{ applications: [...] }` (each row has `id`, `applicant_name`, `status`). *(route `api/endpoints/class-jobs-endpoint.php:91-94` `get_applications`; perm `wcb/view-applications` at :1196; response envelope `:1125`)*
5. Click a status filter pill, e.g. `.wcb-filter-pill[data-wcb-filter="submitted"]` (label "New", `data-wp-on--click="actions.setAppsFilter"`) → expect the list narrows to applications whose `status === 'submitted'` and the pill shows `state.isAppsFilterSubmitted` active. *(render.php:624-627; view.js `filteredApps` ~line 270, counts ~line 333-348)*
6. Reset to the "All" pill `.wcb-filter-pill[data-wcb-filter="all"]`, then click an applicant row `.wcb-applicant-row` (carries `data-wcb-app-id`, `data-wp-on--click="actions.selectApplicant"`) → expect the detail panel `.wcb-applicant-detail` shows `.wcb-detail-name`, `.wcb-detail-email`, cover letter, and the `.wcb-status-select` reflecting the current status. *(render.php:661-707; view.js `selectApplicant` ~line 838)*
7. In the detail panel change `.wcb-status-select` (`data-wp-on--change="actions.updateAppStatus"`, `data-wcb-app-id` bound to the selected app) from "Submitted" to "Reviewing" → expect a PATCH to fire and `.wcb-status-msg` shows the saved confirmation (`state.statusMsg` = "saved"). *(render.php:683-696; view.js `updateAppStatus` → `applyStatusChange` ~line 962)*
8. Observe the network call `PATCH /wp-json/wcb/v1/applications/<appId>/status` with header `X-WP-Nonce` and body `{"status":"reviewing"}` → expect HTTP 200 returning `{ "id": <appId>, "status": "reviewing" }`. *(route `api/endpoints/class-applications-endpoint.php:68-76` `update_status`; allowed = submitted/reviewing/shortlisted/rejected/hired at :418-423; response :447-451)*
9. Verify persistence: `wp post meta get <appId> _wcb_status` → expect `reviewing`; `wp post meta get <appId> _wcb_status_log` → expect the latest log entry `{from: submitted, to: reviewing, by: <employer-id>}`. *(meta writes `class-applications-endpoint.php:433-443`; hook `wcb_application_status_changed` fired at :445)*
10. Repeat the status change to "Shortlisted" via the same `.wcb-status-select` → expect 200 with `{"status":"shortlisted"}` and the "Shortlisted" filter pill count (`.wcb-filter-pill[data-wcb-filter="shortlisted"] .wcb-pill-count`) increments by one. *(view.js `appsCountShortlisted` ~line 342)*
11. Switch to the Board (Kanban) layout: click `.wcb-layout-btn[data-layout="board"]` (`data-wp-on--click="actions.setAppsLayout"`) → expect `.wcb-apps-board` shown with a column per status (`.wcb-board-col` with `data-status`) and the shortlisted card now sitting under the Shortlisted column. *(render.php:614-617 toggle, 711-731 board; view.js `setAppsLayout` ~line 998, `appsBoardColumns` ~line 313)*
12. Drag a card `.wcb-board-card[data-wcb-app-id]` (draggable, `dragstart` → `actions.onCardDragStart`) and drop it on the "Rejected" column `.wcb-board-col[data-status="rejected"]` (`drop` → `actions.onColumnDrop`) → expect a PATCH `{"status":"rejected"}` returning 200 and the card relocates to that column. *(render.php:713-728; view.js `onColumnDrop` routes through `applyStatusChange` ~line 1001+)*
13. tail debug.log diff for this journey's window → expect ZERO new fatal / warning / `wp_register_ability` notice lines.

## Teardown

```bash
# Restore the test application's status to 'submitted' so the walkthrough is re-runnable.
# Resolve <appId> from the GET in step 4 (first row's id), then:
wp post meta update <appId> _wcb_status submitted
wp post meta delete <appId> _wcb_status_log
```

## Notes
- Seed data required: `seed:jobs` plus at least one published `wcb_application` whose `_wcb_job_id`
  points to a job owned by `employer.figma`. Without an applicant, the Applications tab shows the
  "No applications yet for this job" empty state (`render.php:653-655`) and steps 6-12 cannot run.
- Page slug grounded in DB: `wp post list --post_type=page` → `8,employer-dashboard`. Block content
  `<!-- wp:wp-career-board/employer-dashboard /-->` set by `admin/class-setup-wizard.php:414-417`.
- REST base + nonce injected at `blocks/employer-dashboard/render.php:152-153`
  (`apiBase` = `rest_url('wcb/v1')`, `nonce` = `wp_create_nonce('wp_rest')`); all PATCHes route through
  the shared `applyStatusChange` action (`blocks/employer-dashboard/view.js:962`).
- Status vocabulary grounded in `modules/applications/class-application-status.php:28-32`
  (submitted, reviewing, shortlisted, rejected, hired). `withdrawn` (candidate-only) and `job_removed`
  (system-only) are intentionally excluded from the employer-actionable allowlist.
- This is a Free-only feature; no Pro module needed. Pro adds AI ranking
  (`/ai/ranked-applications/<id>`, `.wcb-ai-rank-btn`) on top of the same list but is out of scope here.
