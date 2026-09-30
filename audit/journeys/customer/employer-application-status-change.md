---
id: employer-application-status-change
priority: high
personas: employer.figma
requires: mu:autologin, seed:applications
last_verified: 2026-09-27
needs: cli
---

# Employer transitions an application through submitted → reviewing → shortlisted

**Why this journey exists:** application status changes are the core of the employer-candidate workflow; a status write that succeeds at the API layer but does not persist (or persists an invalid value) silently corrupts the pipeline. Since 1.8.0 every writer goes through `ApplicationLifecycle::transition()`: one event and one email per real change, none for a repeat, and candidates read "Not selected" where employers read "Rejected".

## Steps

1. As `employer.figma`, navigate to `/?autologin=employer.figma` → expect HTTP 200, employer is logged in
2. Find an application for one of employer.figma's jobs in `submitted` status: `wp post list --post_type=wcb_application --post_author=50 --meta_key=_wcb_status --meta_value=submitted --field=ID --posts_per_page=1` (or filter by job IDs owned by author 50) → capture as `<app-id>`; if none found, create one via `customer/apply-to-job.md` first
3. PATCH `/wp-json/wcb/v1/applications/<app-id>/status` with body `{"status": "reviewing"}` → expect HTTP 200, response `status` equals `reviewing`
4. Verify DB: `wp post meta get <app-id> _wcb_status` → expect `reviewing`
5. PATCH `/wp-json/wcb/v1/applications/<app-id>/status` with body `{"status": "shortlisted"}` → expect HTTP 200, response `status` equals `shortlisted`
6. Verify DB: `wp post meta get <app-id> _wcb_status` → expect `shortlisted`
7. PATCH the same endpoint again with `{"status": "shortlisted"}` → expect HTTP 200 with `changed: false`; Mailpit shows NO new email to the candidate
8. PATCH with `{"status": "withdrawn"}` → expect HTTP 400 (`withdrawn` is candidate-only; employer set = `submitted, reviewing, shortlisted, rejected, hired`)
9. PATCH with `{"status": "rejected"}` → expect HTTP 200, `status_label` = "Rejected", `status_tone` = "danger"; the candidate's email (Mailpit) says "Not selected"
10. As the candidate, GET `/wp-json/wcb/v1/candidates/<candidate-id>/applications` → the row's `status_label` is "Not selected"
11. Navigate to the employer dashboard `/employer-dashboard/?autologin=employer.figma` → Applications → the detail picker shows "Rejected"; change it and the status message updates without a reload
12. As the candidate, withdraw a second, still-open application from the candidate dashboard → the row stays with a "Withdrawn" badge; the employer dashboard shows a read-only "Withdrawn" badge (no picker) and PATCHing it returns 409
13. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

```bash
# Reset the application back to submitted for other journeys
wp post meta update <app-id> _wcb_status submitted
```

## Notes

- The status endpoint is `PUT/PATCH /wcb/v1/applications/(?P<id>\\d+)/status` with permission `update_permissions_check (employer of job)` per manifest.
- Employer-settable statuses come from `ApplicationStatus::employer_actionable()` (filter `wcb_employer_actionable_statuses`). `withdrawn` is set by the candidate, `job_removed` by the system; neither can be changed by the employer afterwards.
- `wcb_application_status_changed` fires once per real change with `($app_id, $old, $new, $reason, $actor)`; a same-status save fires nothing.
- Automated coverage: `tests/test-application-lifecycle.php` (Free) and `wp-career-board-pro/tests/test-pipeline-status-sync.php` (Kanban stage <-> status).
