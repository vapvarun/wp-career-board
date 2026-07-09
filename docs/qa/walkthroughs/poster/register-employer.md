---
id: poster-register-employer
priority: high
personas: employer.figma
requires: mu:autologin, setting:users_can_register
last_verified: 2026-07-08
covers: blocks/employer-registration (wcb/employer-registration), POST /wcb/v1/employers/register, employer-dashboard page, role wcb_employer
---

# Walkthrough: Register as an Employer — pick the "Hire Talent" role, create the account, land on the employer dashboard

**Why this journey exists:** This is NEW coverage — the "Register as an employer" row in `docs/qa/COMMON_USE_CASES.md` (Job Poster → Tier 1) previously had ⚠ *no dedicated journey*. It traces the full anonymous-visitor path: open the `wcb/employer-registration` block, choose the Employer role, fill name/email/company/password, submit to `POST /wcb/v1/employers/register`, and confirm the server creates a `wcb_employer` user + `wcb_company`, logs them in, and returns the dashboard URL the block redirects to. Guards the role-assignment contract (`set_role('wcb_employer')`, no subscriber residue) and the auto-login-into-dashboard handoff.

## Steps

1. As an anonymous visitor (open a fresh/incognito session — do NOT autologin), navigate to the page that embeds the registration block, e.g. `http://jobboard.local/register/` → expect HTTP 200 and the wrapper `.wcb-employer-reg` with `data-wp-interactive="wcb-employer-registration"`, showing the role picker `.wcb-role-picker` with heading "Join WP Career Board". (NOT the `.wcb-employer-reg--logged-in` notice — that renders only when the current user already holds `wcb_employer`/`wcb_candidate`, `blocks/employer-registration/render.php:24-46`.)

2. Click the "Hire Talent" role card `.wcb-role-card[data-wp-on--click="actions.selectEmployer"]` (`blocks/employer-registration/render.php:120-124`) → expect the role picker to hide and the form step to show (`state.role === 'employer'`, driven by `data-wp-class--wcb-hidden="!state.role"` at render.php:129), the title `[data-wp-text="state.roleTitle"]` reading "Create an Employer Account" (view.js `roleTitle` getter, `blocks/employer-registration/view.js:23-27`), and the Company Name / Website / Industry / Size / HQ fields becoming visible (they carry `data-wp-class--wcb-hidden="state.isCandidate"`, render.php:199-246).

3. Fill the credential fields — type into `#wcb-reg-first-name` (`actions.updateFirstName`) e.g. `Fiona`, `#wcb-reg-last-name` (`actions.updateLastName`) e.g. `Figma`, `#wcb-reg-email` (`actions.updateEmail`, label "Work Email" via `state.emailLabel`) e.g. `fiona.qa+employer@example.test`, and `#wcb-reg-password` (`actions.updatePassword`) a value of at least 8 chars (`render.php:147-262`) → expect each bound `state.*` populated and `#wcb-hp-reg` honeypot (render.php:145) left empty.

4. Fill `#wcb-reg-company` (`actions.updateCompanyName`) e.g. `QA Hiring Co` and optionally `#wcb-reg-website` / `#wcb-reg-industry` / `#wcb-reg-size` / `#wcb-reg-hq` (all via `actions.updateField` + `data-wcb-field`, render.php:211-246) → expect `state.companyName` set. Leave the honeypot blank.

5. Submit the form `.wcb-reg-form[data-wp-on--submit="actions.submit"]` via the "Create Account" button (render.php:143, 267-274) → expect a single `POST http://jobboard.local/wp-json/wcb/v1/employers/register` carrying header `X-WP-Nonce` and JSON body `{ first_name:"Fiona", last_name:"Figma", email:"fiona.qa+employer@example.test", password:"…", company_name:"QA Hiring Co", website?, industry?, size?, hq? }` (body assembled in `view.js:86-105` — the employer branch appends `company_name` + optional company fields).

6. Observe the response → expect HTTP 200 with body `{ user_id:<int>, company_id:<int>, dashboard_url:"http://jobboard.local/employer-dashboard/" }` (`api/endpoints/class-employers-endpoint.php:357-363`). The route is `POST /employers/register`, `permission_callback => '__return_true'` (register.php:42-48). (Precondition: `users_can_register` must be ON — otherwise step 5 returns HTTP 403 `wcb_registration_disabled`, class-employers-endpoint.php:234-240. A duplicate email returns 409 `wcb_email_exists` :273-279; a password < 8 chars returns 400 `wcb_weak_password` :281-287.)

