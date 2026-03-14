# WP Career Board — Complete Design Specification

**Date:** 2026-03-14
**Status:** Updated after market gap audit — 2026-03-14
**Author:** Varun Dubey, Wbcom Designs

---

## 1. Plugin Identity & Positioning

**Plugin:** WP Career Board
**Domain:** wpcareerboard.com
**Slug:** `wp-career-board`
**Text Domain:** `wp-career-board`
**Tagline:** *The community-powered job board for WordPress*

**Positioning statement:**
> WP Career Board is the only WordPress job board plugin built for the next five years — natively on the Interactivity API, with first-class Reign and BuddyX Pro support, a vendor-agnostic credit system, and AI matching baked in from day one. While others bolt features on, WP Career Board ships complete.

**Requirements:** WP 6.9+, PHP 8.1+

### Technical Conventions
- **Prefix:** `wcb_` — all CPT slugs, DB tables, hooks, filters, options, transients
- **Admin menu:** Top-level "WP Career Board" menu in wp-admin (WooCommerce-style)
- **Pro dependency:** Pro plugin requires Free to be active. Dependency check on Pro activation. Pro adds modules on top of Free core.
- **Distribution:** Both Free and Pro hosted exclusively on wbcomdesigns.com via EDD. No WordPress.org submission.
- **Team:** Varun Dubey + Claude Code
- **Alpha target:** 1 month from project start (Phase 1 core working)

**Distribution:** Freemium
- **Free (Core):** WordPress.org — `wp-career-board`
- **Pro:** wbcomdesigns.com — `wp-career-board-pro`

**Licensing:** EDD Software Licensing — Tiered Annual + Lifetime option
**Tiers:** Single Site / 5 Sites / Unlimited Sites

### Free vs Pro Feature Split

| Feature | Free | Pro |
|---------|------|-----|
| Single job board | ✓ | ✓ |
| Job listings CPT + taxonomies | ✓ | ✓ |
| Employer + candidate roles | ✓ | ✓ |
| Basic job search + filters | ✓ | ✓ |
| Simple apply (no credits) | ✓ | ✓ |
| Basic company + candidate profiles | ✓ | ✓ |
| wp_mail email notifications | ✓ | ✓ |
| Reign + BuddyX Pro templates | ✓ | ✓ |
| BuddyPress basic integration | ✓ | ✓ |
| WCAG 2.1 AA + GDPR basics | ✓ | ✓ |
| Multi-board engine | ✗ | ✓ |
| Credit system + Stripe + webhooks | ✗ | ✓ |
| No-code field builder | ✗ | ✓ |
| Resume builder (repeater groups) | ✗ | ✓ |
| Stage pipeline ATS | ✗ | ✓ |
| AI matching + suggestions + chat | ✗ | ✓ |
| Job alerts (saved searches) | ✗ | ✓ |
| Map view + radius search | ✗ | ✓ |
| PWA + offline browsing | ✗ | ✓ |
| Per-board i18n + currency | ✗ | ✓ |
| Advanced notifications (push/SMS/WhatsApp) | ✗ | ✓ |
| Migration tools | ✗ | ✓ |
| Analytics dashboard | ✗ | ✓ |
| Dynamic OG image generation | ✗ | ✓ |
| Google Indexing API (instant indexing) | ✗ | ✓ |
| Knockout/scored screening questions | ✗ | ✓ |
| Candidate subscription tier (priority apply) | ✗ | ✓ |
| Calendly/Cal.com interview scheduling | ✗ | ✓ |
| Ghost job enforcement + Verified Active badge | ✗ | ✓ |
| Niche board presets (Trades, Creator, Climate…) | ✗ | ✓ |
| LinkedIn cross-posting API | ✗ | ✓ |
| Async video application field | ✗ | ✓ |
| TestGorilla skills assessment integration | ✗ | ✓ |
| Employer branding package | ✗ | ✓ |
| Zapier/Make outbound webhooks | ✗ | ✓ |
| Salary transparency compliance toggle | ✗ | ✓ |
| Checkr background check API | ✗ | ✓ |
| Merge.dev HRIS sync (BambooHR, Workday…) | ✗ | ✓ |
| Levels.fyi salary data widget | ✗ | ✓ |
| DocuSign offer letter + e-signature | ✗ | ✓ |
| Employer hiring intelligence dashboard | ✗ | ✓ |

**Theme support:**
- Reign — full template layer + Customizer + panels + design system
- BuddyX Pro — full template layer + design system
- Any WordPress theme — baseline functional styles

**BuddyPress / BuddyBoss:** Optional. Auto-detected. Unlocks community features when active.

**Replaces:** BuddyPress Job Manager (Wbcom). Migration tool included.
**Testing:** Manual QA before each release.
**Accessibility:** WCAG 2.1 AA across all interfaces.

---

## 2. Core Architecture

**Pattern:** API-First Modular Monolith

Single plugin. All subsystems are internal modules — each registers its own CPTs, REST routes, Interactivity API controllers, Abilities API rules, and Gutenberg blocks. Feature flags in admin toggle modules on/off. No external module plugins required.

```
wp-career-board/
├── core/                    # Plugin bootstrap, module loader, hook registry
├── modules/
│   ├── boards/              # Multi-board engine
│   ├── fields/              # No-code field builder
│   ├── credits/             # Credit ledger + Stripe + webhooks
│   ├── jobs/                # Job listing CPT + taxonomies
│   ├── employers/           # Company profiles + dashboards
│   ├── candidates/          # Resume builder + profiles
│   ├── applications/        # Apply flow + stage pipeline
│   ├── search/              # Search + filters + alerts
│   ├── ai/                  # AI matching + suggestions
│   ├── maps/                # Pluggable map provider
│   ├── notifications/       # Abstracted notification system
│   ├── moderation/          # Configurable approval system
│   ├── seo/                 # Schema + OG + sitemap
│   ├── pwa/                 # PWA + mobile UX
│   ├── i18n/                # Per-board language/currency
│   ├── gdpr/                # Data retention + erasure
│   ├── analytics/           # Standard reporting
│   └── migration/           # Import tools from competitors
├── integrations/
│   ├── buddypress/          # BP member types, activity, groups
│   ├── buddyboss/           # BuddyBoss platform support
│   ├── reign/               # Reign template layer + Customizer
│   └── buddyx-pro/          # BuddyX Pro template layer
├── blocks/                  # All Gutenberg blocks (Interactivity API)
├── api/                     # REST API v1 endpoints
└── admin/                   # Admin UI, settings, analytics dashboard
```

