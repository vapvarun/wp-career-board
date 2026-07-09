---
id: seeker-browse-search-filter-jobs
priority: critical
personas: anonymous, sarah.chen
requires: mu:autologin, seed:jobs
last_verified: 2026-07-08
covers: job-listings block, job-search block, job-filters block, GET /wcb/v1/jobs, GET /wcb/v1/search, wcb:search event, wcb:results event
---

# Browse, search & filter jobs — the Find Jobs archive, keyword search, and filter chips

**Why this journey exists:** This is the entry funnel that feeds the apply path — a candidate lands on the
Find Jobs archive, types a keyword, and narrows with filter chips, all driven by the Interactivity API against
`wcb/v1/jobs` with zero page reloads. If browse/search/filter breaks, every downstream conversion breaks with
it. It consolidates the discovery half of `customer/walkthrough-find-jobs-and-apply` (steps 1-4),
`customer/walkthrough-job-search-filters`, `customer/search-jobs-keyword`, and
`customer/job-listings-board-filter` into one human-runnable pass, and guards the 1.5.1 `wcb:results` DOM-event
contract.

## Steps

1. As `anonymous`, navigate to `/find-jobs/` → expect HTTP 200 and the listings wrapper `.wcb-job-listings`
   rendering at least one job card `article.wcb-job-card` inside `.wcb-jobs-container`. `find-jobs` is the
   canonical `jobs_archive_page` slug (`admin/class-pages.php:46`); the setup wizard composes that page from the
   heading + job-search + job-filters + job-listings blocks (`admin/class-setup-wizard.php:422-424`). The CPT
   archive `/jobs/` (`has_archive => 'jobs'`, `modules/jobs/class-jobs-module.php:206-214`) renders the same
   block and is an equivalent entry point.
2. Type `engineer` (or any seeded title term) into the search field `#wcb-search-input` and submit the form
   `form.wcb-search-form` → the input syncs via `data-wp-on--input="actions.updateQuery"` and submit runs
   `data-wp-on--submit="actions.search"`, which pushes `?wcb_search=engineer` into the URL and dispatches the
   `wcb:search` CustomEvent (`blocks/job-search/render.php:36-59`, `blocks/job-search/view.js:18-40`). Expect the
   card grid `.wcb-jobs-container` to re-render filtered results and the count `.wcb-results-count` to update —
   the job-listings store listens for `wcb:search` → `applyFilters` → `fetchJobs`
   (`blocks/job-listings/view.js:583-602`).
3. **1.5.1 — assert the `wcb:results` DOM event fires.** Before submitting a search, register a listener in the
   browser console: `window.__wcbResults = null; document.addEventListener('wcb:results', e => window.__wcbResults = e.detail);`
   Then perform step 2 (or tick a filter). After the fetch resolves, read `window.__wcbResults` → expect an
   object `{ jobIds: [<int>, …], total: <int> }` where `jobIds` are the IDs of the currently-visible cards and
   `total` matches `.wcb-results-count`. `fetchJobs` dispatches this on every resolved fetch so downstream
   listeners (e.g. the Pro job-map block) can sync to the actually-matched jobs
   (`blocks/job-listings/view.js:529-538`).
4. Assert the search REST contract directly: `GET /wp-json/wcb/v1/jobs?search=engineer&per_page=10&page=1` →
   expect HTTP 200 and envelope JSON `{ "jobs": [...], "total": <int>, "pages": <int>, "has_more": <bool> }`,
   where every returned job's `title` (or `_wcb_company_name`) contains the term — search is restricted to title
   + company name, never the post body (`api/endpoints/class-jobs-endpoint.php:152-156,321-460`). The response
   envelope is grounded at `:372-385`; `view.js` tolerates the legacy bare-array shape via the
   `X-WCB-Total` header (`view.js:500-523`).
5. Assert the empty-state contract: `GET /wp-json/wcb/v1/search?q=xyzzy_gibberish_smoke_99_no_match` → expect
   HTTP 200 (NOT 400/500) and an empty array `[]` or `{ total: 0 }`. In the browser, force the same by searching
   a nonsense term → expect the empty-state card `.wcb-empty-state` ("No jobs match your filters") via
   `state.hasNoJobs` and a "Clear filters" CTA (`blocks/job-listings/render.php:793-804`; `view.js:168-170`). The
   `/wcb/v1/search` route is public (`permission_callback => __return_true`).
