---
id: job-listings-board-filter
priority: high
personas: anonymous
requires: seed:jobs
last_verified: 2026-06-09
needs: cli
---

# Job Listings board filter actually narrows results

**Why this journey exists:** Basecamp 9976414471. The listings block's board chip sends the query param `board` (view.js: `url.searchParams.set('board', value)`), but the REST endpoint only read `board_id` — so the board filter was silently ignored (`?board=N` returned every job regardless). Now the endpoint reads `board` OR `board_id`. This guards the param contract between view.js and the REST endpoint so a future rename can't silently break it again.

## Steps

1. Capture the unfiltered count: GET `/wp-json/wcb/v1/jobs` → record `<total>` (the jobs array length)
2. Find a board with jobs: `wp post list --post_type=wcb_board --field=ID --posts_per_page=1` → `<board-id>`; confirm at least one job carries it: `wp post list --post_type=wcb_job --meta_key=_wcb_board_id --meta_value=<board-id> --field=ID` → `<on-board>` count ≥ 1
3. **Frontend param** — GET `/wp-json/wcb/v1/jobs?board=<board-id>` → expect the count to equal `<on-board>` (NOT `<total>`); the `board` param must engage the filter
4. **Negative** — GET `/wp-json/wcb/v1/jobs?board=999999` (nonexistent board) → expect `0` (filter engaged), NOT `<total>` (the old ignored-param bug returned everything)
5. **Other callers** — GET `/wp-json/wcb/v1/jobs?board_id=<board-id>` → expect the same count as step 3 (both param spellings resolve to the same `_wcb_board_id` meta_query)
6. Browser: on a site with ≥2 boards, `/find-jobs/` renders a Board chip group; clicking a board chip narrows the visible cards and shows an active "× <board>" chip
7. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

_None — read-only queries._

## Notes

- REST: `JobsEndpoint::get_items()` reads `$request->get_param('board_id') ?? $request->get_param('board')` → `meta_query` on `_wcb_board_id` (NUMERIC).
- A job only matches a board filter if it has the `_wcb_board_id` postmeta. Jobs created without an explicit board (implicit default board) won't carry the meta and won't match any board filter — that's expected; the chip only appears when multiple boards exist.
- The board chip group is hidden on single-board installs (nothing to filter by).
