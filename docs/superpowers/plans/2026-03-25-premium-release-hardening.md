# Premium Release Hardening — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make WP Career Board (Free + Pro) production-ready by fixing accessibility, i18n, RTL, CSS design tokens, usability, and language consistency issues found in the premium audit.

**Architecture:** Four independent subsystems fixed in priority order. Each produces a self-contained commit. CSS tokens are applied per-block to avoid a big-bang rewrite. JS i18n strings are moved to `wp_interactivity_state()` to leverage PHP's `__()` functions.

**Tech Stack:** WordPress Interactivity API, theme.json v3, CSS Logical Properties, WordPress i18n (`__()`, `esc_html__()`, `_n()`)

**Plugins:**
- Free: `/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board/`
- Pro: `/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board-pro/`

**Reference:**
- Audit doc: `docs/PREMIUM-AUDIT.md`
- theme.json tokens: `theme.json` (8 font sizes, 7 spacings, 9 colors)
- CSS custom properties: `var(--wp--preset--color--wcb-primary)`, `var(--wp--preset--font-size--sm)`, `var(--wp--preset--spacing--20)`, etc.

---

## Subsystem 1: Critical Accessibility (Free + Pro)

Quick targeted fixes — aria-labels, dialog roles, reduced-motion. Each block is one task.

### Task 1.1: Job Listings — form labels + aria-live

**Files:**
- Modify: `blocks/job-listings/render.php`
- Modify: `blocks/job-listings/style.css`

- [ ] **Step 1:** Add `aria-label` to sort select (line ~267): `aria-label="<?php esc_attr_e( 'Sort jobs', 'wp-career-board' ); ?>"`
- [ ] **Step 2:** Add visually-hidden label for search input: `<label class="screen-reader-text" for="wcb-job-search"><?php esc_html_e( 'Search jobs', 'wp-career-board' ); ?></label>` and add `id="wcb-job-search"` to the input
- [ ] **Step 3:** Add `aria-live="polite"` to `.wcb-results-count` paragraph (if not already present)
- [ ] **Step 4:** Add `.screen-reader-text` CSS class if not in stylesheet:
```css
.screen-reader-text {
    clip: rect(1px, 1px, 1px, 1px);
    clip-path: inset(50%);
    height: 1px;
    width: 1px;
    margin: -1px;
    overflow: hidden;
    padding: 0;
    position: absolute;
    word-wrap: normal !important;
}
```
- [ ] **Step 5:** Run `php -l` on render.php
- [ ] **Step 6:** Commit: `fix(wcb): add a11y labels to job-listings search and sort`

### Task 1.2: Job Single — apply panel dialog role + reduced-motion

**Files:**
- Modify: `blocks/job-single/render.php`
- Modify: `blocks/job-single/style.css`

- [ ] **Step 1:** Add to the apply panel div: `role="dialog" aria-modal="true" aria-labelledby="wcb-apply-title"`
- [ ] **Step 2:** Add `id="wcb-apply-title"` to the `<h2>` heading inside the panel
- [ ] **Step 3:** Add `aria-label="<?php esc_attr_e( 'Close application panel', 'wp-career-board' ); ?>"` to the close button (the × button)
- [ ] **Step 4:** Add to style.css:
```css
@media (prefers-reduced-motion: reduce) {
    .wcb-apply-panel { animation: none !important; }
}
```
- [ ] **Step 5:** Run `php -l`, verify in browser
- [ ] **Step 6:** Commit: `fix(wcb): add dialog role and reduced-motion to apply panel`

### Task 1.3: Employer Dashboard — search labels + skeleton a11y

**Files:**
- Modify: `blocks/employer-dashboard/render.php`

- [ ] **Step 1:** Add `aria-label` to the job search input (~line 322): `aria-label="<?php esc_attr_e( 'Search your jobs', 'wp-career-board' ); ?>"`
- [ ] **Step 2:** Add `aria-label` to the application job search input (~line 382): `aria-label="<?php esc_attr_e( 'Search applications by job', 'wp-career-board' ); ?>"`
- [ ] **Step 3:** Add `role="status" aria-label="<?php esc_attr_e( 'Loading', 'wp-career-board' ); ?>"` to `.wcb-db-loading` div
- [ ] **Step 4:** Add `aria-label="<?php esc_attr_e( 'Toggle navigation', 'wp-career-board' ); ?>"` to `.wcb-nav-toggle` button
- [ ] **Step 5:** Run `php -l`, commit: `fix(wcb): add a11y labels to employer-dashboard inputs and loading`

