---
feature: block wp-career-board/job-filters (+ job-listings filter sidebar)
roles: anonymous, candidate, employer, admin
surface: frontend block (Interactivity) → drives the job-listings store
last_walked: 2026-06-26
---

# Job filters — full browser walkthrough

**What it is:** Faceted job filtering. The standalone `job-filters` block renders a row of selects (Category, Job Type, Location, Experience) plus min/max salary inputs and a "Remote only" checkbox; the same facets also render as the reorderable sidebar groups inside `job-listings`.
**Where it lives:** `job-filters` placed alongside `job-listings` on `/find-jobs/`; the built-in sidebar lives in the `job-listings` block itself.

## As anonymous
1. Navigate to `/find-jobs/` → expect the filter controls. Standalone block: **All Categories**, **All Types**, **All Locations**, **All Experience Levels** selects, Min/Max salary number inputs, and a **Remote only** checkbox.
2. Change the Category select → the `wcb-search` store updates `state.filters.wcb_category` → listings re-fetch; the selection survives a reload (seeded from the `wcb_category` GET param).
3. In the `job-listings` sidebar, the same facets render as grouped checkbox lists: Job type, Experience, Category, Tags, Location (Remote only), Job board (when seeded), and a Salary range slider (min/max, with a **Reset**). The slider tooltip shows the site default currency symbol, not a hardcoded `$`.
4. Toggle several checkboxes across groups → multi-select ORs within a group, ANDs across groups; an active-filter chip row appears with removable `×` pills and **Clear all** (which wipes only user filters, never a shortcode-baked `boardId`/`metaFilter` scope).
5. Set a salary min above every job → listings show the empty-state card with **Clear filters**.

## As candidate
1. `?autologin=wcb_demo_candidate` → filtering behaves identically; bookmarking the filtered results works (cross-check `browse-job-listings.md`).

## As employer / admin
1. `?autologin=wcb_demo_employer` / `?autologin=1` → filters are a public read surface; behavior is identical for every role.

## As admin (editor — reorder/hide groups)
1. `?autologin=1` → edit the page with the `job-listings` block → the sidebar group **order** (`filterOrder`) and **hidden groups** (`hiddenFilters`) are block attributes; reorder/hide in the editor, save, reload front end → sidebar reflects the new order; a group newly shipped in a later version still appears even on an older saved block.

## Themes & states
- Reign, BuddyX light, **BuddyX dark** at 1440px + 390px. At 390px the sidebar collapses behind the `chevron-down` toggle; the standalone filter row wraps without overflow.
- Empty/edge: a taxonomy with no terms renders no group (empty buffers are skipped, not blank headings).

## Contracts guarded
- Store ownership: `job-filters` writes only `state.filters`; `job-search` owns `state.query` — same `wcb-search` namespace, no clobber.
- Scope integrity: **Clear all** never touches `baseFilters` (board/meta scope baked by the integrator).
- a11y: every select/input has an `aria-label`; checkboxes and chip-remove buttons have `:focus-visible` rings and 40px targets.
- Dark mode: select text, salary slider track/tooltip, and chips stay readable on BuddyX dark.
