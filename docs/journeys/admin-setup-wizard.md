---
feature: admin page — Setup Wizard (create pages, seed/remove sample data, complete)
roles: admin
surface: admin page (wcb-setup-wizard) + REST (/wizard/*)
last_walked: 2026-06-26
---

# Setup wizard — full browser walkthrough

**What it is:** A first-run, step-based wizard that creates the app pages, optionally seeds sample data, and marks setup complete. Steps are REST-driven (no admin-ajax).
**Where it lives:** `wp-admin/admin.php?page=wcb-setup-wizard` (cap `wcb_manage_settings`). On a fresh install `maybe_redirect()` lands the admin here. Steps come from the `wcb_wizard_steps` filter (Pro appends its own).

## As admin
1. `?autologin=1` → `wp-admin/admin.php?page=wcb-setup-wizard` → expect the wizard shell with a step indicator (Create Pages → Sample Data) and a primary button per step.
2. **Step 1 — Create Pages:** click the primary button → `POST /wcb/v1/wizard/create-pages` → the app pages (jobs archive, dashboards, post-job, registration, company archive) are created and wired into `wcb_page_settings`; re-running is idempotent (existing pages are reused, not duplicated). Expect a success state and advance to step 2.
3. **Step 2 — Sample Data:** click **Install sample data** → `POST /wcb/v1/wizard/sample-data` seeds demo jobs/companies/candidate + employer users; created IDs are tracked in `wcb_sample_data_ids` and `wcb_sample_data_installed` flips true. Re-running re-tracks existing IDs (no duplicate seed).
4. **Remove sample data:** the same step offers a remove action → `POST /wcb/v1/wizard/remove-sample-data` deletes exactly the tracked IDs and clears the flag — leaving real content untouched.
5. **Finish:** click Complete → `POST /wcb/v1/wizard/complete` sets `wcb_setup_complete=true`, fires `wcb_wizard_completed`, and returns a redirect to the Career Board dashboard (`admin.php?page=wp-career-board`).
6. Revisit `?page=wcb-setup-wizard` after completion → it no longer force-renders (guarded by `is_setup_complete()`), unless an add-on forces it via `wcb_wizard_force_render`.

## As candidate / employer
- No access — all `/wizard/*` routes use `wizard_permission_check` (`wcb_manage_settings`); non-admins get a 403.

## Themes & states
- Admin-only — 1440px + 390px; the step shell and buttons stack cleanly on mobile.
- Idempotency states: "already created" / "already installed" messaging on re-run, never silent duplicates.
- Error: a REST failure surfaces an inline error on the step, not a blank advance.

## Contracts guarded
- Sample-data round-trip: seed tracks IDs; remove deletes only those tracked IDs → no orphan demo content, no collateral deletion of real posts.
- `wcb_setup_complete` is the single completion source of truth; the redirect dismisses the first-run nudge.
- Permission: every `/wizard/*` route gated on `wcb_manage_settings`.
- a11y: step buttons have focus-visible rings and 40px tap targets.
