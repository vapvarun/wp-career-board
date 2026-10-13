---
id: admin-applications-page-renders
priority: high
personas: varundubey
requires: mu:autologin, seed:jobs
last_verified: 2026-10-13
bug_ref: 10389790610
needs: cli
---

# Admin views and filters the Applications list table

**Why this journey exists:** Guards that the wcb-applications list table renders and that per-status filters (submitted/reviewing/shortlisted/rejected) each return the correct subset — not the full unfiltered list — because the application status is stored as post_status on the `wcb_application` CPT and must match the filter.

## Steps

1. As `varundubey`, navigate to `/wp-admin/edit.php?post_type=wcb_application&autologin=1` → expect 200, list table renders with no PHP error
2. Seed one application per status to guarantee each filter tab has a result:
   ```bash
   APP_SUB=$(wp post create --post_type=wcb_application --post_title='Elena Rossi applying for Backend Developer' --meta_input='{"_wcb_qa_smoke":"1"}' \
     --post_status=submitted --post_author=1 --porcelain)
   APP_REV=$(wp post create --post_type=wcb_application --post_title='Tomas Ferreira applying for Product Designer' --meta_input='{"_wcb_qa_smoke":"1"}' \
     --post_status=reviewing --post_author=1 --porcelain)
   APP_SHO=$(wp post create --post_type=wcb_application --post_title='Aisha Karim applying for Data Engineer' --meta_input='{"_wcb_qa_smoke":"1"}' \
     --post_status=shortlisted --post_author=1 --porcelain)
   APP_REJ=$(wp post create --post_type=wcb_application --post_title='Daniel Okafor applying for QA Engineer' --meta_input='{"_wcb_qa_smoke":"1"}' \
     --post_status=rejected --post_author=1 --porcelain)
   ```
3. Verify all four custom statuses are registered:
   ```bash
   wp post status list | grep -E 'submitted|reviewing|shortlisted|rejected'
   ```
   → four lines returned; if any are missing, that is a registration regression
4. Filter to `submitted`: navigate to `edit.php?post_type=wcb_application&post_status=submitted` → expect "Elena Rossi applying for Backend Developer" in the list and "Tomas Ferreira applying for Product Designer" NOT in the list
5. Filter to `reviewing` → expect "Tomas Ferreira applying for Product Designer" present, others absent from this filtered view
6. Filter to `shortlisted` → expect "Aisha Karim applying for Data Engineer" present
7. Filter to `rejected` → expect "Daniel Okafor applying for QA Engineer" present
8. Return to all-applications view (no filter); confirm all four smoke records appear
9. **View resume + Edit user (1.8.1, Basecamp 10389790610).** The plugin Applications screen is `$WCB_SITE/wp-admin/admin.php?page=wcb-applications`. Seed an application whose candidate has a published resume (`wcb_resume` post, `post_status=publish`, same author; or `_wcb_resume_id` on the application pointing at it) → on the row, the row actions (View | View resume | Email Candidate | Trash) include **View resume** (opens in a new tab, `target=_blank rel=noopener`, URL = the resume permalink); **View resume** is absent for an application whose candidate has no published resume and for a guest application (no user).
10. Open the application screen (row action **View**, i.e. `$WCB_SITE/wp-admin/post.php?post=<app-id>&action=edit`) → the Applicant card (`ApplicantCard` widget) shows **View resume** (button) and **Edit user** (link to `user-edit.php?user_id=<candidate>`), and the old label "View candidate profile" is gone. A guest application (`_wcb_candidate_id` 0) shows neither button.
11. Filter hook: `add_filter( 'wcb_candidate_resume_url', '__return_empty_string' );` (mu-plugin) hides **View resume** on both screens; remove the mu-plugin after the step.
12. Search box: `#wcb-application-search-input` with placeholder "Job title or candidate name…" and one label "Search Applications".
13. Diff `debug.log` → expect ZERO new fatal/warning/notice lines

## Teardown

```bash
wp post delete $APP_SUB $APP_REV $APP_SHO $APP_REJ --force
```

## Notes

- Application statuses (`submitted`, `reviewing`, `shortlisted`, `rejected`) are custom post statuses registered by the applications module. If the filter tabs do not appear in the UI but the WP-CLI list returns rows, the tab-rendering code is the issue — check `class-admin-applications.php::get_views()`.
- The list table renders via `edit.php?post_type=wcb_application` (native CPT screen); there is no custom admin-page slug for applications in v1.1.0.
- Steps 1-9 above use the CPT URL `edit.php?post_type=wcb_application`; the 1.8.1 steps use the plugin list table at `page=wcb-applications`. The application screen is the `wcb_application` edit screen with the Applicant card metabox (`class-admin-meta-boxes.php`).
