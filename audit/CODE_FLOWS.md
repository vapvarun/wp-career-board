# WP Career Board — Code Flow Maps

**Generated**: 2026-04-29
**Source**: [`audit/manifest.json`](manifest.json)

---

## Flow 1 — Job listings (public browsing)

**Entry**: any page containing the `wp-career-board/job-listings` block (or `[wcb_job_listings]`).

### Code path
1. WordPress renders the page → `blocks/job-listings/render.php` runs (server-side).
2. `render.php` calls `wp_interactivity_state( 'wp-career-board/job-listings', […] )` to seed the store with current filters/board.
3. Browser loads `view.js` (Interactivity module). Store dispatches a fetch to `/wp-json/wcb/v1/jobs?…` on hydration and on filter change.
4. REST: `JobsEndpoint::get_items` → `WP_Query` against `wcb_job` CPT → applies `wcb_jobs_post_filter`, `wcb_jobs_allowed_meta_filters`, `wcb_job_listings_query_args` filters → returns paginated JSON.
5. Each job is run through `wcb_rest_prepare_job` and `wcb_job_response` filters before serialization.
6. Browser updates DOM via `data-wp-*` directives.

### Key files
| File | Role |
|---|---|
| `blocks/job-listings/block.json` | Block metadata + viewScriptModule |
| `blocks/job-listings/render.php` | SSR + state seed |
| `blocks/job-listings/view.js` | Interactivity store |
| `api/endpoints/class-jobs-endpoint.php` | REST handler `get_items` |
| `modules/jobs/class-jobs-module.php` | CPT registration + meta |
| `modules/search/class-search-module.php` | `pre_get_posts` archive filter |

### REST chain
| Step | Route | Method | Auth |
|---|---|---|---|
| 1 | `/wcb/v1/jobs?board=…&meta=…&page=…` | GET | public |
| 2 | `/wcb/v1/jobs/{id}/bookmark` | POST | logged-in candidate |

### Permissions
- Read: `__return_true`.
- Bookmark: inline `is_user_logged_in()` (`wcb_bookmark_jobs` ability granted to `wcb_candidate`).

---

## Flow 2 — Job posting (job-form, multi-step)

**Entry**: page with `wp-career-board/job-form` block. Roles: `wcb_employer`, `administrator`.

### Code path
1. `render.php` checks `wp_is_authorized( 'wcb_post_jobs' )` → otherwise renders gated message.
2. State is seeded with industries, locations, draft id (if any).
3. `view.js` walks Step1 → Step4 (preview) entirely client-side; each step fires `wcb_job_form_step{N}_fields` server-side via render hooks for Pro extensions.
4. On submit: `POST /wcb/v1/jobs`.
5. `JobsEndpoint::create_item` runs:
   - `wcb_pre_job_submit` filter (AntiSpamModule verifies CAPTCHA token).
   - `wcb_before_create_job` filter.
   - `wp_insert_post( wcb_job, status=pending|publish )` per moderation setting.
   - Fires `wcb_job_created` action → `Email_Job_Pending` notifies admin/employer.
6. Response returned; `view.js` redirects to dashboard.

### Permissions
- `create_item_permissions_check`: `wp_is_authorized( 'wcb_post_jobs' )` + nonce.

---

## Flow 3 — Application submission (candidate applies)

**Entry**: `wp-career-board/job-single` block on a `wcb_job` CPT page.

### Code path
1. Block renders job details + apply form (fields filtered through `wcb_application_form_fields_groups`).
2. Resume upload is a separate REST call: `POST /wcb/v1/candidates/resume-upload` → returns attachment ID stored in store state.
3. Submit: `POST /wcb/v1/jobs/{id}/apply`.
4. `ApplicationsEndpoint::submit_application`:
   - `wcb_pre_application_submit` filter (AntiSpam).
   - `wcb_before_create_application` filter.
   - Inserts `wcb_application` post.
   - Fires `wcb_application_submitted` action → emails: `Email_App_Confirmation` (candidate), `Email_App_Received` (employer), `Email_App_Guest` (if guest).
5. Frontend renders success state.

### Permissions
- `submit_permissions_check`: logged-in candidate (`wcb_apply_jobs`) OR guest if guest applications enabled.

---

## Flow 4 — Candidate dashboard

**Entry**: page with `wp-career-board/candidate-dashboard` block. Role gate: `wcb_access_candidate_dashboard`.

