# Role × Data Exposure Baseline — 2026-05-07

> **Scope:** WP Career Board (Free, 1.1.x) + WP Career Board Pro (1.1.x). Lists every public surface (REST endpoint, block render output, admin page, CPT REST exposure) and records which role can read which fields TODAY. Drives Task 3.7 which closes the gaps recorded in the *Findings* section at the bottom.
>
> **Methodology:** walked every `register_rest_route()` call in both plugins, read each endpoint's `permission_callback` + `prepare_*()` shape, then walked every `blocks/*/render.php` and `register_post_type()` / `register_post_meta()` site to record what gets emitted to the page. Cross-referenced with the Abilities API registry from Task 3.5. Manifest at `audit/manifest.json` was used as inventory but verified by re-grep; the audit picked up endpoints the manifest does not list (`/admin/emails/test`, `/admin/emails/log`, `/candidates/me/privacy/{action}`, `/fields/reorder`, `/wizard/create-pro-pages`, `/ai/ranked-applications/{job_id}`, `/jobs/ai-description`, `/boards/{id}/credit-cost` — actually `/boards/{id}/stages/{stage_id}` per code) and dropped one phantom (`/ai/chat`, `/fields/values/{post_id}` — both listed in manifest but absent from code).

---

## Roles in scope

| Role | Slug / Cap | Source |
|---|---|---|
| **Anonymous** | not logged in | n/a |
| **Subscriber** | `subscriber` (WP core) | base WP |
| **Candidate** | `wcb_candidate` role + `wcb_apply_jobs`, `wcb_access_candidate_dashboard`, `wcb_withdraw_application` caps | `core/class-roles.php` |
| **Employer** | `wcb_employer` role + `wcb_post_jobs`, `wcb_view_applications`, `wcb_manage_company`, `wcb_access_employer_dashboard` caps | `core/class-roles.php` |
| **Admin** | `manage_options` (WP) → granted every `wcb/*` ability via permission_callback fallback | `core/class-roles.php` and each ability registration |

Pro adds these caps to `wcb_employer` (via `WCB\Pro\Core\ProRoles`): `wcbp_manage_boards`, `wcbp_manage_alerts`, `wcbp_manage_credits`, `wcbp_view_resumes`, `wcbp_manage_ai`. Mapping happens through Abilities API only — no raw `current_user_can()` checks.

In ability terms (current Free + Pro registry):

```
wcb/post-jobs                      → employer + admin
wcb/manage-company                 → employer + admin
wcb/access-employer-dashboard      → employer + admin
wcb/view-applications              → employer + admin
wcb/moderate-jobs                  → admin (employers do NOT moderate)
wcb/apply-jobs                     → candidate + admin
wcb/access-candidate-dashboard     → candidate + admin
wcb/withdraw-application           → candidate + admin
wcb/manage-settings                → admin only
wcb/manage-boards                  → admin + employer (Pro)
wcb/manage-alerts                  → admin (Pro)
wcb/manage-credits                 → admin (Pro)
wcb/view-resumes                   → admin + employer (Pro)
wcb/manage-ai                      → admin (Pro)
```

---

## Entities + canonical fields

The plugin owns four CPTs and a number of post-meta + user-meta keys. Each CPT is documented below with its full field set and the role that can read each field through any public surface (REST, block render, or admin).

Legend:
- `public` — anonymous can read
- `auth` — any logged-in WP user (Subscriber+) can read
- `role-only` — only the named role can read
- `owner-only` — only the row's author/candidate/employer can read
- `admin-only` — only `wcb/manage-settings` holders can read
- `none` — never exposed by the plugin's own surfaces

### `wcb_job` (post type)

`register_post_type` at `wp-career-board/modules/jobs/class-jobs-module.php:42` (not directly read in this audit but called out via post_type config in `class-jobs-meta.php`). `show_in_rest: true`, `public: true`. Postmeta with `show_in_rest: true` registered at `wp-career-board/modules/jobs/class-jobs-meta.php:40-69`.

| Field | Anon | Subscriber | Candidate | Employer (owner) | Employer (other) | Admin | Notes |
|---|---|---|---|---|---|---|---|
| `id`, `title`, `permalink`, `excerpt`, `description`, `created_at`, `updated_at`, `date` | public | public | public | public | public | public | `JobsEndpoint::prepare_item_for_response_array()` lines 954-1003 |
| `status` | public | public | public | public | public | public | `prepare_item_for_response_array` line 959 — including `pending`/`draft` if reachable |
| `rejection_reason` | public | public | public | public | public | public | `prepare_item_for_response_array` lines 949-952 — only when status is draft/trash but emitted to ANY caller of `/wcb/v1/jobs/{id}` |
| `author` (user ID) | public | public | public | public | public | public | `prepare_item_for_response_array` line 960 |
| `company`, `initials`, `trust`, `verified` | public | public | public | public | public | public | lines 968-973 |
| `deadline`, `salary_min`, `salary_max`, `salary_currency`, `salary_type`, `salary_label`, `remote`, `featured` | public | public | public | public | public | public | lines 975-982 |
| `board_id`, `board_currency` | public | public | public | public | public | public | lines 983-984 |
| `location`, `type`, `experience`, `category`, `categories`, `job_types`, `locations`, `experience_slugs`, `tags` | public | public | public | public | public | public | lines 986-997 |
| `thumbnail` | public | public | public | public | public | public | line 998 |
| `apply_url` | public | public | public | public | public | public | line 999 |
| **`apply_email`** | **public** | **public** | **public** | public | **public** | public | line 1000 — **GAP, see Findings F-1.** Employers expect this to be a routing inbox visible only to applicants who actually apply. Today it's served on the cold `/wcb/v1/jobs/{id}` cards to anonymous scrapers. |
| **`lat`, `lng`** | **public** | **public** | **public** | public | **public** | public | lines 1001-1002 — emitted with full precision. Acceptable for office-pin maps but a privacy concern when the job's location is a private remote employer's home address. |
| Postmeta `_wcb_*` via `/wp/v2/wcb_job/{id}` (WP REST) | auth | auth | auth | auth | auth | auth | `register_post_meta` show_in_rest line 61 — exposes raw `_wcb_apply_email`, `_wcb_company_id`, `_wcb_company_name` to any logged-in user with edit_posts. **GAP F-2.** |

### `wcb_application` (post type)

`register_post_type` at `wp-career-board/modules/applications/class-applications-module.php:60`. `public: false`, `show_in_rest: true`. Postmeta registered at `wp-career-board/modules/applications/class-applications-meta.php:40-65`.

Surfaces that read application data:
- `/wcb/v1/applications/{id}` — `class-applications-endpoint.php:326` (`get_item`)
- `/wcb/v1/applications/{id}/status` — line 346 (`update_status`)
- `/wcb/v1/jobs/{id}/applications` — `class-jobs-endpoint.php:775` (`get_applications`)
- `/wcb/v1/employers/{id}/applications` — `class-employers-endpoint.php:665` (`get_applications`)
- `/wcb/v1/candidates/{id}/applications` — `class-applications-endpoint.php:398` (`get_candidate_applications`)
- `/wp/v2/wcb_application/{id}` (WP core, postmeta exposed)

| Field | Applicant (candidate) | Job owner (employer) | Other employer | Admin | Notes |
|---|---|---|---|---|---|
| `id`, `job_id` | role-only | role-only | none | admin | applicant view: `prepare_application` line 850; job-owner view: same row |
| `candidate_id` | role-only | role-only | none | admin | line 852 |
| `cover_letter` | role-only | role-only | none | admin | line 853 — sensitive applicant prose |
| `resume_id`, `resume_url` | role-only | role-only | none | admin | lines 854-855 |
| `status`, `status_history` | role-only | role-only | none | admin | lines 856-857 — **status_history includes WHO changed status, WHEN, FROM/TO**. This audit trail leaks to the candidate, who can see internal employer reviewer activity. **GAP F-3.** |
| `submitted_at` | role-only | role-only | none | admin | line 858 |
| `applicant_name` (employer-side responses) | n/a | role-only | none | admin | `JobsEndpoint::get_applications` line 800-802; `EmployersEndpoint::get_applications` line 719-721 |
| `applicant_email` (employer-side responses) | n/a | role-only | none | admin | same locations 803-805 / 722-724 |
| `_wcb_status_log` via WP REST | auth | auth | auth | auth | meta has `show_in_rest: true` and write `auth_callback` only — read is gated by post-edit cap on the application post. Public-style read for any user with edit_posts. **GAP F-4.** |
| `_wcb_guest_email`, `_wcb_guest_name` | n/a | role-only | none | admin | applications-meta line 47-48; via WP REST exposed to anyone with edit_posts |
| `_wcb_tags` (Pro) | n/a | role-only | none | admin | written by Pipeline module `pipeline-endpoint.php:149` |
| `_wcb_stage_id` (Pro) | n/a | role-only | none | admin | written `pipeline-endpoint.php:90` |

