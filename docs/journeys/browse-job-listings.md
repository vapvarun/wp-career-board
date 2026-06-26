---
feature: block wp-career-board/job-listings
roles: anonymous, candidate, employer, admin
surface: frontend block + REST (GET /jobs, POST /jobs/{id}/bookmark)
last_walked: 2026-06-26
---

# Browse job listings — full browser walkthrough

**What it is:** The reactive jobs archive — server-rendered cards, a filter sidebar, sort, view-switch (grid/list), Load More pagination, and per-card bookmark.
**Where it lives:** `/find-jobs/` (the configured jobs page) and the `wcb_job` CPT archive at `/jobs/`; anywhere the `job-listings` block is placed.

## As anonymous
1. Navigate to `/find-jobs/` → expect 200; featured jobs render first, then newest. Each card shows title, company + green verified tick (where the company has a trust level), badges (Featured / Remote / type / experience / location with a `map-pin` icon), salary, deadline, and a "View Job" ghost button.
2. Toolbar shows the result count, the **Sort jobs** select (Newest first / Oldest first), and the **View layout** switcher → click the list/grid toggle → cards reflow without a page reload.
3. Filter sidebar is present (Job type, Experience, Category, Tags, Location/Remote only, Salary range, Job board where seeded). Toggle a checkbox → list re-fetches via `GET /wcb/v1/jobs`; an active-filter chip row appears with removable `×` pills + **Clear all**.
4. Scroll down → **Load more jobs** shows only when `totalCount` exceeds what's painted; click → next page appends.
5. Click a card's **bookmark** button → expect the sign-in/register gate (anonymous cannot bookmark; no POST fires).

## As candidate
1. `?autologin=wcb_demo_candidate` → `/find-jobs/`.
2. Click a card **bookmark** → fills in (Saved); keyboard-focus the button → visible focus ring. Reload → still Saved (state seeded from `_wcb_bookmark` user meta).
3. Apply a Job-type + Salary filter together → results AND across both; remove one pill → list re-fetches with the remaining scope intact.
4. Switch sort to **Oldest first** while a filter is active → order changes, filter persists.

## As employer
1. `?autologin=wcb_demo_employer` → `/find-jobs/` → browses identically; employers have no `wcb_bookmark_jobs` ability, so the bookmark control gates like anonymous.

## As admin
1. `?autologin=1` → `/find-jobs/` → admin holds every `wcb_*` ability, so bookmark works as for a candidate.
2. wp-admin → Jobs → confirms the same published jobs back the archive; an unpublished/expired job drops out of the listing.

## Themes & states
- Reign, BuddyX light, **BuddyX dark** at 1440px + 390px. At 390px the filter sidebar collapses behind the `chevron-down` toggle; the search/sort/switcher toolbar stacks cleanly.
- Empty state: filters matching nothing → self-contained "No jobs match your filters" card with a **Clear filters** CTA (only shown when filters are active).

## Contracts guarded
- REST↔JS: `GET /wcb/v1/jobs` page/filter responses match the `wcb-job-listings` store shape; Load More stops at `totalCount` (no infinite empty pages).
- Grid tracks use `minmax(0,1fr)` so cards never overflow siblings off-viewport.
- a11y: bookmark / chip-remove / switcher controls have `:focus-visible` rings and 40px tap targets.
- Dark mode: trust tick, badges, and active-filter chips stay readable on BuddyX dark.
