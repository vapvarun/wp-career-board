# WP Career Board — Architecture Overview

## 1. System Overview

| Layer | Detail |
|-------|--------|
| **Stack** | PHP 8.1+, WordPress 6.9+, Interactivity API, Abilities API, REST API |
| **Free plugin** | `wp-career-board` — core job board: CPTs, taxonomies, blocks, search, moderation |
| **Pro plugin** | `wp-career-board-pro` — extends Free (requires it active): multi-board, credits, AI, field builder, pipeline, PWA |
| **Namespace** | Free: `WCB\` / Pro: `WCB\Pro\` |
| **Prefix** | Free globals: `wcb_` / Pro globals: `wcbp_` |
| **REST namespace** | `wcb/v1` (shared by both plugins) |
| **Frontend** | Interactivity API only (`data-wp-*` directives). Zero jQuery, zero admin-ajax |
| **Distribution** | wbcomdesigns.com via EDD Software Licensing (not WordPress.org) |

---

## 2. Free Plugin — `wp-career-board`

### Custom Post Types (5)

| CPT | Slug | Purpose |
|-----|------|---------|
| Job | `wcb_job` | Job listings with expiry, salary, taxonomy terms |
| Company | `wcb_company` | Employer company profiles |
| Application | `wcb_application` | Candidate job applications (private) |
| Resume | `wcb_resume` | Candidate resumes (private by default, Pro makes public) |
| Board | `wcb_board` | Named job board containers |

### Taxonomies (5)

| Taxonomy | Slug | Hierarchical | Attached to |
|----------|------|-------------|-------------|
| Job Categories | `wcb_category` | Yes | `wcb_job` |
| Job Types | `wcb_job_type` | No | `wcb_job` |
| Job Tags | `wcb_tag` | No | `wcb_job` |
| Locations | `wcb_location` | Yes | `wcb_job` |
| Experience Levels | `wcb_experience` | No | `wcb_job` |

### Gutenberg Blocks (14)

| Block | Description |
|-------|-------------|
| `job-listings` | Reactive job grid with infinite scroll and bookmark toggle |
| `job-single` | Full job detail view with slide-in application panel |
| `job-search` | Search bar for the job listings grid |
| `job-search-hero` | Full-width search form with optional filters (horizontal/vertical) |
| `job-filters` | Taxonomy filter dropdowns for the listings grid |
| `job-form` | Multi-step form for employers to post new jobs |
| `job-stats` | Horizontal stat strip (total jobs, companies, candidates) |
| `featured-jobs` | Static server-rendered grid of featured jobs |
| `recent-jobs` | Sidebar widget listing most recently published jobs |
| `employer-dashboard` | Tabbed dashboard: My Jobs and Company Profile |
| `employer-registration` | Registration form for new employers |
| `candidate-dashboard` | Tabbed dashboard: My Applications and Saved Jobs |
| `company-archive` | Interactive company directory with grid/list toggle |
| `company-profile` | Public company profile with owner inline-edit |

### REST Endpoints (7)

All under `/wp-json/wcb/v1/`.

| Endpoint | Class | Purpose |
|----------|-------|---------|
| `/jobs` | `Jobs_Endpoint` | CRUD for job listings |
| `/applications` | `Applications_Endpoint` | Submit and manage applications |
| `/candidates` | `Candidates_Endpoint` | Candidate profile operations |
| `/employers` | `Employers_Endpoint` | Employer profile operations |
| `/companies` | `Companies_Endpoint` | Company CRUD |
| `/search` | `Search_Endpoint` | Full-text job search with filters |
| `/import` | `Import_Endpoint` | Bulk job import (CSV/JSON) |

### Database Tables (3)

| Table | Key Columns | Purpose |
|-------|-------------|---------|
| `wcb_notifications_log` | user_id, event_type, channel, status, sent_at | Email notification audit log |
| `wcb_job_views` | job_id, viewed_at, ip_hash | Job view tracking for analytics |
| `wcb_gdpr_log` | user_id, action, ip_hash, created_at | GDPR compliance audit trail |

### Modules (12)

`jobs`, `employers`, `candidates`, `applications`, `search`, `notifications`, `moderation`, `seo`, `gdpr`, `antispam`, `boards`, `theme-integration`

### WP-CLI Commands (4)

| Command | Purpose |
|---------|---------|
| `wp wcb status` | Content counts (jobs, companies, applications, users by role) |
| `wp wcb job list\|approve\|expire` | Job listing operations |
| `wp wcb application list\|view` | Application management |
| `wp wcb migrate wpjm\|wpjm-resumes` | Import from WP Job Manager |

### Cron Jobs (1)

| Hook | Schedule | Purpose |
|------|----------|---------|
| `wcb_check_job_expiry` | Daily | Transition expired jobs to `wcb_expired` status |

### Integrations

| Integration | Directory | Scope |
|-------------|-----------|-------|
| BuddyPress | `integrations/buddypress/` | Activity stream items for new jobs |
| BuddyX Pro | `integrations/buddyx-pro/` | Theme-specific styling and templates |
| Reign | `integrations/reign/` | Theme-specific styling and templates |

---

## 3. Pro Plugin — `wp-career-board-pro`

**Requires Free active.** Guard: `Requires Plugins: wp-career-board` header + runtime `wcbp_free_active()` check.

### Additional Taxonomy (1)

| Taxonomy | Slug | Attached to | Notes |
|----------|------|-------------|-------|
| Skills | `wcb_resume_skill` | `wcb_resume` | Hidden UI, synced from `_wcb_resume_skills` meta |

### Additional Blocks (15)

| Block | Description |
|-------|-------------|
| `application-kanban` | Drag-and-drop Kanban board for hiring pipeline stages |
| `board-switcher` | Tab bar to switch between job boards (updates shared search state) |
| `credit-balance` | Employer credit balance with Buy Credits button and transaction history |
| `ai-chat-search` | Natural language job search powered by AI (semantic matching) |
| `job-alerts` | Subscribe to alerts for current search filters, manage frequency |
| `job-map` | Leaflet map with job pins synced to listings search state |
| `resume-builder` | Section-by-section resume editor (education, experience, skills) |
| `resume-archive` | Public archive of candidate resumes with avatar, skills, location |
| `resume-single` | Public-facing resume page for `wcb_resume` post template |
| `resume-search-hero` | Full-width resume search with skill and open-to-work filters |
| `resume-map` | Leaflet map showing candidate locations |
| `my-applications` | Candidate's submitted applications list (scoped to current user) |
| `featured-candidates` | Sidebar widget listing featured public resumes |
| `featured-companies` | Sidebar widget listing featured companies with open role counts |
| `open-to-work` | Sidebar widget listing candidates open to work |

### Additional REST Endpoints (9)

All under `/wp-json/wcb/v1/`.

| Endpoint | Class | Purpose |
|----------|-------|---------|
| `/boards-pro` | `Boards_Pro_Endpoint` | Board stage management and job-board assignments |
| `/credits` | `Credits_Endpoint` | Credit balance, top-up, hold/deduct/refund |
| `/fields` | `Fields_Endpoint` | Custom field group and definition CRUD |
| `/ai` | `AI_Endpoint` | Semantic search, embedding generation |
| `/pipeline` | `Pipeline_Endpoint` | Application stage transitions (Kanban moves) |
| `/alerts` | `Alerts_Endpoint` | Job alert subscription CRUD |
| `/resumes` | `Resume_Endpoint` | Public resume listing and detail |
| `/geocode` | `Geocode_Endpoint` | Address-to-coordinates for map blocks |
| `/notifications-bell` | `Notifications_Bell_Endpoint` | In-app notification bell (read/dismiss) |

### Database Tables (9)

Pro creates 9 tables. It also reads the Free `wcb_job_views` table for analytics.

| Table | Key Columns | Purpose |
|-------|-------------|---------|
| `wcb_credit_ledger` | employer_id, entry_type, amount, created_at | Append-only credit ledger (never UPDATE) |
| `wcb_field_groups` | board_id, entity_type, label, sort_order | Field builder group containers |
| `wcb_field_definitions` | group_id, field_key, field_type, rules | Individual field definitions |
| `wcb_field_values` | post_id, field_key, value | Custom field data per post |
| `wcb_job_boards` | job_id, board_id | Job-to-board many-to-many junction |
| `wcb_job_alerts` | user_id, board_id, filters, frequency | Saved search alert subscriptions |
| `wcb_application_stages` | board_id, label, color, is_terminal | Kanban pipeline stage definitions |
| `wcb_ai_vectors` | entity_type, entity_id, provider, vector | AI embedding vectors |
| `wcb_notifications` | user_id, event_type, message, is_read | In-app notification bell entries |

### Modules (14)

`credits`, `fields`, `boards`, `ai`, `alerts`, `pipeline`, `resume`, `maps`, `notifications-pro`, `notifications-bell`, `pwa`, `migration`, `analytics`, `feed`

### Cron Jobs (2)

| Hook | Schedule | Purpose |
|------|----------|---------|
| `wcb_send_daily_alerts` | Daily at 8 AM | Email daily job alert digests |
| `wcb_send_weekly_alerts` | Weekly (Monday 8 AM) | Email weekly job alert digests |

### Integrations

| Integration | Scope |
|-------------|-------|
| WooCommerce | Credit purchases via WooCommerce products, subscriptions, and memberships |
| BuddyPress | Profile tabs (Employer/Candidate), BP notifications on new applications |

### Licensing

EDD Software Licensing. License stored in `wcbp_license_key` option. Validity cached in `wcbp_license_valid` weekly transient. All Pro features gated behind `WCB\Pro\Core\License::is_valid()`.

---

## 4. Permission Model

### Roles (3)

| Role | Slug | Capabilities |
|------|------|-------------|
| Employer | `wcb_employer` | `wcb_post_jobs`, `wcb_manage_company`, `wcb_view_applications`, `wcb_access_employer_dashboard`, `wcb_view_resumes`* |
| Candidate | `wcb_candidate` | `wcb_apply_jobs`, `wcb_manage_resume`, `wcb_bookmark_jobs`, `wcb_manage_alerts`* |
| Board Moderator | `wcb_board_moderator` | `wcb_moderate_jobs` |

*Pro adds `wcb_view_resumes` to employers, `wcb_manage_alerts` to candidates.*

### Abilities (Abilities API)

| Ability | Source | Granted to |
|---------|--------|------------|
| `wcb_post_jobs` | Free | Employer, Admin |
| `wcb_manage_company` | Free | Employer, Admin |
| `wcb_view_applications` | Free | Employer, Admin |
| `wcb_access_employer_dashboard` | Free | Employer, Admin |
| `wcb_apply_jobs` | Free | Candidate, Admin |
| `wcb_manage_resume` | Free | Candidate, Admin |
| `wcb_bookmark_jobs` | Free | Candidate, Admin |
| `wcb_moderate_jobs` | Free | Moderator, Admin |
| `wcb_manage_settings` | Free | Admin |
| `wcb_view_analytics` | Free | Admin |
| `wcb_view_resumes` | Pro | Employer, Admin |
| `wcb_manage_alerts` | Pro | Candidate, Admin |
| `wcbp_manage_boards` | Pro | Admin |
| `wcbp_manage_credits` | Pro | Admin |
| `wcbp_manage_ai` | Pro | Admin |

---

## 5. Data Flow

### Job Posting Flow

```
Employer → POST /wcb/v1/jobs (with wcb_post_jobs ability)
  → Job created as pending (or published if moderation disabled)
  → wcb_check_job_expiry cron transitions past-deadline jobs to wcb_expired
  → Notifications module emails admin on pending, employer on publish
  → BuddyPress integration posts activity item on publish
