---
id: candidate-bookmark-job
priority: high
personas: sarah.chen
requires: mu:autologin, seed:jobs
last_verified: 2026-05-09
needs: cli
---

# Candidate saves a job bookmark, views it in saved-jobs, then unsaves it

**Why this journey exists:** bookmark toggle (save/unsave) is a pure AJAX-replace flow using the Interactivity API store; a broken toggle that returns 200 but silently fails to persist is nearly invisible in manual testing. Verifies both the POST and the GET list round-trip, plus the count decrement on unsave.

## Steps

1. As `sarah.chen`, navigate to `/?autologin=sarah.chen` → expect HTTP 200, candidate is logged in
2. Pick a published `wcb_job` ID: `wp post list --post_type=wcb_job --post_status=publish --field=ID --posts_per_page=1` → capture as `<job-id>`
3. POST `/wp-json/wcb/v1/jobs/<job-id>/bookmark` with an empty body (or `{}`) → expect HTTP 200, response `{"bookmarked": true}` (or equivalent truthy field)
4. Verify the bookmark persists: GET `/wp-json/wcb/v1/candidates/<sarah-id>/bookmarks` (where `<sarah-id>` = `wp user get sarah.chen --field=ID`) → expect the response array contains an entry with `job_id` equal to `<job-id>`
5. Navigate to the saved-jobs UI page at `/candidate-dashboard/?autologin=sarah.chen` → expect HTTP 200, the bookmarked job title is visible in the "Saved Jobs" section
6. POST `/wp-json/wcb/v1/jobs/<job-id>/bookmark` again (toggle-off) → expect HTTP 200, response `{"bookmarked": false}` (or equivalent falsy field)
7. Re-fetch bookmarks: GET `/wp-json/wcb/v1/candidates/<sarah-id>/bookmarks` → expect `<job-id>` is NO LONGER in the response array
8. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

```bash
# Ensure bookmark is cleared (idempotent — second POST to /bookmark removes it if present)
# No persistent records to clean beyond the toggle itself
```

## Notes

- The bookmark endpoint is `POST /wcb/v1/jobs/(?P<id>\\d+)/bookmark` per the manifest (permission: `is_user_logged_in inline`).
- The GET endpoint for the saved list is `GET /wcb/v1/candidates/(?P<id>\\d+)/bookmarks` (permission: `self_permissions_check`).
- If the response shape differs (e.g. a `saved` key instead of `bookmarked`), update this journey to match and file a note — the shape is undocumented in the manifest.
