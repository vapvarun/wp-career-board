# WP Career Board — Bug Tracker

> Synced with Basecamp Project `45156734`, Bugs Column `9381846253`
> Last updated: 2026-03-25 (round 2)

---

## Critical (3)

### B-1: [Pro] uninstall.php missing wcb_notifications table drop — FIXED
- **Basecamp:** #9717102172
- **Plugin:** Pro
- **File:** `uninstall.php` (source + dist)
- **Fix applied:** Added `wcb_notifications` table to drop list (now 9/9 tables). Also added 15 missing option keys (B-6 fixed together). Organized by module with comments.

---

### B-2: [Pro] License::ITEM_ID = 0 breaks EDD auto-updates — FIXED (with B-7)
- **Basecamp:** #9717102606
- **Plugin:** Pro
- **Files:** `core/class-license.php`, `core/class-pro-plugin.php` (source + dist)
- **Fix applied:** Removed legacy `boot()` call (no more broken updater/checks/AJAX). Kept `is_active()` for AI endpoint gating — rewired to read modern EDD SL SDK status. Single licensing source of truth now.

---

### B-3: [Free] Gruntfile.js missing import/ directory from dist copy
- **Basecamp:** #9717102765
- **Plugin:** Free
- **File:** `Gruntfile.js` (line 62-82)
- **Issue:** `copy.dist.src` array doesn't include `import/`. The `import/class-wpjm-importer.php` will be excluded from release zip — potential autoload fatal.
- **Fix:** Add `'import/**'` to the src array.
- **Questions:**
  - [ ] Is the WPJM importer feature ready for v1.0.0 or should it be deferred?
  - [ ] Any other directories missing from Gruntfile? Do a full audit of dirs vs copy list.

---

## High (2)

### B-9: [Free] Employer Dashboard overview stats show all zeros on initial load
- **Basecamp:** #9717122922
- **Plugin:** Free
- **Page:** `/employer-dashboard/`
- **Issue:** Overview section shows Total Jobs: 0, Live: 0, Total Applicants: 0, New This Week: 0 on first load. Sidebar correctly shows "My Jobs 31" and "Applications 20". Stats populate after navigating to sub-page and back.
- **Repro:**
  1. Navigate directly to `/employer-dashboard/`
  2. Overview = all zeros
  3. Click "Post a Job" → click "Dashboard"
  4. Now shows correct values
- **Questions:**
  - [ ] Is the overview rendered via Interactivity API store? Could be a hydration timing issue.
  - [ ] Does this happen for non-admin employer users too, or only admin?
  - [ ] Is the overview fetching stats via REST API or inline PHP?

---

### B-10: [Free] Active Jobs count mismatch between admin and frontend
- **Basecamp:** #9717123290
- **Plugin:** Free
- **Pages:** Admin Companies list vs Frontend `/companies/`
- **Issue:** Counts don't match:
  | Company | Admin "Active Jobs" | Frontend "Open Positions" |
  |---------|-------------------|--------------------------|
  | Linear | 0 | 6 |
  | Shopify | 0 | 6 |
  | Acme Corp | 3 | 7 |
  | Anthropic | 5 | 7 |
- **Questions:**
  - [ ] Admin query: does it count by `post_author` or by `_wcb_company_id` meta?
  - [ ] Frontend query: does it count by company CPT relationship or something else?
  - [ ] Which count is correct? Are there orphaned jobs not linked to a company properly?

---

## Medium (2)

### B-4: [Free/Pro] Empty resume card on Find Candidates page
- **Basecamp:** #9717103155
- **Plugin:** Free + Pro
- **Page:** `/find-candidates/` and `/resume/jane-smith/`
- **Issue:** First resume card displays with no name, no title, no skills — just grey avatar + "View Resume" link. Single resume page at `/resume/jane-smith/` renders completely empty (just heading "Jane Smith", no content).
- **Root cause found:**
  - Resume ID 318 ("Jane Smith") has `post_author = 0` — no linked WordPress user
  - It was migrated from WPJM (`_wcb_migrated_source = wp-job-manager-resumes`)
  - The resume HAS meta: `_wcb_candidate_title = "Senior Software Engineer"`, `_wcb_location = "Austin, TX"`, `_wcb_contact_email = "jane@example.com"`, plus post_content with description
  - But the template/blocks require a linked user (post_author > 0) to render — so everything is blank