### Code path
1. `render.php` gates by `wp_is_authorized( 'wcb_access_candidate_dashboard' )`.
2. View module hydrates four parallel REST calls:
   - `GET /candidates/{me}` (profile)
   - `GET /candidates/{me}/bookmarks`
   - `GET /candidates/{me}/applications`
   - Resume metadata embedded in `/candidates/{me}` response.
3. Profile edits → `PUT /candidates/{id}` (filtered through `wcb_rest_prepare_candidate`).
4. Withdrawing an application → `DELETE /applications/{id}` → fires `wcb_application_withdrawn`.

---

## Flow 5 — Employer dashboard

**Entry**: page with `wp-career-board/employer-dashboard`. Gate: `wcb_access_employer_dashboard`.

### Code path
1. View module fetches `GET /employers/me/jobs` and `GET /employers/{id}/applications` in parallel.
2. Application status update → `PUT /applications/{id}/status` → fires `wcb_application_status_changed` → `Email_App_Status`.
3. Pro hook surfaces: `wcb_credit_purchase_url`, `wcb_employer_credit_balance`, `wcb_credits_enabled` (Free returns the no-op default).

---

## Flow 6 — Employer/candidate registration

**Entry**: page with `wp-career-board/employer-registration` block. Public access (no role required).

### Code path
1. SSR renders signup form. View.js controls inline validation.
2. Submit:
   - Employer: `POST /wcb/v1/employers/register` → creates user, assigns `wcb_employer` role, optionally creates `wcb_company` post → fires `wcb_employer_registered`.
   - Candidate: `POST /wcb/v1/candidates/register` → creates user, assigns `wcb_candidate` role → fires `wcb_candidate_registered`.
3. Frontend redirects to the appropriate dashboard.

---

## Flow 7 — Job moderation (Approve / Reject)

**Entry**: admin under "Jobs → Pending Moderation" or via the moderation dashboard block.

### Code path
1. Admin clicks Approve/Reject → JS fires `POST /wcb/v1/jobs/{id}/approve` (or `…/reject`).
2. `ModerationModule::approve_job` checks `wp_is_authorized( 'wcb_moderate_jobs' )` (filtered via `wcb_moderate_jobs_ability_check`).
3. Updates `post_status` → fires `wcb_job_approved` / `wcb_job_rejected`.
4. Notifications module sends `Email_Job_Approved` / `Email_Job_Rejected`.

---

## Flow 8 — Setup wizard (first-run)

**Entry**: `?page=wcb-setup-wizard` after activation (auto-redirect on first admin load).

### Code path
1. `wizard.js` walks the user through three REST steps:
   - `POST /wcb/v1/wizard/create-pages` — creates the canonical app pages with the right blocks.
   - `POST /wcb/v1/wizard/sample-data` — inserts sample jobs/companies (toggle).
   - `POST /wcb/v1/wizard/complete` — sets `wcb_setup_complete = true`, fires `wcb_wizard_completed`.
2. `POST /wcb/v1/wizard/remove-sample-data` is exposed for cleanup.

---

## Flow 9 — Cron pipeline (jobs)

```
wp_schedule_event( daily ) ─┬─ wcb_check_job_expiry      → JobsExpiry::run        → fires wcb_job_expired       → Email_Job_Expired
                            ├─ wcb_expire_featured_jobs  → FeaturedExpiry::run    → fires wcb_featured_expired
                            └─ wcb_send_deadline_reminders → DeadlineReminders::run → fires wcb_deadline_reminder → Email_Deadline_Reminder
```

Manually: `wp wcb job run-expiry`.

---

## Flow 10 — AntiSpam gate

The two write hooks `wcb_pre_job_submit` and `wcb_pre_application_submit` are filtered by `AntiSpamModule::verify_request`. The active driver (`RecaptchaDriver` or `TurnstileDriver`, selected via `wcb_captcha_driver` option) verifies the token; if invalid the filter short-circuits the REST handler with a `WP_Error`.

---

## Flow 11 — Settings save

- Tabs `listings`, `pages`, `notifications` save via Settings API (`options.php` → `wcb_settings_group`).
- Tabs `emails`, `import`, `antispam` save via `admin-post.php` action handlers (`admin_post_wcb_save_*`).
- All saves run through the `wcb_settings_sanitize` filter so Pro extensions can sanitize their own keys.

---

## Flow 12 — Migration (WP Job Manager)

`wp wcb migrate wpjm` (and `wpjm-resumes`) walks the WPJM CPT, mapping its taxonomies/meta into the `wcb_*` schema. Idempotent — uses a marker meta to skip already-imported posts.
