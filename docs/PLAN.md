# WP Career Board — Architecture & Release Plan

**Goal:** A fully feature-complete free job board plugin for WordPress. Employers post jobs, candidates apply, admin manages — end-to-end on a single board, no credits required.

**Stack:** PHP 8.1+ · WP 6.9+ · WordPress Interactivity API · WordPress Abilities API · Gutenberg blocks · REST API (`/wp-json/wcb/v1/`) · wp_mail · WP-Cron

**Status: v1.0.0 — Feature Complete, QA Complete ✅**

---

## Architecture

API-First Modular Monolith. Modules expose REST endpoints consumed by Interactivity API blocks. Abilities API governs all permissions. BuddyPress / Reign / BuddyX Pro are optional integrations activated on detection.

### Rules
- Prefix: `wcb_` — functions, options, hooks, meta keys, CPTs, taxonomies, DB tables
- Namespace: `WCB\` via `spl_autoload_register`
- REST namespace: `wcb/v1`
- Permissions: Abilities API only — `wp_register_ability()` / `wp_is_authorized()` — never `current_user_can()`
- Frontend: Interactivity API only — zero jQuery, zero `admin-ajax.php`
- DB: `dbDelta()` for tables, `$wpdb->prepare()` for all queries
- PHP: typed properties, `declare(strict_types=1)` in every file

---

## File Structure (Actual)

```
wp-career-board/
├── wp-career-board.php                          # Bootstrap: constants, autoload, init
├── uninstall.php                                # Clean DB tables + options on delete
├── core/
│   ├── class-plugin.php                         # Singleton — loads modules in order
│   ├── class-install.php                        # Creates DB tables, registers roles
│   ├── class-roles.php                          # Registers wcb_employer, wcb_candidate, wcb_board_moderator
│   └── class-abilities.php                      # wp_register_ability() calls
├── modules/
│   ├── jobs/
│   │   ├── class-jobs-module.php                # Registers wcb_job CPT + taxonomies
│   │   ├── class-jobs-meta.php                  # Postmeta helpers
│   │   ├── class-jobs-expiry.php                # WP-Cron auto-expiry
│   │   └── templates/archive-tax.php            # Taxonomy archive template
│   ├── employers/
│   │   ├── class-employers-module.php           # Registers wcb_company CPT
│   │   └── templates/archive-wcb-company.php
│   ├── candidates/
│   │   └── class-candidates-module.php
│   ├── applications/
│   │   ├── class-applications-module.php        # Registers wcb_application CPT
│   │   └── class-applications-meta.php          # Status, stage, custom fields helpers
│   ├── search/
│   │   └── class-search-module.php              # WP_Query builder + URL param sync
│   ├── notifications/
│   │   ├── class-notifications-module.php
│   │   ├── class-notifications-email.php        # wp_mail driver
│   │   ├── class-abstract-email.php
│   │   └── emails/                              # Per-event email classes
│   │       ├── class-email-app-confirmation.php
│   │       ├── class-email-app-guest.php
│   │       ├── class-email-app-received.php
│   │       ├── class-email-app-status.php
│   │       ├── class-email-job-approved.php
│   │       ├── class-email-job-expired.php
│   │       ├── class-email-job-pending.php
│   │       └── class-email-job-rejected.php
│   ├── moderation/
│   │   └── class-moderation-module.php          # Approval queue logic
│   ├── seo/
│   │   └── class-seo-module.php                 # JobPosting schema, OG tags, meta
│   ├── gdpr/
│   │   └── class-gdpr-module.php                # WP privacy API: export + erase
│   ├── antispam/
│   │   ├── class-antispam-module.php            # Anti-spam gate for job-form + apply
│   │   ├── class-recaptcha-driver.php           # Google reCAPTCHA v3
│   │   └── class-turnstile-driver.php           # Cloudflare Turnstile
│   ├── boards/
│   │   └── class-boards-module.php              # Single-board management (Free)
│   └── theme-integration/
│       └── class-theme-integration-module.php   # Theme-agnostic compat hooks
├── api/
│   ├── class-rest-controller.php                # Base controller: auth + ability checks
│   └── endpoints/
│       ├── class-jobs-endpoint.php              # /wcb/v1/jobs
│       ├── class-applications-endpoint.php      # /wcb/v1/jobs/{id}/apply, /applications
│       ├── class-candidates-endpoint.php        # /wcb/v1/candidates
│       ├── class-employers-endpoint.php         # /wcb/v1/employers
│       ├── class-companies-endpoint.php         # /wcb/v1/companies
│       ├── class-search-endpoint.php            # /wcb/v1/search
│       └── class-import-endpoint.php            # /wcb/v1/import
├── blocks/
│   ├── job-listings/                            # wcb/job-listings — browsable job grid
│   ├── job-search/                              # wcb/job-search — keyword search bar
│   ├── job-search-hero/                         # wcb/job-search-hero — hero variant
│   ├── job-filters/                             # wcb/job-filters — sidebar filters
│   ├── job-single/                              # wcb/job-single — full job detail
│   ├── job-form/                                # wcb/job-form — post a job
│   ├── job-stats/                               # wcb/job-stats — board stats widget
│   ├── featured-jobs/                           # wcb/featured-jobs — highlighted jobs
│   ├── recent-jobs/                             # wcb/recent-jobs — latest listings
│   ├── employer-dashboard/                      # wcb/employer-dashboard
│   ├── employer-registration/                   # wcb/employer-registration
│   ├── candidate-dashboard/                     # wcb/candidate-dashboard
│   ├── company-profile/                         # wcb/company-profile
│   └── company-archive/                         # wcb/company-archive
│       Each block: block.json · render.php · view.js · style.css
├── integrations/
│   ├── buddypress/
│   │   └── class-bp-integration.php            # Member types, activity streams
│   ├── reign/
│   │   ├── class-reign-integration.php         # Compat CSS, has_block() enqueue
│   │   └── templates/                          # archive-wcb_job.php · single-wcb_job.php
│   └── buddyx-pro/
│       ├── class-buddyx-pro-integration.php
│       └── templates/                          # archive-wcb_job.php · single-wcb_job.php
├── admin/
│   ├── class-admin.php                         # Top-level menu + submenus
│   ├── class-admin-jobs.php                    # Jobs WP_List_Table (search, tabs, bulk)
│   ├── class-admin-applications.php            # Applications list + status change
│   ├── class-admin-employers.php               # Employers list
│   ├── class-admin-candidates.php              # Candidates list
│   ├── class-admin-companies.php               # Companies list + trust_level inline action
│   ├── class-admin-meta-boxes.php              # Centralised meta box registration
│   ├── class-admin-settings.php                # General settings page
│   ├── class-admin-import.php                  # Import admin page
│   ├── class-email-settings.php                # Email settings + template preview
│   ├── class-setup-wizard.php                  # Onboarding wizard
│   └── views/
│       ├── jobs-list.php
│       └── setup-wizard.php
├── cli/
│   ├── class-cli.php                           # WP-CLI command loader
│   ├── class-abstract-cli-command.php
│   ├── class-job-commands.php                  # wp wcb job *
│   ├── class-application-commands.php          # wp wcb application *
│   └── class-migrate-commands.php              # wp wcb migrate *
├── import/
│   └── class-wpjm-importer.php                 # WP Job Manager data importer
├── assets/
│   ├── css/
│   │   ├── frontend.css
│   │   ├── admin.css
│   │   └── admin-rtl.css
│   └── js/
│       ├── admin.js
│       ├── wcb-recaptcha.js
│       ├── wcb-turnstile.js
│       └── wizard.js
├── vendor/
│   └── edd-sl-sdk/                             # EDD Software Licensing SDK (auto-updates)
├── languages/
│   └── wp-career-board.pot
├── build/                                      # Webpack compiled block bundles (gitignored in dev)
├── dist/                                       # Release zip: wp-career-board-{version}.zip
├── package.json                                # @wordpress/scripts build config
├── Gruntfile.js                                # Release packaging
├── theme.json                                  # Block theme color/font presets
├── phpstan.neon                                # PHPStan config (level 6)
└── docs/
    ├── PLAN.md                                 ← this file
    ├── DESIGN-SPEC.md
    ├── RELEASE-CHECKLIST.md
    └── CLI.md