- **Two sub-bugs:**
  1. WPJM importer sets `post_author = 0` for migrated resumes — should try to match by email or create a placeholder user
  2. Single resume template doesn't fall back to meta fields when there's no linked user — should display `_wcb_candidate_title`, `post_content`, etc. from meta
- **Questions:**
  - [ ] Should the archive query exclude resumes with `post_author = 0`?
  - [ ] Or should the template gracefully render from meta when no user is linked?

---

### B-11: [Free] Pending jobs have no Company or Author assigned
- **Basecamp:** #9717123769
- **Plugin:** Free
- **Page:** Admin Jobs list
- **Issue:** 3 Pending Review jobs show Company = "—" and blank Author. Jobs: "React Frontend Engineer", "Senior PHP Developer", "QA Test Custom Fields Job".
- **Questions:**
  - [ ] Were these submitted via the frontend Post a Job form?
  - [ ] Is `_wcb_company_id` meta saved when job goes to `pending` status?
  - [ ] Does the Post a Job form set `post_author` correctly for non-admin employers?

---

## Low (5)

### B-5: [Pro] Duplicate search bars on Find Candidates page
- **Basecamp:** #9717103584
- **Plugin:** Pro
- **Page:** `/find-candidates/`
- **Issue:** Two search bars stacked — hero "Search candidates" and below it "Search resumes" with duplicate skill filter.
- **Questions:**
  - [ ] Is the hero search from the `resume-search-hero` block and the second from `resume-archive` block?
  - [ ] Should we remove the hero search or merge them?

---

### B-6: [Pro] uninstall.php doesn't clean up ~10 Pro option keys — FIXED (with B-1)
- **Basecamp:** #9717103970
- **Plugin:** Pro
- **File:** `uninstall.php` (source + dist)
- **Fix applied:** Added all 15 missing option keys found via full codebase grep. Organized by module: Core (4), Stripe (5), License (2), AI (3), Feed (3), PWA (2), Integrations (2), Resume (1).

---

### B-7: [Pro] Dual EDD licensing mechanisms with conflicting item IDs
- **Basecamp:** #9717104307
- **Plugin:** Pro
- **Files:** `core/class-license.php` + `wp-career-board-pro.php`
- **Issue:** Two EDD licensing approaches coexist with different item IDs (0 vs 1659890).
- **Questions:**
  - [ ] Can we remove the License class entirely and keep only the modern SDK approach?
  - [ ] Does the License settings tab UI depend on the License class?

---

### B-8: [Both] readme.txt missing FAQ and Screenshots sections
- **Basecamp:** #9717104550
- **Plugin:** Free + Pro
- **File:** `readme.txt`
- **Issue:** Missing `== FAQ ==` and `== Screenshots ==` sections.
- **Questions:**
  - [ ] Is this needed for wbcomdesigns.com distribution, or only for wp.org?
  - [ ] Do we have screenshots ready?

---

### B-12: [Free] Some published jobs have blank Author in admin list
- **Basecamp:** #9717124092
- **Plugin:** Free
- **Page:** Admin Jobs list
- **Issue:** Some Published jobs have blank Author column (e.g. TechStartup Frontend Engineer, Acme Corp Senior PHP Developer). Likely from setup wizard or sample data import.
- **Questions:**
  - [ ] Should sample data set `post_author` to the admin user?
  - [ ] Is this just cosmetic or does it affect permissions/ownership?

---

## Verified Working (Not Bugs)