**Data flow:**
```
Block (Interactivity API) → REST API v1 → Module → DB
                                ↑
                    Abilities API (permission check)
```

**Key architectural rules:**
- Every module communicates through the REST API — no direct DB calls across modules
- Abilities API checks on every REST endpoint — no custom capability checks scattered in code
- All user-facing UI is Gutenberg blocks powered by Interactivity API — no shortcodes, no page refresh
- Map, AI, notification providers implement a driver interface — swap without touching core
- BuddyPress/BuddyBoss/Reign/BuddyX are integrations — core works without them

---

## 3. Data Model

### Custom Post Types

| CPT | Slug | Description |
|-----|------|-------------|
| Job Listing | `wcb_job` | Core job post. Belongs to a board. |
| Company | `wcb_company` | Employer company profile. |
| Application | `wcb_application` | Candidate application to a job. |
| Resume | `wcb_resume` | Candidate resume (structured + PDF). |
| Job Board | `wcb_board` | Each board instance. |
| Credit Package | `wcb_credit_package` | Purchasable credit bundles. |

### Taxonomies

| Taxonomy | Slug | Type | Description |
|----------|------|------|-------------|
| Job Category | `wcb_category` | Hierarchical | Industry/function categories |
| Job Type | `wcb_job_type` | Flat | Full-time, Part-time, Contract, Freelance, Internship |
| Job Tag | `wcb_tag` | Flat | Free-form tags |
| Job Location | `wcb_location` | Hierarchical | Country → State → City |
| Experience Level | `wcb_experience` | Flat | Entry, Mid, Senior, Lead, Executive |

### Custom DB Tables

| Table | Key Columns | Purpose |
|-------|-------------|---------|
| `wcb_credit_ledger` | id, employer_id, amount, type (topup/deduction/refund/hold), job_id, note, created_at | Every credit transaction. Append-only. |
| `wcb_field_groups` | id, board_id, entity_type (job/company/candidate), label, order, created_at | Field builder groups. |
| `wcb_field_definitions` | id, group_id, field_key, field_type, label, options (JSON), rules (JSON), visibility, required, order | Individual field configs. |
| `wcb_field_values` | id, post_id, field_key, value (longtext) | Stored custom field values per post. |
| `wcb_job_boards` | id, job_id, board_id | Junction table: many-to-many job ↔ board membership. |
| `wcb_job_alerts` | id, user_id, board_id, search_query, filters (JSON), frequency (daily/weekly/instant), last_sent_at, created_at | Saved searches for job alert emails. |
| `wcb_application_stages` | id, board_id, label, color, order, is_terminal (bool), terminal_outcome (hired/rejected/null) | Stage pipeline stages per board. |
| `wcb_notifications_log` | id, user_id, event_type, channel, payload (JSON), status (sent/failed), sent_at | All notifications sent. |
| `wcb_job_views` | id, job_id, viewed_at, ip_hash | Job view counts for analytics. |
| `wcb_gdpr_log` | id, user_id, action (consent/export/erase), metadata (JSON), ip_hash, created_at | Consent records, erasure requests. |
| `wcb_ai_vectors` | id, entity_type (job/candidate), entity_id, provider, model_version, vector (longblob), created_at, updated_at | Embedding vectors for AI matching. |

### Postmeta & Usermeta Conventions

**`wcb_application` postmeta:**
- `_wcb_job_id` — ID of the job applied to
- `_wcb_candidate_id` — ID of the applying candidate
- `_wcb_resume_id` — ID of the resume used
- `_wcb_cover_letter` — Cover letter text
- `_wcb_status` — Core status: `submitted`, `reviewed`, `closed`
- `_wcb_stage_id` — Current stage ID (null if stage pipeline not active for this board)
- `_wcb_custom_fields` — JSON-encoded custom field values

**Status vs Stage:** Core status (`_wcb_status`) tracks the high-level lifecycle and is always present. Stage (`_wcb_stage_id`) is optional and board-specific — only populated when the stage pipeline module is enabled for the board. Stage changes do not override core status. Core status `closed` is set when a terminal stage (hired/rejected) is reached.

**`wcb_company` postmeta for employer trust:**
- `_wcb_trust_level` — `new`, `verified`, or `trusted`
- `_wcb_domain_verified` — bool, whether email domain verification passed
- `_wcb_verified_domain` — the verified domain string

**Bookmarks:** Stored as usermeta key `_wcb_bookmarks` — JSON array of job IDs per user. Updated atomically on bookmark toggle.

### User Roles

| Role | Capabilities |
|------|-------------|
| `wcb_employer` | Post jobs (costs credits), manage company, view applications, access employer dashboard |
| `wcb_candidate` | Apply to jobs, build resume, bookmark jobs, set profile visibility |
| `wcb_board_moderator` | Approve/reject jobs on assigned boards only |
| `administrator` | Full access to all boards, credits, users, settings |

### BuddyPress Layer (when active)
- `wcb_employer` maps to BP Member Type: `employer`
- `wcb_candidate` maps to BP Member Type: `candidate`
- `wcb_company` optionally maps to BP Group
- Resume fields sync with BP xProfile fields

---

## 4. Module Breakdown

### Module 1 — Boards
Multi-board engine. Each board has its own: slug, name, description, field schema, job categories, moderation mode (auto-publish / approval), credit cost per listing, language, currency, date format, map provider, active AI features, and notification settings. Jobs belong to one or more boards. Boards are manageable from a dedicated admin screen.

### Module 2 — Field Builder
No-code drag-and-drop field builder scoped per board + entity (job, company, candidate).

