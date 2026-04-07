# WP Career Board — QA Testing Plan

**Goal:** 100% test coverage across Free + Pro before v1.0.0 release.

---

## Test Infrastructure

| Component | Description |
|-----------|-------------|
| REST API tests | `wp eval-file` with internal `rest_do_request()` dispatch (no HTTP) |
| WP-CLI tests | `wp eval-file` calling `WP_CLI::runcommand()` with captured output |
| Browser tests | Playwright MCP tools (manual before each release) |
| Seed data | `tests/fixtures/seed-data.php` — 3 employers, 5 companies, 17 jobs (15 published + 2 pending), 5 candidates, 5 resumes, 13 applications (11 user + 2 guest) |
| Cleanup | `tests/fixtures/cleanup-seed-data.php` — removes all seeded data, safe to run on clean install |
| Runner | `tests/run-all-tests.sh` — seed, CLI, REST Free, REST Pro, cleanup |

---

## Existing Test Suites

### 1. REST API Tests — Free (`test-rest-api-free.php`)

**File:** `wp-content/plugins/wp-career-board/tests/test-rest-api-free.php`
**Assertions:** 57

| Endpoint | Method | Tests |
|----------|--------|-------|
| `/wcb/v1/jobs` | GET | 200 for anon, response is array, at least 1 job returned |
| `/wcb/v1/jobs` | GET | Pagination: per_page=5 returns 200, respects limit |
| `/wcb/v1/jobs` | POST | Anon returns 401/403 |
| `/wcb/v1/jobs` | POST | Employer creates job (200/201), cleanup after |
| `/wcb/v1/jobs/{id}` | GET | 200 for anon, correct ID returned |
| `/wcb/v1/jobs/{id}` | PUT | Anon returns 401/403 |
| `/wcb/v1/jobs/{id}` | PUT | Admin returns 200, restores original |
| `/wcb/v1/jobs/{id}` | DELETE | Anon returns 401/403 |
| `/wcb/v1/jobs/{id}/bookmark` | POST | Anon returns 401/403 |
| `/wcb/v1/jobs/{id}/bookmark` | POST | Candidate returns 200 (toggle on/off) |
| `/wcb/v1/jobs/{id}/applications` | GET | Anon returns 401/403 |
| `/wcb/v1/jobs/{id}/applications` | GET | Admin returns 200 |
| `/wcb/v1/jobs/{id}/apply` | POST | Guest apply returns 200/201, cleanup |
| `/wcb/v1/applications/{id}` | GET | Anon returns 401/403 |
| `/wcb/v1/applications/{id}` | GET | Admin returns 200 |
| `/wcb/v1/applications/{id}/status` | PUT | Anon returns 401/403 |
| `/wcb/v1/applications/{id}/status` | PUT | Admin returns 200, restores original |
| `/wcb/v1/applications/{id}` | DELETE | Anon returns 401/403 (withdraw gate) |
| `/wcb/v1/candidates/{id}/applications` | GET | Anon returns 401/403 |
| `/wcb/v1/candidates/{id}/applications` | GET | Self returns 200 |
| `/wcb/v1/candidates/resume-upload` | POST | Anon returns 401/403 |
| `/wcb/v1/candidates/{id}` | GET | 200 for anon, correct ID |
| `/wcb/v1/candidates/{id}` | PUT | Anon returns 401/403 |
| `/wcb/v1/candidates/{id}` | PUT | Self returns 200, restores original |
| `/wcb/v1/candidates/{id}/bookmarks` | GET | Anon returns 401/403 |
| `/wcb/v1/candidates/{id}/bookmarks` | GET | Self returns 200 |
| `/wcb/v1/candidates/register` | POST | Returns 200/201 or 403 (if registration disabled), cleanup |
| `/wcb/v1/companies` | GET | 200 for anon, response is array |
| `/wcb/v1/companies/{id}/trust` | POST | Anon returns 401/403 |
| `/wcb/v1/companies/{id}/trust` | POST | Admin returns 200, restores original |
| `/wcb/v1/employers/register` | POST | Returns 200/201 or 403, cleanup user + company |
| `/wcb/v1/employers` | POST | Anon returns 401/403 |
| `/wcb/v1/employers/{id}` | GET | 200 for anon |
| `/wcb/v1/employers/{id}` | PUT | Anon returns 401/403 |
| `/wcb/v1/employers/{id}/jobs` | GET | 200 for anon |
| `/wcb/v1/employers/{id}/applications` | GET | Anon returns 401/403 |
| `/wcb/v1/employers/{id}/applications` | GET | Admin returns 200 |
| `/wcb/v1/employers/{id}/logo` | POST | Anon returns 401/403 |
| `/wcb/v1/employers/me/jobs` | GET | Anon returns 401/403 |
| `/wcb/v1/employers/me/jobs` | GET | Employer returns 200 |
| `/wcb/v1/search` | GET | 200 for anon, response is array |
| `/wcb/v1/import/status` | GET | Anon returns 401/403 |
| `/wcb/v1/import/status` | GET | Admin returns 200 |
| `/wcb/v1/import/run` | POST | Anon returns 401/403 |
| `/wcb/v1/jobs/{id}/approve` | POST | Anon returns 401/403 |
| `/wcb/v1/jobs/{id}/approve` | POST | Admin returns 200, status = publish, restores |
| `/wcb/v1/jobs/{id}/reject` | POST | Anon returns 401/403 |
| `/wcb/v1/jobs/{id}/reject` | POST | Admin returns 200, status = draft, reason saved, restores |
| `/wcb/v1/wizard/create-pages` | POST | Anon returns 401/403 |
| `/wcb/v1/wizard/create-pages` | POST | Admin returns 200 |
| `/wcb/v1/wizard/sample-data` | POST | Anon returns 401/403 |
| `/wcb/v1/wizard/complete` | POST | Anon returns 401/403 |