The `get_item_permissions_check` at `class-applications-endpoint.php:728-740` correctly limits read to: applicant ∪ job owner ∪ admin. The `update_status` permission at line 753-770 is correct (job owner ∪ admin, requires `wcb/view-applications` ability).

### `wcb_company` (post type)

`register_post_type` at `wp-career-board/modules/employers/class-employers-module.php:132-157`. `public: true`, `has_archive: 'companies'`, `show_in_rest: true`. Rendered by `wp-career-board/blocks/company-profile/render.php` and the `/wcb/v1/companies` directory. No `register_post_meta()` for company meta — each post-meta key is read directly by the endpoints.

| Field | Anon | Subscriber | Candidate | Employer (owner) | Employer (other) | Admin | Notes |
|---|---|---|---|---|---|---|---|
| `id`, `name`, `permalink`, `description`, `tagline` | public | public | public | public | public | public | `EmployersEndpoint::prepare_company` line 916-921; block at `blocks/company-profile/render.php:33-35` |
| `logo` (post thumbnail URL) | public | public | public | public | public | public | line 920 / render line 45 |
| `website`, `linkedin`, `twitter` | public | public | public | public | public | public | lines 922, 928-929 / render lines 36-38 |
| `industry`, `size`, `hq`, `company_type`, `founded` | public | public | public | public | public | public | lines 923-927 |
| `trust_level` | public | public | public | public | public | public | line 930 — **acceptable** (publicly meaningful badge) |
| `_wcb_company_id` link to user account | public | public | public | public | public | public | exposed indirectly via `_wcb_company_id` post meta on jobs (line 524) and via `prepare_item` job_count_by_author (line 313) |

Listing endpoint `/wcb/v1/companies` at `class-companies-endpoint.php:36-44` is `__return_true`. Every company (and the author user-id linkage) is enumerable.

### `wcb_resume` (post type, Pro-driven)

`register_post_type` at `wp-career-board/modules/candidates/class-candidates-module.php:84-126`. `public` and `publicly_queryable` are toggled by the `resume_archive_enabled` setting + `wcb_resume_archive_enabled` filter. `show_in_rest: true` ALWAYS regardless of archive toggle.

When `resume_archive_enabled` is true, every `wcb_resume` post is reachable at `/?p=<id>` AND through `/wp-json/wp/v2/wcb_resume/{id}`.

Pro REST routes at `wp-career-board-pro/api/endpoints/class-resume-endpoint.php`. Pro block render at `wp-career-board-pro/blocks/resume-single/render.php`.

| Field | Anon | Subscriber | Candidate (self) | Candidate (other) | Employer | Admin | Notes |
|---|---|---|---|---|---|---|---|
| `id`, `title`, `permalink`, `summary`, `sections` (full resume body) | **public** | **public** | role-only | **public** | **public** | admin | `class-resume-endpoint.php::list_public_resumes` (no auth, line 49) and `resume-single/render.php` (no gate). **GAP F-5 — block render does NOT check `_wcb_resume_public`. ANY direct permalink loads the resume even when archive is on AND the resume's `_wcb_resume_public` is empty.** |
| `candidateName`, `candidateId`, `avatar`, `jobTitle`, `location`, `skills` | public | public | role-only | public | public | admin | `ResumeModule::build_archive_item` line 1086-1097 |
| `_wcb_resume_summary` | public when public flag set | same | self | public | public | admin | meta read at `class-resume-endpoint.php:445` |
| `_wcb_resume_attachment_id` (PDF binary) | n/a | n/a | role-only | none | role-only via `/wcb/v1/resumes/{id}/pdf` | admin | gated by `can_access_resume` line 280-308 |
| `_wcb_resume_public` toggle | reads expose this implicitly | same | self can change | public can read indirectly | n/a | admin | `class-resume-endpoint.php::save_resume` line 489 |
| `_wcb_resume_data` user meta (linkedin, github, twitter, location, headline) | **public via resume-single** | same | self | **public** | **public** | admin | render line 44-52 — pulls from `_wcb_resume_data`. **GAP F-6 — exposes social URLs and location even on private resumes that are only public via direct permalink leak.** |
| `experience`, `education_college`, `certifications`, `skills`, `languages`, `portfolio` | **public** | same | self | **public** | **public** | admin | render line 40 + lines 257+. Same gap as F-5 — full resume body to anonymous. |
| `_wcb_open_to_work` user meta | **public** | same | self | **public** | **public** | admin | render line 45 — boolean flag visible on hero |

### `wcb_board` (post type, Free)

`register_post_type` at `modules/boards/class-boards-module.php:42`. `public: false`, `show_in_rest: true`. Used to bucket jobs by employer-defined "boards" (default board, freelance board, etc.).

| Field | Anon | Subscriber | Candidate | Employer | Admin | Notes |
|---|---|---|---|---|---|---|
| `id`, `title`, `currency` via `/wcb/v1/boards/{id}` | public | public | public | public | public | `BoardsProEndpoint::get_board` line 103, `permission_callback: __return_true` line 40 |
| Stage list `/wcb/v1/boards/{id}/stages` GET | none | role-only (logged-in) | role-only | role-only owner OR `wcb/manage-boards` | admin | permission line 152-165 — requires login + ownership OR ability |
| Stage CRUD POST/PUT/DELETE | none | none | none | role-only with `wcb/manage-boards` | admin | line 134-139 |
| Credit cost `/wcb/v1/boards/{id}/credit-cost` (manifest entry) | n/a | n/a | n/a | n/a | n/a | **route NOT in code** — manifest stale, no implementation |

### User data via WP core `/wp-json/wp/v2/users`

WP core exposes the user list to anyone with `list_users` cap (admin) and individual user records to anyone with `edit_users` cap. With `?context=embed` the route returns `id, name, url, description, link, slug, avatar_urls` to ANY logged-in user — **no email**.

Plugin does NOT add `_wcb_*` user meta to the WP core REST schema, so candidate `_wcb_resume_data`, `_wcb_company_id`, `_wcb_open_to_work`, `_wcb_profile_visibility`, `_wcb_bookmark` are NOT served via `/wp/v2/users` directly. They reach the wire only through the plugin's own routes. Good.

But: `prepare_candidate()` at `wp-career-board/api/endpoints/class-candidates-endpoint.php:466-491` returns `display_name`, `bio`, `avatar`, `resume_data`, `profile_visibility` — and `get_item_permissions_check` is `return true;` (line 425). The visibility is enforced inside the callback (line 287-298) but the response shape itself is anonymous-readable for any candidate with `profile_visibility: public` (the default).

Custom fields installed by Pro's field-builder set `visibility: 'public' | 'employer_only' | 'admin_only'`, but the visibility filter is enforced only when fields render through the Pro module. The raw `wcb_field_values` table is reachable through `/wcb/v1/fields/values/{post_id}` per the manifest — **but the route is not in code** (Pro endpoint manifest is stale). Custom field values stored in postmeta on `wcb_job` posts still reach the wire through the `wcb_job_response` filter Pro hooks (`modules/fields/class-fields-module.php`).

---

## REST endpoints

### Free namespace `wcb/v1`

