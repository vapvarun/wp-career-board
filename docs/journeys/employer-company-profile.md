---
feature: block wp-career-board/employer-dashboard — Company Profile tab
roles: employer, admin
surface: frontend block + REST (POST /employers, PATCH /employers/{id}, POST /employers/{id}/logo)
last_walked: 2026-06-26
---

# Company profile edit — full browser walkthrough

**What it is:** The employer's company profile editor inside the employer dashboard — logo upload, identity fields, and a live preview card.
**Where it lives:** `/employer-dashboard/` → **Profile** tab (the `COMPANY → Profile` nav item, `actions.switchToCompany`).

## As anonymous / logged-in non-employer
1. `/employer-dashboard/` logged out → "Please sign in to access your dashboard." + Sign In.
2. As a plain subscriber → "The employer dashboard is for employers…" with **Register as an employer** / **Go to Candidate Dashboard** links. Gated by `wp_is_ability_granted( 'wcb/manage-company' )`.

## As employer (no company yet)
1. `?autologin=wcb_demo_employer` → Overview shows the onboarding card "Welcome! Let's get you set up." → **Set Up Company Profile** opens the Profile tab.
2. The logo field is hidden and a hint reads "Save your company profile first to enable logo upload." Enter a Company Name and **Save Profile** with the name blank → inline error "Company name is required." (client-side, new company only).
3. Fill the name → **Save Profile** → POSTs `/wcb/v1/employers`, stores the returned `companyId`, success line "Profile saved successfully." (check icon, clears after 3s).

## As employer (editing an existing company)
1. Profile tab fields, all bound to Interactivity state and saved via PATCH `/employers/{id}`: **Company Name**, **Tagline**, **About the Company** (rich editor), **Industry** (select, `Industries::all()`), **Company Size** (select, 1-10 … 5,000+), **HQ Location** (free text, e.g. "San Francisco, CA"), **Website**, **Company Type** (select), **Founded Year** (number), **LinkedIn**, **X (Twitter)**.
2. **Logo upload:** with a saved company, the file input accepts jpeg/png/gif/webp → `actions.uploadLogo` POSTs FormData to `/employers/{id}/logo`; the label cycles **Upload Logo → Uploading… → Change Logo** and the current-logo thumbnail + preview image update on success. Uploading without a saved company shows "Please save your company profile before uploading a logo."
3. **Live Preview** card (right column) reflects logo, name, tagline, description excerpt, and industry/size/HQ chips as you type.
4. **HQ → scoped location term:** saving HQ runs `Locations::sync_company_hq()`, which mirrors the free-text HQ into a `wcb_location` term attached to the company. That term is exactly what the post-a-job Location dropdown later offers (HQ + Remote + Other) — cross-check `employer-post-job.md`.
5. Edit display name / email / password from the **Settings** tab (`POST /account`); those are account, not company, fields.

## As admin
1. `?autologin=1` → wp-admin → **Career Board → Companies** (`wcb_company` CPT) lists the company; trust flags set via `POST /companies/{id}/trust`. Admins also see the full `wcb_location` taxonomy when posting/editing jobs.

## Themes & states
- Reign, BuddyX light, **BuddyX dark** at 1440px + 390px. Two-column profile/preview grid collapses to one column on mobile; logo control and selects stay tappable (40px).
- Empty/edge: no-company onboarding card; save error → `role="alert"` line; logo upload failure leaves the field retryable.

## Contracts guarded
- REST↔JS: save body keys (`name`, `description`, `tagline`, `website`, `industry`, `size`, `hq`, `company_type`, `founded`, `linkedin`, `twitter`, `custom_fields`) match the `EmployersEndpoint` schema; logo response `logo_url` updates state.
- Security: profile + logo gated on `wcb/manage-company` (own company only).
- HQ contract: HQ text round-trips into a `wcb_location` term so the job-form location dropdown is never empty for the employer.
- a11y: labelled fields, `role="status"`/`alert` save feedback, dark-mode-readable preview card.