### Task 1.4: Candidate Dashboard — search labels + loading role

**Files:**
- Modify: `blocks/candidate-dashboard/render.php`

- [ ] **Step 1:** Add `aria-label` to nav toggle button
- [ ] **Step 2:** Add `role="status"` to all `.wcb-cd-loading` containers
- [ ] **Step 3:** Add visually-hidden label for new resume title input: `<label class="screen-reader-text" for="wcb-new-resume-title">` + `id` on input
- [ ] **Step 4:** Run `php -l`, commit: `fix(wcb): add a11y labels to candidate-dashboard`

### Task 1.5: Company Archive — aria-label on toggle buttons + aria-live

**Files:**
- Modify: `blocks/company-archive/render.php`

- [ ] **Step 1:** Replace `title=` with `aria-label=` on list/grid toggle buttons
- [ ] **Step 2:** Add `aria-live="polite"` to `.wcb-ca-results` paragraph
- [ ] **Step 3:** Set server-side `alt` attribute on company logo images (not just `data-wp-bind--alt`)
- [ ] **Step 4:** Run `php -l`, commit: `fix(wcb): add a11y to company-archive toggles and results count`

### Task 1.6: Pro — Resume Builder labels + board switcher tabs

**Files:**
- Modify: Pro `blocks/resume-builder/render.php`
- Modify: Pro `blocks/board-switcher/render.php`

- [ ] **Step 1:** In resume-builder, wrap each field group in implicit `<label>` containing the input (or add `for`/`id` pairing using section key + field name)
- [ ] **Step 2:** Change "✕ Remove" button: `<span aria-hidden="true">✕</span> <?php esc_html_e( 'Remove', 'wp-career-board-pro' ); ?>`
- [ ] **Step 3:** In board-switcher, add `data-wp-bind--aria-selected` to each tab button
- [ ] **Step 4:** Add `aria-live="polite"` regions to credit-balance, job-alerts, application-kanban blocks
- [ ] **Step 5:** Run `php -l` on all changed files, commit: `fix(wcbp): add a11y labels to resume-builder and board-switcher`

---

## Subsystem 2: JS i18n — Move Hardcoded Strings to State (Free + Pro)

Pattern: In each block's `render.php`, add a `'strings'` key to `wp_interactivity_state()`. In `view.js`, reference `state.strings.keyName` instead of hardcoded English.

### Task 2.1: Employer Dashboard JS i18n

**Files:**
- Modify: `blocks/employer-dashboard/render.php` — add strings to `wp_interactivity_state()`
- Modify: `blocks/employer-dashboard/view.js` — replace hardcoded strings with `state.strings.*`

- [ ] **Step 1:** In render.php, add to `wp_interactivity_state()` array:
```php
'strings' => array(
    'errorLoadJobs'    => __( 'Could not load your jobs.', 'wp-career-board' ),
    'errorLoadApps'    => __( 'Could not load applications.', 'wp-career-board' ),
    'errorSaveProfile' => __( 'Could not save profile. Please try again.', 'wp-career-board' ),
    'errorSaveLogo'    => __( 'Please save your company profile before uploading a logo.', 'wp-career-board' ),
    'errorConnection'  => __( 'Connection error. Please check your network and try again.', 'wp-career-board' ),
    'overview'         => __( 'Overview', 'wp-career-board' ),
    'myJobs'           => __( 'My Jobs', 'wp-career-board' ),
    'applications'     => __( 'Applications', 'wp-career-board' ),
    'profile'          => __( 'Profile', 'wp-career-board' ),
    'postAJob'         => __( 'Post a Job', 'wp-career-board' ),
),
```
- [ ] **Step 2:** In view.js, replace each hardcoded string:
  - `'Could not load your jobs.'` → `state.strings.errorLoadJobs`
  - `'Overview'` → `state.strings.overview`
  - etc.
- [ ] **Step 3:** Verify in browser — dashboard should work identically
- [ ] **Step 4:** Commit: `fix(wcb): move employer-dashboard JS strings to wp_interactivity_state for i18n`

### Task 2.2: Candidate Dashboard JS i18n

**Files:**
- Modify: `blocks/candidate-dashboard/render.php`
- Modify: `blocks/candidate-dashboard/view.js`

Same pattern as 2.1. Strings to move:
- `'Could not load your applications.'`
- `'Could not load saved jobs.'`
- `'Could not load your alerts.'`
- `'Could not load your resumes.'`
- `'Could not remove saved job. Please try again.'`
- `'Could not create resume. Please try again.'`
- `'Could not delete resume. Please try again.'`
- Tab labels: `'Overview'`, `'My Applications'`, `'Saved Jobs'`, `'My Resumes'`, `'Job Alerts'`

