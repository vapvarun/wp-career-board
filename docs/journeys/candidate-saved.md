---
feature: candidate saved / bookmarks (MY SAVES group)
roles: anonymous, candidate, employer, admin
surface: frontend block (dashboard MY SAVES tabs) + REST (/jobs/{id}/bookmark, /candidates/{id}/bookmarks, company + resume bookmark)
last_walked: 2026-06-26
---

# Candidate saved / bookmarks — full browser walkthrough

**What it is:** The three "My Saves" lists any logged-in user can build — **Saved Jobs**, **Saved Companies**, **Saved Resumes** — surfaced as a nav group inside both dashboards.
**Where it lives:** `/candidate-dashboard/` → MY SAVES nav (mirrored in `/employer-dashboard/`). Stored as `_wcb_bookmark` (jobs), `_wcb_company_bookmark`, `_wcb_resume_bookmark` user-meta.

## As anonymous
1. On any job / company, the bookmark control prompts sign-in — the REST gate is `is_user_logged_in()`, so no anonymous bookmarks are written.

## As candidate
1. `?autologin=wcb_demo_candidate` → bookmark a job from a listing or `/jobs/<slug>/` → `POST /jobs/{id}/bookmark` fills the control; reload → still saved.
2. `/candidate-dashboard/` → **Saved Jobs** tab → the bookmarked job appears with company · location · type meta, a **View Job** link (new tab), and **Remove** (`unbookmark` → list + nav badge decrement, no reload).
3. **Saved Companies** tab → companies you bookmarked, each with industry · HQ, **View Profile**, and **Remove**. Empty → "No saved companies yet." + **Browse Companies** CTA.
4. **Saved Resumes** tab → only present when the `wcb_resume` CPT is registered (Pro); shows saved candidate resumes with role · location, **View Resume**, **Remove**. Empty → **Browse Candidates** CTA.
5. Each MY SAVES nav item carries a count **badge** seeded server-side from the meta arrays (no zero-flash) and updated live as you remove items.

## As employer
1. `?autologin=wcb_demo_employer` → `/employer-dashboard/` → the **identical** MY SAVES group (Saved Jobs / Saved Companies / Saved Resumes) appears — bookmarking is per-user, not per-role, so an employer can save jobs, companies, and resumes too.

## As admin
1. `?autologin=1` → either dashboard → MY SAVES renders; admin can exercise all three lists for QA.

## Themes & states
- Reign, BuddyX light, **BuddyX dark** at 1440px + 390px. Each tab has its own loading (spinner), error (`role="alert"`), and empty state with a relevant browse CTA.
- Saved Resumes tab is omitted entirely on Free-only installs (CPT not registered) — not shown-then-broken.

## Contracts guarded
- Permission: bookmark writes gated on login only (not a role cap) — candidate, employer, and admin all share the feature.
- Counts: seed badges (`savedJobsCount` / `savedCompaniesCount` / `savedResumesCount`) match the `_wcb_*_bookmark` meta-array lengths; live decrement on remove.
- Dependency gating: the Saved Resumes tab + its REST call are suppressed when `wcb_resume` isn't registered (the 402 the audit caught).
- a11y: remove buttons have focus rings + 40px tap targets; lists are `aria-live="polite"`.
- REST↔JS: bookmark/unbookmark responses match `view.js`; all fetches use `@wcb/fetch` (15s timeout).