---

### 2. REST API Tests — Pro (`test-rest-api-pro.php`)

**File:** `wp-content/plugins/wp-career-board-pro/tests/test-rest-api-pro.php`
**Assertions:** 55

| Endpoint | Method | Tests |
|----------|--------|-------|
| `/wcb/v1/boards/{id}` | GET | Route exists (200 or 404) |
| `/wcb/v1/boards/{id}/stages` | GET | Anon returns 401/403 |
| `/wcb/v1/boards/{id}/stages` | GET | Admin returns 200 |
| `/wcb/v1/boards/{id}/stages` | POST | Anon returns 401/403 |
| `/wcb/v1/boards/{id}/stages` | POST | Admin returns 200 (create stage) |
| `/wcb/v1/boards/{id}/stages/{stage_id}` | PUT | Anon returns 401/403 |
| `/wcb/v1/boards/{id}/stages/{stage_id}` | PUT | Admin returns 200 |
| `/wcb/v1/boards/{id}/stages/{stage_id}` | DELETE | Anon returns 401/403 |
| `/wcb/v1/boards/{id}/stages/{stage_id}` | DELETE | Admin returns 200 |
| `/wcb/v1/credits/packages` | GET | 200 for anon, response is array |
| `/wcb/v1/credits/checkout` | POST | Anon returns 401/403 |
| `/wcb/v1/credits/webhook` | POST | Route exists (no 500) |
| `/wcb/v1/employers/{id}/credits` | GET | Anon returns 401/403 |
| `/wcb/v1/employers/{id}/credits` | GET | Self returns 200 |
| `/wcb/v1/fields/groups` | GET | Anon returns 401/403 |
| `/wcb/v1/fields/groups` | GET | Admin returns 200 |
| `/wcb/v1/fields/groups` | POST | Anon returns 401/403 |
| `/wcb/v1/fields/groups` | POST | Admin returns 200 (create group) |
| `/wcb/v1/fields/groups/{id}` | PUT | Anon returns 401/403 |
| `/wcb/v1/fields/groups/{id}` | PUT | Admin returns 200 |
| `/wcb/v1/fields/groups/{id}/fields` | GET | Admin returns 200 |
| `/wcb/v1/fields/groups/{id}/fields` | POST | Anon returns 401/403 |
| `/wcb/v1/fields/groups/{id}/fields` | POST | Admin returns 200 (create field) |
| `/wcb/v1/fields/{id}` | PUT | Anon returns 401/403 |
| `/wcb/v1/fields/{id}` | PUT | Admin returns 200 |
| `/wcb/v1/fields/{id}` | DELETE | Anon returns 401/403 |
| `/wcb/v1/fields/{id}` | DELETE | Admin returns 200 |
| `/wcb/v1/fields/groups/{id}` | DELETE | Admin returns 200 (cleanup) |
| `/wcb/v1/fields/reorder` | POST | Anon returns 401/403 |
| `/wcb/v1/fields/reorder` | POST | Admin returns 200 |
| `/wcb/v1/ai/match` | POST | Anon returns 401/403 |
| `/wcb/v1/candidates/{id}/matches` | GET | Anon returns 401/403 |
| `/wcb/v1/ai/ranked-applications/{job_id}` | GET | Anon returns 401/403 |
| `/wcb/v1/jobs/ai-description` | POST | Anon returns 401/403 |
| `/wcb/v1/applications/{id}/stage` | PUT | Anon returns 401/403 |
| `/wcb/v1/applications/{id}/stage` | PUT | Admin returns 200, restores |
| `/wcb/v1/jobs/{id}/kanban` | GET | Anon returns 401/403 |
| `/wcb/v1/jobs/{id}/kanban` | GET | Admin returns 200 |
| `/wcb/v1/alerts` | GET | Anon returns 401/403 |
| `/wcb/v1/alerts` | GET | Candidate returns 200 |
| `/wcb/v1/alerts` | POST | Anon returns 401/403 |
| `/wcb/v1/alerts` | POST | Candidate returns 200 (create alert) |
| `/wcb/v1/alerts/{id}` | PUT | Anon returns 401/403 |
| `/wcb/v1/alerts/{id}` | PUT | Owner returns 200 |
| `/wcb/v1/alerts/{id}` | DELETE | Anon returns 401/403 |
| `/wcb/v1/alerts/{id}` | DELETE | Owner returns 200 |
| `/wcb/v1/resumes` | GET | 200 for anon (public archive) |
| `/wcb/v1/candidates/{id}/resumes` | GET | Anon returns 401/403 |
| `/wcb/v1/candidates/{id}/resumes` | GET | Self returns 200 |
| `/wcb/v1/candidates/{id}/resumes` | POST | Anon returns 401/403 |
| `/wcb/v1/candidates/{id}/resumes` | POST | Self returns 200/201, cleanup |
| `/wcb/v1/resumes/{id}` | GET | Anon returns 401/403 |
| `/wcb/v1/resumes/{id}` | GET | Owner returns 200 |
| `/wcb/v1/resumes/{id}` | PUT | Anon returns 401/403 |
| `/wcb/v1/resumes/{id}` | DELETE | Anon returns 401/403 |
| `/wcb/v1/resumes/{id}/pdf` | GET | Anon returns 401/403 |
| `/wcb/v1/geocode` | GET | Route exists (200 or 422) |
| `/wcb/v1/notifications` | GET | Anon returns 401/403 |
| `/wcb/v1/notifications` | GET | Candidate returns 200, response has `notifications` + `unread_count` |
| `/wcb/v1/notifications/{id}/read` | PUT | Anon returns 401/403 |
| `/wcb/v1/notifications/read-all` | POST | Anon returns 401/403 |
| `/wcb/v1/notifications/read-all` | POST | Candidate returns 200 |