- **Apply button hidden for job owner** — correctly hides "Apply Now" when logged in as the employer who posted the job. Shows for other users (candidates). Tested with alex.kumar on varundubey's jobs.
- **Apply modal** — opens with resume selector, file upload (PDF/DOC/DOCX, 5MB), cover letter, Submit button. Resume dropdown pre-populates with user's existing resumes.
- **Save/Bookmark Job** — AJAX works, button toggles to "Saved"
- **Company profile page** — renders correctly with hero, about, details, open positions list, "Load more jobs" button
- **Admin Boards page** — loads with board list (Main Board 5 jobs, Tech Board 0 jobs), credit cost, edit links
- **Admin Field Builder** — loads with board selector, shows 17 supported field types. Correctly blocks non-admin users.
- **Admin Import page** — shows 3 import options (WPJM Jobs, WPJM Resumes, CSV). Correctly detects WPJM as "not active". CSV upload has file picker + import button.
- **Post a Job wizard** — 4-step form (Basics, Details, Categories, Preview) loads correctly
- **Admin Applications** — 99 applications, status filters (Submitted/Reviewing/Shortlisted/Rejected/Hired), inline status change dropdown
- **Role isolation** — candidate (alex.kumar) blocked from admin Field Builder page with "Sorry, you are not allowed to access this page." — correct capability enforcement
- **Job Alerts** — "Alert me" button on Find Jobs page works instantly, toggles to "✓ Alert saved"
- **Guest apply** — logged-out users see Apply Now button and get a simplified form (Name, Email, Cover Letter — no resume upload). Good UX.
- **Apply button role-aware** — hidden for job owner, visible for candidates and guests. Two Apply Now buttons shown (header + sidebar) for non-owners.
- **Save Job persists** — "Staff Software Engineer" shows "Remove bookmark" icon on Find Jobs page after being saved from single job page. Cross-page state works.

---

### B-14: [Free] Apply button doesn't reflect "already applied" state on page reload
- **Basecamp:** #9717407378
- **Plugin:** Free
- **Page:** Single job pages
- **Issue:** After applying to a job, revisiting the page shows "Apply Now" again instead of "Already Applied". The full modal opens and only shows "You have already applied to this job" after the user submits again.
- **Backend is correct:** Only 1 application record in DB (confirmed: ID 341 for alex.kumar → job 178). Duplicate prevention works server-side.
- **Root cause:** The Interactivity API store doesn't query existing applications on page load to set the initial "already applied" state.
- **Questions:**
  - [ ] Should the single job template pass `data-wp-context` with `hasApplied: true/false`?
  - [ ] Or should the Apply block make a REST API call on mount to check?

---

### B-13: [Free] Homepage CTAs link to 404 pages
- **Basecamp:** #9717305032
- **Plugin:** Free
- **Page:** Homepage (bottom CTA section + hero)
- **Issue:** Three homepage links point to non-existent pages:
  - "Go to Employer Portal →" → `/employer-portal/` → **404** (should be `/employer-dashboard/`)
  - "Go to Candidate Portal →" → `/candidate-portal/` → **404** (should be `/candidate-dashboard/`)
  - Hero "Post a Job" button → `/employer-portal/` → **404** (should be `/post-a-job/`)
- **Note:** Nav menu links are correct. Only the homepage block content has wrong URLs. Likely in demo/sample content from setup wizard.
- **Questions:**
  - [ ] Is this hardcoded in a block pattern or in the setup wizard sample content?
  - [ ] Should setup wizard create these pages, or fix the URLs to match existing pages?

---

## Not Yet Tested (Pending)

These areas haven't been tested yet. More bugs may surface:

- [ ] **Post a Job** — full submit end-to-end (filled form but didn't submit)
- [x] **Apply to Job** — full submit works (alex.kumar → Growth Marketing Manager at Figma). Resume selector, cover letter, submit all functional.
- [x] **Job Alerts** — "Alert me" works instantly on Find Jobs page
- [x] **Duplicate application** — backend prevents (shows "You have already applied"), but **UI doesn't reflect state on reload** (B-14)
- [x] **Guest application** — logged-out users get simplified form (Name, Email, Cover Letter)
- [x] **REST API** — `/wp-json/wcb/v1/jobs` returns valid JSON with all 20 published jobs, full metadata
- [x] **Cron jobs** — 3 WCB crons registered: `wcb_send_daily_alerts`, `wcb_check_job_expiry`, `wcb_send_weekly_alerts`
- [x] **Single resume (empty)** — root cause: post_author=0 from WPJM import, template requires linked user (B-4)
- [ ] **Resume Builder** — create/edit resume with 7 sections (not tested — needs candidate login)
- [ ] **Application Pipeline** — Kanban drag-drop (not tested — needs employer view of applications)
- [ ] **Deep code flow audit** — race conditions, capability checks, data integrity (rate limited — retry later)
- [x] **Email notifications** — WORKING. Mailpit on port 10106 shows 28 emails. Verified: application confirmation to candidate, new application alert to employer, job approval notifications, status update emails, pending review alerts. All sending correctly.
- [ ] **Employer accessing candidate dashboard** — does it show correct empty state or block?
