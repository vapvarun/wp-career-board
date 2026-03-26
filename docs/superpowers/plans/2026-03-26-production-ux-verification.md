# Production UX Verification Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete production-readiness verification of all user-facing actions, hover effects, notices, and UX flows across WP Career Board (Free + Pro). No browser alerts (window.confirm/alert), all actions use inline notices, all hover effects are uniform, all interactive states are polished.

**Architecture:** Two-phase verification. Phase 1: code-level audit (grep/read source for patterns). Phase 2: browser verification with Playwright MCP. Code audit catches systemic issues across all blocks; browser confirms the rendered output.

**Tech Stack:** Playwright MCP, WordPress Interactivity API blocks

**Site:** `http://job-portal.local`
**Auto-login:** `?autologin=1` (admin), `?autologin=alex.kumar` (candidate)
**Mailpit:** `http://localhost:10106` (email verification)

---

## Phase 0: Code-Level Flow Audit (run BEFORE browser verification)

Grep/read all block source files to catch systemic issues across the entire plugin.

### Task 0.1: Hover Effect Consistency (CSS audit)

- [ ] Grep all `style.css` files for `:hover` rules — verify they all use the same pattern (border-color + light bg tint, not just text color change)
- [ ] Check all button hover states use `var(--wp--preset--color--wcb-primary)` for border/color
- [ ] Check no hover state uses hardcoded hex colors (should all be tokens now)
- [ ] Verify `:focus-visible` exists on all interactive elements alongside `:hover`
- [ ] Check `transition` properties are scoped (not `all`) to prevent layout shifts

### Task 0.2: Action Flow Audit (JS audit)

- [ ] Grep all `view.js` files for `window.alert(` — should be ZERO occurrences
- [ ] Grep all `view.js` files for `window.confirm(` — list all occurrences, verify each is for a destructive action only
- [ ] Grep for `state.error =` — verify all error messages come from `state.strings.*` (not hardcoded)
- [ ] Grep for `catch {` or `catch (` — verify no silent failures (each should set an error state)
- [ ] Check all AJAX actions (fetch calls) have: loading state before, success/error state after
- [ ] Verify no `console.log` or `console.error` left in production JS

### Task 0.3: Notice/Feedback Patterns (render.php audit)

- [ ] Grep all `render.php` files for `role="alert"` — verify all error containers have it
- [ ] Grep for `role="status"` — verify all loading containers have it
- [ ] Grep for `aria-live` — verify all dynamically updated regions have it
- [ ] Check all form submit buttons have a loading/disabled state during submission
- [ ] Verify all success states show inline feedback (not page reload or alert)

### Task 0.4: Empty State Audit

- [ ] Grep all `render.php` for `return;` with no output — blocks that silently vanish when empty
- [ ] Each list/grid block should show a "No items found" message when empty
- [ ] Dashboard overview panels should show loading skeleton, not "No data" while fetching

### Task 0.5: Theme Override Resilience

- [ ] Check all inputs have inline style or high-specificity CSS for padding (Reign theme overrides)
- [ ] Check all buttons have sufficient specificity to override theme button styles
- [ ] Verify block wrapper class matches WordPress naming convention (`wp-block-{namespace}-{name}`)

---

## Phase 1: Browser Verification (run AFTER code audit fixes)

## Verification Checklist Per Page

For each page, verify:
1. **Hover effects** — every button, link, card, chip has a visible hover state. No jarring color jumps. Consistent with design tokens.
2. **Click actions** — every interactive element does something. No dead clicks.
3. **Loading states** — async actions show spinner/skeleton, not blank.
4. **Success feedback** — actions confirm with inline message, badge change, or toast. Never `window.alert()`.
5. **Error feedback** — failed actions show inline error message, not `window.alert()`.
6. **Destructive actions** — delete/close/remove uses `window.confirm()` (acceptable) or inline confirmation pattern.
7. **Empty states** — when no data, show helpful message, not blank.
8. **Mobile** — verify at 390px viewport width.
9. **Console errors** — check for 0 JS errors.
10. **Focus states** — keyboard Tab shows visible focus ring on interactive elements.

---

## Task 1: Homepage + Navigation

**URL:** `http://job-portal.local/`

- [ ] Verify all nav menu hover effects (Jobs, Companies, For Employers, For Candidates)
- [ ] Verify dropdown menus open/close on hover
- [ ] Verify hero CTA buttons hover (Browse Jobs, Post a Job, Find Talent)
- [ ] Verify hero search bar — type and submit
- [ ] Verify "Featured Opportunities" job cards hover + "View Job" links
- [ ] Verify "Browse by Industry" cards hover
- [ ] Verify footer links hover
- [ ] Verify "Go to Employer Dashboard" and "Go to Candidate Dashboard" CTAs work (not 404)
- [ ] Check notification bell — hover + click
- [ ] Check user dropdown — hover + click
- [ ] Mobile 390px — hamburger menu, hero layout
- [ ] Console: 0 errors