---

### 3. WP-CLI Tests (`test-cli-commands.php`)

**File:** `wp-content/plugins/wp-career-board/tests/test-cli-commands.php`
**Assertions:** 30

| # | Command | Assertions |
|---|---------|------------|
| 1 | `wp wcb status` | Exit 0, stdout contains "Job" |
| 2 | `wp wcb abilities --format=json` | Exit 0, valid JSON array, non-empty |
| 3 | `wp wcb abilities --user-id={candidate} --format=json` | Exit 0, valid JSON |
| 4 | `wp wcb job list --format=json` | Exit 0, valid JSON array, count >= 17 |
| 5 | `wp wcb job list --status=pending --format=json` | Exit 0, valid JSON, all items pending |
| 6 | `wp wcb job list --format=ids` | Exit 0, space-separated integers |
| 7 | `wp wcb job approve {id}` | Exit 0, post_status = publish, restores |
| 8 | `wp wcb job reject {id} --reason="..."` | Exit 0, post_status = draft, reason meta saved, restores |
| 9 | `wp wcb job expire {id}` | Exit 0, post_status = wcb_expired, restores |
| 10 | `wp wcb job run-expiry` | Exit 0 |
| 11 | `wp wcb application list --format=json` | Exit 0, valid JSON, count >= 13 |
| 12 | `wp wcb application list --status=shortlisted --format=json` | Exit 0, valid JSON, all shortlisted |
| 13 | `wp wcb application update {id} --status=reviewing` | Exit 0, _wcb_status meta = reviewing, restores |
| 14 | `wp wcb migrate wpjm --dry-run` | Non-zero exit, error mentions WPJM/not active |

