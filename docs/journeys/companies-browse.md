---
feature: blocks wp-career-board/company-archive + company-profile
roles: anonymous, candidate, employer, admin
surface: frontend blocks + REST (GET /companies, POST /companies/{id}/bookmark)
last_walked: 2026-06-26
---

# Companies browse + single — full browser walkthrough

**What it is:** The company directory and the public single-company profile. `company-archive` is the interactive directory (search, Industry + Company-size filters, grid/list, Load More, per-card bookmark); `company-profile` is the LinkedIn-style profile with hero, details, open positions, and a sidebar.
**Where it lives:** `/companies/` (directory) and `/companies/<slug>/` (CPT single); anywhere the blocks are placed.

## As anonymous
1. Navigate to `/companies/` → expect 200; a heading, the toolbar (search, **Sort companies** Newest/Oldest, grid/list switcher), a filter sidebar (**Industry** + **Company size**, both multi-select), and company cards.
2. Each card shows logo (or initials avatar), name + green verified tick (trust level), tagline, Industry/size/HQ chips, an open-positions count ("N open positions" / "No open positions"), and "View Profile".
3. Toggle an Industry checkbox → cards re-fetch via `GET /wcb/v1/companies`; the active filter ANDs with Company size; **Load more companies** appends until `found_posts` is exhausted.
4. Open a company → `/companies/<slug>/` → hero (cover, logo/initials, name + trust badge, tagline, Industry/size/HQ chips, Website/LinkedIn/Twitter links), About, Company Details `<dl>`, and **Open Positions** (paginated, Load more jobs). Sidebar shows Similar companies, Recent jobs, and a job-alert card.
5. Click a card's **bookmark** / the profile hero **Save** → the Save button only renders for logged-in users; anonymous sees no Save affordance.

## As candidate
1. `?autologin=wcb_demo_candidate` → `/companies/` → click a card **bookmark** → fills in (seeded from `_wcb_company_bookmark`); reload → still Saved.
2. Open the same company profile → the hero **Save** button shows **Saved** (state shared with the archive card via `POST /wcb/v1/companies/{id}/bookmark`).

## As employer
1. `?autologin=wcb_demo_employer` → open the employer's own company profile → `wcb_is_owner` is true; the public view renders the same, with profile editing handled separately (cross-check `employer-company-profile.md`).

## As admin
1. `?autologin=1` → `/companies/` → bookmark/Save work as for a candidate.
2. wp-admin → Companies → setting a company's trust level → reload the profile/card → the verified tick / trust badge (`check`, or `star` for premium) appears.

## Themes & states
- Reign, BuddyX light, **BuddyX dark** at 1440px + 390px. At 390px the directory sidebar collapses behind the `chevron-down` toggle; the profile body stacks main-over-sidebar. Card chips align at the same y-position regardless of tagline length (`grid-template-rows: auto 1fr auto`).
- Empty/edge: filters matching nothing → "No companies match your filters" card with **Clear filters**; a company with no open jobs → "No open positions at the moment."

## Contracts guarded
- REST↔JS: `GET /wcb/v1/companies` responses match the `wcb-company-archive` store; Load More stops at `found_posts` (no infinite empty pages).
- Store routing: company bookmark POSTs to `/companies/{id}/bookmark` via a distinct `jobsApiBase` key so the open-positions Load More doesn't clobber the bookmark route.
- a11y: bookmark/Save, switcher, and chip controls have `:focus-visible` rings and 40px targets; external links carry `rel="noopener noreferrer"`.
- Dark mode: trust tick/badge, chips, and sidebar cards stay readable on BuddyX dark.