```

### Application Flow

```
Candidate → POST /wcb/v1/applications (with wcb_apply_jobs ability)
  → Application created, linked to job + candidate
  → Notifications module emails employer
  → BP Pro integration sends BP notification to employer
  → Employer views in employer-dashboard block
  → [Pro] Employer drags application through Kanban pipeline stages
  → [Pro] Stage transitions logged, candidate notified via bell + email
```

### Credit Flow (Pro)

```
Employer → WooCommerce checkout → wcb_credit_ledger INSERT (entry_type=topup)
  → Employer posts featured job → INSERT (entry_type=hold)
  → Job published → INSERT (entry_type=deduct), hold released
  → Job rejected → INSERT (entry_type=refund)
  → Balance = SUM(amount) WHERE employer_id = X
```

---

## 6. File Structure

```
wp-career-board/                      wp-career-board-pro/
├── wp-career-board.php               ├── wp-career-board-pro.php
├── core/                             ├── core/
│   ├── class-plugin.php              │   ├── class-pro-plugin.php
│   ├── class-install.php             │   ├── class-pro-install.php
│   ├── class-roles.php               │   └── class-pro-abilities.php
│   └── class-abilities.php           ├── modules/
├── modules/ (12 modules)             │   ├── credits/adapters/ (WooCommerce)
│   ├── jobs/  employers/             │   ├── fields/  boards/  ai/
│   ├── candidates/  applications/    │   ├── alerts/  pipeline/  resume/
│   ├── search/  notifications/       │   ├── maps/  notifications-pro/
│   ├── moderation/  seo/             │   ├── notifications-bell/  pwa/
│   ├── gdpr/  antispam/              │   ├── migration/  analytics/
│   ├── boards/  theme-integration/   │   └── feed/
├── api/                              ├── api/endpoints/ (9 files)
│   ├── class-rest-controller.php     ├── blocks/ (15 blocks)
│   └── endpoints/ (7 files)          ├── admin/
├── blocks/ (14 blocks)               │   ├── class-pro-admin.php
├── admin/                            │   ├── class-admin-credits.php
│   ├── class-admin.php               │   ├── class-admin-field-builder.php
│   ├── class-admin-settings.php      │   └── class-admin-boards.php
│   └── class-setup-wizard.php        ├── integrations/buddypress/
├── cli/ (status, job, application,   ├── assets/  languages/  tests/
│        migrate)                     └── ...
├── integrations/
│   ├── buddypress/  buddyx-pro/
│   └── reign/
├── import/  assets/  languages/
└── tests/
```
