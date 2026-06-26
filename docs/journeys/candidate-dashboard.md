---
feature: block wp-career-board/candidate-dashboard
roles: anonymous, candidate, admin
surface: frontend block + REST (/candidates/{id}, /candidates/{id}/applications, /candidates/{id}/bookmarks, /jobs/{id}/bookmark)
last_walked: 2026-06-26
---

# Candidate dashboard — full browser walkthrough

**What it is:** The candidate's home base — overview stats, recent applications, recommended jobs, applications list, profile + account settings, all behind a left-panel nav.
**Where it lives:** `/candidate-dashboard/` (the `candidate-dashboard` block on the page set as `candidate_dashboard_page`).

## As anonymous
1. Navigate to `/candidate-dashboard/` → expect the **sign-in gate** ("Please sign in to access your candidate dashboard.") with a **Sign In** button linking to `wp_login_url` with the dashboard as redirect. No dashboard markup leaks.

## As candidate
1. `?autologin=wcb_demo_candidate` → `/candidate-dashboard/` → lands on the **Overview** tab (the logo/"Dashboard" item is active).
2. Overview shows the **stats row** (Applications, Shortlisted, Saved Jobs, My Resumes) seeded server-side so there's no zero-flash before `view.js` hydrates; then two panels — **Recent Applications** + **Saved Jobs** — each with a "View all →" link that switches tabs (no reload).
3. First-time candidate (0 apps / 0 saved / 0 resumes) → the **Welcome card** ("Welcome, <name> 👋") with numbered get-started steps shows; it hides once any activity exists.
4. If AI matching is available, a **Recommended for you** grid renders ("AI-matched to your resume"); on Free it stays hidden cleanly.
5. Left panel → **My Applications**: list of applied jobs with status badges; each row links to the job (new tab) and offers **Withdraw** (live apps, gated by `allowWithdraw`) or **Remove** (for "job no longer available" rows). Withdraw opens the shared confirm-modal.
6. Empty applications → "You haven't applied to any jobs yet." + **Browse Jobs** CTA.
7. **Profile** tab → edit bio / phone / location (sourced from `_wcb_resume_data`); save shows a saved state.
8. **Settings** tab → Account Settings (display name, email) + Change Password; plus a GDPR **Delete your account** flow (sends a confirmation email).

## As admin
1. `?autologin=1` → `/candidate-dashboard/` → the `administrator` role holds `wcb/access-candidate-dashboard`, so the dashboard renders (admin is treated as a super-candidate for QA).

## Themes & states
- Reign, BuddyX light, **BuddyX dark** at 1440px + 390px. At ≤640px the left panel collapses behind the **nav-toggle** button (shows the active tab label); status badges + stat cards stay readable in dark mode.
- Loading (spinner + "Loading…"), error (`role="alert"`), and empty states per tab.

## Contracts guarded
- Render gate: anonymous → sign-in gate; logged-in without ability → "no permission" line (no silent blank).
- Left panel layout is the **shared sidebar shell** (`.wcb-sidebar` / `.wcb-sidebar-nav` / `.wcb-nav-section-label` / `.wcb-nav-item` / `.wcb-sidebar-cta` / `.wcb-sidebar-user`) — byte-for-byte the same structure as the employer dashboard; only the section labels (MY ACTIVITY / MY SAVES / ACCOUNT) and items differ.
- Free-only gating: My Resumes / Job Alerts / Notifications-bell items only appear when the matching Pro filter is true — no dead nav items.
- a11y: nav is `role="tablist"`, items `role="tab"` with `aria-selected`; tab panels `role="tabpanel"`; withdraw/remove buttons have focus rings + 40px tap targets.
- REST↔JS: applications/bookmarks/recommendations responses match what `view.js` renders; fetches route through `@wcb/fetch` (15s timeout, no hang).