| Route | Method | permission_callback | Roles allowed | Fields returned | Concerns |
|---|---|---|---|---|---|
| `/jobs` | GET | `__return_true` | anonymous | id, title, description, salary, **apply_email**, lat, lng (full set above) | F-1 (apply_email leak), see field table |
| `/jobs` | POST | `wcb/post-jobs` | employer + admin | full job | OK |
| `/jobs/{id}` | GET | `__return_true` | anonymous | full job + rejection_reason | F-7: emits `rejection_reason` to anonymous when status is draft/trash, leaking moderator notes |
| `/jobs/{id}` | PUT/PATCH | `update_item_permissions_check` (author OR same company OR admin with `wcb/post-jobs`) | role-only | full job | OK |
| `/jobs/{id}` | DELETE | `delete_item_permissions_check` (same as update) | role-only | `{deleted, id}` | OK |
| `/jobs/{id}/bookmark` | POST | inline `is_user_logged_in()` | any logged-in | `{bookmarked, job_id}` | OK |
| `/jobs/{id}/applications` | GET | `wcb/view-applications` | employer + admin | applicant_name, applicant_email, cover_letter, status, resume_url | **F-8 — does NOT scope to job-owner. Any user with `wcb/view-applications` (i.e. any employer with that ability site-wide) can list applications for any job, even one they did not author.** |
| `/jobs/{id}/apply` | POST | `submit_permissions_check` (anon allowed; employers cannot apply) | anonymous + candidate | submitted application id | OK |
| `/applications/{id}` | GET | applicant ∪ job-owner ∪ admin | role-only | id, job_id, candidate_id, cover_letter, resume_id, resume_url, status, **status_history**, submitted_at | F-3 (status_history leaks reviewer audit trail to candidate) |
| `/applications/{id}` | DELETE | `withdraw_permissions_check` (candidate owner + setting toggle + admin) | role-only | `{deleted, id}` | OK |
| `/applications/{id}/status` | PUT/PATCH | `wcb/view-applications` AND (job-owner OR admin) | role-only | `{id, status}` | OK |
| `/applications/{id}/stage` (Pro) | PUT | `wcb/moderate-jobs` | admin only | `{id, stage_id}` | **F-9 — does NOT verify the actor is the application's job-owner. Any moderator (admin) can move ANY application across pipelines, even on jobs they don't own. Acceptable for admin, but if `wcb/moderate-jobs` is granted to a non-admin role, this becomes IDOR.** |
| `/candidates/{id}` | GET | `__return_true`, visibility enforced inside | anon when public | id, display_name, bio, profile_visibility, avatar, resume_data | OK (visibility check inside) |
| `/candidates/{id}` | PUT/PATCH | self ∪ admin | role-only | same | OK |
| `/candidates/{id}/applications` | GET | self ∪ admin | role-only | id, jobTitle, jobPermalink, company, status, created_at, updated_at | OK |
| `/candidates/{id}/bookmarks` | GET | self ∪ admin | role-only | id, title, permalink, company, location, type | OK |
| `/candidates/register` | POST | `__return_true` | anonymous | `{user_id, dashboard_url}` | OK (rate-limit could be added but out of scope) |
| `/candidates/me/privacy/{action}` | POST | `me_logged_in_check` | any logged-in | `{request_id, action, email, pending}` | OK |
| `/candidates/resume-upload` | POST | inline `is_user_logged_in()` | any logged-in | `{attachment_id}` | **F-10 — uploads attached to user but no MIME re-check before media_handle_upload, and no per-user rate limit. Subscribers/candidates can flood media library.** |
| `/companies` | GET | `__return_true` | anonymous | id, name, logo, tagline, industry, size, hq, trust, permalink, **author_id (implicit via job_count)**, job_count | OK on its face — but discloses every employer user_id (job-counts indexed by author). |
| `/companies/{id}/trust` | POST | `wcb/manage-settings` | admin | `{id, trust_level}` | OK |
| `/employers/register` | POST | `__return_true` | anonymous | `{user_id, company_id, dashboard_url}` | OK |
| `/employers` | POST | `wcb/manage-company` | role-only | full company | OK |
| `/employers/{id}` | GET | `__return_true` | anonymous | full company prepare_company | **F-11 — listing+single endpoint exposes the SAME field set as `/companies` (id, name, description, logo, tagline, website, industry, size, hq, company_type, founded, linkedin, twitter, trust_level, permalink) regardless of whether the company is published. Acceptable for published, but draft companies are also readable through this route since `get_post()` doesn't filter by status.** |
| `/employers/{id}` | PUT/PATCH | `update_item_permissions_check` (owner OR admin, both via `wcb/manage-company`) | role-only | same | OK |
| `/employers/{id}/jobs` | GET | `__return_true` | anonymous | jobs scoped to that company; owner sees pending+draft, others see publish only | OK |
| `/employers/{id}/applications` | GET | owner + `wcb/view-applications` OR admin | role-only | id, job_id, job_title, applicant_name, applicant_email, status, submitted_at | OK |
| `/employers/{id}/logo` | POST | `update_item_permissions_check` | role-only | `{logo_url}` | OK |
| `/employers/me/jobs` | GET | `wcb/access-employer-dashboard` | role-only | jobs authored by current user | OK |
| `/import/status`, `/import/run` | GET/POST | `wcb/manage-settings` | admin | counts / batch result | OK |
| `/admin/dismiss-banner` | POST | `wcb/manage-settings` | admin | `{dismissed: true}` | OK |
| `/admin/emails/test` | POST | `wcb/manage-settings` | admin | `{sent, to, logged}` | OK |
| `/admin/emails/log` | GET | `wcb/manage-settings` | admin | id, user_id, event_type, channel, recipient, subject, status, sent_at | **F-12 — `recipient` is a real customer email pulled from the `wp_wcb_notifications_log.payload` JSON. Admin scope is correct, but the response is NOT redactable — and Pro extends this through `wcb_admin_email_log_response`, so addons can see candidate emails too.** |
| `/settings/app-config` | GET | `__return_true` | anonymous | site_name, site_url, plugin_version, pro_version, is_pro_active, is_pro_licensed, per_page, currency, moderation_mode, allow_withdraw, feature_toggles, timezone, locale, rest_namespace, captcha_required | OK — non-sensitive bootstrap |
| `/search` | GET | `__return_true` | anonymous | delegates to `/jobs` | inherits F-1 |
| `/wizard/create-pages`, `/wizard/sample-data`, `/wizard/complete`, `/wizard/remove-sample-data` | POST | `wizard_permission_check` (`wcb/manage-settings`) | admin | wizard responses | OK |
| `/jobs/{id}/approve`, `/jobs/{id}/reject` | POST | `wcb/moderate-jobs` (with `wcb_moderate_jobs_ability_check` filter) | admin (and any context-scoped grantee) | `{id, status}` | OK |

### Pro namespace `wcb/v1` (extends Free)

