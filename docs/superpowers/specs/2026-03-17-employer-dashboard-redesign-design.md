# Employer Dashboard Redesign — Design Spec

**Date:** 2026-03-17
**Phase:** Phase 1 of Approach C (Fix → Design System → Dashboard → Outward)
**Scope:** `blocks/employer-dashboard/` — render.php, view.js, style.css

---

## 1. Design Direction

**Style:** Notion Minimal — white sidebar, hairline borders (`1px solid #e2e8f0`), no filled background on nav items. Blends with any WordPress theme. Maximum content space.

**No new design tokens required for Phase 1.** All colours are hardcoded as CSS custom properties on `.wcb-dashboard` so themes cannot override them. A full design-system token layer (mapping to `--wp--preset--color--*`) is deferred to Phase 2.

**Responsive:** Desktop-first. Sidebar collapses to a hamburger drawer at `< 768px`. Content stacks to single column.

---

## 2. Layout Shell

```
┌─────────────────────────────────────────────────────┐
│  SIDEBAR (220px fixed)  │  MAIN CONTENT (flex: 1)   │
│                         │                           │
│  Logo                   │  Page title + subtitle    │
│  ─────────────────────  │                           │
│  Overview               │  [ view content ]         │
│                         │                           │
│  JOBS                   │                           │
│    My Jobs   [12]       │                           │
│                         │                           │
│  HIRING                 │                           │
│    Applications [5 new] │                           │
│                         │                           │
│  COMPANY                │                           │
│    Profile              │                           │
│    Public Page ↗        │                           │
│  ─────────────────────  │                           │
│  [ + Post a Job ]       │                           │
│  Avatar  Varun Corp     │                           │
└─────────────────────────────────────────────────────┘
```

### Sidebar anatomy

| Element | CSS class | Notes |
|---|---|---|
| Logo + wordmark | `.wcb-sidebar-logo` | Icon 24×24 dark pill, bold 14px |
| Section label | `.wcb-nav-section-label` | 10px uppercase `#94a3b8` |
| Nav item | `.wcb-nav-item` | 13px `#64748b`; active: `#f1f5f9` bg + `#0f172a` text |
| Badge | `.wcb-nav-badge` | `#f1f5f9` bg; `.highlight`: `#dbeafe`/`#1d4ed8` |
| CTA button | `.wcb-sidebar-cta` | `#0f172a` bg, full-width, 12px bold |
| User row | `.wcb-sidebar-user` | 26px avatar circle, company name + role |

---

## 3. State Architecture

### Migration from `state.tab`

`state.tab` and all associated getters (`isTabJobs`, `isTabApps`, `isTabProfile`) and actions (`switchToJobs`, `switchToApps`, `switchToProfile`) are **removed entirely**. They are replaced by `state.currentView` with values `'overview' | 'jobs' | 'applications' | 'company'`. Every `data-wp-class--wcb-tab-active` and `data-wp-class--wcb-tab-panel` directive in render.php is replaced with `data-wp-class--wcb-view-active` bound to the new getters. The old `.wcb-dashboard-tabs` nav is removed; the new sidebar handles navigation.

### New state properties

```js
// Navigation
currentView:   'overview' | 'jobs' | 'applications' | 'company'

// My Jobs
jobFilter:     'all' | 'live' | 'draft' | 'pending' | 'closed'
jobSearch:     ''

// Applications
// appsJobId is pre-populated server-side from $_GET['job_apps'] (same as existing behaviour)
appsJobId:     number   // PHP: absint( $_GET['job_apps'] ?? '0' ) — already in wp_interactivity_state()
appsFilter:    'all' | 'submitted' | 'shortlisted'
selectedAppId: null | number  // null = nothing selected (sentinel); store as null not 0
```

### `isClosed` flag

`isClosed` is a **client-side computed boolean** set in the `jobs.map()` inside `init()`:
```js
isClosed: j.status !== 'publish',
```
It is not returned from the API. It is already present in the current view.js and is unchanged.

### New getters

```js
isViewOverview()       // state.currentView === 'overview'
isViewJobs()           // state.currentView === 'jobs'
isViewApplications()   // state.currentView === 'applications'
isViewCompany()        // state.currentView === 'company'

// Requires state.jobs populated (init() always fetches jobs — see §4.1)
filteredJobs()         // state.jobs filtered by jobFilter + jobSearch
selectedApp()          // state.applications.find(a => a.id === state.selectedAppId) ?? null
overviewRecentApps()   // [...state.allApplications].sort by submitted_at desc, slice(0,4)
overviewActiveJobs()   // state.jobs.filter(j => j.status === 'publish').slice(0, 3)
closedJobsCount()      // state.jobs.filter(j => j.isClosed).length
pendingJobsCount()     // state.jobs.filter(j => j.status === 'pending').length
noAppSelected()        // state.selectedAppId === null
```