**Field types:** text, textarea, number, URL, email, date, date range, select, multi-select, checkbox, radio, file upload (PDF/image), video URL, location/map picker, salary range (min/max + currency), repeater, conditional (show field X if field Y = Z).

**Features:** Field groups with section headers. Per-field: required/optional toggle, visibility (public/employer-only/admin-only), help text, placeholder, default value. Drag-and-drop field ordering. Field visibility rules per board.

### Module 3 — Credits
Append-only credit ledger per employer. Credits are the platform currency.

**Top-up methods:**
- Stripe Checkout (native)
- Webhook receiver (signed payload — any external system: WooCommerce, EDD, custom)

**Credit packages** defined in admin (e.g., 10 credits = $29). Each board sets its own credit cost per job.

**Credit deduction timing (precise):**
- Auto-publish mode: credits deducted at moment of submit (submit = publish). If publish fails after deduction, credits are refunded automatically.
- Approval-required mode: credits held (ledger entry type: `hold`) at submit. Deducted (converted to `deduction`) on admin approval. Returned (ledger entry type: `refund`) on rejection.

Transaction log with CSV export. Low-credit email alert sent when balance drops below configurable threshold.

### Module 4 — Jobs
`wcb_job` CPT with core fields (title, description, deadline, salary, location, remote flag) + custom fields from field builder.

**Job status flow:** `draft → pending_review → published → expired`

Auto-expiry via WP-Cron. Featured job flag (costs extra credits). Job duplication (repost with one click). Per-board: credit cost, auto-publish vs approval, expiry days. `JobPosting` schema injected automatically. XML sitemap entry added. Dynamic OG image generation is handled by the SEO module (Phase 8) and triggered on job publish via hook.

### Module 5 — Employers
`wcb_company` CPT: company name, logo, banner, description, website, size, industry, social links + custom fields.

**Employer dashboard (Interactivity API):** manage jobs, view applications, track credit balance, buy credits, edit company profile.

**Employer trust levels:** New (requires approval) / Verified (auto-publish) / Trusted (extra perks). Optional email domain verification. Multiple employers can belong to one company.

### Module 6 — Candidates
`wcb_resume` CPT with structured resume sections using repeater group fields:

| Group | Fields |
|-------|--------|
| **School** | School name, degree/certificate, field of study, start date, end date, grade, description |
| **College / University** | Institution name, degree type (Bachelor/Master/PhD/Diploma), field of study, start date, end date, GPA, description, achievements |
| **Job Experience** | Company name, job title, employment type, location, start date, end date, currently working here (checkbox), description, skills used |
| **Certifications** | Certification name, issuing body, issue date, expiry date, credential ID, URL |
| **Skills** | Skill name, proficiency level (Beginner/Intermediate/Expert) |
| **Languages** | Language, proficiency (Basic/Conversational/Fluent/Native) |
| **Portfolio / Links** | Label, URL |

Optional PDF upload alongside structured resume. BP xProfile sync when BuddyPress active.

**Candidate dashboard:** manage applications, saved jobs, profile visibility toggle (public/private), job alerts, AI-powered job suggestions.

### Module 7 — Applications
**Core:** Candidate submits application (cover letter + resume selection + custom fields). Application stored as `wcb_application`. Employer sees all applications per job. Status: `submitted → reviewed → closed`.

**Stage Pipeline (optional module):** Employer defines custom stages per board (e.g., Applied → Phone Screen → Interview → Offer → Hired/Rejected). Applications move through stages via drag-and-drop Kanban. Email notification sent to candidate on stage change.

**Additional:** Bookmarks (candidates save jobs, swipe-to-bookmark on mobile). Application tags (employers tag applications for filtering).

### Module 8 — Search & Discovery
Keyword search (title + description + custom fields). Filters: category, job type, location, experience level, salary range, remote flag, tags, board, posted date, company. URL param sync (shareable filtered URLs).

**Job Alerts:** Candidate subscribes to saved search. Notified when matching job posted.

**AI features (when AI module active):** Semantic matching score per job, AI job suggestions on dashboard, chat-based natural language search, AI-ranked application shortlist for employers.

### Module 9 — AI
Abstracted AI interface with pluggable providers: OpenAI, Anthropic Claude, Ollama (local), custom endpoint. Per-feature provider selection in admin.

**Features:**
1. Candidate ↔ Job semantic matching via embeddings (`wcb_ai_vectors`)
2. AI job description generator for employers
3. Resume gap analysis for candidates
4. Application ranking for employers (0-100 score with reasoning)
5. Chat-based natural language search

### Module 10 — Maps
Pluggable map provider interface. Drivers: Google Maps (API key), Mapbox (API key), OpenStreetMap/Leaflet (free, bundled). Admin selects provider per board.

**Features:** Job location geocoding on save, map view of listings, radius search, location autocomplete in search bar and job form.

### Module 11 — Notifications
Core notification engine with pluggable channel drivers.

**Event types:** job published, application received, application status changed, stage changed, credit low, credit added, job expiring, job alert match.

**Drivers:** Email (wp_mail / SendGrid / Mailgun / SES), In-App (notification bell), Web Push, SMS (Twilio).

Per-user channel preferences. Per-board notification defaults. Full notification log in admin.

### Module 12 — Moderation
Per-board setting: auto-publish or approval-required. Per-employer override based on trust level.

**Admin moderation queue:** pending jobs list with preview, approve/reject with reason. Rejection triggers employer notification. Moderation history log per job.

**Credit handling:** Auto-publish deducts on submit. Approval-required holds credits, deducts on approval, returns on rejection.

### Module 13 — SEO
`JobPosting` schema (Google for Jobs). Auto meta title/description per job. Canonical URLs. Separate XML sitemap for jobs. Breadcrumb schema. `Organization` schema for companies. Open Graph + Twitter Card tags. Dynamic OG image generation per job (title + company logo + salary + location). Yoast SEO + RankMath compatible.

