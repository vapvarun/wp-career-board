# WP Career Board — Feature Audit Report

**Generated**: 2026-04-29
**Version**: 1.1.0
**Source**: [`audit/manifest.json`](manifest.json)
**Counts**: 15 blocks · 11 shortcodes · 35 REST endpoints · 9 admin pages · 5 CPTs · 5 taxonomies · 3 custom tables · 3 cron jobs · 4 WP-CLI commands · 11 abilities/capabilities · 9 transactional emails · 0 admin-ajax actions

---

## 1. Frontend features (Gutenberg blocks + Interactivity API)

### 1.1 Block: `wp-career-board/job-listings`
- **Slug**: `wp-career-board/job-listings`
- **Render**: `blocks/job-listings/render.php`
- **Roles**: public (read), candidate (bookmark)
- **Sub-views**: list / grid / single
- **REST calls**: `GET /wcb/v1/jobs`, `POST /wcb/v1/jobs/{id}/bookmark`
- **JS module**: `blocks/job-listings/view.js` (Interactivity API store)
- **Settings toggle**: `wcb_settings.listings.*`, `wcb_default_board_id`

### 1.2 Block: `wp-career-board/job-form` (multi-step)
- **Render**: `blocks/job-form/render.php`
- **Roles**: `wcb_employer`, `administrator` (`wcb_post_jobs`)
- **Sub-views**: step1 / step2 / step3 / step4 preview
- **REST calls**: `POST /wcb/v1/jobs`, `PUT /wcb/v1/jobs/{id}`
- **JS module**: `blocks/job-form/view.js`
- **Hooks fired**: `wcb_job_form_step{1..4}_fields|preview`

### 1.3 Block: `wp-career-board/job-form-simple`
- Single-step variant — same permission, same REST POST endpoint.

### 1.4 Block: `wp-career-board/job-single`
- **Render**: `blocks/job-single/render.php` — interactive apply form embedded.
- **REST calls**: `GET /wcb/v1/jobs/{id}`, `POST /wcb/v1/jobs/{id}/apply`, resume upload.
- **Roles**: public (view), `wcb_candidate` (apply).

### 1.5 Block: `wp-career-board/job-search` + `job-search-hero`
- **Hero** is a non-interactive landing component that renders the search input;
  results render via `job-listings` on submit.
- **REST**: `GET /wcb/v1/search`.

### 1.6 Block: `wp-career-board/job-filters`
- **Render**: `blocks/job-filters/render.php`
- **Interactivity**: yes — controls the `job-listings` store.

### 1.7 Block: `wp-career-board/featured-jobs` / `recent-jobs` / `job-stats`
- Static SSR blocks (no interactivity) — render via `WP_Query` against `wcb_job` CPT.

### 1.8 Block: `wp-career-board/candidate-dashboard`
- **Roles**: `wcb_candidate`, `administrator` (`wcb_access_candidate_dashboard`)
- **REST**: `/candidates/{id}`, `/candidates/{id}/bookmarks`, `/candidates/{id}/applications`, `POST /candidates/resume-upload`
- **Sub-views**: profile / bookmarks / applications / resume

### 1.9 Block: `wp-career-board/employer-dashboard`
- **Roles**: `wcb_employer`, `administrator` (`wcb_access_employer_dashboard`)
- **REST**: `/employers/{id}`, `/employers/{id}/jobs`, `/employers/{id}/applications`, `/employers/me/jobs`
- **Sub-views**: my jobs / received applications / company / billing teaser
- **Filters**: `wcb_credit_purchase_url`, `wcb_employer_credit_balance` (Pro hooks)

### 1.10 Block: `wp-career-board/employer-registration`
- **Roles**: public (registers and assigns `wcb_employer` role)
- **REST**: `POST /wcb/v1/employers/register`
- **AntiSpam**: gated by `wcb_pre_application_submit`/`wcb_pre_job_submit` filters

### 1.11 Block: `wp-career-board/company-profile` / `company-archive`
- Public profiles for `wcb_company` CPT.
- **REST**: `GET /companies`, `GET /employers/{id}/jobs`.

### 1.12 Shortcode bridges
All 10 user-facing blocks are also exposed as shortcodes via the adapter in
`core/class-plugin.php` (`register_shortcodes`). Plus `[wcb_widget id=…]`
for legacy widget areas.

---

## 2. AJAX handlers

_None_ — the plugin uses **REST + Interactivity API only**. There are zero plugin-own `wp_ajax_*` actions. Admin-side write-paths (settings save, anti-spam save) use `admin-post.php`, not admin-ajax.

