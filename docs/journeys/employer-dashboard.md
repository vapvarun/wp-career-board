---
feature: block wp-career-board/employer-dashboard
roles: anonymous, candidate, employer, admin
surface: frontend block + REST (/employers/{id}, /employers/{id}/applications, /jobs, /jobs/{id}, /applications/{id}/status)
last_walked: 2026-06-26
---

# Employer dashboard — full browser walkthrough

**What it is:** The employer's control center — overview stats, My Jobs, Post a Job, Applications (list + Kanban board), Company Profile, Credits (Pro), with an onboarding nudge for new accounts.
**Where it lives:** `/employer-dashboard/` (the `employer-dashboard` block on the page set as `employer_dashboard_page`).

## As anonymous
1. Navigate to `/employer-dashboard/` → expect the **sign-in gate** ("Please sign in to access your dashboard.") with a Sign In button. No dashboard markup leaks.

## As candidate
1. `?autologin=wcb_demo_candidate` → `/employer-dashboard/` → lacks `wcb/manage-company`, so the **wrong-role gate** shows: "The employer dashboard is for employers…" with **Register as an employer** and **Go to Candidate Dashboard** buttons (when those pages are configured).

## As employer
1. `?autologin=wcb_demo_employer` → `/employer-dashboard/` → **Overview** tab. Stats row (Total Jobs, Published, Applications, New this week) is SSR-seeded from the employer's own jobs/apps so numbers show before hydration.
2. **Onboarding banner** — if the employer has no company yet (`noCompany`), a "Welcome! Let's get you set up." card with **Set Up Company Profile**; once a company exists but no jobs, it flips to "Your company is ready." + **Post Your First Job**.
3. Left panel → **My Jobs**: list of own jobs with filter/search; empty state offers Set Up Company / Post First Job. Close-a-job uses the shared confirm-modal.
4. **Post a Job** (sidebar CTA + JOBS group) → embeds the job-form; on submit it flags `_needsJobsRefresh` so My Jobs reloads (cross-check `employer-post-job.md`).
5. **Applications** (HIRING group) → received applications, switchable between **list** (split panel) and **board** (Kanban drag-and-drop by status); changing status shows "Status updated. The candidate has been notified." (cross-check `employer-applications.md`).
6. **Company Profile** (COMPANY group) → edit name, tagline, website, industry, size, HQ, founded, socials + logo upload; save persists to company post-meta.
7. **Credits** (CREDITS group, Pro only) → nav badge shows balance + **Buy Credits ↗**; Overview shows a Credits stat card, a success banner after `?wcb_credits_added=N`, and a low-balance banner under threshold. On Free the whole group is absent.
8. **MY SAVES** (Saved Jobs / Companies / Resumes) + **ACCOUNT** (Settings, Notifications) groups — same as the candidate dashboard.

## As admin
1. `?autologin=1` → `/employer-dashboard/` → admin holds every `wcb_*` ability, so the dashboard renders fully for QA.

## Themes & states
- Reign, BuddyX light, **BuddyX dark** at 1440px + 390px. At ≤640px the sidebar collapses behind the nav-toggle; stat cards, Kanban columns, and credit banners stay readable in dark mode.
- Loading / error / empty states per tab; onboarding + credit banners are conditional, never empty shells.

## Contracts guarded
- Render gate: anonymous → sign-in; wrong role → helpful redirect gate (not a blank or a 403 page).
- Left panel layout is the **shared sidebar shell** — identical structure to the candidate dashboard; only the section labels (JOBS / HIRING / COMPANY / CREDITS / MY SAVES / ACCOUNT) and items differ.
- Pro gating: the entire CREDITS group + Credits stat/banners render only when `wcb_credits_enabled` is true — no dead UI on Free.
- a11y: `role="tablist"` nav, `role="tabpanel"` panels, `aria-selected`; Kanban drag targets + buttons have focus rings + 40px tap targets.
- REST↔JS: jobs/applications/status responses match `view.js`; fetches route through `@wcb/fetch` (15s timeout).