### Module 14 — PWA + Mobile
Service worker scoped to the plugin's public pages only (`/jobs/*`, `/companies/*`, `/candidates/*` — configurable). Cache strategy: stale-while-revalidate for job listings (fast loads + background refresh), network-first for application forms (always fresh). Cache size limit: 50MB, evicted LRU. Service worker version-stamped on each release — old caches cleared on activation. Explicitly avoids interfering with admin pages or other plugins' service workers.

Offline job browsing (Cache API — job cards cached on visit). Web Push subscription (VAPID keys generated on install). PWA install prompt shown after second visit.

**Mobile UX:** Bottom navigation bar (Browse / Saved / Applications / Dashboard). Swipe-to-bookmark. Thumb-friendly touch targets (min 44px). Mobile-first application form flow.

### Module 15 — i18n
Per-board: language (WPML/Polylang compatible), currency (symbol, position, decimals), date format, number format. RTL stylesheet. One install runs multiple language boards simultaneously. All strings translation-ready (`.pot` file).

### Module 16 — GDPR
WordPress privacy tools integration. Candidate data export on request. Account deletion wipes personal data. Configurable data retention (auto-delete applications after X days via WP-Cron). Consent logging (`wcb_gdpr_log`). Employer DPA acceptance on registration. Email opt-in consent tracked separately.

### Module 17 — Analytics
Admin dashboard: total jobs, applications, employers, candidates, credits issued/spent. Per-board stats. Job view counts. Application rate per job. Credit transaction history. Employer activity (active/ghost). Board performance comparison. CSV export.

### Module 18 — Migration
**One-click importers:**
- WP Job Manager (wordpress.org/plugins/wp-job-manager) → WP Career Board
- WPJobBoard (wpjobboard.net) → WP Career Board
- Simple Job Board (wordpress.org/plugins/simple-job-board) → WP Career Board
- Job Board Manager (wordpress.org/plugins/job-board-manager) → WP Career Board *(lower priority — small install base, included for completeness)*
- BuddyPress Job Manager (Wbcom internal) → WP Career Board (schema fully known)
- CSV importer with column mapping UI (Indeed export, LinkedIn export, any source)

---

## 5. Integrations

### BuddyPress / BuddyBoss (auto-detected)

| Feature | When BP/BB active |
|---------|------------------|
| Member Types | `wcb_employer` → BP Employer. `wcb_candidate` → BP Candidate |
| Profile Sync | Resume fields sync bidirectionally with BP xProfile field groups |
| Activity Stream | Job posted, applied, hired → BP activity |
| Company as BP Group | `wcb_company` optionally linked to BP Group |
| Notifications | WP Career Board notifications in BP notification bell |
| Friend Connections | Candidates see BP connections at a company |
| Messages | Employer ↔ Candidate via BP private messages |
| BuddyBoss | Boss Bar + profile cards + BuddyBoss notification integration |

### Reign Theme (dedicated)

| Area | Integration |
|------|------------|
| Templates | Job archive, single job, employer dashboard, candidate dashboard, company profile, application list |
| Navigation | WP Career Board panels in Reign left sidebar navigation |
| Dashboard | Employer + Candidate dashboards use Reign dashboard layout |
| Customizer | Reign Customizer section: colours, layout, sidebar position, card style |
| Widgets | Job listings, featured jobs, job search widgets — Reign-styled |
| Header | Optional job search bar in Reign header |

### BuddyX Pro Theme (dedicated)

| Area | Integration |
|------|------------|
| Templates | Full template override layer matching BuddyX Pro design system |
| Navigation | WP Career Board items in BuddyX Pro navigation |
| Dashboard | Employer + Candidate dashboards use BuddyX Pro panel layout |
| Profile | Job-seeking status badge on BuddyX Pro member profiles |
| Customizer | BuddyX Pro Customizer section for WP Career Board settings |

### Third Party Services

| Service / Plugin | What it unlocks | Connection |
|-----------------|-----------------|------------|
| Stripe | Credit top-up via Checkout | PHP SDK, API keys |
| OpenAI | AI features (GPT-4 + embeddings) | API key |
| Anthropic Claude | Alternative AI provider | API key |
| Ollama | Local AI, no API cost | Server URL |
| Google Maps | Geocoding, map view, radius search | API key per board |
| Mapbox | Same as Google Maps | API key per board |
| OpenStreetMap + Leaflet | Free map view | Bundled, zero config |
| SendGrid | Email delivery | API key |
| Mailgun | Email delivery | API key |
| Amazon SES | Email delivery | AWS credentials |
| Twilio | SMS notifications | Account SID + token |
| WooCommerce | Credit top-up via WC orders | Webhook receiver |
| Easy Digital Downloads | Credit top-up via EDD purchases | Webhook receiver |
| WPML | Multilingual job listings | String registration |
| Polylang | Multilingual job listings | String registration |
| Yoast SEO | Schema compatibility | Auto-detected |
| RankMath | Schema compatibility | Auto-detected |
| EDD Software Licensing | License keys + update delivery | Standard Wbcom model |

**No dependencies on:** WooCommerce, any form plugin, any page builder, any map plugin, any membership plugin, any SEO plugin.

---

## 6. Gutenberg Blocks & Interactivity API

All blocks powered by WordPress Interactivity API. Zero page reloads in any user journey. Server-side rendered on first load (SEO-friendly), client-side reactive after hydration.

### Job Seeker Blocks

| Block | Behaviour |
|-------|-----------|
| `wcb/job-search` | Live filtering as user types. Filters update URL params. |
| `wcb/job-listings` | Reactive grid/list toggle. Infinite scroll. Bookmark toggle per card. Swipe-to-bookmark on mobile. |
| `wcb/job-filters` | Sidebar or horizontal. Each filter updates results instantly. Active filter chips. |
| `wcb/job-single` | Apply button → slide-in application panel. AI match score for logged-in candidates. |
| `wcb/job-map` | Interactive map. Clicking pin shows job card. Synced with list view. |
| `wcb/job-alerts` | Subscribe to current search. Email frequency picker. Manage alerts inline. |
| `wcb/ai-chat-search` | Chat input. Natural language → AI parsed → results update. |
| `wcb/candidate-dashboard` | Tabbed: Applications, Saved Jobs, Profile, Resume, Job Alerts, Notifications. |
| `wcb/resume-builder` | Section-by-section editor. Repeater groups. Live preview. PDF download. |