| Route | Method | permission_callback | Roles allowed | Fields returned | Concerns |
|---|---|---|---|---|---|
| `/wizard/activate-license`, `/wizard/setup-credits`, `/wizard/setup-ai`, `/wizard/create-pro-pages` | POST | `wizard_permission_check` (`wcb/manage-settings`) | admin | wizard responses | OK |
| `/notifications` | GET | `is_logged_in` | any logged-in | id, event_type, message, link, is_read, created_at | OK (scoped to current user inside callback) |
| `/notifications/{id}/read`, `/notifications/read-all` | PUT/POST | `is_logged_in` | any logged-in | `{success: true}` | OK |
| `/alerts` | GET | `logged_in_check` | any logged-in | id, user_id, board_id, search_query, filters, frequency, created_at | OK (scoped to current user inside `get_alerts`) |
| `/alerts` | POST | `logged_in_check` | any logged-in | `{id}` | OK |
| `/alerts/{id}` | PUT/DELETE | `ownership_check` | role-only | `{updated/deleted}` | OK |
| `/ai/match` | POST | `logged_in_check` (rate-limited 30/h) | any logged-in | match scores | **F-13 — AI route burns license quota for any logged-in subscriber, even one without `wcb_apply_jobs`. Subscribers can spam the AI driver until rate-limit hits.** |
| `/candidates/{id}/matches` | GET | self ∪ `wcb/manage-ai` | role-only | match scores | OK |
| `/jobs/{id}/matches` | GET (manifest) / `/ai/ranked-applications/{job_id}` | `wcb/view-applications` | employer + admin | ranked apps | OK on its face — but rate-limit shares one bucket with `/ai/match`, so a subscriber can starve the employer's quota |
| `/jobs/ai-description` | POST | `post_jobs_check` (`wcb/post-jobs`) | employer + admin | `{description}` | OK |
| `/fields/groups`, `/fields/groups/{id}`, `/fields/groups/{group_id}/fields`, `/fields/{id}`, `/fields/reorder` | GET/POST/PUT/DELETE | `manage_permissions_check` (`wcb/manage-boards`) | employer + admin | field group + definition rows | **F-14 — employers can read any other employer's field groups. The `manage_permissions_check` checks ABILITY, not OWNERSHIP. Multi-tenant boards leak. Same applies to all five routes.** |
| `/applications/{id}/stage` | PUT | `wcb/moderate-jobs` | admin (per Free) | `{id, stage_id}` | F-9 |
| `/jobs/{id}/kanban` | GET | `wcb/view-applications` | employer + admin | columns array including app id, candidate_id, submitted_at, tags | F-8 same pattern — does not scope to job-owner |
| `/credits/packages` | GET | `__return_true` | anonymous | id, title, credits, price | OK |
| `/employers/{id}/credits` | GET | self ∪ `wcb/manage-credits` | role-only | balance, ledger | OK |
| `/geocode` | GET | `__return_true` (license-gated through `pro_check` no-op) | anonymous | lat, lng | **F-15 — geocode is rate-limited only by 24-hour transient cache. Any anonymous visitor can scrape lat/lng for any address through the plugin's licensed Google API quota.** |
| `/resumes` | GET | `__return_true` | anonymous | full resume archive cards | F-5 (when archive enabled, anonymous reads private resumes through this route too if `_wcb_resume_public` filter is bypassed by `includePrivate` block attribute — ResumeModule respects it correctly here, so this route alone is OK) |
| `/candidates/{id}/resumes` | GET/POST | self ∪ `wcb/view-resumes` ∪ admin | role-only (employer + admin) | resume list / created resume | OK |
| `/resumes/{id}` | GET | self ∪ `wcb/view-resumes` ∪ admin | role-only | id, title, permalink, summary, sections | OK |
| `/resumes/{id}` | PUT/DELETE | self ∪ `wcb/view-resumes` ∪ admin | role-only | structured response | **F-16 — `wcb/view-resumes` (granted to employers) gates EDITS too via `can_access_resume` line 280-308. An employer with that ability can DELETE a candidate's resume. Should be self-only on PUT/DELETE.** |
| `/resumes/{id}/pdf` | GET/POST | `can_access_resume` | role-only | binary PDF / replacement upload | OK |
| `/boards/{id}` | GET | `__return_true` | anonymous | `{id, title, currency}` | OK |
| `/boards/{id}/stages` | GET | logged-in + (owner OR `wcb/manage-boards`) | role-only | stage rows | OK |
| `/boards/{id}/stages` | POST | `wcb/manage-boards` | employer + admin | `{id}` | F-14 same pattern — no ownership check |
| `/boards/{id}/stages/{stage_id}` | PUT/DELETE | `wcb/manage-boards` | employer + admin | `{updated/deleted}` | F-14 |
| `/analytics/credits.csv` | GET | `wcb/manage-credits` (license-gated) | admin | full ledger CSV (employer_id + dates + notes) | OK on its face. Admin gating correct. |

---

## Frontend block render output

Inventory of every block's rendered HTML — what does the page emit to anonymous visitors when the block is on a public page?

### Free — `wp-career-board/blocks/`

| Block | Public-anon exposure | Auth-only? | Notes |
|---|---|---|---|
| `job-listings/render.php` | full job cards (title, company, salary, location, apply_url, apply_email if shown) | no | inherits F-1 — apply_email is part of job-card data |
| `job-form/render.php` | empty form (no data) | no | OK |
| `job-form-simple/render.php` | empty form | no | OK |
| `job-single/render.php` | full job title, company, salary, description, apply chip — exposes `apply_email`/`apply_url` from postmeta as a public mailto/href | no | acceptable — that's the apply target. Also exposes lat/lng for the embed map. F-1/F-15 already cover this. |
| `job-search/render.php`, `job-search-hero/render.php`, `job-filters/render.php`, `job-stats/render.php` | search UI; reads aggregate counts | no | OK |
| `featured-jobs/render.php`, `recent-jobs/render.php` | full job cards | no | OK (same shape as `/jobs`) |
| `candidate-dashboard/render.php` | gated `is_user_logged_in()` + `wcb/access-candidate-dashboard` (line 30-34); else login gate | yes | OK |
| `employer-dashboard/render.php` | gated same way at line 13-30 + `wcb/manage-company` | yes | OK |
| `employer-registration/render.php` | empty registration form | no | OK |
| `company-profile/render.php` | full company profile + open jobs list (10 per page) + author user_id seeded into Interactivity state line 271 | no | acceptable — companies are public profiles, but `_wcb_company_id` linkage to user_id is exposed |
| `company-archive/render.php` | full company directory | no | acceptable |

### Pro — `wp-career-board-pro/blocks/`

| Block | Public-anon exposure | Notes |
|---|---|---|
| `ai-chat-search/render.php` | empty search shell, hits `/ai/match` after login | OK |
| `application-kanban/render.php` | seeds Interactivity state with `jobId`, `kanbanUrl`, `stageUrl`, nonce. Renders empty `<div id="wcb-ak-columns">` — actual data fetched via REST. **No frontend permission gate** (lines 47-66) — block emits the kanban shell to anonymous visitors. Data is empty for anonymous because `/jobs/{id}/kanban` rejects them, but the JS will continue to call REST and render nothing. **F-17 — should hard-gate this block to `wcb/view-applications` or self-render an empty placeholder, not leak the existence of `kanbanUrl`/nonce to anonymous scrapers.** |
| `board-switcher/render.php` | board selector — anonymous reads currency from `/boards/{id}` | OK |
| `credit-balance/render.php` | balance widget, gated to logged-in employer (need to verify in render) | spot-check: employer-only context expected |
| `featured-candidates/render.php`, `featured-companies/render.php` | top-N candidate/company cards | inherits F-5 if candidate side serves private resumes |
| `job-alerts/render.php`, `my-applications/render.php` | candidate-only utility | should be gated to candidate role |
| `job-map/render.php`, `resume-map/render.php` | map of jobs/resumes; reads lat/lng | inherits F-15 (lat/lng leak), and F-5 for resume-map |
| `open-to-work/render.php` | toggle for `_wcb_open_to_work` user meta | candidate-only |
| `resume-archive/render.php` | full resume cards. Default `includePrivate: false` (block attribute) so archive respects `_wcb_resume_public` flag. **F-18 — block attribute `includePrivate: true` lets a page editor render private resumes publicly. This is by design but is a foot-gun: a customer who accidentally toggles it in the editor exposes every candidate's data to anonymous web visitors.** |
| `resume-builder/render.php` | candidate-only resume editor | gated inside |
| `resume-form-simple/render.php` | candidate-only quick form | gated inside |
| `resume-search-hero/render.php` | search shell | OK |
| **`resume-single/render.php`** | full resume body — name, headline, location, linkedin, github, twitter, summary, work experience, education, certifications, skills, languages, portfolio | **F-5 — block does NOT check `_wcb_resume_public`. Direct permalink renders ANY resume, public or private, when the archive is enabled. Combined with F-6 (social URLs included), this is the most material privacy gap in the plugin pair.** |

---

## AJAX handlers

Per CLAUDE.md (F-5/F-6, P-9): zero. Confirmed by re-grep:

```
$ grep -rn "wp_ajax_\|admin-ajax" wp-career-board wp-career-board-pro --include='*.php' | grep -v audit | grep -v vendor
(zero matches)
```

Action: **None.** The `admin-ajax.php` migration to REST is complete.

---

## Admin pages

All admin pages are top-level `wp-career-board` parent + `wcb_manage_settings` cap. Verified at `wp-career-board/admin/class-admin.php:58-115` and `wp-career-board-pro/admin/class-pro-admin.php:130-188`.