6. In the filter sidebar `.wcb-filter-panel`, tick a Job type checkbox
   `.wcb-filter-panel__option input[data-wp-on--change="actions.toggleTypeChip"]` → expect an active-filter chip
   `.wcb-active-chip` to appear in `.wcb-active-filters` and the result set to narrow; the fetch appends
   `&type=<slug>` to the REST call (`blocks/job-listings/render.php:502-516,685-695`; `view.js:252-266,464-466`).
7. Tick an Experience checkbox (`actions.toggleExpChip`) and a Category checkbox (`actions.toggleCatChip`) →
   expect two more `.wcb-active-chip` pills and the listing to re-fetch with `&experience=<slug>` +
   `&category=<slug>` ANDed together (`blocks/job-listings/render.php:522-556`; `view.js:269-300,467-472`).
8. **Board chip contract (Basecamp 9976414471).** On an install with ≥2 boards, `/find-jobs/` renders a Board
   chip group; click a board chip → the visible cards narrow and an active "× <board>" chip shows. Assert the
   param contract directly: `GET /wp-json/wcb/v1/jobs?board=<board-id>` → expect the count to equal the jobs
   carrying that `_wcb_board_id` (NOT the unfiltered total); `?board=999999` → expect `0` (filter engaged, not
   ignored); `?board_id=<board-id>` → expect the same count as `?board=<board-id>` (both spellings resolve to the
   same meta_query — `JobsEndpoint::get_items()` reads `board_id ?? board`).
9. Remove one filter pill by clicking its `.wcb-active-chip-remove` button (`actions.removeFilter`) → expect that
   single chip to disappear, the others to remain, and the listing to re-fetch with that one param dropped
   (`blocks/job-listings/render.php:686-694`; `view.js:349-357`).
10. Click "Clear all" `.wcb-filter-panel__clear` (`actions.clearFilters`) → expect every `.wcb-active-chip` to
    clear, the search query cleared, and the full unfiltered listing to return
    (`blocks/job-listings/render.php:488`; `view.js:401-407`).
11. Switch personas — navigate to `/find-jobs/?autologin=sarah.chen&wcb_search=engineer` → expect HTTP 200 and
    the store to hydrate `state.searchQuery` from the `?wcb_search` URL param on init and auto-fetch the filtered
    results, so a shared/bookmarked search link survives a reload for a logged-in candidate
    (`blocks/job-listings/view.js:567-579`).
12. tail debug.log diff for this journey's window → expect ZERO new fatal/warning lines (no Interactivity
    hydration notices, no `WP_Query`/REST errors from the filter params).

## Teardown (safe to re-run)

```bash
# Read-only journey — no posts/users created. Reset is just clearing the
# per-filter-combination transient cache the jobs endpoint warms.
wp transient delete --all
```

## Notes

- **1.5.1-new:** the `wcb:results` DOM event (step 3) is dispatched from `fetchJobs` on EVERY resolved fetch,
  not only on search — filtering, clearing, and load-more all re-dispatch it with the new cumulative `jobIds`.
  It is distinct from the older `wcb:search` event (carries only the query/filters, not matched IDs). Grounded at
  `blocks/job-listings/view.js:529-538`.
- Two search surfaces exist: the block-driven `#wcb-search-input` (drives `GET /wcb/v1/jobs?search=`) and the
  standalone `GET /wcb/v1/search` endpoint (used by `customer/search-jobs-keyword`). Both are public. The
  location filter on `/wcb/v1/search` may take the term slug rather than the display name — try both if it
  returns unexpectedly empty.
- `job-filters` is a standalone server-rendered block that reads `$_GET` filter params
  (`blocks/job-filters/render.php:18-37`) and populates taxonomy dropdowns; on the Find Jobs page the interactive
  filter UI is the `.wcb-filter-panel` rendered inside the job-listings block. Both target the same taxonomies
  (`wcb_category`, `wcb_job_type`, `wcb_location`, `wcb_experience`).
- Board chip group is hidden on single-board installs (nothing to filter by); a job only matches a board filter
  if it carries `_wcb_board_id` postmeta — jobs on the implicit default board won't match. Skip step 8 on a
  single-board site.
- seed:jobs required — needs published `wcb_job` posts across multiple type/experience/category terms so each
  filter narrows a non-empty set.
</content>
</invoke>
