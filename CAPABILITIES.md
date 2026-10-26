# WP Career Board (Free) — Capabilities

Buyer-level rollup of what the Free plugin does. Verified against code on the `1.8.2` branch.
Status legend: `YES` (shipped, code-verified) · `YES·partial` (works with a named limit) ·
`PLANNED` (roadmap/card exists, unbuilt) · `NO` (absent, often by design) · `PRO` (delivered by the Pro plugin).

**Last verified against code: 2026-10-26 (branch 1.8.2)**
Positioning: self-hosted, block-native, REST-first jobs + resume board — a next-gen alternative to WP Job Manager-class plugins. See [`wp-career-board-pro/CAPABILITIES.md`](../wp-career-board-pro/CAPABILITIES.md) for the Pro layer.

## Posting & listing jobs
| Can it… | Status | How / evidence |
|---|---|---|
| Let employers post jobs from the frontend (guest + logged-in) with preview? | YES | `job-form` 4-step wizard + `job-form-simple`; `POST/PUT /jobs` |
| Show searchable, filterable listings (grid/list/single)? | YES | `job-listings` block; `job-filters` reorderable sidebar; `GET /search` |
| Filter by type, category, location, tag, experience, salary range, board? | YES | 5 taxonomies + salary-range slider; `wcb_default_filter_order` |
| Auto-expire jobs + send deadline reminders? | YES | hourly cron `wcb_check_job_expiry`, daily cron `wcb_send_deadline_reminders` |
| Pre-moderate jobs (pending → approve/reject → resubmit)? | YES | `POST /jobs/{id}/approve\|reject`; Flagged queue + report-a-job |
| Emit Google for Jobs JobPosting JSON-LD automatically? | YES·partial | `modules/seo/class-seo-module.php` (datePosted, validThrough, employmentType, hiringOrganization, baseSalary, remote `jobLocationType`) — **not validated against Rich Results; no physical `jobLocation` address block confirmed** |
| Publish a jobs RSS feed? | YES | `/jobs/feed/` with full job fields |
| Sort filter terms (categories, types, locations, experience, tags) by a set order? | YES | `Order` field on Add/Edit/Quick Edit plus a sortable `Order` column; `core/class-taxonomy.php` |
| Nest child categories and locations under their parent in filters and forms? | YES | `core/class-locations.php`, `job-filters` block, `job-form` block |
| Let Find Jobs filter by several categories at once, and keep filters in the address bar? | YES | `job-listings` / `job-filters` blocks; shared links reopen with the same filters |
| Show a closed page for an expired job instead of a 404? | YES | single-job template; employers get a warning 3 days before a job ends |
| Let admins trash and restore jobs and companies? | YES | `Trash` view with `Restore` and `Delete Permanently`; `admin/class-admin-jobs.php`, `admin/class-admin-companies.php` |
| Publish a job-specific XML sitemap + ping Google Indexing API / IndexNow? | NO | only RSS + Pro per-board XML feed; no daily job sitemap or indexing pings |

## Applications & candidates
| Can it… | Status | How / evidence |
|---|---|---|
| Capture applications in-plugin (not just email/URL routing)? | YES | `wcb_application` CPT; apply drawer; guest applications — **free, unlike WPJM's $79/yr addon** |
| Give employers an application management screen (statuses, notes, export)? | YES | rebuilt Edit Application screen; 5 statuses; bulk CSV export |
| Give candidates a dashboard (profile, bookmarks, applications, resume)? | YES | `candidate-dashboard` block |
| Let candidates track their own applications? | YES | dashboard applications tab (+ Pro `my-applications` block) |
| Upload a resume (PDF/DOC/DOCX) attached to an application? | YES | `POST /candidates/resume-upload`, ≤20MB |
| Bookmark jobs, companies, and resumes? | YES | `/candidates/{id}/bookmarks`, `/saved-companies`, `/saved-resumes` |
| Keep private hiring notes and a 1-5 rating on each application, and export applications to CSV? | YES | Edit Application screen; employer dashboard export |
| Show an admin Suspended view of candidates? | YES | `admin/class-admin-candidates.php` via `member_views()` in `admin/class-reported-members.php` |
| Let any member apply without forcing a Candidate role? | YES | role-optional; `wcb_candidate_requires_role` filter |
| One-click apply with a previously saved resume? | YES·partial | PDF auto-attached on apply; a true "apply with saved resume, no re-upload" one-click path is not surfaced |

## Employers & companies
| Can it… | Status | How / evidence |
|---|---|---|
| Give employers a dashboard (my jobs, received applications, company)? | YES | `employer-dashboard`; `wcb_access_employer_dashboard` |
| Self-register employers + upload logo? | YES | `employer-registration`; `POST /employers/register`, `/logo` |
| Let one employer run several companies? | YES | dashboard, applications and Post a Job cover all of them; `blocks/employer-dashboard/render.php`, `api/endpoints/class-companies-endpoint.php` |
| Ban/unban employers from admin, with a Banned view? | YES | `wcb-employers` screen; `admin/class-admin-employers.php`; `wcb_employer_banned/unbanned` |
| Open a member's own jobs or applications from the Employers and Candidates lists? | YES | Active Jobs and Applications counts link to a filtered list with a removable chip |
| Public company profiles + directory with trust signals? | YES | `company-profile`/`company-archive` (`wcb_company` CPT); trust endpoint |