---

## Task 2: Find Jobs Page — Full Interaction Audit

**URL:** `http://job-portal.local/find-jobs/`

- [ ] **Search bar**: icon visible, placeholder text clear, type a query → results filter
- [ ] **Sort dropdown**: hover, click, change to "Oldest first" → jobs reorder
- [ ] **Filter chips**: hover effect (light blue bg), click to activate (solid blue), click again to deactivate
- [ ] **Active filter pills**: appear below chips, "×" remove button works, "Clear all" works
- [ ] **Results count**: updates dynamically on filter/search
- [ ] **Alert me button**: hover effect, click → saves alert, button changes to "✓ Alert saved"
- [ ] **Grid/List toggle**: hover, click to switch views, state persists
- [ ] **Job cards**: hover effect on card border, title link hover, "Save job" button hover + click
- [ ] **Bookmark toggle**: click "Save job" → icon changes to filled/bookmarked, text changes to "Saved"
- [ ] **View Job link**: hover + click navigates to single job
- [ ] **Load more**: button at bottom, click loads next batch
- [ ] **Empty state**: search for nonsense query → "No jobs found" message shown
- [ ] Mobile 390px — chips wrap, cards stack, search usable
- [ ] Console: 0 errors

---

## Task 3: Single Job Page — Apply Flow

**URL:** `http://job-portal.local/jobs/senior-php-developer/` (as candidate alex.kumar)

- [ ] **Save Job button**: hover, click → toggles to "Saved"
- [ ] **Share buttons**: X, LinkedIn, Copy link — hover effects, click copy shows feedback
- [ ] **Apply Now button**: visible for non-owner, hover effect
- [ ] **Apply modal**: opens as slide-in panel, has `role="dialog"`, close button works
- [ ] **Resume selector**: dropdown populates with user's resumes
- [ ] **File upload**: click to upload area, file type validation hint shown
- [ ] **Cover letter**: textarea with placeholder
- [ ] **Submit**: click → loading state → "✓ Application Submitted" → Apply button changes permanently
- [ ] **Already applied**: revisit page → should show "✓ Application Submitted" not "Apply Now"
- [ ] **Job owner view**: login as admin → no Apply button, shows company sidebar
- [ ] **Guest view**: logged out → Apply shows simplified form (name, email, cover letter)
- [ ] **Reduced motion**: verify panel doesn't animate with `prefers-reduced-motion`
- [ ] Console: 0 errors

---

## Task 4: Employer Dashboard — All Tabs

**URL:** `http://job-portal.local/employer-dashboard/` (as admin)

- [ ] **Overview stats**: show real numbers immediately (SSR), not zeros
- [ ] **Recent Applications list**: shows names, jobs, status badges
- [ ] **Active Jobs list**: shows job titles, applicant counts
- [ ] **Sidebar nav**: all buttons have hover effect, active state highlighted
- [ ] **My Jobs tab**: job list loads, search works, filter tabs (All/Live/Draft/Pending/Closed) work
- [ ] **Close Job action**: click → `window.confirm()` dialog → job status changes
- [ ] **Post a Job tab**: multi-step wizard (Basics → Details → Categories → Preview) works
- [ ] **Applications tab**: click a job → applications list loads, status dropdown changes status
- [ ] **Profile tab**: company form loads, fields editable, save works with success feedback
- [ ] **Logo upload**: click → file picker → upload progress → logo updates
- [ ] **Notification bell**: click → dropdown opens, "Mark all read" works
- [ ] Mobile 390px — sidebar collapses to hamburger, tabs accessible
- [ ] Console: 0 errors

---

## Task 5: Candidate Dashboard — All Tabs

**URL:** `http://job-portal.local/candidate-dashboard/` (as alex.kumar)

- [ ] **Overview stats**: applications, saved jobs, resumes, alerts counts correct
- [ ] **My Applications tab**: list loads, status badges correct (Submitted/Reviewing/etc)
- [ ] **Saved Jobs tab**: bookmarked jobs listed, "Remove" button works
- [ ] **Remove bookmark**: confirm behavior — does it use confirm dialog or instant?
- [ ] **Job Alerts tab**: shows saved alerts with filter criteria, delete works
- [ ] **Delete alert**: uses `window.confirm()` dialog before deleting
- [ ] **My Resumes tab**: shows resume list, "Create Resume" button works
- [ ] **Create resume**: enter title → submit → new resume created
- [ ] **Delete resume**: two-step inline confirmation (not browser alert)
- [ ] Mobile 390px — sidebar collapses
- [ ] Console: 0 errors

