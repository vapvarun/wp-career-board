---
feature: blocks wp-career-board/featured-jobs + recent-jobs + job-stats
roles: anonymous, candidate, employer, admin
surface: frontend blocks (static SSR, no Interactivity)
last_walked: 2026-06-26
---

# Featured / recent / stats showcase blocks — full browser walkthrough

**What it is:** Three static server-rendered showcase blocks. `featured-jobs` is a grid of jobs flagged `_wcb_featured`; `recent-jobs` is a sidebar list of the newest jobs; `job-stats` is a counter strip (Jobs / Companies / Candidates).
**Where it lives:** Typically the home/landing page and sidebars; also auto-injected into the company-profile sidebar (`recent-jobs`).

## As anonymous
1. Navigate to a page carrying the blocks → expect:
   - **Featured Jobs** — a heading (custom title or "Featured Jobs") and up to `perPage` (default 3) cards, each with job title link, company name, location, and a "View Job" link; a "View all jobs →" link when a target URL resolves.
   - **Recent Jobs** — "Recent Jobs" heading + "View all →"; a list of items each showing the company logo (or initial avatar), job title, company, location + type badges, and "<time> ago".
   - **Job Stats** — a strip of counts with Lucide icons: `briefcase` Jobs, `building-2` Companies, `users` Candidates (each toggleable via block attributes); counts use `number_format_i18n`.
2. Click a featured/recent card → navigates to the job single at `/jobs/<slug>/`.
3. Stats reflect only `publish` counts of `wcb_job` / `wcb_company` / `wcb_resume`.

## As candidate / employer
1. `?autologin=wcb_demo_candidate` / `?autologin=wcb_demo_employer` → blocks are read-only showcases; rendering is identical for every logged-in role (no role-specific controls).

## As admin
1. `?autologin=1` → on a site with no featured jobs, the **Featured Jobs** block shows an editor-only empty hint ("No featured jobs to display. Mark jobs as featured in the editor.") with an `inbox` icon — anonymous visitors see nothing rather than an empty shell.
2. The **Recent Jobs** empty hint ("No recent jobs to display.") is likewise editor-only.
3. wp-admin → Jobs → toggle a job's Featured flag → reload the page → it appears/disappears from the Featured grid.

## Themes & states
- Reign, BuddyX light, **BuddyX dark** at 1440px + 390px. At 390px the featured grid collapses to one column; the stat strip wraps; the recent-jobs list stays single-column.
- Empty/edge: zero published content → `job-stats` renders nothing (returns early when no stats are enabled); featured/recent show the editor-only hint described above.

## Contracts guarded
- These are pure SSR blocks — no REST, no Interactivity store; no `view.js` to drift.
- "View all" targets resolve to the configured `jobs_archive_page`, omitted cleanly when unset.
- Grid tracks use `minmax(0,1fr)`; logos use fixed `width/height` + `loading="lazy"` (no layout shift / N+1 — author thumbnails are pre-fetched).
- Dark mode: card titles, company/location text, badges, and stat counts stay readable on BuddyX dark.
