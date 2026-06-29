---
id: walkthrough-candidate-dashboard
priority: high
personas: sarah.chen
requires: mu:autologin, seed:jobs
last_verified: 2026-06-29
---

# Walkthrough: Candidate Dashboard — a candidate manages applications, saved jobs, profile and account in one sidebar app

**Why this journey exists:** This is the end-to-end walkthrough of the Candidate Dashboard. It traces the full
happy path a real candidate takes — landing on the dashboard, reviewing the Overview stats, switching through the
sidebar tabs (My Applications, Saved Jobs, Saved Companies, Profile, Settings), editing and saving their profile,
removing a saved job, and withdrawing an application — so the whole functionality is browser-coverable in one pass.

## Steps

1. As `sarah.chen`, navigate to `http://jobboard.local/candidate-dashboard/?autologin=sarah.chen` → expect HTTP 200 and the dashboard shell `div.wcb-dashboard[data-wp-interactive="wcb-candidate-dashboard"]` with sidebar `aside.wcb-sidebar` rendered (NOT the `.wcb-db-gate` "Please sign in" fallback). _(render.php:18-28, 268-401)_
2. On the Overview panel (`div.wcb-view-panel` bound to `state.isTabOverview`), expect the stats row `.wcb-stats-row` to show four `.wcb-stat-card` tiles — Applications, Shortlisted, Saved Jobs, My Resumes — each with a `.wcb-stat-value` number. _(render.php:455-471)_
3. Click the sidebar nav button `#wcb-tab-applications` (`actions.switchToApplications`) → expect the My Applications panel (`[aria-labelledby="wcb-tab-applications"]`) to become active and a `GET {apiBase}/candidates/{candidateId}/applications` call to fire (`apiBase` = `http://jobboard.local/wp-json/wcb/v1`). Expect either application rows `.wcb-cd-app-row` or the empty state `.wcb-cd-empty` ("You haven't applied to any jobs yet."). _(render.php:293-296,536-601; view.js:290-291; class-applications-endpoint.php:81)_
4. If an application row is present, expect each `.wcb-cd-app-row` to carry a `.wcb-cd-status-badge[data-status]` and a `.wcb-cd-withdraw-btn` (visible when `state.allowWithdraw`). _(render.php:549-590)_
5. Click `#wcb-tab-bookmarks` (`actions.switchToBookmarks`) → expect the Saved Jobs panel (`[aria-labelledby="wcb-tab-bookmarks"]`) active and a `GET {apiBase}/candidates/{candidateId}/bookmarks` call. Expect bookmark rows `.wcb-cd-bookmark-row` (each with a `.wcb-cd-bookmark-title a` job link) or the empty state `.wcb-cd-empty` ("No saved jobs yet."). _(render.php:354-356,604-657; view.js:311-312; class-candidates-endpoint.php:56)_
6. With at least one saved job present, click its Remove button `.wcb-cbtn--danger` (`actions.unbookmark`) → expect a `POST {apiBase}/jobs/{bookmark.id}/bookmark` (toggle-off) call and the row to disappear from `.wcb-panel`. _(render.php:642-646; view.js:881-905; class-jobs-endpoint.php:79 CREATABLE → toggle_bookmark)_
7. Click `#wcb-tab-saved-companies` (`actions.switchToSavedCompanies`) → expect the Saved Companies panel (`[aria-labelledby="wcb-tab-saved-companies"]`) active and a `GET {apiBase}/candidates/{candidateId}/saved-companies` call, rendering `.wcb-cd-bookmark-row` items or the "No saved companies yet." empty state. _(render.php:358-360,660-713; view.js:442-443; class-candidates-endpoint.php:66)_
8. Click `#wcb-tab-profile` (`actions.switchToProfile`) → expect the My Profile panel (`[aria-labelledby="wcb-tab-profile"]`) active with the read-only email `#wcb-profile-email`, editable `#wcb-profile-phone`, `#wcb-profile-location`, and the bio textarea `#wcb-profile-bio`. _(render.php:370-375,960-1015)_
9. Type a new value into `#wcb-profile-location` (e.g. "Bengaluru, India") via `actions.updateProfileLocation`, then click `.wcb-cbtn--primary` "Save Profile" (`actions.saveProfile`) → expect a `PUT {apiBase}/candidates/{candidateId}` with JSON body `{ bio, resume_data:{ phone, location }, custom_fields }`, and the `.wcb-save-confirm` "Saved" badge (`state.profileSaved`) to flash. _(render.php:990-998,1026-1034; view.js:627-650; class-candidates-endpoint.php:39 EDITABLE)_
10. Reload `http://jobboard.local/candidate-dashboard/?autologin=sarah.chen`, re-open Profile → expect `#wcb-profile-location` to still show the saved value (persistence confirmed via `_wcb_resume_data` user meta). _(render.php:217-225)_
11. Click `#wcb-tab-settings` (`actions.switchToSettings`) → expect the Account Settings panel (`[aria-labelledby="wcb-tab-settings"]`) active with `#wcb-account-name`, `#wcb-account-email`, the Change Password fields (`#wcb-account-curpw`/`#wcb-account-newpw`/`#wcb-account-confpw`), and the "Privacy & My Data" panel (`.wcb-privacy-panel`) with Export + Delete controls. _(render.php:376-381,1038-1111)_
12. Return to My Applications (`#wcb-tab-applications`); with a live application and `state.allowWithdraw` true, click `.wcb-cd-withdraw-btn` (`actions.withdrawApplication`) → expect the shared confirm modal (`wcb-confirm-modal`), and on confirm a `DELETE {apiBase}/applications/{application.id}` call that removes the row. _(render.php:37-39,577-583; view.js:1182-1201; class-applications-endpoint.php:52 DELETABLE → withdraw_application)_
13. tail debug.log diff → expect ZERO new fatal/warning lines.