7. Expect the block to swap to its success state: `.wcb-reg-success` loses `.wcb-hidden` (`data-wp-class--wcb-hidden="!state.submitted"`, render.php:92) showing "Account created!" and the employer copy "You are now logged in as an employer…" (render.php:93-96), and the `.wcb-btn--primary[data-wp-bind--href="state.dashboardUrl"]` "Go to Dashboard →" link points at `state.dashboardUrl` (view.js:127-128 reads `data.dashboard_url`).

8. Assert role assignment in the DB: `wp user get fiona.figma --field=roles` → expect exactly `wcb_employer` (single role — `set_role('wcb_employer')` at class-employers-endpoint.php:305 / :190 uses replace semantics, so there is NO leftover `subscriber`). Also `wp user meta get <user_id> _wcb_company_id` → expect the `company_id` from step 6, and `wp post get <company_id> --field=post_type` → expect `wcb_company` (created publish, authored by the new user, class-employers-endpoint.php:318-328).

9. Assert the new employer is authenticated and can reach the dashboard: the register callback calls `wp_set_auth_cookie()` (class-employers-endpoint.php:346-347), so click "Go to Dashboard →" (or navigate to `http://jobboard.local/employer-dashboard/`) → expect HTTP 200 and the employer-dashboard shell renders (tabs `#wcb-tab-jobs` "My Jobs" and `#wcb-tab-postjob` "Post a Job" present, `blocks/employer-dashboard/render.php:327-332`) — NOT a "sign in as an employer" gate.

10. Confirm the `wcb_employer_registered` side-effect fired: the action `do_action( 'wcb_employer_registered', $user_id, $company_id )` runs at class-employers-endpoint.php:350 — verify no PHP notice/warning was emitted by any listener (checked in step 11).

11. tail `wp-content/debug.log` diff over the whole run → expect ZERO new fatal/warning lines.

## Teardown

```bash
# Safe + re-runnable: remove the QA employer account (reassigning nothing) and its company.
# Resolve ids by the deterministic email/username this walkthrough uses.
UID=$(wp user get fiona.qa+employer@example.test --field=ID 2>/dev/null || echo "")
if [ -n "$UID" ]; then
  CID=$(wp user meta get "$UID" _wcb_company_id 2>/dev/null || echo "")
  [ -n "$CID" ] && wp post delete "$CID" --force
  wp user delete "$UID" --yes --reassign=1
fi
```

## Notes

- **NEW COVERAGE (fills a ⚠ catalog gap)** — before this file the "Register as an employer" use case had no dedicated journey and was walked by hand only. The regression sentinel still lives (or should be added) under `audit/journeys/`; this file is the human-runnable *how*.
- **Response is HTTP 200, not 201** — the register callback returns via `rest_ensure_response()` (class-employers-endpoint.php:357), unlike `POST /jobs` which returns 201. Do not assert 201 here.
- **Two branches in `register_employer()`** — a *logged-in* user with no WCB role is promote-only (empty body, class-employers-endpoint.php:157-233); this walkthrough exercises the *anonymous* branch (:234-363) which needs `users_can_register` ON. If your environment has open registration disabled, either enable it (`wp option update users_can_register 1`) or run the logged-in promote branch instead (autologin a role-less user, submit an empty body).
- **Username derivation** — the server builds `user_login` from `first_name.last_name` lowercased (`fiona.figma`), appending a random 100–999 suffix on collision (class-employers-endpoint.php:289-295). If `fiona.figma` already exists the teardown email lookup still resolves the right account.
- **Candidate mirror** — the same block registers candidates via `/candidates/register` when the "Find a Job" card is chosen (`view.js:86-88`); that path is covered by `customer/candidate-register`.
- Page slug `register` is the conventional host for this block; confirm on the target site with `wp post list --post_type=page --field=post_name`. The dashboard slug is `employer-dashboard` (resolved from the `employer_dashboard_page` setting, class-employers-endpoint.php:352).
