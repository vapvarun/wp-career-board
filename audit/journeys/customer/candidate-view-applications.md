---
id: candidate-view-applications
priority: high
personas: sarah.chen
requires: mu:autologin, seed:jobs, seed:applications
last_verified: 2026-05-09
needs: cli
---

# Candidate's "My Applications" lists exactly her fixture applications

**Why this journey exists:** the candidate applications list must be scoped to the logged-in candidate; returning another candidate's applications is a data-isolation bug, returning zero when applications exist is a silent regression. Verifies the ownership filter on the `/wcb/v1/candidates/<id>/applications` endpoint.

## Steps

1. Establish baseline fixture count: `wp post list --post_type=wcb_application --post_author=$(wp user get sarah.chen --field=ID) --post_status=publish --field=ID | wc -l` → capture as `<sarah-count>` (should be 3 per seed contract)
2. As `sarah.chen`, navigate to `/?autologin=sarah.chen` → expect HTTP 200, candidate is logged in
3. GET `/wp-json/wcb/v1/candidates/<sarah-id>/applications` (where `<sarah-id>` = `wp user get sarah.chen --field=ID`) → expect HTTP 200, response is a JSON array with exactly `<sarah-count>` items
4. Verify each item in the response has `_wcb_candidate_id` matching sarah.chen's user ID: inspect `candidate_id` or `applicant_id` field in every response item → expect all equal `<sarah-id>` (no other candidates' applications)
5. Verify each item has a `status` field with a value from the allowlist `[submitted, reviewing, shortlisted, rejected]` → expect no item has an empty or legacy status value (e.g. `applied` or `hired`)
6. Verify cross-isolation: as `marcus.williams` (user 52), GET `/wp-json/wcb/v1/candidates/<sarah-id>/applications` → expect HTTP 403 (marcus cannot read sarah's application list)
7. Navigate to `/candidate-dashboard/?autologin=sarah.chen` → expect HTTP 200, the "My Applications" section renders a list with the same count as step 3
8. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

None — read-only journey (no data created).

## Notes

- The seed contract expects exactly 3 applications for sarah.chen. If the count differs, the seed is stale — do not update this journey; fix the seed instead.
- The autologin-as-marcus step uses: navigate to `/?autologin=marcus.williams` first, then make the GET without the autologin param (the cookie persists in the browser session).
- Application `post_status` is `publish` per the plugin's custom status mapping — confirmed in `apply-to-job.md`.
