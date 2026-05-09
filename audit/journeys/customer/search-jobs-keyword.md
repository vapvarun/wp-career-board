---
id: search-jobs-keyword
priority: high
personas: anonymous
requires: seed:jobs
last_verified: 2026-05-09
needs: cli
---

# Job search returns matches for a known token, empty state for gibberish, and narrows on location filter

**Why this journey exists:** search is how candidates find jobs; a broken query that returns 0 results for a known keyword, or 500 on gibberish, drives ticket volume. Verifies both the happy path and the failure-state contract (clean empty state, not an error).

## Steps

1. Find a token guaranteed to match at least one published job: `wp post list --post_type=wcb_job --post_status=publish --field=post_title --posts_per_page=1` → extract a single meaningful word from the title (e.g. "Engineer", "Developer") → capture as `<known-token>`
2. As anonymous, GET `/wp-json/wcb/v1/search?q=<known-token>` → expect HTTP 200, response is a JSON array with at least 1 item, each item has a non-empty `title` field containing or related to `<known-token>`
3. As anonymous, GET `/wp-json/wcb/v1/search?q=xyzzy_gibberish_smoke_99_no_match` → expect HTTP 200 (NOT 400 or 500), response is an empty array `[]` or a JSON object with `total: 0` (clean empty state)
4. Find a `wcb_location` taxonomy term assigned to at least one job: `wp term list wcb_location --field=name --number=1` → capture as `<location-term>`
5. GET `/wp-json/wcb/v1/search?q=<known-token>&location=<location-term>` → expect HTTP 200, response array length is ≤ step 2 response length (location filter narrows the result set)
6. GET `/wp-json/wcb/v1/jobs?post_status=publish` (unfiltered listing) → confirm total published job count is ≥ result from step 2 (search is a subset of all published jobs)
7. Navigate to the front-end search page (or any page with `wp-career-board/job-search` block) with `?autologin=1` → expect HTTP 200, search input field renders; type `<known-token>` → expect result list updates (at least 1 result visible)
8. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

None — read-only journey.

## Notes

- The search endpoint is `GET /wcb/v1/search` per manifest with permission `__return_true` (public).
- Location filter may use the term slug rather than the display name; try both if step 5 returns unexpectedly empty.
- The job listings endpoint is `GET /wcb/v1/jobs` — distinct from the search endpoint; both are public.