- [ ] **Step 1:** Add `'strings'` array to `wp_interactivity_state()` in render.php
- [ ] **Step 2:** Replace all hardcoded strings in view.js with `state.strings.*`
- [ ] **Step 3:** Verify in browser, commit: `fix(wcb): move candidate-dashboard JS strings to state for i18n`

### Task 2.3: Job Single + Job Listings JS i18n

**Files:**
- Modify: `blocks/job-single/render.php` + `view.js`
- Modify: `blocks/job-listings/render.php` + `view.js`

Job Single strings:
- `'Please enter your name and email to apply.'`

Job Listings strings:
- `'Remove bookmark'`, `'Bookmark job'` (aria-labels)
- Count labels: `'1 job'`, `'%d jobs'`, `'%d of %d jobs'`
- `'Up to '` salary prefix (in render.php PHP — use `__()`)

- [ ] **Step 1:** Add strings to both render.php files
- [ ] **Step 2:** Replace in both view.js files
- [ ] **Step 3:** Fix `'Up to '` in job-listings/render.php line 99: wrap with `__( 'Up to %s', 'wp-career-board' )`
- [ ] **Step 4:** Verify, commit: `fix(wcb): move job-single and job-listings JS strings to state for i18n`

### Task 2.4: Pro Plugin JS i18n

**Files:**
- Modify: Pro `blocks/job-alerts/view.js` + `render.php`
- Modify: Pro `blocks/application-kanban/view.js` + `render.php`
- Modify: Pro `blocks/resume-single/render.php`

- [ ] **Step 1:** Job alerts: pass `'Remote'`, `'All Jobs'`, `'up to'`, and currency symbol via `wp_interactivity_state()`
- [ ] **Step 2:** Kanban: pass `'Applicant'` label via state; replace `'Applicant #' + id` with `state.strings.applicant + ' #' + id`
- [ ] **Step 3:** Resume single: wrap duration strings in `_n()`:
```php
sprintf( _n( '%d yr', '%d yrs', $yrs, 'wp-career-board-pro' ), $yrs )
sprintf( _n( '%d mo', '%d mos', $mos, 'wp-career-board-pro' ), $mos )
```
- [ ] **Step 4:** Verify, commit: `fix(wcbp): move Pro JS strings to state for i18n + duration _n()`

---

## Subsystem 3: RTL + CSS Design Tokens (Free + Pro)

Strategy: Process one block at a time. For each block:
1. Replace directional CSS with logical properties
2. Replace hardcoded colors with `var(--wp--preset--color--wcb-*)`
3. Replace hardcoded font-sizes with `var(--wp--preset--font-size--*)`
4. Replace hardcoded spacing with `var(--wp--preset--spacing--*)`

### Token Mapping Reference

**Colors:**
| Hardcoded | Token |
|-----------|-------|
| `#2563eb` | `var(--wp--preset--color--wcb-primary)` |
| `#1d4ed8` | `var(--wp--preset--color--wcb-primary-dark)` |
| `#0f172a` | `var(--wp--preset--color--wcb-contrast)` |
| `#1e293b` | `var(--wp--preset--color--wcb-avatar-bg)` |
| `#6b7280` | `var(--wp--preset--color--wcb-muted)` |
| `#f1f5f9` | `var(--wp--preset--color--wcb-surface)` |
| `#e2e8f0` | `var(--wp--preset--color--wcb-border)` |
| `#f59e0b` | `var(--wp--preset--color--wcb-featured)` |
| `#ffffff` | `var(--wp--preset--color--wcb-base)` |

**RTL Logical Properties:**
| Physical | Logical |
|----------|---------|
| `margin-left` | `margin-inline-start` |
| `margin-right` | `margin-inline-end` |
| `padding-left` | `padding-inline-start` |
| `padding-right` | `padding-inline-end` |
| `text-align: left` | `text-align: start` |
| `text-align: right` | `text-align: end` |
| `float: left` | `float: inline-start` |
| `float: right` | `float: inline-end` |
| `left:` (position) | `inset-inline-start:` |
| `right:` (position) | `inset-inline-end:` |
| `border-left` | `border-inline-start` |
| `border-right` | `border-inline-end` |
| `background-position: right` | `background-position: inline-end` |

### Task 3.1: job-listings/style.css — tokens + RTL