---

### 4. Browser/Functionality Tests (Playwright MCP)

Run manually before each release using Playwright MCP tools.

#### 4a. Public Pages

| # | Page | URL | Checks |
|---|------|-----|--------|
| B01 | Homepage | `/` | Hero block renders, search bar functional, featured jobs load, stats display, CTA buttons link correctly |
| B02 | Find Jobs | `/find-jobs/` | Job cards load (10 per page), search input filters, type/level/location filter toggles work, grid/list toggle, sort dropdown, "Load more" pagination, "Alert me" button (Pro) |
| B03 | Job Single | `/job/{slug}/` | Title/company/salary/location render, Apply Now opens modal, share buttons, related jobs section |
| B04 | Companies | `/companies/` | Company cards render, industry/size filters, verified badge, "View Profile" links |
| B05 | Company Profile | `/company/{slug}/` | Company info header, open positions list, location/website/size |
| B06 | Employer Registration | `/employer-register/` | Form renders, validation (empty fields, weak password), submit creates user + company |
| B07 | Find Resumes (Pro) | `/find-resumes/` | Resume cards load, skill/location filters, search bar |

#### 4b. Authenticated -- Employer

| # | Page | URL | Checks |
|---|------|-----|--------|
| B08 | Employer Dashboard | `/employer-dashboard/` | Stats cards (jobs/apps/credits), recent applications list, active jobs list, sidebar nav links |
| B09 | Post a Job | `/post-a-job/` | 4-step wizard: Details > Requirements > Company > Review, form validation per step, submit creates job |
| B10 | My Jobs | `/employer-dashboard/?tab=jobs` | Job list with status badges (published/pending/expired), edit/delete actions |
| B11 | Applications | `/employer-dashboard/?tab=applications` | Application list, status filter dropdown, status change dropdown, applicant details expand |
| B12 | Company Profile Edit | `/employer-dashboard/?tab=company` | Form pre-fills current data, save persists, logo upload |
| B13 | Pipeline Kanban (Pro) | `/employer-dashboard/?tab=pipeline` | Kanban columns render, drag-drop moves cards, stage labels match config |
| B14 | Credits Balance (Pro) | `/employer-dashboard/?tab=credits` | Balance displays, packages list, top-up button |

#### 4c. Authenticated -- Candidate

| # | Page | URL | Checks |
|---|------|-----|--------|
| B15 | Candidate Dashboard | `/candidate-dashboard/` | Stats cards, recent applications, saved jobs count |
| B16 | My Applications | `/candidate-dashboard/?tab=applications` | Application list with status badges, withdraw action |
| B17 | Saved Jobs | `/candidate-dashboard/?tab=saved` | Bookmarked job list, unbookmark (heart toggle) works |
| B18 | My Resumes (Pro) | `/candidate-dashboard/?tab=resumes` | Resume list, create/edit/delete, section editor |
| B19 | Job Alerts (Pro) | `/candidate-dashboard/?tab=alerts` | Alert list, create new (keyword + frequency), edit, delete |

