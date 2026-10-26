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

**Why this journey exists:** Guards that the `wcb-applications` list table renders and that each status tab returns the correct subset, not the full list. An application's status is the `_wcb_status` postmeta (`ApplicationStatus`), NOT a WordPress `post_status`; the tabs filter with `?app_status=<slug>`.

## Steps

1. As `varundubey`, navigate to `/wp-admin/admin.php?page=wcb-applications&autologin=1` → expect 200, list table renders with no PHP error
2. Seed one application per status to guarantee each filter tab has a result (a status tab is hidden when its count is 0):
   ```bash
   JOB_ID=$(wp post list --post_type=wcb_job --post_status=publish --field=ID --posts_per_page=1)
   mk() { ID=$(wp post create --post_type=wcb_application --post_title="$1" --post_status=publish --post_author=1 --porcelain); wp post meta update $ID _wcb_job_id $JOB_ID >/dev/null; wp post meta update $ID _wcb_status $2 >/dev/null; wp post meta update $ID _wcb_guest_name "$3" >/dev/null; echo $ID; }
   APP_SUB=$(mk 'Elena Rossi applying for Backend Developer' submitted 'Elena Rossi')
   APP_REV=$(mk 'Tomas Ferreira applying for Product Designer' reviewing 'Tomas Ferreira')
   APP_SHO=$(mk 'Aisha Karim applying for Data Engineer' shortlisted 'Aisha Karim')
   APP_REJ=$(mk 'Daniel Okafor applying for QA Engineer' rejected 'Daniel Okafor')
   ```
3. Verify the status slugs the tabs are built from: `wp eval 'echo implode( ",", \WCB\Modules\Applications\ApplicationStatus::all() );'` → `submitted,reviewing,shortlisted,rejected,hired,withdrawn,job_removed,position_closed`. There is no custom post status for these: `wp post get $APP_SUB --field=post_status` = `publish` and `wp post meta get $APP_SUB _wcb_status` = `submitted`
4. Filter to submitted: navigate to `admin.php?page=wcb-applications&app_status=submitted` → expect "Elena Rossi" in the list and "Tomas Ferreira" NOT in the list; the "Submitted" tab is marked current
5. Filter to `app_status=reviewing` → expect "Tomas Ferreira" present, others absent
6. Filter to `app_status=shortlisted` → expect "Aisha Karim" present
7. Filter to `app_status=rejected` → expect "Daniel Okafor" present (tab label "Rejected")
8. Return to the unfiltered list (no `app_status`); confirm all four smoke records appear and the "All" count equals the sum of the tab counts
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

- Status tabs come from `AdminApplications::get_views()` (`admin/class-admin-applications.php`), counted by `ApplicationStatus::counts()`; tabs with a zero count are hidden unless currently selected.
- The list is the custom admin page `admin.php?page=wcb-applications` (a `WP_List_Table` subclass), not the native `edit.php?post_type=wcb_application` screen. The application edit screen is the `wcb_application` post edit screen with the Applicant card metabox (`class-admin-meta-boxes.php`).
- Steps 9-13 use the plugin list table and the application edit screen.