### New actions

```js
switchView(view)          // sets state.currentView
setJobFilter(event)       // sets state.jobFilter from data-wcb-filter on clicked button
setJobSearch(event)       // sets state.jobSearch from event.target.value
selectApplicant(event)    // parseInt(event.target.dataset.wcbAppId, 10) → state.selectedAppId
setAppsFilter(event)      // sets state.appsFilter from data-wcb-filter on clicked button

// Generator (async) — uses yield, like all other async actions in this store
*switchAppsJob(event) {
    state.appsJobId = parseInt( event.target.dataset.wcbJobId, 10 );
    state.selectedAppId = null;
    yield actions.loadApplications();   // must use yield — loadApplications is also a generator
}
```

### Two application arrays (important distinction)

| Array | Purpose | Populated by |
|---|---|---|
| `state.allApplications` | Overview panel — all apps across all jobs | `init()` via new `/employers/{id}/applications` endpoint |
| `state.applications` | Applications view split panel — one job's apps | `loadApplications()` / `switchAppsJob()` |

`selectedApp` reads from `state.applications` (the per-job list). The Applications view filter pills operate on `state.applications`. `allApplications` is only consumed by `overviewRecentApps`.

---

## 4. Views

The block renders one of four views. PHP sets initial `currentView`: `'applications'` when `$_GET['job_apps']` is present, `'overview'` otherwise.

### 4.1 Overview

**Purpose:** Landing screen — quick health check and recent activity.

**Data loading in `init()`:** `init()` always performs two fetches unconditionally, regardless of `currentView`:
1. `GET /wcb/v1/employers/{companyId}/jobs` → `state.jobs` (existing fetch, unchanged)
2. `GET /wcb/v1/employers/{companyId}/applications` → `state.allApplications` (new endpoint, §6)

Both fetches run in parallel via two concurrent `yield fetch()` calls. `overviewActiveJobs` and `overviewRecentApps` derive from these arrays. The existing conditional `appsJobId > 0` fetch (which loads `state.applications` for the Applications split panel) runs after both parallel fetches complete, unchanged.

**Stats row** — 4 cards (`repeat(4, 1fr)`):
- Total Jobs (neutral)
- Live / Published (green `#059669`)
- Total Applicants (blue `#2563eb`)
- New This Week = `allApplications` count where `submitted_at` within last 7 days (amber `#d97706`)

**Two-column panel row** (`1fr 1fr`):
- *Recent Applications* — 4 rows from `overviewRecentApps`. Each: avatar initials, name, job title, date, status badge. "View all →" sets `currentView = 'applications'`.
- *Active Jobs* — 3 rows from `overviewActiveJobs`. Each: status dot, title, meta, deadline badge. "Manage all →" sets `currentView = 'jobs'`.

---

### 4.2 My Jobs

**Purpose:** Full job list with filtering and per-job actions.

**Filter pills:** All / Live / Draft / Pending / Closed. Plus search input (right-aligned, client-side title filter).

**`filteredJobs` logic:**

| `jobFilter` value | Status matches |
|---|---|
| `'all'` | all statuses |
| `'live'` | `status === 'publish'` |
| `'draft'` | `status === 'draft'` |
| `'pending'` | `status === 'pending'` |
| `'closed'` | `isClosed === true` (i.e., `status !== 'publish'` — covers draft, pending, and any future closed status) |

`jobSearch` further filters by `job.title.toLowerCase().includes(state.jobSearch.toLowerCase())`.

**Job row** — flex, vertically centred:

| Slot | Content |
|---|---|
| Status dot | 6px circle: green=publish, amber=draft/pending, grey=closed |
| Title + meta | Bold 14px title; 12px grey "Location · Type · Posted date" |
| Status badge | Published (green) / Draft (amber) / Pending (indigo) / Closed (grey) |
| Applicant chip | Blue pill "N applicants" — links to Applications view for that job; non-clickable span when 0 |
| Actions | Contextual — see table below |

**Contextual actions per status:**

| Job status | Actions shown |
|---|---|
| `publish` | View ↗ · Edit · Close |
| `draft` | View ↗ · Edit · Publish |
| `pending` | View ↗ · Edit · *(no Publish — pending requires admin approval)* |
| closed (draft via Close) | View ↗ · Reopen |

`pending` jobs explicitly do **not** show a Publish button client-side. The PATCH `status: 'publish'` action is reserved for `closeJob`/`reopenJob` which only fire on `publish` and `draft` statuses respectively. Pending moderation is handled by the admin, not the employer.

Closed jobs render at `opacity: 0.6` with title `text-decoration: line-through`.

---

### 4.3 Applications