### Employer Blocks

| Block | Behaviour |
|-------|-----------|
| `wcb/employer-dashboard` | Tabbed: My Jobs, Applications, Company Profile, Credits, Analytics. |
| `wcb/job-form` | Multi-step job posting form. Live credit cost preview. AI description generator. |
| `wcb/application-list` | Per-job applications. Stage pipeline Kanban (drag-and-drop). AI score per application. |
| `wcb/credit-balance` | Live credit balance. Buy credits → Stripe Checkout. |
| `wcb/company-profile-editor` | Inline editable. Logo + banner upload. Live preview. |

### Content Blocks

| Block | Purpose |
|-------|---------|
| `wcb/featured-jobs` | Curated featured listings for homepage/landing pages |
| `wcb/job-stats` | Public stats (X jobs, Y companies, Z candidates) |
| `wcb/board-switcher` | Multi-board tab switcher |
| `wcb/company-directory` | Searchable company grid |
| `wcb/candidate-directory` | Searchable candidate listing (public profiles only) |

### Interactivity API Pattern
```
Block PHP render → wp_interactivity_state() seeds initial data
                ↓
            Directives (data-wp-bind, data-wp-on, data-wp-context)
                ↓
            store() actions → fetch() REST API v1 endpoints
                ↓
            Reactive DOM updates (no page reload)
```

---

## 7. REST API

**Base:** `/wp-json/wcb/v1/`
**Auth:** WordPress Application Passwords
**Permissions:** Abilities API check on every endpoint

### Boards
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/boards` | List all boards |
| GET | `/boards/{id}` | Single board + field schema |
| POST | `/boards` | Create board (admin) |
| PATCH | `/boards/{id}` | Update board settings |
| DELETE | `/boards/{id}` | Delete board. Cascades: jobs moved to default board or trashed per admin setting. Credits refunded for unposted jobs. |

### Jobs
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/jobs` | Search + filter. Params: `board`, `search`, `category`, `type`, `location`, `salary_min`, `salary_max`, `remote`, `tag`, `page`, `per_page` |
| GET | `/jobs/{id}` | Single job (increments view count) |
| POST | `/jobs` | Create job (deducts credits) |
| PATCH | `/jobs/{id}` | Update job |
| DELETE | `/jobs/{id}` | Trash job |
| POST | `/jobs/{id}/bookmark` | Toggle bookmark (candidate) |
| GET | `/jobs/{id}/applications` | Applications for job (employer) |
| POST | `/jobs/ai-description` | AI-generated job description |

### Applications
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/jobs/{id}/apply` | Submit application |
| GET | `/applications/{id}` | Single application |
| PATCH | `/applications/{id}/stage` | Move to stage |
| PATCH | `/applications/{id}/status` | Update status |
| POST | `/applications/{id}/tags` | Add/remove tags |

### Candidates
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/candidates/{id}` | Candidate profile (if public) |
| PATCH | `/candidates/{id}` | Update profile + resume |
| GET | `/candidates/{id}/matches` | AI job suggestions |
| GET | `/candidates/{id}/applications` | My applications |
| GET | `/candidates/{id}/bookmarks` | My saved jobs |

### Employers
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/employers/{id}` | Company profile |
| PATCH | `/employers/{id}` | Update company |
| GET | `/employers/{id}/jobs` | Employer's jobs |
| GET | `/employers/{id}/credits` | Balance + transaction log |

### Credits
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/credits/checkout` | Initiate Stripe checkout |
| POST | `/credits/webhook` | Signed webhook receiver |
| GET | `/credits/packages` | Available credit packages |

### Search & Alerts
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/search` | Unified search |
| POST | `/search/ai-chat` | Natural language AI search |
| GET | `/alerts` | My job alerts |
| POST | `/alerts` | Create job alert |
| DELETE | `/alerts/{id}` | Delete job alert |

### AI
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/ai/match` | Match score (job ↔ candidate) |
| POST | `/ai/gap-analysis` | Resume gap analysis |
| GET | `/ai/ranked-applications/{job_id}` | AI-ranked applications |

### Stages (Pipeline)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/boards/{id}/stages` | List stages for a board |
| POST | `/boards/{id}/stages` | Create stage (employer/admin) |
| PATCH | `/boards/{id}/stages/{stage_id}` | Update stage label, colour, order |
| DELETE | `/boards/{id}/stages/{stage_id}` | Delete stage (applications in this stage moved to previous stage) |

### Notifications
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/notifications` | List in-app notifications for current user |
| PATCH | `/notifications/{id}` | Mark notification read/unread |
| DELETE | `/notifications/{id}` | Dismiss notification |
| GET | `/notifications/preferences` | Get user's channel preferences |
| PATCH | `/notifications/preferences` | Update channel preferences |

### Admin
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/admin/stats` | Platform analytics |
| GET | `/admin/notifications/log` | Notification log (filterable by user, channel, event type, date) |
| GET | `/admin/moderation` | Pending jobs queue |
| POST | `/admin/moderation/{job_id}/approve` | Approve job |
| POST | `/admin/moderation/{job_id}/reject` | Reject with reason |
| GET | `/admin/moderation/{job_id}/history` | Moderation history log for a job |
| GET | `/admin/applications/export` | CSV export of all applications (filterable by job/board/date) |
| GET | `/admin/gdpr/requests` | List pending data export/erasure requests |
| POST | `/admin/gdpr/export/{user_id}` | Trigger personal data export |
| DELETE | `/admin/gdpr/erase/{user_id}` | Erase candidate personal data |
| POST | `/admin/migration/{source}` | Trigger migration from source plugin |

---

## 8. Build Phases & Roadmap

