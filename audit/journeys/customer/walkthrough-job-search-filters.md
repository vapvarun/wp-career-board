---
id: walkthrough-job-search-filters
priority: high
personas: anonymous, sarah.chen
requires: mu:autologin, seed:jobs
last_verified: 2026-06-29
---

# Walkthrough: Job Search & Filters — search by keyword, narrow with filters, sort, and load more without a reload

**Why this journey exists:** This is the end-to-end walkthrough of the job discovery surface in WP Career Board.
It traces the full happy path a visitor takes to find a job — type a keyword, narrow with the filter sidebar
(type, experience, category, location/remote, salary), re-sort, remove a chip, clear all, and page through results —
all driven by the Interactivity API against the `wcb/v1/jobs` REST endpoint with zero page reloads. Covering the
whole search+filter mechanism in one pass guards the entry funnel that feeds the apply path.

## Steps

1. As `anonymous`, navigate to `/find-jobs/` → expect HTTP 200 and the listings wrapper `.wcb-job-listings`
   rendering at least one job card `article.wcb-job-card`. `find-jobs` is the canonical `jobs_archive_page`
   slug (`admin/class-pages.php:46`); the page composes the job-search + filter + job-listings blocks. The CPT
   archive `/jobs/` (`has_archive => 'jobs'`) renders the same block and is an equivalent entry point.
2. Type `engineer` (or any seeded title term) into the search field `#wcb-search-input` and submit the form
   `form.wcb-search-form` → the input syncs via `data-wp-on--input="actions.updateQuery"` and submit runs
   `data-wp-on--submit="actions.search"`, which pushes `?wcb_search=engineer` into the URL and dispatches the
   `wcb:search` CustomEvent (`blocks/job-search/render.php:36-59`, `blocks/job-search/view.js:18-40`). Expect the
   card grid `.wcb-jobs-container` to re-render filtered results and the count `.wcb-results-count` to update —
   the job-listings store listens for `wcb:search` → `applyFilters` → `fetchJobs` (`blocks/job-listings/view.js:583-602`).
3. Assert the search REST contract directly: `GET /wp-json/wcb/v1/jobs?search=engineer&per_page=10&page=1`
   → expect HTTP 200 and envelope JSON `{ "jobs": [...], "total": <int>, "pages": <int>, "has_more": <bool> }`,
   where every returned job's `title` (or company name) contains the term — search is restricted to title +
   `_wcb_company_name` only, never post body (`api/endpoints/class-jobs-endpoint.php:152-156,321-460,372-385`).
4. In the filter sidebar `.wcb-filter-panel`, tick a Job type checkbox (`.wcb-filter-panel__group` → Job type →
   `input[data-wp-on--change="actions.toggleTypeChip"]`) → expect an active-filter chip `.wcb-active-chip` to
   appear in `.wcb-active-filters` and the result set to narrow; the fetch appends `&type=<slug>` to the REST
   call (`blocks/job-listings/render.php:502-516,685-695`; `view.js:252-266,464-466`).
5. Tick an Experience checkbox (`input[data-wp-on--change="actions.toggleExpChip"]`) and a Category checkbox
   (`input[data-wp-on--change="actions.toggleCatChip"]`) → expect two more `.wcb-active-chip` pills and the
   listing to re-fetch with `&experience=<slug>` + `&category=<slug>` ANDed together
   (`blocks/job-listings/render.php:522-556`; `view.js:269-300,467-472`).
6. Tick the Location group's "Remote only" checkbox (`input[data-wp-on--change="actions.toggleRemote"]`) →
   expect a Remote chip and the fetch to add `&remote=1` (server adds a `_wcb_remote=1` meta_query)
   (`blocks/job-listings/render.php:582-592`; `view.js:320-329,473`; endpoint `:218-223`).
7. Drag the Salary minimum slider `#wcb-salary-min-range` (and/or `#wcb-salary-max-range`) and release →
   `actions.updateSalaryMin`/`updateSalaryMax` commit `salary_min`/`salary_max` into `activeFilters`, the
   chip reads e.g. `$60k+`, and the fetch adds `&salary_min=<n>` (server compares against `_wcb_salary_max >=`)
   (`blocks/job-listings/render.php:636-661`; `view.js:372-390,483-486`; endpoint `:225-243`).