---

## Task 6: Companies Page + Company Profile

**URL:** `http://job-portal.local/companies/`

- [ ] **Company cards**: hover effect on cards
- [ ] **Industry/Size filters**: dropdown hover, selection filters companies
- [ ] **List/Grid toggle**: hover + click, view switches
- [ ] **"View Profile" link**: hover + click
- [ ] **Single company page**: hero renders, About section, Company Details, Open Positions list
- [ ] **Job cards in company profile**: hover, "View Job" links work
- [ ] **"Load more jobs"**: button appears if >10 jobs, click loads more
- [ ] Mobile 390px
- [ ] Console: 0 errors

---

## Task 7: Find Candidates Page (Pro)

**URL:** `http://job-portal.local/find-candidates/`

- [ ] **Single search bar** (no duplicate after B-5 fix)
- [ ] **Skill filter dropdown**: hover, selection filters
- [ ] **Availability filter**: "All Candidates" / "Open to Work"
- [ ] **Resume cards**: hover effect, avatar + name + title + skills + location
- [ ] **No blank cards** (B-4 fix — Jane Smith excluded)
- [ ] **"View Resume" link**: hover + click navigates to resume detail
- [ ] **Single resume page**: renders all sections (experience, education, skills)
- [ ] Mobile 390px
- [ ] Console: 0 errors

---

## Task 8: Admin Pages — All Career Board Subpages

**URL:** `http://job-portal.local/wp-admin/admin.php?page=wp-career-board`

- [ ] **Dashboard**: stats cards (Active Jobs, Applications, Employers, Candidates), pending review table, recent applications
- [ ] **Jobs list**: bulk actions, search, status filters, Approve/Reject buttons
- [ ] **Applications list**: status change dropdown works inline, search works
- [ ] **Companies list**: trust level dropdown works, active jobs count correct (B-10 fix)
- [ ] **Candidates list**: loads, shows candidate info
- [ ] **Employers list**: loads, shows company linkage
- [ ] **Settings**: all tabs load (Job Listings, Pages, Notifications, Emails, Resumes, AI, Feed, Credits, Integrations, License)
- [ ] **Settings save**: click "Save Changes" → success notice
- [ ] **Boards (Pro)**: board list loads, edit link works
- [ ] **Field Builder (Pro)**: board selector works, field types display
- [ ] **Import page**: WPJM status correct, CSV upload button present
- [ ] Console: 0 errors

---

## Task 9: Registration + Auth Flow

**URL:** `http://job-portal.local/employer-registration/` (logged out)

- [ ] **Role picker**: Employer/Candidate cards hover + click
- [ ] **Registration form**: all fields have labels, password hint visible (B-4.2 fix)
- [ ] **Form validation**: submit empty → `role="alert"` error message shown inline
- [ ] **Submit**: creates account, redirects to dashboard
- [ ] **"Sign in" link**: hover, click → navigates to login
- [ ] Mobile 390px
- [ ] Console: 0 errors

---

## Task 10: Cross-Page Consistency Check

- [ ] **All buttons** use consistent hover pattern (border-color + light bg tint)
- [ ] **All inputs** have visible focus ring (2px blue outline)
- [ ] **All status badges** use consistent color scheme (green=success, yellow=pending, red=error)
- [ ] **No `window.alert()` anywhere** — only `window.confirm()` for destructive actions
- [ ] **No hardcoded English in JS** — all user-facing text comes from state.strings
- [ ] **No broken images** — all placeholder avatars show initials fallback
- [ ] **Footer** consistent across all pages
- [ ] **RTL check**: Settings → General → Site Language → Arabic → verify layout flips

---

## Task 11: Email Verification

**Mailpit URL:** `http://localhost:10106`

- [ ] Submit an application → check Mailpit for candidate confirmation + employer notification
- [ ] Change application status → check Mailpit for status update email
- [ ] Post a new job (as non-admin employer) → check Mailpit for admin approval notification
- [ ] Email formatting: HTML renders correctly, links work, no broken images

---

## Execution Notes

- Use Playwright MCP tools for all browser testing
- Take screenshots at key checkpoints
- Fix issues immediately when found — commit after each task
- Log all issues found in `docs/PRODUCTION-UX-ISSUES.md`
- After all tasks: push to GitHub, update Basecamp
