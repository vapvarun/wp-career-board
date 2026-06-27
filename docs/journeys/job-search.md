---
feature: blocks wp-career-board/job-search + job-search-hero
roles: anonymous, candidate, employer, admin
surface: frontend blocks (Interactivity inline search + GET-form hero) → job-listings results
last_walked: 2026-06-26
---

# Job search + hero — full browser walkthrough

**What it is:** Two search entry points. `job-search` is the inline reactive search field that drives the listings store in place; `job-search-hero` is a landing component (keyword + optional Category/Location/Job-type selects) that submits a GET form to the jobs page.
**Where it lives:** `job-search` sits above `job-listings` on `/find-jobs/`; `job-search-hero` is placed on the home/landing page and posts to the configured jobs archive (falls back to the `wcb_job` CPT archive when no page is set).

## As anonymous
1. Navigate to the landing page with the hero → expect the keyword input (`wcb_search`) plus any enabled selects: **All Categories** (`wcb_category`), **All Locations** (`wcb_location`), **All Types** (`wcb_job_type`); selects only render when terms exist.
2. Type a keyword, pick a category, click **Search** → GET-form navigates to `/find-jobs/?wcb_search=…&wcb_category=…`; the listings block reads those params and renders the matching, pre-filtered set (no flash of unfiltered jobs).
3. On `/find-jobs/`, use the inline `job-search` field → type a term and submit → results re-fetch in place via the `wcb-search` store (no full navigation); the field value persists from the `wcb_search` query param on reload.
4. Submit an empty hero search → lands on the jobs archive showing all jobs (no error).

## As candidate
1. `?autologin=wcb_demo_candidate` → repeat the hero + inline search → identical behavior; bookmarking on the resulting cards now works (cross-check `browse-job-listings.md`).

## As employer
1. `?autologin=wcb_demo_employer` → search works identically; search is a public read surface for every role.

## As admin
1. `?autologin=1` → confirm the hero's configured submit target resolves to the Find Jobs page; if the `jobs_archive_page` setting is unset, the form action falls back to `/jobs/` rather than the site home.

## Themes & states
- Reign, BuddyX light, **BuddyX dark** at 1440px + 390px. At 390px the hero stacks input → selects → button; the inline search field and its Search button share one height (the Reign/BuddyX button-metric alignment fix) and never fuse into one slab.
- Empty/edge: a keyword matching nothing routes to the listings empty-state card, not a blank page.

## Contracts guarded
- Param contract: hero emits `wcb_search` / `wcb_category` / `wcb_location` / `wcb_job_type`; both `job-listings` and `job-filters` consume the same keys.
- Namespace split: `job-search` owns `state.query`, `job-filters` owns `state.filters` in the shared `wcb-search` store — neither clobbers the other.
- a11y: every field has a `screen-reader-text` label; Search buttons have `:focus-visible` rings and 40px tap targets.
- Dark mode: hero input/select text and placeholders stay readable on BuddyX dark.