## Monetization
| Can it… | Status | How / evidence |
|---|---|---|
| Charge for job posting / featured listings? | PRO | Free ships only the bridge filters (`wcb_employer_credit_balance`, `wcb_credit_purchase_url`, `wcb_credits_enabled`); Pro Credits SDK fills them |
| Sell resume-database access (gate candidate contact behind payment)? | NO | deliberate non-goal — WCB is a plugin, not a SaaS marketplace |

## Notifications & operations
| Can it… | Status | How / evidence |
|---|---|---|
| Send transactional emails (pending, approved, rejected, received, status, expiry, guest magic-link)? | YES | 18 `AbstractEmail` templates in `modules/notifications/emails/` (adds welcome, rejection, guest status, account deletion, verify account, report received); `wcb_registered_emails` |
| Let the owner edit email bodies + test-send? | YES | Emails settings tab: one form, one `Save changes` for sender, look and templates; `POST /admin/emails/test` |
| Send each email in the reader's language? | YES | guests in the language they applied in, accounts in their own or the site language |
| Let members switch off optional emails? | YES | dashboard Email Notifications preferences |
| Link to documentation from Settings? | YES | `Help` group with a `Documentation` link; `wcb_docs_url` filter in `admin/class-admin-settings.php` |
| In-app notifications panel (read/unread, mark-all, delete)? | YES | dashboard panel; `wcb_notification_created`; `wcb_notifications_log` |
| Spam-protect submission + application forms? | YES | reCAPTCHA v3 / Turnstile drivers; `wcb_pre_*_submit` gates |
| Send Zapier/webhook triggers on job/application events? | NO | no webhook layer — hooks fire in-PHP only |
| Track listing view stats? | YES | `wcb_job_views` table |

## Platform, data & compliance
| Can it… | Status | How / evidence |
|---|---|---|
| Expose a documented REST API (headless-ready, App-Password auth)? | YES | routes under `wcb/v1`, listed in `docs/website/developer-guide/03-rest-api.md`; `GET /settings/app-config` |
| Ship block-native UI with no page builder required? | YES | 17 Interactivity-API blocks; all also shortcodes + `[wcb_widget]` |
| Migrate from WP Job Manager (+ WPJM Resumes)? | YES | `wcb migrate` CLI; `class-wpjm-importer.php` (non-destructive) |
| Import jobs from CSV/XML? | YES·partial | CSV importer present; Pro adds board XML feed import |
| GDPR export/erase with an audit trail? | YES | `POST /candidates/me/privacy/{export\|erase}`; `wcb_gdpr_log` |
| Multilingual (WPML / Polylang)? | YES | documented compat |
| Run on multisite? | UNVERIFIED | no explicit multisite handling found |
| Provide WP-CLI tooling? | YES | 5 commands (`wcb`, `job`, `application`, `migrate`, `scale`) |

## Developer surface
| Can a third party… | Status | How / evidence |
|---|---|---|
| Add/edit/remove job-form fields via filters? | YES | `wcb_job_form_fields` + per-step filters (the WPJM `submit_job_form_fields` equivalent) |
| Find a curated hook reference + extension cookbook? | YES | `docs/HOOKS.md`; `developer-guide/02-hooks-reference.md`, `05-extension-cookbook.md` (13 recipes) |
| Override the primary frontend templates from a theme? | YES·partial | theme copies of `single-wcb_job.php` and `single-wcb_company.php` win (Pro adds `single-wcb_resume.php`), plus emails; block templates for archives; Site Health flags a stale copy. Cards and listings are not overridable, so customize those via hooks. See `docs/website/developer-guide/06-template-overrides.md` |
| Extend frontend JS behavior via a documented hook API? | NO | no `@wordpress/hooks`; the `wcb:search`/`wcb:results` CustomEvent seam is undocumented |
| Register a whole add-on module cleanly? | YES | Addons boot on their own `plugins_loaded` / `init` and integrate through the same filters Pro uses — 67 of them, the load-bearing ones being `wcb_pro_active`, `wcb_module_renders` (inject HTML into Free's dashboards), `wcb_settings_tabs` + `wcb_settings_tab_{slug}` (add a settings tab), `wcb_registered_emails` (register an email into Free's notification layer), `wcb_page_settings` (add a managed page), and the `wcb_{job,company,candidate,resume}_form_fields` / `wcb_job_response` pair (add fields and return them over REST). Every one is live in `wp-career-board-pro`, so an addon can copy a working call site rather than an abstraction. |
| Restyle without `!important` wars? | YES | 116 `--wcb-*` tokens; buddyx dark-mode compat layer |
