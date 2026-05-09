---
id: admin-applications-page-renders
priority: high
personas: varundubey
requires: mu:autologin, seed:jobs
last_verified: 2026-05-09
needs: cli
---

# Admin views and filters the Applications list table

**Why this journey exists:** Guards that the wcb-applications list table renders and that per-status filters (submitted/reviewing/shortlisted/rejected) each return the correct subset — not the full unfiltered list — because the application status is stored as post_status on the `wcb_application` CPT and must match the filter.

## Steps

1. As `varundubey`, navigate to `/wp-admin/edit.php?post_type=wcb_application&autologin=1` → expect 200, list table renders with no PHP error
2. Seed one application per status to guarantee each filter tab has a result:
   ```bash
   APP_SUB=$(wp post create --post_type=wcb_application --post_title='Smoke App Submitted' \
     --post_status=submitted --post_author=1 --porcelain)
   APP_REV=$(wp post create --post_type=wcb_application --post_title='Smoke App Reviewing' \
     --post_status=reviewing --post_author=1 --porcelain)
   APP_SHO=$(wp post create --post_type=wcb_application --post_title='Smoke App Shortlisted' \
     --post_status=shortlisted --post_author=1 --porcelain)
   APP_REJ=$(wp post create --post_type=wcb_application --post_title='Smoke App Rejected' \
     --post_status=rejected --post_author=1 --porcelain)
   ```
3. Verify all four custom statuses are registered:
   ```bash
   wp post status list | grep -E 'submitted|reviewing|shortlisted|rejected'
   ```
   → four lines returned; if any are missing, that is a registration regression
4. Filter to `submitted`: navigate to `edit.php?post_type=wcb_application&post_status=submitted` → expect "Smoke App Submitted" in the list and "Smoke App Reviewing" NOT in the list
5. Filter to `reviewing` → expect "Smoke App Reviewing" present, others absent from this filtered view
6. Filter to `shortlisted` → expect "Smoke App Shortlisted" present
7. Filter to `rejected` → expect "Smoke App Rejected" present
8. Return to all-applications view (no filter); confirm all four smoke records appear
9. Diff `debug.log` → expect ZERO new fatal/warning/notice lines

## Teardown

```bash
wp post delete $APP_SUB $APP_REV $APP_SHO $APP_REJ --force
```

## Notes

- Application statuses (`submitted`, `reviewing`, `shortlisted`, `rejected`) are custom post statuses registered by the applications module. If the filter tabs do not appear in the UI but the WP-CLI list returns rows, the tab-rendering code is the issue — check `class-admin-applications.php::get_views()`.
- The list table renders via `edit.php?post_type=wcb_application` (native CPT screen); there is no custom admin-page slug for applications in v1.1.0.