```

---

## Progress Tracker

| ID | Task | Status | Commit | Notes |
|----|------|--------|--------|-------|
| T1 | Plugin main file + autoloader | ✅ | `8bde815` | 2026-03-14 |
| T2 | DB install + user roles + Abilities API | ✅ | `ddc05c4` | 2026-03-14 |
| T3 | CPTs + taxonomies (wcb_job, wcb_company, wcb_application, wcb_board) | ✅ | `b5feb51` | 2026-03-14 |
| T4 | REST base controller | ✅ | `884468c` | 2026-03-14 |
| T5 | Jobs REST endpoint | ✅ | `82afd2f` | 2026-03-14 |
| T6 | Applications REST endpoint | ✅ | `487541c` | 2026-03-14 |
| T7 | Employers + candidates + search + companies + import endpoints | ✅ | `f1e8bbd` | 2026-03-14 |
| T8 | Email notifications — wp_mail driver + 8 email classes | ✅ | `94c3dec` | 2026-03-14 |
| T9 | Moderation queue | ✅ | `e723f2f` | 2026-03-14 |
| T10 | SEO module — JobPosting schema, OG tags | ✅ | `f61eeb5` | 2026-03-14 |
| T11 | GDPR module — privacy export + erase | ✅ | `471a4b0` | 2026-03-14 |
| T12 | Admin menu + settings page | ✅ | `c9f65d5` | 2026-03-14 |
| T13 | Setup wizard + sample data | ✅ | `43e8564` | 2026-03-14 |
| T14 | Build configuration (@wordpress/scripts) | ✅ | `e26589c` | 2026-03-14 |
| T15 | wcb/job-listings block | ✅ | `e4b70ae` | 2026-03-14 |
| T16 | wcb/job-search + wcb/job-filters blocks | ✅ | `28d22ac` | 2026-03-14 |
| T17 | wcb/job-single + wcb/job-form + dashboards + company blocks | ✅ | `f6b64c5` | 2026-03-14 |
| T18 | Reign integration — compat CSS + conditional enqueue | ✅ | `fa73d70` | 2026-03-20 |
| T19 | BuddyX Pro integration | ⚠️ Deferred | — | Blocks are theme-agnostic; revisit when BuddyX Pro-specific hooks are confirmed |
| T20 | BuddyPress integration — member types + activity streams | ✅ | `b358a29` | 2026-03-15 |
| T21a | Admin dashboard overhaul — stats, branded header | ✅ | `2c57139` | 2026-03-15 |
| T21b | Jobs admin WP_List_Table — search, tabs, pagination, bulk | ✅ | `5ee4885` | 2026-03-15 |
| T21c | Applications admin WP_List_Table — status tabs, bulk trash | ✅ | `120da99` | 2026-03-15 |
| T21d | Employers admin WP_List_Table | ✅ | `be6f5c0` | 2026-03-15 |
| T21e | Candidates admin WP_List_Table | ✅ | `be6f5c0` | 2026-03-15 |
| T21f | Companies admin WP_List_Table + trust_level inline action | ✅ | `be6f5c0` | 2026-03-15 |
| T22a | Styled modal replacing window.confirm/prompt; rejection reason flow | ✅ | `467b218` | 2026-03-16 |
| T22b | Bulk application status change | ✅ | `467b218` | 2026-03-16 |
| T22c | Companies trust_level column + inline change | ✅ | `467b218` | 2026-03-16 |
| T22d | Loading states on status selects | ✅ | `467b218` | 2026-03-16 |
| T22e | Re-run Setup Wizard link on dashboard + settings | ✅ | `467b218` | 2026-03-16 |
| T22f | Audit log — _wcb_status_log appended on status change | ✅ | `467b218` | 2026-03-16 |
| T22g | Applications — mailto candidate link in row actions | ✅ | `467b218` | 2026-03-16 |
| T23 | reCAPTCHA v3 + Cloudflare Turnstile anti-spam | ✅ | `4885428` | 2026-03-17 |
| T24 | Guest applications — apply without registration (email only) | ✅ | `e4c8f64` | 2026-03-17 |
| T25 | Frontend company profile editor in employer-dashboard | ✅ | — | 2026-03-17 |
| T26 | Social sharing on job-single — X, LinkedIn, copy link | ✅ | `7025f26` | 2026-03-17 |
| T27a | Salary range + remote filter UI in job-filters block | ✅ | `5378fe1` | 2026-03-17 |
| T27b | Company name search + REST caching + extension hooks | ✅ | `27162d8` | 2026-03-17 |
| T28 | Admin dashboard — Getting Started checklist card | ✅ | `8cd6c6f` | 2026-03-24 |

---

## Deferred Tasks

| ID | Task | Reason |
|----|------|--------|
| T19 | BuddyX Pro integration | Blocks are theme-agnostic. Revisit when BuddyX Pro-specific hooks are confirmed. |

---

## Pre-Release QA Checklist

> Run before tagging v1.0.0. Check each row manually or via Playwright MCP.

### Backend — REST API

| Test | Expected Result | Status |
|------|----------------|--------|
| `GET /wcb/v1/jobs` (unauthenticated) | Returns published jobs list | ✅ |
| `POST /wcb/v1/jobs` (unauthenticated) | 401 Unauthorized | ✅ |
| `POST /wcb/v1/jobs` (employer user) | Job created, returns ID | ✅ |
| `GET /wcb/v1/jobs/{id}` | Returns full job object with meta | ✅ |
| `POST /wcb/v1/jobs/{id}/apply` (duplicate) | 409 Conflict | ✅ |
| `GET /wcb/v1/search?keyword=php&location=london` | Filtered results | ✅ |
| `GET /wcb/v1/search?salary_min=50000&remote=1` | Salary + remote filter works | ✅ |
| `GET /wcb/v1/employers/{id}/applications` (unauthenticated) | 401 Unauthorized | ✅ |
| `GET /wcb/v1/employers/{id}/applications` (employer) | Returns own company's applications only | ✅ |
| `PATCH /wcb/v1/applications/{id}/status` status change | Status updated | ✅ |
| `GET /wcb/v1/candidates/{id}` (public profile) | Returns candidate profile | ✅ |
| `GET /wcb/v1/companies` | Returns company/employer list | ✅ |

### Frontend — Blocks

| Block | Test | Status |
|-------|------|--------|
| job-listings | Renders 15 of 25 jobs, "Load more" pagination works | ✅ |
| job-search | Search box present, filters listings live | ✅ |
| job-filters | Type, experience, remote buttons visible and functional | ✅ |
| job-single | Full job detail renders with salary, company, sharing | ✅ |
| job-form | Employer can post job via REST | ✅ |
| employer-dashboard | Renders with nav (My Jobs, Applications, Profile), stats | ✅ |
| candidate-dashboard | Renders with Applications, Saved Jobs, resume count | ✅ |
| company-profile | Company info + edit form (employer-scoped) | ✅ |
| employer-registration | Registration endpoint active (`POST /employers/register`) | ✅ |
| featured-jobs | Featured badge visible on job cards | ✅ |
| recent-jobs | Jobs ordered by recency, "days ago" label | ✅ |
| job-stats | Board stats visible in candidate dashboard overview | ✅ |

### Admin UI

| Area | Test | Status |
|------|------|--------|
| Jobs list | Page loads, jobs listed with status tabs | ✅ |
| Jobs list | Reject modal fires with reason field | ✅ |
| Applications list | Page loads, status tabs, rows visible | ✅ |
| Applications list | Bulk status change | ✅ |
| Applications list | Audit log meta box shows on edit screen | ✅ |
| Employers list | Paginated, searchable | ✅ |
| Candidates list | Paginated, searchable | ✅ |
| Companies list | trust_level inline change | ✅ |
| Settings | Save general settings, verify stored in wp_options | ✅ |
| Email settings | Preview email template | ✅ |
| Setup wizard | Re-run from dashboard link works | ✅ |
| Admin dashboard | Getting Started card shows/hides correctly | ✅ |

### Email Notifications

| Trigger | Recipients | Status |
|---------|-----------|--------|
| Candidate applies | Employer (app received) + Candidate (confirmation) | ✅ |
| Guest applies | Guest email (confirmation with link) | ✅ |
| Admin approves job | Employer | ✅ |
| Admin rejects job | Employer (with reason) | ✅ |
| Application status changed | Candidate | ✅ |
| Job auto-expires (cron) | Employer | ✅ |

### Integrations

| Integration | Test | Status |
|-------------|------|--------|
| BuddyPress | Employer/candidate member types assigned on registration | ✅ |
| BuddyPress | Job posted → activity stream entry created | ✅ |
| Reign theme | Job pages use Reign layout (compat CSS loaded) | ✅ `fa73d70` |
| BuddyX Pro | Job pages use BuddyX Pro layout | ⚠️ Deferred (T19) |

### Security

| Check | Status |
|-------|--------|
| All REST write endpoints return 401 without authentication | ✅ |
| Admin AJAX handlers check nonce via `check_ajax_referer()` | ✅ |
| No raw `echo $variable` — all output uses `esc_html()` / `esc_attr()` / `wp_kses_post()` | ✅ |
| All DB queries use `$wpdb->prepare()` | ✅ |
| File upload endpoints validate MIME type | ✅ |

### SEO & GDPR

| Test | Status |
|------|--------|
| Job single page — valid `JobPosting` LD+JSON in `<head>` | ✅ |
| OG title/description tags present on job single | ✅ |
| Privacy export — exporter registered via `wp_privacy_personal_data_exporters` | ✅ |
| Privacy erase — eraser registered via `wp_privacy_personal_data_erasers` | ✅ |