#### 4d. Admin

| # | Page | URL | Checks |
|---|------|-----|--------|
| B20 | Settings — General | `/wp-admin/admin.php?page=wcb-settings` | Tab renders, fields save, page reload preserves |
| B21 | Settings — Job Listings | `/wp-admin/admin.php?page=wcb-settings&tab=jobs` | Per-page, default sort, expiry days fields |
| B22 | Settings — Registration | `/wp-admin/admin.php?page=wcb-settings&tab=registration` | Enable/disable toggles |
| B23 | Settings — Notifications | `/wp-admin/admin.php?page=wcb-settings&tab=notifications` | Email toggle matrix, from-name/email |
| B24 | Settings — Antispam | `/wp-admin/admin.php?page=wcb-settings&tab=antispam` | reCAPTCHA / Turnstile key fields |
| B25 | Settings — SEO | `/wp-admin/admin.php?page=wcb-settings&tab=seo` | Schema toggle, OG toggle |
| B26 | Jobs list | `/wp-admin/edit.php?post_type=wcb_job` | Table renders, bulk actions (approve/reject), status column |
| B27 | Applications list | `/wp-admin/edit.php?post_type=wcb_application` | Table renders, status column, linked job |
| B28 | Companies list | `/wp-admin/edit.php?post_type=wcb_company` | Table renders, trust level column |
| B29 | Candidates list | `/wp-admin/admin.php?page=wcb-candidates` | Table renders, role/status |
| B30 | Setup Wizard | `/wp-admin/admin.php?page=wcb-wizard` | Steps complete, pages created, sample data install/remove |
| B31 | Field Builder (Pro) | `/wp-admin/admin.php?page=wcb-fields` | Groups + fields CRUD, drag-reorder |
| B32 | Boards (Pro) | `/wp-admin/admin.php?page=wcb-boards` | Board list, stage editor, create/edit board |
| B33 | Credits Config (Pro) | `/wp-admin/admin.php?page=wcb-settings#credits` | Credit mappings, provider detection, admin adjustments |
| B34 | Import | `/wp-admin/admin.php?page=wcb-import` | WPJM import UI, CSV upload (Pro) |

#### 4e. Responsive (390px viewport)

| # | Page | Check |
|---|------|-------|
| R01 | Homepage | Hero stacks, search bar full-width, featured jobs single-column |
| R02 | Find Jobs | Filters collapse to drawer/accordion, job cards stack |
| R03 | Job Single + Apply Modal | Content readable, modal full-screen |
| R04 | Companies | Cards stack single-column |
| R05 | Employer Dashboard | Sidebar collapses to hamburger/dropdown, stats stack |
| R06 | Candidate Dashboard | Same sidebar collapse, cards stack |
| R07 | Post a Job (multi-step) | Wizard steps stack, form fields full-width |
| R08 | Employer Registration | Form fields full-width, submit button accessible |

---

## Blocks Coverage

### Free Blocks (14)

| Block | Slug | Browser Test |
|-------|------|-------------|
| Job Search Hero | `wcb/job-search-hero` | B01 |
| Featured Jobs | `wcb/featured-jobs` | B01 |
| Job Stats | `wcb/job-stats` | B01 |
| Recent Jobs | `wcb/recent-jobs` | B01 |
| Job Listings | `wcb/job-listings` | B02 |
| Job Filters | `wcb/job-filters` | B02 |
| Job Search | `wcb/job-search` | B02 |
| Job Single | `wcb/job-single` | B03 |
| Job Form | `wcb/job-form` | B09 |
| Company Archive | `wcb/company-archive` | B04 |
| Company Profile | `wcb/company-profile` | B05 |
| Employer Dashboard | `wcb/employer-dashboard` | B08 |
| Candidate Dashboard | `wcb/candidate-dashboard` | B15 |
| Employer Registration | `wcb/employer-registration` | B06 |