**Purpose:** Review candidates for a selected job.

**Job selector pills** — horizontal scroll row, one pill per job that has `appCount > 0`. First pill auto-selected on load if `appsJobId > 0`. Selecting a pill calls `switchAppsJob()`.

**`appsFilter` groups** — filter key maps to `status` field values:

| `appsFilter` pill label | State value | `status` match |
|---|---|---|
| All | `'all'` | all |
| New | `'submitted'` | `status === 'submitted'` |
| Shortlisted | `'shortlisted'` | `status === 'shortlisted'` |

The filter key stored in state is the **status string itself**, not a label. `setAppsFilter` stores `'submitted'` when the "New" pill is clicked. The pill buttons use `data-wcb-filter="submitted"` (not `"new"`). Filter getter: `state.appsFilter === 'all' ? state.applications : state.applications.filter(a => a.status === state.appsFilter)`.

**No job selected state:** Shows centred "Select a job above to view its applications" message with "Go to My Jobs" button when `appsJobId === 0`.

**Split panel** — CSS grid `280px 1fr`, min-height `420px`:

*Left — Applicant list:*
- Filter pills: All / New / Shortlisted
- Each row: 32px avatar initials circle, name (bold), status + date, blue unread dot when `status === 'submitted'`
- Clicking a row calls `selectApplicant(event)` via `data-wp-bind--data-wcb-app-id="context.app.id"` + `data-wp-on--click="actions.selectApplicant"`
- Selected row: `#f8fafc` background — `data-wp-class--wcb-selected="state.isSelectedApp"` where `isSelectedApp` checks `context.app.id === state.selectedAppId`

*Right — Detail pane:*
- When `selectedAppId === null` (`state.noAppSelected`): "Select an applicant from the list" empty state
- When selected: 44px avatar, name (18px bold), email + applied date
- Status `<select>` bound to `context.app.status` via `data-wp-bind--value`; `data-wp-on--change="actions.updateAppStatus"`; inline style colour-coded: submitted=grey, reviewing=indigo, shortlisted=green, rejected=red, hired=purple
- Cover Letter: labelled block, `#f8fafc` bg, 13px / 1.7 line-height
- Attachments: resume file chip shown only when `context.app.resume_url` is non-null; links to `context.app.resume_url` with `target="_blank"`

**`resume_url` resolution** (in `get_applications()` endpoint):
```php
$resume_attachment_id = (int) get_post_meta( $p->ID, '_wcb_resume_attachment_id', true );
$resume_url = $resume_attachment_id > 0
    ? wp_get_attachment_url( $resume_attachment_id )
    : null;
```
Pro resume posts (`_wcb_resume_id`) are out of scope for Phase 1 and ignored here.

---

### 4.4 Company Profile

**Purpose:** Edit company data; live public-listing preview.

**Two-column layout** — `1fr 340px`:

*Left — Edit form:*
- **Logo area:** Render a static placeholder box (64×64 dashed border, "Upload Logo" label, `cursor: default`, `opacity: 0.5`) with a `<p>` caption: "Logo upload coming soon." No upload input, no file handler. Not broken — explicitly labelled as upcoming.
- Fields: Company Name, Tagline (with hint text), About (textarea 4 rows), Industry, Company Size (select), HQ Location, Website (url input)
- 2-col rows: Industry + Size, HQ + Website
- Save button → calls `actions.saveProfile()` (existing action, unchanged)
- "Preview public page ↗" link → `state.companyDirUrl` or `state.companySite` if no archive page configured

*Right — Live preview card:*
- Renders company name, tagline, description excerpt, meta chips (industry, size, HQ, website)
- Updates reactively as user types — bound to `state.companyName`, `state.companyTagline`, `state.companyDesc`, etc. (same state as form fields)
- Logo slot shows a grey placeholder box

---

## 5. CSS Architecture

All styles scoped to `.wcb-dashboard`. No `!important` except existing tab-button override rules which are retained.

### Design tokens (local, Phase 1 only)

```css
.wcb-dashboard {
  --wcb-text-primary:   #0f172a;
  --wcb-text-secondary: #64748b;
  --wcb-text-muted:     #94a3b8;
  --wcb-border:         #e2e8f0;
  --wcb-bg-subtle:      #f8fafc;
  --wcb-bg-hover:       #f1f5f9;
  --wcb-green:          #059669;
  --wcb-blue:           #2563eb;
  --wcb-amber:          #d97706;
  --wcb-radius:         8px;
  --wcb-radius-sm:      5px;
}
```

### New component classes