### Phase 1 — Foundation
Plugin bootstrap, module loader, `wcb_board` CPT + multi-board engine, `wcb_job` / `wcb_company` / `wcb_application` / `wcb_resume` CPTs, core taxonomies, user roles + Abilities API wiring, Field Builder (all field types including repeater + conditional), admin settings, setup wizard + sample data, EDD Software Licensing, baseline theme styles.

### Phase 2 — Employer Layer
Company profiles, employer dashboard (Interactivity API), multi-step job posting form, job status flow, job duplication, featured jobs, auto-expiry, moderation system (configurable per board + per employer trust level), trust levels, optional email domain verification.

**Minimal email layer (Phase 2 prerequisite):** A lightweight `wp_mail()`-based email sender is introduced in Phase 2 to support job approval/rejection notifications and low-credit alerts. This is not the full notification engine (Phase 9) — it uses simple template files and `wp_mail()` only. The full engine in Phase 9 replaces and extends it with pluggable drivers and in-app/push/SMS channels.

### Phase 3 — Credit System
Credit ledger, credit packages, Stripe Checkout (native), webhook receiver, credit deduction/refund logic, low-credit alerts, credit balance widget, transaction log + CSV export.

### Phase 4 — Candidate Layer
Candidate profiles, resume builder (all repeater groups: School, College, Job Experience, Certifications, Skills, Languages, Portfolio), PDF upload, privacy toggle, candidate dashboard, bookmarks, application tracking, BP xProfile sync.

### Phase 5 — Application System
Application submission, application list (employer view), status tracking, stage pipeline Kanban (including stage CRUD endpoints), application tags, CSV export of applications. Stage change notifications in Phase 5 use the same lightweight `wp_mail()` layer introduced in Phase 2. The full notification engine in Phase 9 upgrades these to use pluggable drivers (SendGrid, in-app bell, push, SMS) without breaking Phase 5 behaviour.

### Phase 6 — Search & Discovery
Keyword search, all filters, URL param sync, map view block, job alerts (saved search → email), `wcb/job-search` / `wcb/job-listings` / `wcb/job-filters` blocks.

### Phase 7 — AI Layer
Pluggable AI provider interface, embedding generation + `wcb_ai_vectors`, semantic matching, AI job description generator, resume gap analysis, application ranking, chat-based search block.

### Phase 8 — SEO + PWA
`JobPosting` schema, auto meta tags, canonical URLs, XML sitemap, breadcrumb + Organization schema, dynamic OG image generation, OG + Twitter Card tags, Yoast/RankMath compatibility, service worker, offline caching, Web Push, PWA install prompt, mobile UX (bottom nav, swipe gestures, touch targets).

### Phase 9 — Notifications + i18n + GDPR
Notification engine + all drivers (email, in-app, push, SMS), per-user channel preferences, per-board language/currency/date/number format, RTL stylesheet, WPML/Polylang compatibility, data retention policies, Personal Data Export + Eraser, consent logging, employer DPA.

### Phase 10 — Theme Integrations
Reign: full template layer + navigation panels + Customizer + dashboard layout + widgets + header search. BuddyX Pro: full template layer + navigation + panels + profile badges + Customizer. BuddyPress: member types + activity stream + BP Groups + notification bridge + friend connections + BP messages. BuddyBoss: Boss Bar + profile cards + notification integration.

### Phase 11 — Analytics + Admin Polish
Analytics dashboard, CSV exports, moderation queue polish, notification log, GDPR admin dashboard.

### Phase 12 — Migration Tools
WP Job Manager, WPJobBoard, Simple Job Board, Job Board Manager, BuddyPress Job Manager importers + CSV importer with column mapping UI.

### Milestones

| Milestone | Phases | Deliverable |
|-----------|--------|-------------|
| **Alpha** | 1–3 | Working job board with credits. Internal testing. |
| **Beta** | 4–6 | Full employer + candidate experience with search. Beta customers. |
| **v1.0** | 7–9 | AI + PWA + notifications + i18n + GDPR. Public launch. |
| **v1.5** | 10 | Reign + BuddyX Pro + BuddyPress/BuddyBoss integrations. |
| **v2.0** | 11–12 | Analytics + migration tools. Full platform. |

---

## 9. Third Party Dependencies

**Required:** None. Plugin works standalone.

### Optional (unlocks enhanced features)

| Service | Feature unlocked | Connection |
|---------|-----------------|------------|
| Stripe | Credit top-up | PHP SDK + API keys |
| OpenAI | AI features + embeddings | API key |
| Anthropic Claude | Alternative AI provider | API key |
| Ollama | Local AI (no cost) | Server URL |
| Google Maps | Map view, geocoding, radius search | API key per board |
| Mapbox | Map view alternative | API key per board |
| OpenStreetMap/Leaflet | Free map view | Bundled, zero config |
| SendGrid | Email delivery | API key |
| Mailgun | Email delivery | API key |
| Amazon SES | Email delivery | AWS credentials |
| Twilio | SMS notifications | SID + token |
| WooCommerce | Credit top-up via WC orders | Webhook receiver |
| EDD | Credit top-up via EDD purchases | Webhook receiver |
| BuddyPress | Community features | Auto-detected |
| BuddyBoss Platform | BuddyBoss features | Auto-detected |
| WPML | Multilingual listings | String registration |
| Polylang | Multilingual listings | String registration |
| Yoast SEO | Schema compatibility | Auto-detected |
| RankMath | Schema compatibility | Auto-detected |

---

## 10. Competitive Positioning