- [ ] **Step 1:** Replace all matching hex colors with CSS custom properties
- [ ] **Step 2:** Replace `margin-left`, `padding-left/right` with logical properties
- [ ] **Step 3:** Replace hardcoded font-sizes with nearest theme.json token
- [ ] **Step 4:** Verify visually in browser at 1440px and 390px
- [ ] **Step 5:** Commit: `refactor(wcb): migrate job-listings CSS to theme.json tokens + logical properties`

### Task 3.2–3.7: Repeat for each block CSS

One task per block, same pattern:
- 3.2: `job-single/style.css`
- 3.3: `employer-dashboard/style.css` (largest — 11 directional properties)
- 3.4: `candidate-dashboard/style.css`
- 3.5: `company-archive/style.css`
- 3.6: `job-form/style.css`
- 3.7: Remaining blocks (employer-registration, featured-jobs, recent-jobs, job-search, job-filters, company-profile, job-stats)

### Task 3.8: Pro block CSS — tokens + RTL

Same pattern for Pro blocks:
- `resume-single/style.css` (timeline positioning — highest RTL priority)
- `resume-builder/style.css`
- `open-to-work/style.css`
- Other Pro block CSS files

- [ ] Per-block: replace colors, font-sizes, directional properties
- [ ] Verify visually
- [ ] Commit per block or batch: `refactor(wcbp): migrate Pro block CSS to tokens + logical properties`

---

## Subsystem 4: Usability + Language Consistency

### Task 4.1: Confirmation dialogs for destructive actions

**Files:**
- Modify: `blocks/employer-dashboard/view.js` — add confirm() before closeJob
- Modify: Pro `blocks/resume-builder/view.js` — add confirm() before removeEntry
- Modify: Pro `blocks/job-alerts/view.js` — add confirm() before deleteAlert

- [ ] **Step 1:** In employer-dashboard view.js `actions.closeJob`: add `if (!window.confirm(state.strings.confirmCloseJob)) return;`
- [ ] **Step 2:** In resume-builder view.js: add confirm before remove entry
- [ ] **Step 3:** In job-alerts view.js: add confirm before delete alert
- [ ] **Step 4:** Add the confirm strings to each render.php's `wp_interactivity_state()` strings array
- [ ] **Step 5:** Commit: `fix(wcb): add confirmation dialogs for destructive actions`

### Task 4.2: Validation error role="alert" + password hint

**Files:**
- Modify: `blocks/job-form/render.php` — add `role="alert"` to validation error `<p>`
- Modify: `blocks/employer-registration/render.php` — add password requirements hint

- [ ] **Step 1:** Add `role="alert"` to `.wcb-form-error` paragraph
- [ ] **Step 2:** Add hint text below password field: `<span class="wcb-form-hint"><?php esc_html_e( 'Minimum 8 characters', 'wp-career-board' ); ?></span>`
- [ ] **Step 3:** Commit: `fix(wcb): add role=alert to validation errors + password hint`

### Task 4.3: Language consistency

**Files:**
- Modify: `blocks/job-listings/render.php` — change "Bookmark job" to "Save Job"
- Modify: `blocks/job-listings/view.js` — change bookmark aria-labels
- Modify: `blocks/employer-registration/render.php` — standardize "Sign in" casing
- Modify: `blocks/employer-dashboard/render.php` — change "Total Applicants" to "Total Applications"

- [ ] **Step 1:** Replace "Bookmark job" → "Save job" and "Remove bookmark" → "Saved" in job-listings
- [ ] **Step 2:** Standardize "Sign In" → "Sign in" (sentence case) in employer-registration
- [ ] **Step 3:** Change "Total Applicants" → "Total Applications" in employer-dashboard
- [ ] **Step 4:** Commit: `fix(wcb): standardize terminology — Save job, Sign in, Applications`

---

## Execution Order

1. **Subsystem 1** (a11y) — 6 tasks, ~30 min
2. **Subsystem 2** (JS i18n) — 4 tasks, ~45 min
3. **Subsystem 4** (usability + language) — 3 tasks, ~20 min
4. **Subsystem 3** (CSS tokens + RTL) — 8+ tasks, ~2 hours

Total: ~3.5 hours across multiple sessions.

## Verification

After all subsystems complete:
1. Run WPCS on all modified files
2. Browser test at 1440px and 390px — all pages
3. Check 0 JS console errors
4. Regenerate .pot files: `wp i18n make-pot . languages/wp-career-board.pot`
5. Test with RTL language (Settings → General → Site Language → Arabic/Hebrew)