### Pro Blocks (15)

| Block | Slug | Browser Test |
|-------|------|-------------|
| Application Kanban | `wcb/application-kanban` | B13 |
| Credit Balance | `wcb/credit-balance` | B14 |
| Job Map | `wcb/job-map` | B02 (Pro overlay) |
| AI Chat Search | `wcb/ai-chat-search` | B02 (Pro overlay) |
| Job Alerts | `wcb/job-alerts` | B19 |
| Board Switcher | `wcb/board-switcher` | B02 (Pro overlay) |
| Resume Builder | `wcb/resume-builder` | B18 |
| Resume Single | `wcb/resume-single` | B07 |
| Resume Archive | `wcb/resume-archive` | B07 |
| Resume Search Hero | `wcb/resume-search-hero` | B07 |
| Resume Map | `wcb/resume-map` | B07 (Pro overlay) |
| My Applications | `wcb/my-applications` | B16 |
| Featured Candidates | `wcb/featured-candidates` | B01 (Pro overlay) |
| Featured Companies | `wcb/featured-companies` | B01 (Pro overlay) |
| Open to Work | `wcb/open-to-work` | B15 (Pro overlay) |

---

## Tests to Add for 100% Coverage

### 5. Unit Tests (PHPUnit)

| Class/Module | Plugin | Priority | Tests Needed |
|-------------|--------|----------|--------------|
| `WCB\Pro\Credits\CreditsModule` | Pro | P0 | `topup()` inserts ledger row, `deduct()` inserts negative, `hold()` inserts hold, `refund()` inserts refund, `get_balance()` = SUM of amounts, hold-then-deduct sequence, insufficient balance error |
| `WCB\Pro\Core\License` | Pro | P0 | `is_valid()` with valid transient, `is_valid()` with expired transient triggers remote check, `check_license()` sets transient, invalid key returns false |
| `WCB\Core\Install` | Free | P1 | All custom tables created via `dbDelta()`, custom roles (`wcb_employer`, `wcb_candidate`) registered, default options seeded, version option updated |
| `WCB\Pro\Core\ProInstall` | Pro | P1 | Pro tables created (`wcb_credit_ledger`, `wcb_field_groups`, `wcb_field_definitions`, `wcb_field_values`, `wcb_job_boards`, `wcb_job_alerts`, `wcb_application_stages`, `wcb_ai_vectors`), version migration from older schema |
| `WCB\Notifications\AbstractEmail` | Free | P1 | Template file loaded from correct path, placeholder replacement (`{candidate_name}`, `{job_title}`, etc.), `send()` calls `wp_mail()`, disabled email skips send |
| `WCB\Antispam\AntispamModule` | Free | P1 | reCAPTCHA token verification (mock HTTP), Turnstile token verification (mock HTTP), bypass when provider not configured, invalid token returns WP_Error |
| `WCB\Search\SearchModule` | Free | P1 | Query builder: keyword search, filter by type, filter by location, combined filters, empty results |
| `WCB\Jobs\JobsExpiry` | Free | P2 | Cron callback finds jobs past expiry date, transitions status to `wcb_expired`, skips already-expired jobs, fires `wcb_job_expired` action |
| `WCB\Gdpr\GdprModule` | Free | P2 | `export_data()` returns correct format for WP exporter, `erase_data()` removes user meta + applications, anonymizes guest applications |
| `WCB\Seo\SeoModule` | Free | P2 | JobPosting schema output on job single, Organization schema on company profile, OG meta tags rendered, schema validates against Google structured data spec |
| `WCB\Pro\Migration\CsvImporter` | Pro | P2 | Row parsing with headers, required field validation, meta sanitization, skip duplicate slugs, error collection per row |
| `WCB\Pro\Alerts\AlertsModule` | Pro | P2 | Cron digest builds WP_Query from alert criteria, matching jobs returned, frequency gating (daily/weekly/instant), email sent on match |
| `WCB\Pro\Resume\ResumeModule` | Pro | P2 | Section CRUD (education, experience, skills), section ordering, visibility toggle |
| `WCB\Pro\Resume\ResumePdfExporter` | Pro | P2 | PDF generated with correct content, file served with correct MIME type |
| `WCB\Core\Abilities` | Free | P2 | `wcb_post_job` registered, employer has ability, candidate does not, admin has all abilities |
| `WCB\Pro\Core\ProAbilities` | Pro | P2 | `wcbp_manage_boards`, `wcbp_use_ai`, `wcbp_manage_fields` registered and mapped correctly |
| `WCB\Pro\Ai\AiModule` | Pro | P3 | Driver selection (OpenAI/Claude/Ollama), match scoring algorithm, ranked applications sort, AI description generation (mocked) |
| `WCB\Pro\Maps\MapsModule` | Pro | P3 | Driver selection (Google/Mapbox/Leaflet), geocode caching, coordinate storage |
| `WCB\Pro\Feed\JobFeedModule` | Pro | P3 | XML/JSON feed generation, feed item structure |
| `WCB\Pro\Analytics\AnalyticsModule` | Pro | P3 | View tracking, click tracking, aggregate queries |
| `WCB\Pro\Pwa\PwaModule` | Pro | P3 | Service worker registered, manifest.json served |