```
.wcb-dashboard-shell        flex container (sidebar + main)
.wcb-sidebar                220px fixed sidebar
.wcb-nav-section-label      section divider label
.wcb-nav-item               sidebar nav link/button
.wcb-nav-badge              counter badge on nav item
.wcb-sidebar-cta            Post a Job CTA button
.wcb-sidebar-user           bottom user row
.wcb-main                   flex:1 content area
.wcb-page-title             20px/700 page heading
.wcb-page-sub               subtitle beneath heading
.wcb-stats-row              4-col stats grid
.wcb-stat-card              individual stat block
.wcb-two-col                2-col panel grid (overview)
.wcb-panel                  white bordered card
.wcb-panel-header           panel title + link row
.wcb-filter-bar             pill button filter row
.wcb-job-row                single job item in My Jobs
.wcb-status-dot             6px status indicator circle
.wcb-apps-selector          job pill switcher row
.wcb-split-panel            280px/1fr applications grid
.wcb-applicant-row          left-pane applicant list item
.wcb-applicant-detail       right-pane detail area
.wcb-status-select          colour-coded status <select>
.wcb-profile-grid           1fr/340px company layout
.wcb-logo-placeholder       dashed logo upload placeholder
.wcb-preview-card           company listing preview card
```

---

## 6. API Changes Required

### New endpoint: `GET /wcb/v1/employers/{id}/applications`

Returns all applications across all jobs owned by the employer, sorted by `post_date DESC`. Fetches first 20, no pagination UI in Phase 1. Response shape matches `GET /wcb/v1/jobs/{id}/applications` plus a `job_title` field (from `get_the_title( get_post_meta($p->ID, '_wcb_job_id', true) )`).

**Permission callback:** The requesting user must (a) have `wcb_view_applications` ability AND (b) be the `post_author` of the company post identified by `{id}`, OR have `wcb_manage_settings`. Implementation mirrors `update_item_permissions_check` in `EmployersEndpoint` — load the company post, compare `post_author` to `get_current_user_id()`.

### Modified: `GET /wcb/v1/employers/{id}/jobs`

Add `deadline` field to each job item:
```php
'deadline' => (string) get_post_meta( $p->ID, '_wcb_deadline', true ) ?: null,
```

### Modified: `GET /wcb/v1/jobs/{id}/applications`

Add `resume_url` field:
```php
$resume_attachment_id = (int) get_post_meta( $p->ID, '_wcb_resume_attachment_id', true );
'resume_url' => $resume_attachment_id > 0 ? wp_get_attachment_url( $resume_attachment_id ) : null,
```

---

## 7. PHP Render Changes (`render.php`)

- Remove `.wcb-dashboard-tabs` `<nav>` entirely
- Remove all `.wcb-tab-panel` / `.wcb-tab-btn` elements
- Add sidebar HTML with `.wcb-sidebar`, `.wcb-nav-section-label`, `.wcb-nav-item` elements
- Add to `wp_interactivity_state()`:
  - `'currentView'` → `$wcb_apps_job_id > 0 ? 'applications' : 'overview'`
  - `'jobFilter'` → `'all'`
  - `'jobSearch'` → `''`
  - `'appsFilter'` → `'all'`
  - `'selectedAppId'` → `null`
  - `'allApplications'` → `[]`
- Remove `'tab'` from `wp_interactivity_state()`
- Add four `.wcb-view-panel` divs with `data-wp-class--wcb-view-active="state.isViewOverview"` etc.
- Add `allApplications` to state; `init()` extended to populate it

---

## 8. Out of Scope (Phase 1)

- Candidate dashboard (My Resume, saved jobs, applications history)
- Admin panel redesign
- Public job board / company archive redesign
- Resume directory
- Logo upload backend (shown as labelled placeholder only)
- Kanban pipeline view (Pro feature)
- Analytics / reporting
- Notification system
- `GET /wcb/v1/employers/{id}/applications` endpoint pagination UI (fetch first 20, no load-more in Phase 1)

---

## 9. Verification Checklist

1. **State migration** — no references to `state.tab`, `isTabJobs`, `isTabApps`, `isTabProfile`, `switchToJobs`, `switchToApps`, `switchToProfile` remain in render.php or view.js
2. **Overview** — stats correct; recent applications populated from `allApplications`; links to correct views
3. **My Jobs** — All/Live/Draft/Pending/Closed pills filter correctly; search filters by title; pending jobs show no Publish button; Close/Reopen/Publish actions work; applicant chip non-clickable at 0
4. **Applications** — job selector loads correct applicants; split panel shows detail on row click; status dropdown PATCH updates state; resume chip appears only when `resume_url` non-null
5. **Company Profile** — logo placeholder labelled "coming soon"; preview card updates live; Save patches API
6. **Mobile** — sidebar collapses to hamburger at 768px; all views scroll on 375px viewport
7. **Theme compatibility** — TT3, TT4, Astra: no colour bleed into sidebar or panels