| Page | Cap | Renders | Notes |
|---|---|---|---|
| Career Board (dashboard) | `wcb_manage_settings` | counts + recent activity | OK |
| Jobs (`wcb-jobs`) | `wcb_manage_settings` | job list, full data including `_wcb_*` meta | OK |
| Applications (`wcb-applications`) | `wcb_manage_settings` | application list with applicant_email, cover_letter, resume_url | OK (admin-only) |
| Candidates (`wcb-candidates`) | `wcb_manage_settings` | resume list, candidate user data | OK |
| Companies (`wcb-companies`) | `wcb_manage_settings` | company list + author email link | OK |
| Employers (`wcb-employers`) | `wcb_manage_settings` | employer user list | OK |
| Settings (`wcb-settings`) | `wcb_manage_settings` | settings tabs (general, jobs, applications, emails, credits, ai-settings, job-feed, resumes) | OK |
| Emails (`wcb-emails`) | `wcb_manage_settings` | email log viewer (uses `/admin/emails/log`) | F-12 (email recipients in payload) |
| Setup Wizard (`wcb-setup-wizard`) | `wcb_manage_settings` | initial setup | OK |
| Resumes (Pro, `edit.php?post_type=wcb_resume`) | `wcb_manage_settings` (via map_meta_cap) | resume CPT list table | OK |
| Credits, AI Settings, Job Feed (Pro) | `wcb_manage_settings` | redirect-only stubs to settings tabs | OK |

Taxonomy submenus (Job Categories, Types, Locations, Experience, Tags) all gated `wcb_manage_settings` at `class-admin.php:131-135`.

---

## Findings — actual exposure gaps

Severity scale: **Critical** = data the user did NOT consent to share, exposed to roles that should not see it; **Important** = data exposure beyond what UX implies; **Minor** = quota/abuse issues, future-proofing.

### Critical

**F-5 — Private `wcb_resume` posts publicly readable when archive enabled.**
- *File:* `wp-career-board-pro/blocks/resume-single/render.php` lines 17-32 (no `_wcb_resume_public` check, no auth gate)
- *Surface:* the rendered HTML of `?p=<resume_id>` and `/resume/<slug>/`
- *Trigger:* admin enables `wcb_settings.resume_archive_enabled`. Once on, the CPT becomes `publicly_queryable` (`modules/candidates/class-candidates-module.php:111-112`) and ANY logged-out visitor with a permalink reads the full resume body — even resumes that the candidate explicitly toggled private via `_wcb_resume_public = ''`.
- *Recommended fix:* gate `resume-single/render.php` on `'1' === get_post_meta( $post->ID, '_wcb_resume_public', true ) || (int) $post->post_author === get_current_user_id() || wp_is_ability_granted( 'wcb/view-resumes' )`.

**F-6 — Resume social URLs and location served on private resumes via direct permalink.**
- *File:* `wp-career-board-pro/blocks/resume-single/render.php` lines 44-52
- *Surface:* same as F-5 — rendered HTML
- *Detail:* `_wcb_resume_data` user meta (linkedin, github, twitter, location, headline, website) is read unconditionally and printed. Independent of F-5: even if the post is non-public, this meta still bleeds through any inadvertent permalink scrape.
- *Recommended fix:* same gate as F-5.

**F-1 — Job `apply_email` exposed in `/wcb/v1/jobs` cold list response.**
- *File:* `wp-career-board/api/endpoints/class-jobs-endpoint.php:1000`
- *Surface:* `/wcb/v1/jobs`, `/wcb/v1/jobs/{id}`, `/wcb/v1/search`, every block that renders job cards
- *Detail:* the field is included on every job's prepared response unconditionally. Anonymous scrapers harvest the employer's recruiter inbox in bulk for spam.
- *Recommended fix:* Either (a) drop `apply_email` from the public response and serve it only when the candidate clicks Apply (move into the apply flow), or (b) keep it on the single-job route but obfuscate (e.g. `mailto:apply+<token>@careerboard.example`).

**F-3 — Application `status_history` (audit trail) leaks to candidate.**
- *File:* `wp-career-board/api/endpoints/class-applications-endpoint.php:857` (`status_history`)
- *Surface:* `/wcb/v1/applications/{id}` GET — the candidate is allowed to read their own application
- *Detail:* `prepare_application` returns `status_history` which contains `{from, to, by: <user_id>, at}` rows. The candidate sees the user_id of every reviewer plus exact timestamps — internal employer activity that the candidate has no business reading.
- *Recommended fix:* split prepare_application into a self-view (no status_history) and an employer/admin view (full).

### Important

**F-2 — `_wcb_*` postmeta on `wcb_job` exposed via WP core `/wp/v2/wcb_job/{id}`.**
- *File:* `wp-career-board/modules/jobs/class-jobs-meta.php:57-69`
- *Surface:* WP core's `/wp/v2/wcb_job/{id}` endpoint (active because `show_in_rest: true`)
- *Detail:* Every meta key including `_wcb_apply_email`, `_wcb_company_id`, `_wcb_company_name`, `_wcb_salary_min/max` is registered with `show_in_rest: true`. WP serves them to logged-in users. Customer expectation is that the plugin's `/wcb/v1/jobs` is the canonical contract — they don't expect `/wp/v2/wcb_job` to also work.
- *Recommended fix:* either gate via `auth_callback` for read (currently only gates write), or set the post type's `show_in_rest: false` and serve everything through `/wcb/v1`.

**F-4 — Application `_wcb_status_log` postmeta exposed via WP core `/wp/v2/wcb_application/{id}`.**
- *File:* `wp-career-board/modules/applications/class-applications-meta.php:52`
- *Surface:* WP core's REST endpoint
- *Detail:* Same root cause as F-2. The `auth_callback` only gates writes. Reads fall through to WP's default which checks edit_post on the application — meaning admins and post-edit-cap users see the audit trail.
- *Recommended fix:* register meta with `show_in_rest: false`. Plugin's own `/wcb/v1/applications/{id}` is the canonical surface.

**F-7 — `rejection_reason` on jobs leaks to anonymous when status is draft/trash.**
- *File:* `wp-career-board/api/endpoints/class-jobs-endpoint.php:949-952` and `:966`
- *Surface:* `/wcb/v1/jobs/{id}` GET — `permission_callback: __return_true`
- *Detail:* The endpoint allows anonymous reads of any post regardless of post_status (no `'post_status' => 'publish'` filter). For draft/trash status, `rejection_reason` is filled and emitted. Anonymous scrapers can harvest moderator notes for rejected jobs.
- *Recommended fix:* return 404 in `get_item` when the post is not in `array('publish')` AND requester is not the author or admin.

**F-8 — `/wcb/v1/jobs/{id}/applications` does not scope to job-owner.**
- *File:* `wp-career-board/api/endpoints/class-jobs-endpoint.php:897-899`
- *Surface:* the endpoint itself
- *Detail:* `view_applications_permissions_check` only verifies the actor holds `wcb/view-applications`. It does NOT verify the actor is the author of the job in `$request['id']`. Any employer can list applications for any other employer's job. IDOR.
- *Recommended fix:* mirror the pattern from `update_permissions_check` at line 753: load the job, check `(int) $job->post_author === get_current_user_id() || wcb/manage-settings`.

**F-9 — `/applications/{id}/stage` (Pro) does not scope to job-owner.**
- *File:* `wp-career-board-pro/api/endpoints/class-pipeline-endpoint.php:211-213` (`manage_permissions_check`)
- *Surface:* PUT `/applications/{id}/stage`
- *Detail:* Only checks `wcb/moderate-jobs`. If a non-admin role is granted that ability (which the `wcb_moderate_jobs_ability_check` filter explicitly supports for context-scoped grants), they can move ANY application across ANY pipeline.
- *Recommended fix:* load the application, look up its `_wcb_job_id`, verify the actor is the job's author OR has `wcb/manage-settings`.

**F-12 — Email log `/admin/emails/log` returns customer recipients in full.**
- *File:* `wp-career-board/api/endpoints/class-admin-endpoint.php:236-303`
- *Surface:* admin page only, but pulled into UI verbatim
- *Detail:* the `recipient` field comes directly from `wp_wcb_notifications_log.payload['to']` and is shown to admins. Compliance-wise this is OK (admins can already see all data), but the response is also extended via `wcb_admin_email_log_response` which means add-ons can read these emails too. Worth flagging.
- *Recommended fix:* none required for admin-only routes; document that the filter receives PII so addon authors handle accordingly. Add a redact toggle for compliance staff.

**F-14 — Pro field-builder routes don't scope by board owner.**
- *File:* `wp-career-board-pro/api/endpoints/class-fields-endpoint.php:180-185`
- *Surface:* all 5 `/fields/*` routes
- *Detail:* `manage_permissions_check` only checks `wcb/manage-boards` (granted to every employer). Employer A can list, edit, and delete employer B's field groups and definitions. Multi-tenant leak.
- *Recommended fix:* require admin OR (employer who owns the target board). Compute board ownership via the `wcb_field_groups.board_id` row → `wcb_board.post_author`.

