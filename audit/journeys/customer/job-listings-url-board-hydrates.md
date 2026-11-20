---
id: job-listings-url-board-hydrates
priority: high
personas: anonymous, candidate, employer
requires: mu:autologin, seed:jobs, pro:active
last_verified: 2026-11-20
bug_ref: 10516901346
needs: cli
---

# A ?board= link stays applied after the first click

**Why this journey exists:** Basecamp 10516901346. The server filtered the first paint by `?board=<id>`, but `render.php` never seeded `activeFilters[board_<id>]`, so the board checkbox was unticked, no pill showed, and the first sort, filter or Load more fetched every board and dropped `board` from the address bar. Pinned by `tests/test-job-search.php` (section "?board= reaches the client state").

## Steps

1. Pick a board with jobs: `<board-id>` with `<on-board>` published jobs (1000+ jobs on the site in total), some of them remote.
2. As anonymous open a page with only the job-listings block (sidebar filters) at `?board=<board-id>` → the "<board name>" checkbox under **Job board** is ticked, an "× <board name>" pill shows, "<on-board> jobs found".
3. Tick **Remote only** → URL becomes `?board=<board-id>&remote=1`, the count drops to the board's remote jobs (NOT the site-wide remote count), both pills show.
4. Remove the board pill → URL `?remote=1`, the checkbox unticks, the count is the site-wide remote count.
5. Repeat 2-3 on `/find-jobs/` (job-filters bar + listing): the pill shows and Remote keeps the board in the URL and results.
6. `?board_id=<board-id>` behaves the same as `?board=`; `?board=abc` shows no board pill.
7. A listing block fixed to one board (`{"boardId":<A>}`) opened at `?board=<B>` → shows board A's jobs (not an empty list), board A's checkbox is ticked, no removable pill, and **Clear all** keeps board A.
8. After step 3, cards keep their board badge in List layout (REST `GET /wcb/v1/jobs` carries `board_name`, Pro `tests/test-board-client-parity.php`).
9. Repeat 2-4 as a candidate and an employer, at 1440, 1024 and 390, light and dark (`html[data-bx-mode="dark"]`), and once in an RTL locale.
10. tail debug.log diff → expect ZERO new fatal/warning lines.

## Teardown

_None — read-only._