8. Change the sort dropdown `.wcb-sort-select` (in `.wcb-listings-toolbar` `.wcb-toolbar-end` on this page,
   since the standalone search block hides the toolbar's own input) from "Newest first" to "Oldest first"
   `value="date_asc"` → `actions.changeSort` re-fetches with `&orderby=date&order=ASC` and the first card flips
   to the oldest seeded job (`templates/parts/archive-toolbar.php:99-115,163-170`; `view.js:246-249,448-454`;
   endpoint `:300-307,1470-1485`).
9. Remove one filter pill by clicking its `.wcb-active-chip-remove` button (`actions.removeFilter`) → expect
   that single chip to disappear, the others to remain, and the listing to re-fetch with that one param dropped
   (`blocks/job-listings/render.php:686-694`; `view.js:349-357`).
10. Click "Clear all" `.wcb-filter-panel__clear` (`actions.clearFilters`) → expect every `.wcb-active-chip` to
    clear, salary sliders reset to 0, the search query cleared, and the full unfiltered listing to return
    (`blocks/job-listings/render.php:488`; `view.js:401-407`).
11. Force an empty result: tick a filter combination no seeded job matches (or search a nonsense term) → expect
    the empty-state card `.wcb-empty-state` with heading "No jobs match your filters" to show via
    `state.hasNoJobs`, and a "Clear filters" CTA (`blocks/job-listings/render.php:793-804`; `view.js:168-170`).
12. With filters cleared and more than one page of seeded jobs present, click "Load more jobs"
    `.wcb-load-more-btn` (`actions.loadMore`) → expect the next page to append to `.wcb-jobs-container`
    (page increments, `&page=2` fetched) and the button to hide when `state.hasMore` becomes false
    (`templates/parts/archive-load-more.php`; `view.js:416-420,509-523`).
13. Switch personas — navigate to `/find-jobs/?autologin=sarah.chen&wcb_search=engineer` → expect HTTP 200 and
    the store to hydrate `state.searchQuery` from the `?wcb_search` URL param on init and auto-fetch the filtered
    results, so a shared/bookmarked search link survives a reload for the logged-in candidate
    (`blocks/job-listings/view.js:567-579`).
14. tail debug.log diff for this journey's window → expect ZERO new fatal/warning lines (no Interactivity
    hydration notices, no `WP_Query`/REST errors from the filter params).

## Teardown

```bash
# Read-only journey — no posts/users created. Reset is just clearing the
# transient cache the jobs endpoint warms per filter combination.
wp transient delete --all
```

## Notes

- Entry slug: `find-jobs` is the canonical `jobs_archive_page` (`admin/class-pages.php:46`); the `wcb_job` CPT
  also serves `/jobs/` (`has_archive => 'jobs'`). Both render the `.wcb-job-listings` block, so either URL works.
- Search input + form selectors: `#wcb-search-input`, `form.wcb-search-form`, `actions.updateQuery` /
  `actions.search` — `blocks/job-search/render.php:36-59`. The block pushes `?wcb_search=` and dispatches the
  `wcb:search` event (`blocks/job-search/view.js:18-40`); job-listings hydrates from the URL + listens for the
  event (`blocks/job-listings/view.js:567-602`).
- Filter controls (all in `.wcb-filter-panel`, checkbox `input` inside `.wcb-filter-panel__option`):
  Job type `actions.toggleTypeChip`, Experience `actions.toggleExpChip`, Category `actions.toggleCatChip`,
  Tags `actions.toggleTagChip`, Remote `actions.toggleRemote`, Salary sliders `#wcb-salary-min-range` /
  `#wcb-salary-max-range` (`actions.updateSalaryMin`/`updateSalaryMax`) — `blocks/job-listings/render.php:481-680`.
  Active pills `.wcb-active-chip` + remove `.wcb-active-chip-remove` (`actions.removeFilter`), "Clear all"
  `.wcb-filter-panel__clear` (`actions.clearFilters`).
- Sort control: `.wcb-sort-select` with `value` `date_desc` / `date_asc`, action `actions.changeSort`
  (`templates/parts/archive-toolbar.php:99-115`). On the Jobs page the standalone `wcb/job-search` block is
  present, so the toolbar hides its own search input and the sort select renders inside `.wcb-toolbar-end`
  (`archive-toolbar.php:163-170`; `blocks/job-listings/render.php:454`).
- REST route + params: `GET /wcb/v1/jobs`, `permission_callback => __return_true` (public read).
  Accepts `search`/`wcb_search`, `category`/`wcb_category`, `type`/`wcb_job_type`, `location`/`wcb_location`,
  `experience`/`wcb_experience`, `tag`/`wcb_tag`, `remote`, `salary_min`, `salary_max`, `board`/`board_id`,
  `author`, `orderby` (enum `date` only), `order` (`ASC`/`DESC`), `page`, `per_page` (max 100) —
  `api/endpoints/class-jobs-endpoint.php:37-53,131-355,1445-1499`. Response envelope since 1.1.0:
  `{ jobs, total, pages, has_more }` (`:372-385`); the view.js fetch tolerates the legacy bare-array shape too
  (`blocks/job-listings/view.js:500-523`).
- seed:jobs required — needs published `wcb_job` posts across multiple types/experience/category terms (and
  more than one page for step 12) so each filter narrows a non-empty set; salary steps need jobs carrying
  `_wcb_salary_min`/`_wcb_salary_max` meta.