| Feature | WP Job Manager | WPJobBoard | Simple JB | **WP Career Board** |
|---------|---------------|-----------|-----------|-------------------|
| Interactivity API | ✗ | ✗ | ✗ | ✓ Native |
| Gutenberg blocks | ✗ | ✗ | ✗ | ✓ Full library |
| AI matching | ✗ | ✗ | ✗ | ✓ Native, pluggable |
| AI job description | ✗ | ✗ | ✗ | ✓ Built-in |
| AI chat search | ✗ | ✗ | ✗ | ✓ Built-in |
| Multi-board (single site) | ✗ | ✗ | ✗ | ✓ Native |
| No-code field builder | ✗ | Partial | ✗ | ✓ Full D&D |
| Vendor-agnostic credits | ✗ | ✗ | ✗ | ✓ Webhook-in |
| BuddyPress deep integration | Via add-on | ✗ | ✗ | ✓ Native |
| BuddyX Pro support | ✗ | ✗ | ✗ | ✓ Native |
| Reign support | ✗ | ✗ | ✗ | ✓ Native |
| PWA + offline | ✗ | ✗ | ✗ | ✓ Built-in |
| Pluggable map providers | Via add-on | Google only | ✗ | ✓ 3 providers |
| Per-board i18n | ✗ | ✗ | ✗ | ✓ Native |
| Full GDPR compliance | Partial | Partial | ✗ | ✓ Full |
| Stage pipeline (ATS) | Via paid add-on | ✓ | ✗ | ✓ Built-in module |
| Migration from competitors | ✗ | ✗ | ✗ | ✓ 5 importers |
| PHP 8.1+ native | Partial | ✗ | ✗ | ✓ Required |
| WP 6.9+ native | ✗ | ✗ | ✗ | ✓ Required |
| Dynamic OG images | ✗ | ✗ | ✗ | ✓ Auto-generated |
| Full resume builder | Via add-on | Basic | ✗ | ✓ Repeater groups |

### Target Buyers

| Buyer | Pain point solved |
|-------|-----------------|
| Reign / BuddyX Pro user | No job board that actually integrates with their theme |
| BuddyPress community owner | Community + job board are two disconnected systems |
| Niche job board founder | WP Job Manager extensions cost $500+/year for less |
| Developer building for client | Modern PHP 8.1+, REST API, clean hooks — not legacy code |
| Multi-community operator | No single WP plugin runs multiple boards from one install |

---

## 11. Market Gap Audit (2026-03-14)

Full research report covering 5 areas: feature gaps, underserved niches, monetisation gaps, technical gaps, and competitive intelligence.

### 11.1 Feature Gaps No WP Plugin Currently Solves

| Gap | User Pain | Our Solution |
|-----|-----------|-------------|
| Ghost job listings | Candidates apply to roles not actively hiring — <2% interview rate | Listing freshness enforcement: auto-flag after X days of no employer activity, "Verified Active" badge, auto-expiry with employer re-confirmation prompt |
| No application status transparency | Candidates ghosted for months — universal complaint on r/jobsearch | Candidate dashboard shows live status per application: Applied → Reviewed → Interview Scheduled → Offer/No |
| Non-conditional application forms | Generic forms cause high abandonment | Conditional application fields per job type — show portfolio for design roles, GitHub for engineering, quota fields for sales |
| Knockout screening questions | Employers drowning in unqualified applications | Scored screening questions with configurable knockout logic — failing a threshold auto-declines with email |
| No interview scheduling | Scheduling an interview requires leaving the plugin | Cal.com (open-source) or Calendly API embedded in employer application view — candidate picks a slot, confirmation fires automatically |
| No async video applications | Modern hiring expects video screening | Video introduction field on application forms — self-hosted MediaRecorder API or Loom/Vimeo URL submission |
| No skills assessment | Pre-hire technical screening disconnected from job board | TestGorilla API integration — employer attaches assessment to listing, candidate auto-receives link on apply, results appear in application record |
| No salary transparency | 50%+ of US listings legally require pay range by 2025 | Per-board "salary transparency required" toggle — mandatory pay range field when enabled |

### 11.2 Underserved Niches — Niche Board Presets

Each preset is a one-click board configuration: pre-built field schema, category taxonomy, default filters, and tailored application form for that niche. No existing WordPress plugin has any preset system.

| Niche | Market Signal | Key Custom Fields |
|-------|--------------|------------------|
| **Skilled Trades** | 40% vacancy rate; electricians at $40/hr driven by AI data centers; retiring workforce | Trade licence upload, union status, shift type (day/night/rotating), journeyman vs apprentice, physical requirements, per-diem/travel pay, SMS notification default |
| **Creator Economy** | 36.8% YoY growth Q1 2025; 1,941 open positions Q3 2024 | YouTube/TikTok/Substack channel size, content niche, platform expertise, portfolio embed (YouTube, Spotify, Substack links) |
| **Climate / Green Jobs** | $1.3T projected investment; boards exist (Climatebase) but none self-hostable | Climate impact category, remote/hybrid/on-site, certifications (LEED, BREEAM), grant-funded flag |
| **Web3 / Crypto** | 300% surge 2023-2025; ~10,000 active global positions | Wallet address for on-chain compensation, token/equity split, DAO participation, protocol expertise, blockchain/chain expertise |
| **Neurodiversity-Friendly** | SAP, Goldman, Microsoft all running active programs | Accommodations available, interview format options (no panel, written preference), quiet workspace flag, WCAG 2.2 AA default |
| **Returnship Programs** | Amazon, Goldman, IBM programs with no WP infrastructure | Cohort-based mode with start date + program duration, re-entry reason field, application deadline per cohort |
| **Non-Profit** | Already heavy WordPress users; can't afford $129-$223/month SaaS | 501(c)(3) status, mission area, volunteer vs paid, grant-funded position flag |

### 11.3 Monetisation Gaps — New Revenue Streams

