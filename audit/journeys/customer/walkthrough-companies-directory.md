---
id: walkthrough-companies-directory
priority: high
personas: anonymous, sarah.chen
requires: mu:autologin, seed:jobs
last_verified: 2026-06-29
---

# Walkthrough: Companies Directory — browse the archive, open a profile, see that company's open jobs

**Why this journey exists:** This is the end-to-end walkthrough of the Companies Directory. It traces the full
happy path a real visitor takes — landing on the public `/companies/` archive, toggling grid/list, filtering by
industry + size, searching + sorting, paging through results, opening a single company profile, reading its Open
Positions, jumping to a job, and (logged in) saving the company — so the whole functionality is browser-coverable
in one pass.

## Steps

1. As `anonymous`, navigate to `http://jobboard.local/companies/` → expect HTTP 200 and the archive shell
   `div.wcb-company-archive[data-wp-interactive="wcb-company-archive"]` with at least one company card
   `article.wcb-ca-card` rendered inside `.wcb-ca-container`. Each card shows `.wcb-ca-name`, a chip row
   `.wcb-ca-card-chips`, and an open-positions label `.wcb-ca-jobs-count` (e.g. "3 open positions" / "No open
   positions"). (CPT `has_archive => 'companies'`, slug `companies` — `modules/employers/class-employers-module.php:215`-`219`; archive template `archive_template()` line 146; markup `blocks/company-archive/render.php:212`-`385`.)
2. Click the list-view button `.wcb-view-switcher .wcb-layout-btn` (action `actions.setList`) → expect the
   container `.wcb-ca-container` to swap class `.wcb-grid` → `.wcb-list` (bound via `data-wp-class--wcb-list="state.isList"`),
   and `localStorage.wcb_archive_layout === 'list'`. Click grid again → reverts to `.wcb-grid`.
   (`blocks/company-archive/view.js:66`-`75`, container binds `render.php:300`-`304`.)
3. In the filter sidebar `aside.wcb-filter-panel`, tick one Industry checkbox
   (`label.wcb-filter-panel__option input[data-wp-on--change="actions.toggleIndustry"]`) → expect the grid to
   re-fetch (page resets to 1, `.wcb-ca-card` list re-renders to only matching companies) and a "Clear all"
   button `.wcb-filter-panel__clear` to appear. (`view.js:81`-`93`; `wcbFetchCompanies()` `view.js:260`-`286`.)
4. Tick one Company-size checkbox (`input[data-wp-on--change="actions.toggleSize"]`) → expect results to narrow
   further (industry AND size both applied; each is OR-within-group). Then click `.wcb-filter-panel__clear`
   (`actions.clearFilters`) → expect industry + size + search all reset and the full result set return.
   (`view.js:95`-`114`.)
5. Type a known company name fragment into the search box `#wcb-company-search` (`.wcb-listings-search`,
   `data-wp-on--input="actions.updateSearch"`) → after the 250ms debounce expect the card list to re-fetch and
   show only title-matching companies; the count `.wcb-results-count` updates ("N companies found").
   (`view.js:154`-`160`; toolbar `templates/parts/archive-toolbar.php`; search id seeded `render.php:223`.)
6. Change the sort dropdown `.wcb-sort-select` (`actions.changeSort`) from "Newest first" to "Oldest first" →
   expect a re-fetch with the order reversed (first card changes). (`view.js:164`-`168`; URL builder maps
   `date_asc` → `orderby=date&order=ASC` `view.js:247`-`253`.)
7. REST contract (anonymous, public): `GET http://jobboard.local/wp-json/wcb/v1/companies?page=1&per_page=20&orderby=date&order=DESC`
   → expect HTTP 200, JSON `{ companies:[…], total:<int>, pages:<int>, has_more:<bool> }` and response headers
   `X-WCB-Total` + `X-WCB-TotalPages`; each `companies[]` item has `id, name, initials, permalink, industry,
   size_label, hq, job_count, jobs_label, trust, verified`. (`api/endpoints/class-companies-endpoint.php:35`-`45`,
   `prepare_item()` `:353`-`399`, envelope `build_companies_response()` `:330`-`342`.)
8. If `has_more` is true, click "Load more companies" `.wcb-load-more-btn` (`actions.loadMore`, wrapper
   `.wcb-load-more-wrap` shown via `state.hasMore`) → expect `page` to increment, the next page to append to the
   existing `.wcb-ca-card` list (no page reload), and the button to hide once `has_more` is false.
   (`view.js:170`-`195`; `templates/parts/archive-load-more.php`.)
9. Click a company card link `a.wcb-ca-card-link` (or its "View Profile" `.wcb-cbtn`) → expect navigation to
   `/companies/<slug>/` HTTP 200 rendering `div.wcb-company-profile.wcb-cp-wrap[data-wp-interactive="wcb-company-profile"]`
   with hero `.wcb-cp-name` (matching the card's company), meta chips `.wcb-cp-meta-chips`, and a "Company Details"
   section `.wcb-cp-details-grid`. (Single template `single_company_template()` `:180`-`190`; markup
   `blocks/company-profile/render.php:113`-`230`.)
10. On the profile, locate the "Open Positions" section (`section.wcb-cp-section` whose title is "Open Positions")
    → expect EITHER job cards `article.wcb-cp-job-card` each with a linked title `.wcb-cp-job-title a` + badges
    `.wcb-cjbadge` (type/location), OR the empty line `.wcb-cp-no-jobs` ("No open positions at the moment.").
    These jobs are this company's published `wcb_job` posts (queried by `_wcb_company_id` = company ID).
    (`render.php:296`-`385`.)
11. Click an open-position title `.wcb-cp-job-title a` (or "View Job" `.wcb-cp-job-apply`) → expect navigation to
    that job's single permalink HTTP 200 (a `wcb_job` single). If the company has > 10 jobs, first click
    "Load more jobs" `.wcb-load-more-btn` (`actions.loadMore`) → expect more `.wcb-cp-job-card` to append from
    `GET /wp-json/wcb/v1/jobs` (`jobsApiBase` `render.php:342`). (Open-positions loader `render.php:373`-`383`.)
12. As `sarah.chen`, open `http://jobboard.local/companies/?autologin=sarah.chen`, then on a company profile click
    the hero Save button `button.wcb-cp-hero-save` (`actions.toggleBookmark`) → expect a `POST` to
    `/wp-json/wcb/v1/companies/<id>/bookmark` returning `{ bookmarked:true, company_id:<id> }`, the button gains
    `.wcb-bookmarked` and its label flips to "Saved". Reload `/companies/` and confirm that company card renders
    pre-saved (archive seeds `bookmarked` from `_wcb_company_bookmark` usermeta). Click Save again → `bookmarked:false`,
    label reverts to "Save". (REST route `:47`-`57` + `toggle_bookmark()` `:143`-`164`; hero button
    `blocks/company-profile/render.php:130`-`144`, archive seed `render.php:90`-`93`,`147`.)
13. tail debug.log diff → expect ZERO new fatal/warning lines.

## Teardown

```bash
# The walkthrough only creates a bookmark for sarah.chen (step 12). If left toggled ON, clear it.
SARAH_ID=$(wp user get sarah.chen --field=ID --path="/Users/varundubey/Local Sites/jobboard/app/public")
wp user meta delete "$SARAH_ID" _wcb_company_bookmark --path="/Users/varundubey/Local Sites/jobboard/app/public"
# No companies/jobs are created by this journey (read-only browse); seeded sample data is left intact.
```

## Notes

- Customer-facing browse flow → lives in `audit/journeys/customer/`.
- Needs `seed:jobs` so at least one `wcb_company` with ≥1 published `wcb_job` (matched on `_wcb_company_id`) exists —
  otherwise step 10 only exercises the empty `.wcb-cp-no-jobs` branch and step 1's `.wcb-ca-jobs-count` reads
  "No open positions". The setup-wizard sample seeder creates 3 companies + 8 jobs (`admin/class-setup-wizard.php:492`).
- Steps 1-11 are fully anonymous (archive + profile are public; companies REST `permission_callback => '__return_true'`).
  Only step 12 (Save) requires login — the bookmark route gates on `is_user_logged_in()`.
- Grounded source files:
  - Archive URL / CPT slug / template: `modules/employers/class-employers-module.php:146`,`180`,`215`-`219`.
  - Archive block markup + selectors + state seed: `blocks/company-archive/render.php`.
  - Archive store (toggles/filters/search/sort/loadMore/bookmark + URL builder): `blocks/company-archive/view.js`.
  - Company profile markup + Open Positions query + Save button: `blocks/company-profile/render.php`.
  - Companies REST route + response shape + bookmark: `api/endpoints/class-companies-endpoint.php:35`-`164`,`330`-`399`.
  - Shared toolbar + load-more parts: `templates/parts/archive-toolbar.php`, `templates/parts/archive-load-more.php`.