**F-16 — Employers can DELETE candidate resumes via `/resumes/{id}` DELETE.**
- *File:* `wp-career-board-pro/api/endpoints/class-resume-endpoint.php:295-308`
- *Surface:* DELETE `/wcb/v1/resumes/{id}`
- *Detail:* `can_access_resume` allows any employer with `wcb/view-resumes` to pass the gate, regardless of HTTP method. The same callback is wired to GET, PUT, AND DELETE on `/resumes/{id}`. Employer accidentally hits DELETE and the candidate's resume is gone.
- *Recommended fix:* split into `can_view_resume` (self ∪ employer-with-view ∪ admin) and `can_modify_resume` (self ∪ admin only). Wire DELETE/PUT to the second.

**F-17 — Application kanban block leaks `kanbanUrl` and nonce to anonymous.**
- *File:* `wp-career-board-pro/blocks/application-kanban/render.php:33-66`
- *Surface:* rendered HTML on any page that embeds `<!-- wp:wcb/application-kanban -->`
- *Detail:* Block emits Interactivity state with `kanbanUrl`, `stageUrl`, and `nonce` to anyone visiting the page. Data fetch fails for anonymous visitors (REST gate works), but block disclosure of the route plus a one-shot nonce is unnecessary. If a page editor accidentally publishes a kanban block on a public page (Elementor preview, content theft), the routes are advertised.
- *Recommended fix:* in render, check `wp_is_ability_granted( 'wcb/view-applications' )` and emit a "this view is for employers" placeholder otherwise.

### Minor

**F-10 — `/candidates/resume-upload` lacks per-user rate limit.**
- *File:* `wp-career-board/api/endpoints/class-applications-endpoint.php:96-100`
- *Surface:* the endpoint
- *Detail:* Allowed for any logged-in user. MIME and size are checked but no rate limit. A subscriber/candidate could flood the media library.
- *Recommended fix:* add `set_transient( 'wcb_resume_uploads_' . $user_id, $count, HOUR_IN_SECONDS )` cap (10/hour or so).

**F-11 — `/employers/{id}` does not filter by post_status.**
- *File:* `wp-career-board/api/endpoints/class-employers-endpoint.php:425-435`
- *Surface:* GET `/wcb/v1/employers/{id}` (also `/companies/{id}`-equivalent)
- *Detail:* `get_post()` returns the company regardless of status. Anonymous can read draft/pending/private companies through this endpoint.
- *Recommended fix:* return 404 when post_status is not `publish` AND requester is not owner/admin.

**F-13 — `/ai/match` quota burns from any logged-in subscriber.**
- *File:* `wp-career-board-pro/api/endpoints/class-ai-endpoint.php:179-193`
- *Surface:* POST `/ai/match`
- *Detail:* Rate-limit (30/hour) is per-user, but the endpoint accepts any logged-in user. A subscriber can hit the quota even though they don't have `wcb/apply-jobs` and won't get useful results. Burns the site-owner's OpenAI/AI driver bill.
- *Recommended fix:* require `wcb/apply-jobs` on the permission_callback; subscribers without the candidate role get 403.

**F-15 — `/geocode` is open to anonymous and burns site geocoding quota.**
- *File:* `wp-career-board-pro/api/endpoints/class-geocode-endpoint.php:38-55`
- *Surface:* GET `/wcb/v1/geocode?address=...`
- *Detail:* `permission_callback: __return_true`. Cached 24h per address but distinct addresses bypass the cache. Any anonymous visitor can run the site-owner's Google Maps API key into a billing wall by iterating addresses.
- *Recommended fix:* require `is_user_logged_in()` minimum, plus per-IP rate limit (20/hour). Or move to a server-side resolver fed only by the post-job form, not an open REST endpoint.

**F-18 — `resume-archive` block `includePrivate: true` attribute leaks private resumes.**
- *File:* `wp-career-board-pro/blocks/resume-archive/render.php:24` and `block.json:20-23`
- *Surface:* whatever public page embeds the block with that attribute set
- *Detail:* By design, but the only gate on flipping it is "can the user edit the page". A site-owner who hands page-editing to a junior staffer ships private resumes publicly. Default of `false` mitigates accidental flips.
- *Recommended fix:* require `wcb/manage-settings` to honor `includePrivate: true` (silently ignore the flag for non-admin renders).

---

## Out-of-scope holes (not regressions, but worth noting)

These are upstream WP behaviors or architectural decisions that we do NOT plan to fix in Task 3.7:

- **WP core `/wp/v2/users` exposing emails to logged-in users with `edit_users`.** WordPress core behavior; the plugin does not extend it but does not lock it down either. Customers running an open registration site should disable user enumeration via a security plugin.
- **`/wp/v2/wcb_company` and `/wp/v2/wcb_job` schema endpoints** are accessible by virtue of `show_in_rest: true`. F-2 narrows the meta exposure but the post fields themselves stay served by core. Acceptable — post titles, content, and statuses are public for these CPTs by design.
- **Author URLs and author archives** for `wcb_employer` users at `/?author=N` reveal user_login when WP isn't hardened. Not a plugin issue; site owners run a site-wide hardener.
- **Custom field visibility (`employer_only`, `admin_only`)** at the Pro field-builder layer is enforced only at render time. Admin write-side respects it, but if a customer disables Pro mid-life-cycle, those values still live in the wcb_field_values table. Not a today-leak, but a data-handling decision worth documenting.
- **`wcb/moderate-jobs` granted to context-scoped roles (BuddyPress group admins, etc.)** via the `wcb_moderate_jobs_ability_check` filter is intended customer-extension behavior. Customers who grant it carelessly (e.g. to all subscribers) effectively make every application stage transition globally writable. Out of scope.
- **`/wcb/v1/admin/emails/log` recipients** (F-12) are intentional admin data; redaction is a compliance feature, not a leak fix.

---

## Summary tally

- **Critical (3):** F-1 (apply_email leak), F-3 (status_history leak to candidates), F-5 (resume-single ignores `_wcb_resume_public`)
- **Important (8):** F-2 (`_wcb_*` job meta via WP REST), F-4 (`_wcb_status_log` via WP REST), F-6 (resume social URLs), F-7 (rejection_reason leak), F-8 (`/jobs/{id}/applications` IDOR), F-9 (`/applications/{id}/stage` IDOR), F-12 (email log recipients), F-14 (`/fields/*` cross-tenant), F-16 (employer can DELETE resumes), F-17 (kanban block leaks routes)
- **Minor (5):** F-10 (resume upload no rate limit), F-11 (`/employers/{id}` no post_status filter), F-13 (`/ai/match` open to subscribers), F-15 (geocode open to anon), F-18 (resume-archive `includePrivate` attribute)

(F-12, F-17, F-16 cross between Important. Tally: 3 Critical / 9 Important / 5 Minor = **17 unique findings**.)

These 17 findings drive the Task 3.7 fix plan.

---

## Appendix A — Per-finding remediation map (input for Task 3.7)

Each finding ID below maps to (a) the file:line location of the gap, (b) the recommended fix shape, and (c) the Abilities API hook from Task 3.5 that the fix should use (no raw `current_user_can`).