---

## 3. REST endpoints

Namespace `wcb/v1` (see manifest for full list of 35 endpoints).

| Group | Endpoints |
|---|---|
| Jobs (CRUD + bookmark + applications) | `/jobs`, `/jobs/{id}`, `/jobs/{id}/bookmark`, `/jobs/{id}/applications`, `/jobs/{id}/apply` |
| Applications | `/applications/{id}`, `/applications/{id}/status`, `/candidates/{id}/applications`, `/candidates/resume-upload` |
| Candidates | `/candidates/{id}`, `/candidates/{id}/bookmarks`, `/candidates/register` |
| Companies | `/companies`, `/companies/{id}/trust` |
| Employers | `/employers/register`, `/employers`, `/employers/{id}`, `/employers/{id}/jobs`, `/employers/{id}/applications`, `/employers/{id}/logo`, `/employers/me/jobs` |
| Search | `/search` |
| Settings | `/settings/app-config` |
| Admin | `/admin/dismiss-banner` |
| Import | `/import/status`, `/import/run` |
| Setup wizard | `/wizard/create-pages`, `/wizard/sample-data`, `/wizard/complete`, `/wizard/remove-sample-data` |
| Moderation | `/jobs/{id}/approve`, `/jobs/{id}/reject` |

Permission strategy: `permission_callback` calls either `__return_true` (public read), inline `is_user_logged_in()`, or a class-method that wraps `wp_is_authorized( '<wcb_*>' )`.

---

## 4. Admin pages

| Title | Slug | Parent | Capability |
|---|---|---|---|
| Career Board | `wp-career-board` | — (top-level) | `wcb_manage_settings` |
| Jobs | `edit.php?post_type=wcb_job` | `wp-career-board` | `wcb_manage_settings` |
| Applications | `edit.php?post_type=wcb_application` | `wp-career-board` | `wcb_manage_settings` |
| Candidates | `edit.php?post_type=wcb_resume` | `wp-career-board` | `wcb_manage_settings` |
| Companies | `edit.php?post_type=wcb_company` | `wp-career-board` | `wcb_manage_settings` |
| Employers | `wcb-employers` | `wp-career-board` | `wcb_manage_settings` |
| Settings | `wcb-settings` | `wp-career-board` | `wcb_manage_settings` |
| Emails | `wcb-emails` | `wp-career-board` | `wcb_manage_settings` |
| Setup Wizard | `wcb-setup-wizard` | `wp-career-board` | `wcb_manage_settings` |

---

## 5. Settings inventory

| Option | Group | Type | Purpose |
|---|---|---|---|
| `wcb_settings` | `wcb_settings_group` (Settings API) | array | Main settings (Listings / Pages / Notifications tabs) |
| `wcb_email_settings` | admin-post | array | Per-email enable/from/subject |
| `wcb_page_settings` | filter only | array | App-page IDs (where blocks live) |
| `wcb_setup_complete` | flag | bool | Wizard completion |
| `wcb_db_version` | flag | string | dbDelta version stamp |
| `wcb_default_board_id` | flag | int | Default `wcb_board` |
| `wcb_captcha_driver` | admin-post | string | none / recaptcha / turnstile |
| `wcb_jobs_cache_v` | flag | int | Cache bust |
| `wcb_sample_data_ids`, `wcb_sample_data_installed` | wizard | array, bool | Sample-data tracking |

Settings tabs (filterable via `wcb_settings_tabs`): `listings`, `pages`, `notifications`, `emails`, `import`. Pro adds: `pipeline`, `credits`, `field-builder`, `ai-settings`, `job-feed`, `boards` (teasers in Free).

---

## 6. Database tables

| Table | Purpose |
|---|---|
| `{prefix}wcb_notifications_log` | Audit log of emails/notifications sent |
| `{prefix}wcb_job_views` | Per-job view counter (de-duped via `ip_hash`) |
| `{prefix}wcb_gdpr_log` | GDPR action audit trail (export/erase) |

All created via `dbDelta()` in `core/class-install.php`. Schema version stored in `wcb_db_version`.

---

## 7. Content types (CPTs / taxonomies)

| Post type | Public | Module |
|---|---|---|
| `wcb_job` | yes | `modules/jobs/` |
| `wcb_application` | no | `modules/applications/` |
| `wcb_company` | yes | `modules/employers/` |
| `wcb_resume` | no | `modules/candidates/` |
| `wcb_board` | no | `modules/boards/` |

