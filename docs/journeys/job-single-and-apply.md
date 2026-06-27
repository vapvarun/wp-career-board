---
feature: block wp-career-board/job-single + apply flow
roles: anonymous, candidate, employer, admin
surface: frontend block + REST (/jobs/{id}, /jobs/{id}/apply, /jobs/{id}/bookmark, /jobs/{id}/report)
last_walked: 2026-06-26
---

# Job single + apply — full browser walkthrough

**What it is:** The single job view — details, company, trust badges, bookmark, apply form, and report-a-job control.
**Where it lives:** `/jobs/<slug>/` (CPT single) and anywhere the `job-single` block is placed.

## As anonymous
1. Navigate to a published job at `/jobs/<slug>/` → expect 200, title, company, location, salary, description render.
2. Trust badges (verified company / featured) render as Lucide icons, not raw glyphs.
3. Click **Apply** → expect the gate prompts sign in / register (no application submitted while logged out).
4. Click **Report this job** → expect the reason select appears; submitting requires login (no anonymous flags).
5. `GET /wp-json/wcb/v1/jobs/{id}` in the network panel → response JSON contains **no** `apply_email` field (no inbox scraping).

## As candidate
1. `?autologin=wcb_demo_candidate` → open the same job.
2. Click the **bookmark** button → fills in; focus the button via keyboard → a visible focus ring shows. Reload → still bookmarked.
3. Click **Apply** → the apply form opens (cover note + resume); submit → expect a success state ("Application submitted") with a check icon, no page reload.
4. Re-open the job → the apply control now shows the **already-applied** state (no duplicate apply).
5. **Report this job** → pick a reason, submit → expect a confirmation; submitting the same job again is deduped (one flag per user).

## As employer
1. `?autologin=wcb_demo_employer` → open one of the employer's own jobs.
2. The owner sees the job but the apply form is not the primary action for their own listing (no self-apply expectation).
3. A new application from the candidate above appears under the employer dashboard → Applications (cross-check with `employer-applications.md`).

## As admin
1. `?autologin=1` → wp-admin → Jobs → the job's row shows status; the application is visible under Applications.
2. If the job was reported, the admin Jobs "Flagged" view lists it with a Flags count and Dismiss / Unpublish actions (cross-check with `moderation.md`).

## Themes & states
- Reign, BuddyX light, **BuddyX dark** at 1440px + 390px. In dark mode the success notice and any status badges stay readable (light text on dark tint, not light-on-light).
- Empty/edge: expired job → apply disabled with a clear message; job with no company → company block omitted cleanly.

## Contracts guarded
- REST↔JS: apply/bookmark responses match what `view.js` expects; results render (no silent failures).
- Security: `apply_email` never leaves the jobs REST response to anonymous.
- a11y: bookmark / apply / report controls have `:focus-visible` rings; 40px tap targets.
- Dark mode: success notice + badges readable under BuddyX/BuddyX Pro dark (the 1.5.0 fix).