### 6. Integration Tests (`wp eval-file`)

| # | Flow | Plugin | Priority | Verification |
|---|------|--------|----------|-------------|
| I01 | Full job lifecycle | Free | P0 | Create (pending) -> approve (publish) -> expire (wcb_expired) — verify post_status at each step |
| I02 | Application lifecycle | Free | P0 | Apply -> reviewing -> shortlisted -> hired — verify `_wcb_status` meta at each step, email sent at each transition |
| I03 | Credit flow | Pro | P0 | Topup(50) -> hold(10) -> deduct(10) -> refund(5) — verify balance = 45 via ledger SUM |
| I04 | Registration -> Post Job -> Receive Application | Both | P1 | Register employer -> create company -> post job -> register candidate -> apply -> employer sees application |
| I05 | WPJM import (mock) | Free | P1 | Create mock WPJM posts -> run import -> verify mapped CPTs/meta/taxonomies |
| I06 | Bookmark lifecycle | Free | P1 | Candidate bookmarks job -> verify in bookmarks list -> unbookmark -> verify removed |
| I07 | Moderation with email | Free | P1 | Submit job (pending) -> admin approves -> verify email sent to employer -> admin rejects another -> verify rejection email |
| I08 | Field builder -> job form | Pro | P1 | Create field group + fields -> post job with custom field values -> verify stored in `wcb_field_values` table |
| I09 | Multi-board filtering | Pro | P1 | Create 2 boards -> assign jobs to each -> verify `/wcb/v1/jobs?board_id=X` returns only board X jobs |
| I10 | Alert trigger | Pro | P2 | Create alert (keyword="engineer", frequency="instant") -> publish matching job -> verify notification created |
| I11 | Resume PDF export | Pro | P2 | Create resume with sections -> request PDF endpoint -> verify 200 response with application/pdf content type |
| I12 | Uninstall cleanup | Free | P1 | Activate -> seed data -> deactivate -> run `uninstall.php` -> verify all tables, options, meta, roles removed |
| I13 | Guest application -> candidate conversion | Free | P2 | Guest applies with email -> register with same email -> verify application linked to new user |
| I14 | CSV import | Pro | P2 | Upload CSV with 5 jobs -> verify all created with correct meta/taxonomies |
| I15 | Theme integration (BuddyPress) | Free | P2 | With BP active -> verify profile tab registered, activity stream entry on application |
| I16 | Pipeline stage movement | Pro | P2 | Move application through stages -> verify `_wcb_stage_id` meta updates, kanban endpoint reflects change |

---

## Running Tests

### Full Suite

```bash
# From WordPress root:
bash wp-content/plugins/wp-career-board/tests/run-all-tests.sh
```

### Individual Suites

