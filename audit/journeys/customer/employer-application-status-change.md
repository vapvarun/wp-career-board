---
id: employer-application-status-change
priority: high
personas: employer.figma
requires: mu:autologin, seed:applications
last_verified: 2026-05-09
needs: cli
---

# Employer transitions an application through submitted → reviewing → shortlisted

**Why this journey exists:** application status changes are the core of the employer-candidate workflow; a status write that succeeds at the API layer but does not persist (or persists an invalid value) silently corrupts the pipeline. Verifies the full transition chain and the v1.1.0 status allowlist.

## Steps

1. As `employer.figma`, navigate to `/?autologin=employer.figma` → expect HTTP 200, employer is logged in
2. Find an application for one of employer.figma's jobs in `submitted` status: `wp post list --post_type=wcb_application --post_author=50 --meta_key=_wcb_status --meta_value=submitted --field=ID --posts_per_page=1` (or filter by job IDs owned by author 50) → capture as `<app-id>`; if none found, create one via `customer/apply-to-job.md` first
3. PATCH `/wp-json/wcb/v1/applications/<app-id>/status` with body `{"status": "reviewing"}` → expect HTTP 200, response `status` equals `reviewing`
4. Verify DB: `wp post meta get <app-id> _wcb_status` → expect `reviewing`
5. PATCH `/wp-json/wcb/v1/applications/<app-id>/status` with body `{"status": "shortlisted"}` → expect HTTP 200, response `status` equals `shortlisted`
6. Verify DB: `wp post meta get <app-id> _wcb_status` → expect `shortlisted`
7. Attempt an invalid status transition: PATCH the same endpoint with body `{"status": "hired"}` → expect HTTP 400 or 422 (not 200) — `hired` is NOT in the v1.1.0 allowlist `[submitted, reviewing, shortlisted, rejected]`
8. Verify DB is unchanged after step 7: `wp post meta get <app-id> _wcb_status` → expect still `shortlisted`
9. Navigate to the employer dashboard `/employer-dashboard/?autologin=employer.figma` → expect the application row shows status label "Shortlisted"
10. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

```bash
# Reset the application back to submitted for other journeys
wp post meta update <app-id> _wcb_status submitted
```

## Notes

- The status endpoint is `PUT/PATCH /wcb/v1/applications/(?P<id>\\d+)/status` with permission `update_permissions_check (employer of job)` per manifest.
- Valid status values per v1.1.0 allowlist: `submitted`, `reviewing`, `shortlisted`, `rejected`. `applied` and `hired` are legacy values that must be rejected.
- If `wcb_application_status_changed` fires on each transition, check debug.log for the hook fire — it is NOT an error and should appear; a missing hook fire after a valid transition is a separate bug.