| ID | File | Line | Recommended fix | Ability check to use |
|---|---|---|---|---|
| F-1 | `wp-career-board/api/endpoints/class-jobs-endpoint.php` | 1000 | Drop `apply_email` from `prepare_item_for_response_array` and serve only via the apply-flow response. Alternative: emit an obfuscated alias. | n/a — field removal |
| F-2 | `wp-career-board/modules/jobs/class-jobs-meta.php` | 56-69 | Either set `'show_in_rest' => false` for all underscore-prefixed keys, or split into a per-key `auth_callback` that reads-checks `wcb/post-jobs` for the post's author and `wcb/manage-settings` otherwise. | `wcb/post-jobs`, `wcb/manage-settings` |
| F-3 | `wp-career-board/api/endpoints/class-applications-endpoint.php` | 845-871 (`prepare_application`) | Add a `$context` param. When the requester is the candidate (line 734 inline check), return without `status_history`. When the requester is the job-owner or admin, include it. | `wcb/view-applications`, `wcb/manage-settings` |
| F-4 | `wp-career-board/modules/applications/class-applications-meta.php` | 51-64 | `'show_in_rest' => false` for `_wcb_status_log` (and ideally for all underscore keys). Plugin's own `/applications/{id}` is the canonical path. | n/a — turn off REST |
| F-5 | `wp-career-board-pro/blocks/resume-single/render.php` | 17-32 | Insert visibility gate immediately after the post-type guard: `$is_public = '1' === (string) get_post_meta( $wcbp_post->ID, '_wcb_resume_public', true ); $is_owner = (int) $wcbp_post->post_author === get_current_user_id(); $can_view = wp_is_ability_granted( 'wcb/view-resumes' ); if ( ! $is_public && ! $is_owner && ! $can_view ) { echo '<p class="wcb-rs-hidden">' . esc_html__( 'This resume is private.', 'wp-career-board-pro' ) . '</p>'; return; }` | `wcb/view-resumes` |
| F-6 | same | 44-52 | Same gate as F-5 (resume_data is rendered in the same block). | `wcb/view-resumes` |
| F-7 | `wp-career-board/api/endpoints/class-jobs-endpoint.php` | 389-402 (`get_item`) | Add status filter: `if ( 'publish' !== $post->post_status && (int) $post->post_author !== get_current_user_id() && ! $this->check_ability( 'wcb/manage-settings' ) ) { return new WP_Error( 'wcb_not_found', ..., array( 'status' => 404 ) ); }` | `wcb/manage-settings` |
| F-8 | `wp-career-board/api/endpoints/class-jobs-endpoint.php` | 897-899 (`view_applications_permissions_check`) | Replace bare ability check with: load `$post = get_post( (int) $request['id'] )`, check `$is_owner = (int) $post->post_author === get_current_user_id()`, return `( $is_owner && $this->check_ability( 'wcb/view-applications' ) ) || $this->check_ability( 'wcb/manage-settings' )`. | `wcb/view-applications`, `wcb/manage-settings` |
| F-9 | `wp-career-board-pro/api/endpoints/class-pipeline-endpoint.php` | 211-213 (`manage_permissions_check`) | Take the request param, load the application's `_wcb_job_id`, load the job, verify `(int) $job->post_author === get_current_user_id()` OR `wcb/manage-settings`. Currently only `wcb/moderate-jobs`. | `wcb/moderate-jobs`, `wcb/manage-settings` |
| F-10 | `wp-career-board/api/endpoints/class-applications-endpoint.php` | 96-100 + handler line 504 | Add hourly transient counter on `get_current_user_id()` cap'd at 10/hour. Mirror the pattern in `class-ai-endpoint.php:181-189`. | `is_user_logged_in()` (existing) |
| F-11 | `wp-career-board/api/endpoints/class-employers-endpoint.php` | 425-435 (`get_item`) | Mirror F-7: 404 when post_status != publish AND requester is not owner/admin. | `wcb/manage-settings` |
| F-12 | `wp-career-board/api/endpoints/class-admin-endpoint.php` | 266-281 (item shaper) | Optional: redact email when a `wcb_redact_email_log` filter returns true. Default off. Compliance-only. | n/a (admin-only route) |
| F-13 | `wp-career-board-pro/api/endpoints/class-ai-endpoint.php` | 179-193 (`logged_in_check`) | Replace `is_user_logged_in()` last-line check with `$this->check_ability( 'wcb/apply-jobs' )`. Subscribers without candidate role are rejected. | `wcb/apply-jobs` |
| F-14 | `wp-career-board-pro/api/endpoints/class-fields-endpoint.php` | 180-185 (`manage_permissions_check`) | Compute board ownership from `$request['board_id']` (POST) or load the field-group row → `board_id` → `wcb_board.post_author`. Allow only when `(int) $board->post_author === get_current_user_id() && wcb/manage-boards` OR `wcb/manage-settings`. | `wcb/manage-boards`, `wcb/manage-settings` |
| F-15 | `wp-career-board-pro/api/endpoints/class-geocode-endpoint.php` | 38-55 | Change `permission_callback` to `'is_user_logged_in'` and add per-user rate limit (20/h). For map blocks served to anonymous visitors, geocode is server-side at job-create time; the runtime endpoint should not be open. | `is_user_logged_in()` |
| F-16 | `wp-career-board-pro/api/endpoints/class-resume-endpoint.php` | 280-308 + route table 110-152 | Split into two callbacks: `can_view_resume` (current logic, used for GET) and `can_modify_resume` (self ∪ admin only, used for PUT/DELETE/PDF-replace). Wire the route table at lines 128-150 accordingly. | `wcb/view-resumes` (read), `wcb/manage-settings` (admin override) |
| F-17 | `wp-career-board-pro/blocks/application-kanban/render.php` | 24-32 | Add ability check before `wp_interactivity_state`: `if ( ! is_user_logged_in() || ! wp_is_ability_granted( 'wcb/view-applications' ) ) { echo '<p class="wcb-ak-placeholder">' . esc_html__( 'Sign in as the employer to view this kanban.', 'wp-career-board-pro' ) . '</p>'; return; }`. | `wcb/view-applications` |
| F-18 | `wp-career-board-pro/blocks/resume-archive/render.php` | 24 | Override the block attribute when not admin: `$wcbp_include_priv = ! empty( $attributes['includePrivate'] ) && wp_is_ability_granted( 'wcb/manage-settings' );`. | `wcb/manage-settings` |

---

## Appendix B — Surface × Role read matrix (compact)

Compact summary of what each role can read across every public surface, holding "logged-in" constant for non-anonymous columns. `*` = inherits a finding, `OK` = no concern.

