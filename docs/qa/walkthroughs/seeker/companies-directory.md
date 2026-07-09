---
id: seeker-companies-directory
priority: medium
personas: anonymous, sarah.chen
requires: mu:autologin, seed:jobs
last_verified: 2026-07-08
covers: company-archive block, company-profile block, GET /wcb/v1/companies, POST /wcb/v1/companies/{id}/bookmark, GET /wcb/v1/jobs
---

# Companies directory — browse the archive and open a single company profile

**Why this journey exists:** the Companies directory is the second discovery surface after Find Jobs — a visitor
browses the public `/companies/` archive (grid/list, filter, search, sort, page) and opens a single company
profile to read its Open Positions. This consolidates `customer/walkthrough-companies-directory` and becomes the
sole coverage for the single `company-profile` view (which had no dedicated journey — flagged ⚠ in the catalog).

## Steps

1. As `anonymous`, navigate to `/companies/` → expect HTTP 200 and the archive shell
   `div.wcb-company-archive[data-wp-interactive="wcb-company-archive"]` with at least one company card
   `article.wcb-ca-card` inside `.wcb-ca-container`. Each card shows `.wcb-ca-name`, a chip row
   `.wcb-ca-card-chips`, and an open-positions label `.wcb-ca-jobs-count` (CPT `has_archive => 'companies'`,
   `modules/employers/class-employers-module.php:215-219`; markup `blocks/company-archive/render.php:212-385`).
2. Click the list-view button `.wcb-view-switcher .wcb-layout-btn` (`actions.setList`) → expect
   `.wcb-ca-container` to swap `.wcb-grid` → `.wcb-list` (bound via `data-wp-class--wcb-list="state.isList"`) and
   `localStorage.wcb_archive_layout === 'list'`; click grid again → reverts
   (`blocks/company-archive/view.js:66-75`; container binds `render.php:300-304`).
3. In the filter sidebar `aside.wcb-filter-panel`, tick one Industry checkbox
   (`input[data-wp-on--change="actions.toggleIndustry"]`) → expect the grid to re-fetch (page resets to 1) and a
   "Clear all" `.wcb-filter-panel__clear` to appear (`view.js:81-93`; `wcbFetchCompanies()` `:260-286`).
4. Tick one Company-size checkbox (`actions.toggleSize`) → expect results to narrow further (industry AND size,
   each OR-within-group); then click `.wcb-filter-panel__clear` (`actions.clearFilters`) → industry + size +
   search all reset and the full set returns (`view.js:95-114`).
5. Type a known company-name fragment into the search box `#wcb-company-search`
   (`data-wp-on--input="actions.updateSearch"`) → after the 250ms debounce expect only title-matching companies
   and the count `.wcb-results-count` to update ("N companies found") (`view.js:154-160`; search id seeded
   `render.php:223`).
6. Change the sort dropdown `.wcb-sort-select` (`actions.changeSort`) from "Newest first" to "Oldest first" →
   expect a re-fetch with the order reversed (first card changes) (`view.js:164-168`, URL builder maps
   `date_asc` → `orderby=date&order=ASC` `:247-253`).
7. Assert the archive REST contract (anonymous, public):
   `GET /wp-json/wcb/v1/companies?page=1&per_page=20&orderby=date&order=DESC` → expect HTTP 200, JSON
   `{ companies:[…], total:<int>, pages:<int>, has_more:<bool> }` plus response headers `X-WCB-Total` +
   `X-WCB-TotalPages`; each item has `id, name, initials, permalink, industry, size_label, hq, job_count,
   jobs_label, trust, verified` (`api/endpoints/class-companies-endpoint.php:35-45`, `prepare_item()` `:353-399`,
   envelope `build_companies_response()` `:330-342`).
8. If `has_more` is true, click "Load more companies" `.wcb-load-more-btn` (`actions.loadMore`) → expect `page`
   to increment, the next page to append (no reload), and the button to hide once `has_more` is false
   (`view.js:170-195`; `templates/parts/archive-load-more.php`).
9. Click a company card link `a.wcb-ca-card-link` (or "View Profile" `.wcb-cbtn`) → expect navigation to
   `/companies/<slug>/` HTTP 200 rendering
   `div.wcb-company-profile.wcb-cp-wrap[data-wp-interactive="wcb-company-profile"]` with hero `.wcb-cp-name`
   matching the card, meta chips `.wcb-cp-meta-chips`, and a "Company Details" section `.wcb-cp-details-grid`
   (single template `single_company_template()` `class-employers-module.php:180-190`; markup
   `blocks/company-profile/render.php:113-230`).
10. On the profile, locate the "Open Positions" section (`section.wcb-cp-section`) → expect EITHER job cards
    `article.wcb-cp-job-card` (each with linked title `.wcb-cp-job-title a` + `.wcb-cjbadge` type/location
    badges) OR the empty line `.wcb-cp-no-jobs` ("No open positions at the moment."). These are the company's
    published `wcb_job` posts queried by `_wcb_company_id` (`render.php:296-385`).
11. Click an open-position title `.wcb-cp-job-title a` (or "View Job" `.wcb-cp-job-apply`) → expect navigation to
    that `wcb_job` single, HTTP 200. If the company has > 10 jobs, first click "Load more jobs"
    `.wcb-load-more-btn` (`actions.loadMore`) → expect more `.wcb-cp-job-card` appended from
    `GET /wp-json/wcb/v1/jobs` (the `jobsApiBase` store base, `render.php:342,373-383`).
12. As `sarah.chen`, open `/companies/?autologin=sarah.chen`, then a profile, and confirm the hero Save button
    behaves — see `seeker/bookmarks` step 8 for the full assertion (`POST /companies/{id}/bookmark`). Only this
    Save step requires login; steps 1-11 are fully anonymous (companies REST `permission_callback => '__return_true'`).
13. tail debug.log diff → expect ZERO new fatal/warning lines.

## Teardown (safe to re-run)

```bash
# Read-only browse — no companies/jobs created. Step 12 may leave a company bookmark; clear it.
SARAH_ID=$(wp user get sarah.chen --field=ID)
wp user meta delete "$SARAH_ID" _wcb_company_bookmark
```

## Notes

- Grounded source: archive URL/CPT slug/template `modules/employers/class-employers-module.php:146,180,215-219`;
  archive markup/selectors `blocks/company-archive/render.php`; archive store (toggles/filters/search/sort/
  loadMore/bookmark + URL builder) `blocks/company-archive/view.js`; profile markup + Open Positions query + Save
  button `blocks/company-profile/render.php`; companies REST route/shape/bookmark
  `api/endpoints/class-companies-endpoint.php:35-164,330-399`; shared toolbar + load-more parts
  `templates/parts/archive-toolbar.php`, `templates/parts/archive-load-more.php`.
- Needs `seed:jobs` so at least one `wcb_company` with ≥1 published `wcb_job` (matched on `_wcb_company_id`)
  exists — otherwise step 10 only exercises the `.wcb-cp-no-jobs` branch and step 1's `.wcb-ca-jobs-count` reads
  "No open positions". The setup-wizard sample seeder creates 3 companies + 8 jobs
  (`admin/class-setup-wizard.php:492`).
- The company Save/bookmark flow is fully specified in `seeker/bookmarks`; it is referenced (not duplicated) here
  to keep the two walkthroughs from drifting.
</content>
</invoke>