## Teardown
```bash
# The walkthrough only removes a saved job and (optionally) withdraws an application,
# both of which are normal candidate actions. To restore seed state, re-run the jobs/
# bookmark seeder, or re-bookmark + re-apply as sarah.chen. No DB cleanup is required
# for the profile edit (it overwrites _wcb_resume_data in place).
wp --path="/Users/varundubey/Local Sites/jobboard/app/public" eval '
  $u = get_user_by("login","sarah.chen");
  if ($u) { echo "candidate id: ".$u->ID."\n"; }
'
```

## Notes
- Page slug `/candidate-dashboard/` is the auto-created "Candidate Dashboard" page holding the
  `wp:wp-career-board/candidate-dashboard` block — `admin/class-setup-wizard.php:418-421`
  (page id stored in the `candidate_dashboard_page` setting).
- `apiBase` is seeded as `untrailingslashit( rest_url('wcb/v1') )` → `http://jobboard.local/wp-json/wcb/v1`; every
  fetch sends the `X-WP-Nonce` header from `state.nonce` (`wp_create_nonce('wp_rest')`) — `render.php:164-165`.
- Real REST routes grounded:
  - `GET /candidates/{id}/applications` — `api/endpoints/class-applications-endpoint.php:81`
  - `GET /candidates/{id}/bookmarks` — `api/endpoints/class-candidates-endpoint.php:56`
  - `GET /candidates/{id}/saved-companies` — `api/endpoints/class-candidates-endpoint.php:66`
  - `PUT /candidates/{id}` (profile save) — `api/endpoints/class-candidates-endpoint.php:39` (EDITABLE)
  - `POST /jobs/{id}/bookmark` (toggle saved job) — `api/endpoints/class-jobs-endpoint.php:79` (CREATABLE)
  - `DELETE /applications/{id}` (withdraw) — `api/endpoints/class-applications-endpoint.php:52` (DELETABLE)
- Selectors/tab IDs grounded in `blocks/candidate-dashboard/render.php`; actions/fetch URLs in
  `blocks/candidate-dashboard/view.js` (all fetches routed through `@wcb/fetch` with a 15s AbortController).
- The dashboard only renders for a logged-in user granted `wcb/access-candidate-dashboard`; `sarah.chen` is the
  candidate persona. Steps 3/5/12 expect seeded jobs + an existing application/bookmark for the non-empty branch;
  the empty-state assertions cover the seed-absent case.
- Pro-only tabs (My Resumes, Job Alerts, Notifications, Saved Resumes) are intentionally out of scope here — they
  are gated by `wcb_pro_*` filters and the `wcb_resume` CPT (`render.php:334,362,382,772-958`).