| Surface | Anon | Subscriber | Candidate | Employer (own data) | Employer (other) | Admin |
|---|---|---|---|---|---|---|
| `/wcb/v1/jobs` (list) | `*F-1` | `*F-1` | `*F-1` | OK | `*F-1` | OK |
| `/wcb/v1/jobs/{id}` | `*F-1*F-7` | `*F-1*F-7` | `*F-1` | OK | `*F-1` | OK |
| `/wp/v2/wcb_job/{id}` | none | `*F-2` | `*F-2` | OK | `*F-2` | OK |
| `/wcb/v1/jobs/{id}/applications` | none | none | none (correct) | OK | `*F-8` | OK |
| `/wcb/v1/jobs/{id}/apply` | OK | OK | OK | rejected (correct) | rejected (correct) | OK |
| `/wcb/v1/applications/{id}` GET | none | none | `*F-3` (own) | OK | none | OK |
| `/wcb/v1/applications/{id}` DELETE | none | none | OK (own + setting) | none | none | OK |
| `/wcb/v1/applications/{id}/status` | none | none | none | OK (own jobs) | `*F-8` analog (per F-8 root cause) | OK |
| `/wcb/v1/applications/{id}/stage` (Pro) | none | none | none | none (correct) | `*F-9` if granted | OK |
| `/wp/v2/wcb_application/{id}` | none | none | none | `*F-4` | `*F-4` | OK |
| `/wcb/v1/candidates/{id}` GET | OK (visibility) | OK (visibility) | OK (visibility) | OK | OK | OK |
| `/wcb/v1/candidates/{id}/applications` | none | none | OK (self) | none | none | OK |
| `/wcb/v1/candidates/resume-upload` | none | `*F-10` | `*F-10` | `*F-10` | `*F-10` | OK |
| `/wcb/v1/candidates/me/privacy/{action}` | none | OK | OK | OK | OK | OK |
| `/wcb/v1/companies` (list) | OK | OK | OK | OK | OK | OK |
| `/wcb/v1/companies/{id}/trust` | none | none | none | none | none | OK |
| `/wcb/v1/employers/{id}` GET | `*F-11` | `*F-11` | `*F-11` | OK (own) | `*F-11` | OK |
| `/wcb/v1/employers/{id}/jobs` | OK | OK | OK | OK (sees pending+draft) | OK (publish only) | OK |
| `/wcb/v1/employers/{id}/applications` | none | none | none | OK (own) | none | OK |
| `/wcb/v1/employers/me/jobs` | none | none | none | OK | OK | OK |
| `/wcb/v1/admin/*` | none | none | none | none | none | OK |
| `/wcb/v1/notifications` (Pro) | none | OK (own) | OK (own) | OK (own) | OK (own) | OK |
| `/wcb/v1/alerts` (Pro) | none | OK (own) | OK (own) | OK (own) | OK (own) | OK |
| `/wcb/v1/ai/match` (Pro) | none | `*F-13` | OK | `*F-13` | `*F-13` | OK |
| `/wcb/v1/candidates/{id}/matches` (Pro) | none | none | OK (self) | none | none | OK |
| `/wcb/v1/jobs/{id}/matches` (Pro) | none | none | none | OK (own) | shares quota with F-13 | OK |
| `/wcb/v1/ai/ranked-applications/{job_id}` (Pro) | none | none | none | `*F-8 analog` (no owner check) | `*F-8 analog` | OK |
| `/wcb/v1/jobs/ai-description` (Pro) | none | none | none | OK | OK | OK |
| `/wcb/v1/fields/*` (Pro) | none | none | none | `*F-14` | `*F-14` (cross-tenant) | OK |
| `/wcb/v1/jobs/{id}/kanban` (Pro) | none | none | none | OK (own) | `*F-8 analog` | OK |
| `/wcb/v1/credits/packages` (Pro) | OK | OK | OK | OK | OK | OK |
| `/wcb/v1/employers/{id}/credits` (Pro) | none | none | none | OK (own) | none | OK |
| `/wcb/v1/geocode` (Pro) | `*F-15` | `*F-15` | `*F-15` | `*F-15` | `*F-15` | OK |
| `/wcb/v1/resumes` (Pro list) | OK (public flag respected) | OK | OK | OK | OK | OK |
| `/wcb/v1/candidates/{id}/resumes` (Pro) | none | none | OK (self) | OK (with `wcb/view-resumes`) | OK | OK |
| `/wcb/v1/resumes/{id}` GET (Pro) | none | none | OK (self) | OK (read-only intent) | OK | OK |
| `/wcb/v1/resumes/{id}` PUT/DELETE (Pro) | none | none | OK (self) | `*F-16` (employer can delete) | `*F-16` | OK |
| `/wcb/v1/resumes/{id}/pdf` (Pro) | none | none | OK (self) | OK (read access) | OK | OK |
| `/wcb/v1/boards/{id}` (Pro) | OK | OK | OK | OK | OK | OK |
| `/wcb/v1/boards/{id}/stages` GET (Pro) | none | OK if logged | OK if logged | OK (own) | OK if has ability | OK |
| `/wcb/v1/boards/{id}/stages` POST/PUT/DELETE | none | none | none | OK if has ability | `*F-14 analog` | OK |
| `/wcb/v1/analytics/credits.csv` (Pro) | none | none | none | none | none | OK |
| Block `job-listings` render | `*F-1` (cards) | same | same | same | same | OK |
| Block `job-single` render | OK (apply chip uses apply_email/url; mailto exposed by design) | same | same | same | same | OK |
| Block `company-profile` render | OK (public profile) | same | same | OK + edit chrome | same | OK |
| Block `company-archive` render | OK | same | same | same | same | OK |
| Block `candidate-dashboard` render | login gate | login gate | OK | rejected (cap mismatch) | rejected | OK |
| Block `employer-dashboard` render | login gate | rejected | rejected | OK | OK | OK |
| Block `employer-registration` render | OK (form) | OK | OK | already-employer state | OK | OK |
| Block `application-kanban` render (Pro) | `*F-17` | `*F-17` | `*F-17` | OK (own) | `*F-17` cross | OK |
| Block `resume-archive` render (Pro) | OK (default) / `*F-18` if includePrivate | same | same | same | same | OK |
| Block `resume-single` render (Pro) | `*F-5*F-6` | same | OK (self) | `*F-5` for private | `*F-5` | OK |
| Block `featured-candidates` (Pro) | `*F-5` analog (uses public-resume archive) | same | same | same | same | OK |
| Block `job-map` / `resume-map` (Pro) | `*F-15` (lat/lng) | same | same | same | same | OK |
| Block `open-to-work` (Pro) | none (gated) | rejected | OK (self) | rejected | rejected | OK |

---

## Appendix C — Quick reference: ability registry mapping

For Task 3.7 fixes, every permission_callback should resolve through the Abilities API. Current (post-3.5) ability map:

| Ability | Granted to | Used by |
|---|---|---|
| `wcb/post-jobs` | wcb_employer + admin | jobs CREATE/UPDATE/DELETE |
| `wcb/manage-company` | wcb_employer + admin | companies CRUD, employer dashboard |
| `wcb/access-employer-dashboard` | wcb_employer + admin | `/employers/me/jobs`, employer-dashboard block |
| `wcb/view-applications` | wcb_employer + admin | applications list/update, kanban |
| `wcb/moderate-jobs` | admin (filterable) | `/jobs/{id}/approve|reject`, pipeline stage updates |
| `wcb/apply-jobs` | wcb_candidate + admin | application submit |
| `wcb/access-candidate-dashboard` | wcb_candidate + admin | candidate-dashboard block |
| `wcb/withdraw-application` | wcb_candidate + admin | DELETE on application |
| `wcb/manage-settings` | admin only | admin/* routes, wizard, import, settings, all admin pages |
| `wcb/manage-boards` (Pro) | wcb_employer + admin | boards-pro routes, fields-pro routes |
| `wcb/manage-alerts` (Pro) | admin (Pro) | alerts ownership override |
| `wcb/manage-credits` (Pro) | admin (Pro) | analytics CSV, balance read |
| `wcb/view-resumes` (Pro) | wcb_employer + admin | resume read across candidates |
| `wcb/manage-ai` (Pro) | admin (Pro) | AI matches read for any candidate |

For the bug-fix surface in Task 3.7, prefer reading state through `wp_is_ability_granted( 'wcb/...' )` (Free) or `$this->check_ability( 'wcb/...' )` (RestController helper). Both wrap the polyfill at `wp-career-board/core/abilities-api-polyfill.php`.

---

## Appendix D — Surfaces NOT in Task 3.7 scope

For audit completeness, these surfaces were checked and produced no findings (or only informational notes):

- All Free `/wizard/*` routes — admin-only, no issue.
- All Free `/admin/*` routes — admin-only, F-12 noted but compliance-class.
- Free `/jobs/{id}/bookmark` — logged-in user toggles their own bookmark, no concerns.
- Free `/companies/{id}/trust` — admin-only, no concerns.
- Free `/employers/register`, `/candidates/register` — both gated open with sane validation.
- Free `/employers/me/jobs` — properly gated to `wcb/access-employer-dashboard`, scoped to current user.
- Pro `/wizard/*` routes — admin-only, no issue.
- Pro `/notifications/*` — scoped to current user, no leak.
- Pro `/alerts` (own) — scoped to current user, no leak.
- Pro `/credits/packages` — public catalog by design.
- Pro `/employers/{id}/credits` — self-or-admin gate is correct.
- Pro `/analytics/credits.csv` — admin-only.
- Pro `/boards/{id}` GET — public board metadata only (id, title, currency).
- Free `wcb_application` admin list — admin-only, no concerns.
- Free `wcb_resume` admin list — admin-only, no concerns.

The mu-plugin `dev-auto-login.php` (CLAUDE.md global instructions) is not part of plugin code and is excluded from this audit.

---

## Appendix E — Verification commands

To re-run this baseline audit on a fresh checkout:

```
# Inventory: every register_rest_route in both plugins
grep -rn "register_rest_route" --include='*.php' \
    --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=audit \
    --exclude-dir=docs --exclude-dir=plan --exclude-dir=tests --exclude-dir=dist \
    wp-career-board wp-career-board-pro

# Inventory: every block render entry point
find wp-career-board wp-career-board-pro -name 'render.php' \
    -not -path '*/vendor/*' -not -path '*/node_modules/*'

# Inventory: every register_post_meta + show_in_rest pair
grep -rn "register_post_meta\|show_in_rest" --include='*.php' \
    wp-career-board wp-career-board-pro

# Inventory: every admin page registration
grep -rn "add_menu_page\|add_submenu_page" --include='*.php' \
    wp-career-board wp-career-board-pro

# Verify no admin-ajax handlers (CLAUDE.md F-5/F-6/P-9)
grep -rn "wp_ajax_\|admin-ajax" --include='*.php' \
    wp-career-board wp-career-board-pro
```

Re-run after Task 3.7 to confirm each finding closed.