```bash
# 1. Seed data
wp eval-file wp-content/plugins/wp-career-board/tests/fixtures/seed-data.php

# 2. CLI commands
wp eval-file wp-content/plugins/wp-career-board/tests/test-cli-commands.php

# 3. REST API — Free
wp eval-file wp-content/plugins/wp-career-board/tests/test-rest-api-free.php

# 4. REST API — Pro (requires both plugins active)
wp eval-file wp-content/plugins/wp-career-board-pro/tests/test-rest-api-pro.php

# 5. Cleanup
wp eval-file wp-content/plugins/wp-career-board/tests/fixtures/cleanup-seed-data.php
```

### Browser Tests

```
1. Navigate to site with Playwright MCP
2. Auto-login: append ?autologin=1 (admin), ?autologin=employer_login, ?autologin=candidate_login
3. Walk through B01-B34 and R01-R08 checklists above
4. Screenshot each page at 1440px and 390px viewports
```

---

## Code Quality Gates (Pre-Release)

```bash
# WPCS (via MCP preferred)
vendor/bin/phpcs --standard=.phpcs.xml.dist .

# PHPStan
vendor/bin/phpstan analyse

# PHP Compatibility (8.1+)
vendor/bin/phpcs --standard=PHPCompatibility --runtime-set testVersion 8.1- .

# PHP Lint (all files, parallel)
find . -name "*.php" -not -path "./vendor/*" -not -path "./node_modules/*" -not -path "./dist/*" | xargs -P4 php -l

# JS Lint
npx eslint assets/js/ blocks/

# CSS Lint
npx stylelint "assets/css/**/*.css" "blocks/**/*.css"

# Build verification
npm run build
```

---

## Test File Index

```
wp-career-board/
├── tests/
│   ├── run-all-tests.sh                     # Runner script
│   ├── test-cli-commands.php                # 30 assertions
│   ├── test-rest-api-free.php               # 57 assertions
│   └── fixtures/
│       ├── seed-data.php                    # Creates all test data
│       └── cleanup-seed-data.php            # Removes all test data
│
wp-career-board-pro/
└── tests/
    └── test-rest-api-pro.php                # 55 assertions
```

**Existing total: 142 assertions across 3 test files.**

---

## Coverage Summary

| Area | Existing | To Add | Status |
|------|----------|--------|--------|
| REST API auth gates (Free) | 57 assertions | -- | Done |
| REST API auth gates (Pro) | 55 assertions | -- | Done |
| WP-CLI commands | 30 assertions | -- | Done |
| Browser: public pages | -- | B01-B07 (7 pages) | TODO |
| Browser: employer flow | -- | B08-B14 (7 pages) | TODO |
| Browser: candidate flow | -- | B15-B19 (5 pages) | TODO |
| Browser: admin | -- | B20-B34 (15 pages) | TODO |
| Browser: responsive | -- | R01-R08 (8 viewports) | TODO |
| Unit: Credits module | -- | 7 tests | TODO (P0) |
| Unit: License module | -- | 4 tests | TODO (P0) |
| Unit: Install/ProInstall | -- | 6 tests | TODO (P1) |
| Unit: Email system | -- | 4 tests | TODO (P1) |
| Unit: Antispam | -- | 4 tests | TODO (P1) |
| Unit: Search | -- | 5 tests | TODO (P1) |
| Unit: other modules | -- | ~30 tests | TODO (P2-P3) |
| Integration: lifecycles | -- | I01-I16 (16 flows) | TODO |
| Code quality gates | -- | 6 commands | Per-commit |

---

## Exit Criteria for v1.0.0

1. All 142 existing assertions pass (0 failures)
2. All P0 unit tests written and passing
3. All P0-P1 integration tests written and passing
4. Browser tests B01-B34 verified via Playwright screenshots
5. Responsive tests R01-R08 verified at 390px
6. WPCS: 0 errors
7. PHPStan: 0 errors
8. PHP lint: 0 syntax errors
9. Build: `npm run build` exits 0
10. Uninstall: clean removal verified (I12)
