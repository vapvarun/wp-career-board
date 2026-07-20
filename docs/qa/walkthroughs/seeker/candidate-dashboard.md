---
id: seeker-candidate-dashboard
priority: high
personas: sarah.chen, marcus.williams
requires: mu:autologin, seed:jobs, seed:applications
last_verified: 2026-07-08
covers: candidate-dashboard block, GET /wcb/v1/candidates/{id}/applications, PUT /wcb/v1/candidates/{id}, DELETE /wcb/v1/applications/{id}
---

# Candidate dashboard — it renders, lists my applications, and edits my profile

**Why this journey exists:** the dashboard is the candidate's home base — the one screen where they review their
applications, edit their profile, and manage their account. This consolidates
`customer/walkthrough-candidate-dashboard`, `customer/candidate-view-applications` (the ownership-scoped
applications list), and `customer/candidate-edit-profile` (the PUT round-trip that must persist) into one
human-runnable pass, including the data-isolation guard that one candidate can't read another's applications.

## Steps

1. As `sarah.chen`, navigate to `/candidate-dashboard/?autologin=sarah.chen` → expect HTTP 200 and the dashboard
   shell `div.wcb-dashboard[data-wp-interactive="wcb-candidate-dashboard"]` with sidebar `aside.wcb-sidebar`
   (NOT the `.wcb-db-gate` "Please sign in" fallback) (`blocks/candidate-dashboard/render.php:18-28,268-401`).
   Page slug `/candidate-dashboard/` holds the `wp:wp-career-board/candidate-dashboard` block
   (`admin/class-setup-wizard.php:418-421`).
2. On the Overview panel (`div.wcb-view-panel` bound to `state.isTabOverview`) → expect the stats row
   `.wcb-stats-row` with four `.wcb-stat-card` tiles (Applications, Shortlisted, Saved Jobs, My Resumes), each
   showing a `.wcb-stat-value` number (`render.php:455-471`).
3. Establish the applications baseline: `wp post list --post_type=wcb_application --post_author=$(wp user get sarah.chen --field=ID) --post_status=publish --field=ID | wc -l`
   → capture `<sarah-count>` (seed contract expects 3).
4. Click `#wcb-tab-applications` (`actions.switchToApplications`) → expect the My Applications panel
   (`[aria-labelledby="wcb-tab-applications"]`) active and a `GET {apiBase}/candidates/{candidateId}/applications`
   call (`apiBase` = `rest_url('wcb/v1')`). Expect either application rows `.wcb-cd-app-row` or the empty state
   `.wcb-cd-empty` (`render.php:293-296,536-601`; `api/endpoints/class-applications-endpoint.php:81`).
5. Assert the applications REST contract + ownership scope:
   `GET /wp-json/wcb/v1/candidates/<sarah-id>/applications` → expect HTTP 200 and exactly `<sarah-count>` items,
   every item's candidate id = `<sarah-id>`, every `status` from the allowlist
   `[submitted, reviewing, shortlisted, rejected]` (no `applied`/`hired`).
6. **Data-isolation guard.** Log in as `marcus.williams` (navigate `/?autologin=marcus.williams`) then
   `GET /wp-json/wcb/v1/candidates/<sarah-id>/applications` → expect HTTP 403 (marcus cannot read sarah's list;
   the endpoint uses `self_permissions_check`).
7. Back as `sarah.chen`, each present `.wcb-cd-app-row` carries a `.wcb-cd-status-badge[data-status]` and (when
   `state.allowWithdraw`) a `.wcb-cd-withdraw-btn` (`render.php:549-590`).
8. Click `#wcb-tab-profile` (`actions.switchToProfile`) → expect the My Profile panel active with read-only
   `#wcb-profile-email`, editable `#wcb-profile-phone`, `#wcb-profile-location`, and bio textarea
   `#wcb-profile-bio` (`render.php:370-375,960-1015`).
9. Type a new value into `#wcb-profile-location` (e.g. "Bengaluru, India") via `actions.updateProfileLocation`,
   then click "Save Profile" `.wcb-cbtn--primary` (`actions.saveProfile`) → expect a
   `PUT {apiBase}/candidates/{candidateId}` with JSON `{ bio, resume_data:{ phone, location }, custom_fields }`
   and the `.wcb-save-confirm` "Saved" badge to flash (`render.php:990-998,1026-1034`;
   `blocks/candidate-dashboard/view.js:627-650`; `api/endpoints/class-candidates-endpoint.php:47` EDITABLE).
10. Reload `/candidate-dashboard/?autologin=sarah.chen`, re-open Profile → expect `#wcb-profile-location` to
    still show the saved value (persisted to `_wcb_resume_data` user meta — `render.php:217-225`).
11. Return to `#wcb-tab-applications`; with a live application and `state.allowWithdraw` true, click
    `.wcb-cd-withdraw-btn` (`actions.withdrawApplication`) → expect the shared confirm modal `wcb-confirm-modal`,
    and on confirm a `DELETE {apiBase}/applications/{application.id}` call that removes the row
    (`render.php:37-39,577-583`; `view.js:1182-1201`; `class-applications-endpoint.php:52` DELETABLE).
12. tail debug.log diff → expect ZERO new fatal/warning lines.

## Teardown (safe to re-run)

```bash
# The walkthrough overwrites _wcb_resume_data in place (no cleanup needed) and may
# withdraw one application. To restore seed state, re-run the applications seeder.
# Restore the display name only if the profile edit changed it.
SARAH_ID=$(wp user get sarah.chen --field=ID)
wp user update "$SARAH_ID" --display_name="Sarah Chen"
```

## Notes

- `apiBase` is seeded as `untrailingslashit( rest_url('wcb/v1') )`; every dashboard fetch sends `X-WP-Nonce` from
  `state.nonce` (`wp_create_nonce('wp_rest')`) — `render.php:164-165`. All fetches route through `@wcb/fetch`
  with a 15s AbortController.
- Grounded REST routes: `GET /candidates/{id}/applications` (`class-applications-endpoint.php:81`),
  `PUT /candidates/{id}` (`class-candidates-endpoint.php:47`, EDITABLE),
  `DELETE /applications/{id}` (`class-applications-endpoint.php:52`, DELETABLE).
- The seed contract expects exactly 3 applications for sarah.chen; if step 3/5 differs the seed is stale — fix
  the seed, not this walkthrough.
- Pro-only tabs (My Resumes, Job Alerts, Notifications, Saved Resumes) are gated by `wcb_pro_*` filters and the
  `wcb_resume` CPT (`render.php:334,362,382,772-958`) — out of scope here. Saved Jobs / Saved Companies tabs are
  covered by `seeker/bookmarks`.
- `marcus.williams` is a second candidate persona (user 52 on the seed), used here purely for the isolation
  check in step 6.
</content>
</invoke>
