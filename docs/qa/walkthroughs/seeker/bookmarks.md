---
id: seeker-bookmarks
priority: medium
personas: sarah.chen
requires: mu:autologin, seed:jobs
last_verified: 2026-07-08
covers: POST /wcb/v1/jobs/{id}/bookmark, POST /wcb/v1/companies/{id}/bookmark, GET /wcb/v1/candidates/{id}/bookmarks, GET /wcb/v1/candidates/{id}/saved-companies, job-single block, company-profile block, candidate-dashboard block
bug_ref: 9977012701
---

# Bookmarks — save a job and save a company, then see them in the dashboard

**Why this journey exists:** bookmark toggles are pure Interactivity-API replace flows that return 200 but can
silently fail to persist — nearly invisible in manual testing. This consolidates `customer/candidate-bookmark-job`
and `customer/candidate-bookmark-company` into one human-runnable pass, and guards Basecamp 9977012701 (the
company-profile block set `apiBase` twice on one store; the jobs root clobbered the companies root so Save POSTed
to `/jobs/{id}/bookmark` and the id landed under `_wcb_bookmark` instead of `_wcb_company_bookmark`).

## Steps

1. As `sarah.chen`, navigate to `/?autologin=sarah.chen` → expect HTTP 200, candidate logged in. Capture
   `<sarah-id>` = `wp user get sarah.chen --field=ID`.
2. Pick a published job: `wp post list --post_type=wcb_job --post_status=publish --field=ID --posts_per_page=1`
   → `<job-id>`.
3. `POST /wp-json/wcb/v1/jobs/<job-id>/bookmark` with `X-WP-Nonce` and empty body → expect HTTP 200,
   `{ "bookmarked": true }` (`api/endpoints/class-jobs-endpoint.php:79` CREATABLE → `toggle_bookmark()`
   `:1002-1020`). This is the same route the job card's `.wcb-cbtn--danger` Remove and the single-job bookmark
   control drive.
4. Verify persistence: `GET /wp-json/wcb/v1/candidates/<sarah-id>/bookmarks` → expect the array to contain an
   entry with `job_id` = `<job-id>` (route `class-candidates-endpoint.php:56`, `self_permissions_check`; stored
   as non-unique `_wcb_bookmark` usermeta, `:409`).
5. Open `/candidate-dashboard/?autologin=sarah.chen`, click `#wcb-tab-bookmarks` (`actions.switchToBookmarks`) →
   expect the Saved Jobs panel active, a `GET {apiBase}/candidates/{candidateId}/bookmarks` call, and the
   bookmarked job title visible in a `.wcb-cd-bookmark-row` (`blocks/candidate-dashboard/render.php:354-356,604-657`).
6. `POST /wp-json/wcb/v1/jobs/<job-id>/bookmark` again (toggle-off) → expect HTTP 200, `{ "bookmarked": false }`;
   re-`GET /candidates/<sarah-id>/bookmarks` → `<job-id>` is NO LONGER present.
7. Navigate to a company profile `/companies/<slug>/?autologin=sarah.chen` → expect HTTP 200; the hero Save
   control `button.wcb-cp-hero-save` reads "Save" (`blocks/company-profile/render.php:130-144`).
8. Click Save (`actions.toggleBookmark`) → assert the network request is
   `POST /wp-json/wcb/v1/companies/<company-id>/bookmark` (NOT `/jobs/...`) → expect HTTP 200,
   `{ bookmarked: true, company_id: <id> }`; the button gains `.wcb-bookmarked` and its label flips to "Saved"
   (route `api/endpoints/class-companies-endpoint.php:47-57` → `toggle_bookmark()` `:143-155`).
9. Verify the correct meta key: `wp user meta get <sarah-id> _wcb_company_bookmark` contains the company id AND
   `wp user meta get <sarah-id> _wcb_bookmark` does NOT (the 9977012701 regression guard — companies must not
   land under the jobs key).
10. Reload `/companies/<slug>/` → the Save control now reads "Saved" (archive/profile seed `bookmarked` from
    `_wcb_company_bookmark`).
11. In the dashboard, click `#wcb-tab-saved-companies` (`actions.switchToSavedCompanies`) → expect a
    `GET {apiBase}/candidates/{candidateId}/saved-companies` call (`class-candidates-endpoint.php:66`) and the
    saved company row rendered (`render.php:358-360,660-713`).
12. Click Save again on the profile → expect `{ bookmarked: false }`, label reverts to "Save".
13. tail debug.log diff → expect ZERO new fatal/warning lines.

## Teardown (safe to re-run)

```bash
# Toggles are self-cleaning, but clear both keys defensively in case a step left one ON.
SARAH_ID=$(wp user get sarah.chen --field=ID)
wp user meta delete "$SARAH_ID" _wcb_company_bookmark
# Job bookmarks are non-unique usermeta rows; re-POST /jobs/{id}/bookmark removes a stray one,
# or clear all job bookmarks for the persona:
wp user meta delete "$SARAH_ID" _wcb_bookmark
```

## Notes

- Job bookmark: `POST /wcb/v1/jobs/(?P<id>\d+)/bookmark` (`class-jobs-endpoint.php:79`), gated on
  `is_user_logged_in()`, stored as non-unique `_wcb_bookmark` usermeta (one row per saved job).
- Company bookmark: `POST /wcb/v1/companies/(?P<id>\d+)/bookmark` (`class-companies-endpoint.php:47`), stored as
  non-unique `_wcb_company_bookmark` usermeta — mirrors the jobs pattern but a DISTINCT key. The root cause of
  9977012701 was a duplicate `apiBase` across two `wp_interactivity_state()` calls on the `wcb-company-profile`
  store; the jobs base now uses the distinct `jobsApiBase` key so the bookmark keeps the companies root.
- If a bookmark response uses a different shape than `{ bookmarked: bool }` (e.g. a `saved` key), update this
  walkthrough and note it — the shape is not schema-documented.
</content>
</invoke>
