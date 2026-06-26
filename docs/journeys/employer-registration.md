---
feature: block wp-career-board/employer-registration
roles: anonymous, logged-in (no role), candidate, employer, admin
surface: frontend block + REST (POST /employers/register, POST /candidates/register)
last_walked: 2026-06-26
---

# Employer registration — full browser walkthrough

**What it is:** A unified sign-up block with a role picker — "Find a Job" (candidate) or "Hire Talent" (employer) — that creates the account/role and drops the user on the matching dashboard. Doubles as the upgrade path for a logged-in roleless user.
**Where it lives:** `/employer-registration/` (the `employer-registration` block on the page set as `employer_registration_page`).

## As anonymous
1. Navigate to `/employer-registration/` → **Step 1 role picker**: two cards, **Find a Job** (briefcase) and **Hire Talent** (building) + a "Sign in" link for existing users.
2. Pick **Hire Talent** → **Step 2 form**: First/Last name, email, password (min 8), plus employer-only fields — Company Name (required), Website, Industry, Company Size, Headquarters. A hidden honeypot (`wcb_hp_reg`) guards against bots. Back arrow returns to the picker.
3. Submit → `POST /employers/register` creates the user + `wcb_employer` role and logs them in → **Step 3 success**: "Account created! You are now logged in as an employer…" + **Go to Dashboard →** to `/employer-dashboard/`.
4. Pick **Find a Job** instead → the company fields hide (`isCandidate`), email label adjusts; submit → `POST /candidates/register` → success points at `/candidate-dashboard/`.

## As logged-in user without a WCB role (the "becomes an employer" path)
1. As a plain subscriber, open `/employer-registration/` → the picker still shows (no role yet). The form shows "Continuing as <name>. Your account will be linked to this role." and **hides** the name/email/password fields (account already exists) — only role + (for employer) company fields remain.
2. Pick **Hire Talent**, fill company name, submit → the existing account gains the `wcb_employer` role and is sent to `/employer-dashboard/` — a logged-in user has become an employer without a second account.

## As candidate / employer (already has a role)
1. `?autologin=wcb_demo_employer` (or `wcb_demo_candidate`) → `/employer-registration/` → no form; a contextual notice ("You're registered as an employer.") with a **Go to your dashboard →** link. No double-registration possible.

## As admin
1. `?autologin=1` → the block renders the same already-registered notice path (admin is not re-registered).

## Themes & states
- Reign, BuddyX light, **BuddyX dark** at 1440px + 390px. Role cards, form inputs, and the submit button (default/hover/focus/active) stay legible in dark mode; the form stacks cleanly at 390px.
- Submitting state ("Creating account…") disables the button; error line is `role="alert"`.

## Contracts guarded
- The only two write endpoints with `permission_callback => '__return_true'` (`/employers/register`, `/candidates/register`) — both anti-spam gated (`wcb_pre_*_submit`) and honeypot-guarded.
- Role picker → correct CPT/role + correct dashboard redirect (employer vs candidate) via the REST `dashboardUrl`.
- Idempotency: existing-role users get a notice, not a re-register form; roleless logged-in users get the link-account flow (no duplicate account).
- a11y: role cards have aria-labels + focus rings; required fields use `aria-required`; honeypot is `aria-hidden` and off-tab.