Taxonomies (all on `wcb_job`): `wcb_category`, `wcb_job_type`, `wcb_tag`, `wcb_location`, `wcb_experience`.

---

## 8. JavaScript modules

Frontend (block view.js — Interactivity API stores):
- `blocks/job-listings/view.js`, `blocks/job-form/view.js`, `blocks/job-form-simple/view.js`
- `blocks/job-single/view.js`, `blocks/job-search/view.js`, `blocks/job-filters/view.js`
- `blocks/candidate-dashboard/view.js`, `blocks/employer-dashboard/view.js`
- `blocks/employer-registration/view.js`, `blocks/company-profile/view.js`, `blocks/company-archive/view.js`

Standalone:
- `assets/js/wcb-recaptcha.js`, `assets/js/wcb-turnstile.js`, `assets/js/wcb-confirm-modal.js`

Admin: `admin.js`, `wizard.js`, `admin/icons.js`, `admin/toast.js`, `admin/settings-nav.js`, `admin/application-detail.js`, vendor `lucide.min.js`.

---

## 9. Email templates

| ID | Trigger | Purpose |
|---|---|---|
| `app_confirmation` | `wcb_application_submitted` | Candidate receipt |
| `app_guest` | `wcb_application_submitted` (guest) | Guest receipt with magic link |
| `app_received` | `wcb_application_submitted` | Employer notification |
| `app_status` | `wcb_application_status_changed` | Candidate status change |
| `deadline_reminder` | `wcb_deadline_reminder` | Employer deadline approach |
| `job_approved` | `wcb_job_approved` | Employer approved |
| `job_pending` | `wcb_job_created` (pending) | Admin/employer pending |
| `job_rejected` | `wcb_job_rejected` | Employer rejected |
| `job_expired` | `wcb_job_expired` | Employer expired |

All extend `WCB\Modules\Notifications\AbstractEmail` and are registered via the `wcb_registered_emails` filter.

---

## 10. Cron jobs

| Hook | Schedule | Handler |
|---|---|---|
| `wcb_check_job_expiry` | daily | `WCB\Modules\Jobs\JobsExpiry::run` |
| `wcb_expire_featured_jobs` | daily | `WCB\Modules\Jobs\FeaturedExpiry::run` |
| `wcb_send_deadline_reminders` | daily | `WCB\Modules\Jobs\DeadlineReminders::run` |

---

## 11. Integrations

| Plugin/Theme | Detection | Bridge |
|---|---|---|
| BuddyPress | `function_exists('buddypress')` | `WCB\Integrations\Buddypress\BpIntegration` |
| Reign theme | `wp_get_theme()->get_stylesheet() === 'reign-theme'` | `WCB\Integrations\Reign\ReignIntegration` (uses `reign_before_content_section`/`reign_after_content_section`) |
| BuddyX Pro theme | `wp_get_theme()->get_stylesheet() === 'buddyx-pro'` | `WCB\Integrations\BuddyxPro\BuddyxProIntegration` |

---

## 12. Custom capabilities (Abilities API)

| Ability | Default roles | Purpose |
|---|---|---|
| `wcb_post_jobs` | wcb_employer, administrator | Post jobs |
| `wcb_manage_company` | wcb_employer, administrator | Edit own company profile |
| `wcb_view_applications` | wcb_employer, administrator | See applications received |
| `wcb_access_employer_dashboard` | wcb_employer, administrator | Render employer dashboard block |
| `wcb_apply_jobs` | wcb_candidate, administrator | Submit applications |
| `wcb_manage_resume` | wcb_candidate, administrator | Upload/edit resume |
| `wcb_bookmark_jobs` | wcb_candidate, administrator | Bookmark jobs |
| `wcb_access_candidate_dashboard` | wcb_candidate, administrator | Render candidate dashboard block |
| `wcb_moderate_jobs` | wcb_board_moderator, administrator | Approve/reject jobs |
| `wcb_manage_settings` | administrator | Access admin pages and write settings |
| `wcb_view_analytics` | administrator | View analytics (Pro feature gate) |

---

## 13. WP-CLI commands

| Command | Subcommands |
|---|---|
| `wp wcb` | `status`, `abilities` |
| `wp wcb job` | `list`, `approve`, `reject`, `expire`, `run-expiry` |
| `wp wcb application` | `list`, `update` |
| `wp wcb migrate` | `wpjm`, `wpjm-resumes` |