| Revenue Model | Market Evidence | WP Career Board Implementation |
|--------------|----------------|-------------------------------|
| **Candidate subscription tier** | Naukri built a B2C business on priority placement. No WP plugin has this. | `wcb_candidate_pro` subscription ($9-19/month): priority application placement, profile boost (surfaced in employer search), application open-tracking, extended history |
| **Employer branding package** | Wellfound charges $149/month; We Work Remotely $299-$408/listing. No WP plugin has structured branding. | Employer branding module: company video embed, culture photo gallery, team profile section, "Featured Employer" homepage placement, sponsored job slots in search |
| **Candidate database access** | LinkedIn Recruiter starts ~$8,000/yr. SMB boards charge $99-$299/month. | Recruiter subscription tier: searchable candidate pool (opt-in candidates only), saved searches, contact request system |
| **Agency / white-label license** | Niceboard $129-$223/month, SmartJobBoard $99-$149/month — WordPress undercuts all | Agency license tier: multi-client board management, client billing, branded board per client domain |
| **Hiring intelligence data product** | Ashby's analytics built into every screen is their core differentiator | Employer-facing dashboard: time-to-fill by role, salary benchmarks from board data, skills demand trends, application volume analytics — sold as employer Pro add-on |
| **Bootcamp partner program** | GA, Flatiron, Multiverse all have partner divisions. No WP job board has infrastructure for this. | Training provider partner pages, graduate candidate badges, cohort job-ready notifications to employers, revenue share on employer hires from bootcamp cohorts |
| **Sponsored employer content** | Built In and The Muse generate significant revenue from employer culture content | Employer content hub: employers pay to publish culture/hiring articles that live as SEO editorial, separate from job listings |

### 11.4 Technical Gaps — New Integrations

| Integration | Gap | Priority |
|-------------|-----|----------|
| **Google Indexing API** | WP plugins add JobPosting schema but don't call the Indexing API. Jobs wait days/weeks to be indexed vs minutes. | Tier 1 — direct SEO moat |
| **LinkedIn Jobs API** | No WP plugin posts directly to LinkedIn via API — only share buttons. LinkedIn partnership approval required. | Tier 1 — major differentiator |
| **Cal.com / Calendly API** | No WP job board integrates interview scheduling. Cal.com is open-source and free. | Tier 1 — closes biggest workflow gap |
| **Async video applications** | No WP plugin has any video application feature. MediaRecorder API is browser-native. | Tier 2 |
| **TestGorilla API** | SMB-friendly skills assessment. 0 WP job board integrations exist. | Tier 2 |
| **WhatsApp Business API** | Dominant outside North America; critical for trades boards (workers not at desks). Already in our notification driver model. | Tier 2 |
| **Zapier / Make webhooks** | First-class documented outbound webhook system with a webhook management UI — events: job posted, applied, status changed, hired | Tier 2 |
| **Checkr background check API** | Mandatory for trades, healthcare, childcare boards. 100+ existing ATS integrations. | Tier 3 |
| **Merge.dev HRIS sync** | One API integration covers BambooHR, Workday, SAP, ADP, 200+ HR systems. "Mark as Hired → sync to onboarding" closes the full hiring lifecycle. | Tier 3 |
| **Levels.fyi salary data API** | Market salary range widget on tech listings. No WP job board uses any salary data API. | Tier 3 |
| **DocuSign / HelloSign offer letter** | E-signature for offer letters closes full recruitment lifecycle inside WordPress. | Tier 3 |

### 11.5 Competitive Intelligence

**Market size:** Global Job Board Software Market $567M (2024) → $1.1B by 2033 (7.78% CAGR). Growing faster than WordPress plugins overall.

**Fastest-growing ATS (2024-2025):**
- **Ashby** — raised $50M Series D (July 2025). 4,000+ customers. Key features: AI Candidate Assistant, AI Notetaker (auto-transcribes interviews), AI-assisted application review, fraudulent candidate detection. Core moat: analytics built into every screen + all-in-one, no add-ons. WP Career Board should mirror this — no fragmentation.
- **Rippling** — NPS ~90. Auto-scheduling, interview recording/transcription, direct HR system sync on hire.

**No new modern-stack WP job board plugins launched in 2024-2025** that use the Interactivity API, Abilities API, or native Gutenberg blocks. UseVerb (AI-powered) is the closest, but it is a simple career page plugin, not a full job board platform. **The gap is real and unoccupied.**

**Developer complaints about existing WP plugins (primary research):**
- Add-on fragmentation: 4-6 paid add-ons ($49-99 each) to get full functionality
- No built-in ATS — forces external tool usage
- Poor documentation, confusing admin UI
- No niche-specific features — no custom fields, no custom filters
- Unstable performance, plugin conflicts (Jobify cited)

### 11.6 Updated Competitive Comparison

| Feature | WP Job Manager | WPJobBoard | Ashby (SaaS) | **WP Career Board** |
|---------|---------------|-----------|-------------|-------------------|
| Interactivity API | ✗ | ✗ | N/A | ✓ |
| AI matching + ranking | ✗ | ✗ | ✓ | ✓ |
| AI interview notes | ✗ | ✗ | ✓ | Roadmap |
| Knockout screening questions | ✗ | ✗ | ✓ | ✓ |
| Interview scheduling | ✗ | ✗ | ✓ | ✓ Cal.com/Calendly |
| Video applications | ✗ | ✗ | ✓ | ✓ |
| Skills assessment | ✗ | ✗ | ✓ | ✓ TestGorilla |
| Multi-board (single site) | ✗ | ✗ | N/A | ✓ |
| Niche board presets | ✗ | ✗ | N/A | ✓ 7 presets |
| Candidate subscription tier | ✗ | ✗ | ✗ | ✓ |
| Employer branding package | ✗ | ✗ | ✗ | ✓ |
| Ghost job enforcement | ✗ | ✗ | ✗ | ✓ |
| Google Indexing API | ✗ | ✗ | N/A | ✓ |
| LinkedIn API cross-posting | ✗ | ✗ | ✓ | ✓ |
| WhatsApp notifications | ✗ | ✗ | ✗ | ✓ |
| HRIS sync on hire | ✗ | ✗ | ✓ | ✓ Merge.dev |
| Offer letter + e-signature | ✗ | ✗ | ✓ | ✓ DocuSign |
| BuddyPress/community native | Via add-on | ✗ | N/A | ✓ |
| WordPress-native | ✓ | ✓ | N/A | ✓ |
| Self-hosted | ✓ | ✓ | ✗ | ✓ |
| Pricing | Free+add-ons | $97+/yr | ~$10k/yr | Free + Pro |

**WP Career Board sits between WordPress plugins and enterprise SaaS — feature parity with Ashby, self-hosted, WordPress-native pricing.**
