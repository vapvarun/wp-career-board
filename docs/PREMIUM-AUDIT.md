# WP Career Board — Premium Release Audit

> Date: 2026-03-25
> Status: In Progress

---

## 1. ACCESSIBILITY (WCAG 2.1 AA)

### Critical
| # | Issue | Location | Fix |
|---|-------|----------|-----|
| A-1 | Sort dropdown has no aria-label | job-listings render.php — combobox | Add `aria-label="Sort jobs"` |
| A-2 | Notification bell link has empty accessible name | Theme header — bell icon link | Add `aria-label="Notifications"` or sr-only text |

### Medium
| # | Issue | Location | Fix |
|---|-------|----------|-----|
| A-3 | Bookmark button images have no alt="" | job-listings, job-single — bookmark `<img>` tags | Add `alt=""` (decorative) since button has text |
| A-4 | Grid/List view toggle images have no alt | job-listings — layout toggle buttons | Add `alt=""` on decorative images |
| A-5 | Location pin images have no alt | job-listings, job-single | Add `alt=""` or `aria-hidden="true"` |
| A-6 | Apply modal panel — focus trap not verified | job-single view.js | Verify focus stays within panel when open |
| A-7 | No aria-live region on job listing area | job-listings — dynamic content | Add `aria-live="polite"` on job results container |
| A-8 | Stat card values not semantically associated with labels | employer-dashboard render.php | Use `aria-label` or `dl/dt/dd` pattern |

---

## 2. TRANSLATION READINESS

### Critical — JS Hardcoded Strings (NOT translatable)
All view.js files use hardcoded English strings. The Interactivity API doesn't support `wp.i18n`. These should be passed from PHP via `wp_interactivity_state()`.

| # | File | Count | Examples |
|---|------|-------|---------|
| T-1 | candidate-dashboard/view.js | 7 | "Could not load your applications.", "Could not remove saved job." |
| T-2 | employer-dashboard/view.js | 4 | "Could not load your jobs.", "Please save your company profile..." |
| T-3 | job-single/view.js | 1 | "Please enter your name and email to apply." |
| T-4 | job-listings/view.js | ? | Check for count labels, "x of y jobs" |
| T-5 | company-archive/view.js | ? | Check for "x companies found" |

**Fix pattern:** Move all user-facing strings to `wp_interactivity_state()` in render.php and reference via `state.strings.errorLoadJobs` in JS.

### Good
- .pot file exists for both Free and Pro
- PHP files correctly use `__()`, `esc_html__()`, `esc_attr__()` with proper text domain
- Translator comments on most printf/sprintf calls

---

## 3. RTL SUPPORT

### Critical
| # | Issue | Fix |
|---|-------|-----|
| R-1 | No frontend RTL CSS for blocks | Generate RTL variants using `grunt-rtlcss` (already installed) or migrate to CSS logical properties |
| R-2 | 25+ uses of `margin-left`/`margin-right`/`padding-left`/`padding-right` in block CSS | Replace with `margin-inline-start`/`margin-inline-end` etc. |
| R-3 | Only `job-listings/style.css` uses some logical properties (4 instances) | All other blocks use physical properties exclusively |

### Status
- Admin RTL CSS: EXISTS (both Free + Pro)
- Frontend RTL CSS: MISSING (both Free + Pro)
- grunt-rtlcss: INSTALLED but not configured for block CSS
- CSS logical properties: Minimal usage (only job-listings)

**Recommendation:** Add `rtlcss` Grunt task for all block `style.css` files, OR migrate all directional CSS to logical properties (modern approach, no separate file needed).

---

## 4. USABILITY

### Verified Working
- Empty states on dashboards (employer + candidate)
- Loading states during async fetches (loading = true in initial state)
- Error messages for failed requests
- Success feedback on apply, bookmark, job alert
- Required fields marked with * on forms
- Pagination via "Load more" button
- Mobile responsive at 390px

### Needs Improvement
| # | Issue | Location | Fix |
|---|-------|----------|-----|
| U-1 | No confirmation dialog on destructive actions (delete resume, withdraw application) | candidate-dashboard view.js | Add `confirm()` or modal before delete/withdraw |
| U-2 | No undo option after bookmark removal | job-listings view.js | Add toast with "Undo" link for 5 seconds |
| U-3 | Recent Applications/Active Jobs show "No applications yet" while loading | employer-dashboard — loading=true but empty state still shows | Show skeleton or "Loading..." instead |

---

## 5. LANGUAGE CONSISTENCY

### Verified
- "Jobs" used consistently (not "Listings" or "Positions" except in context)
- "Apply Now" / "Application Submitted" consistent
- "Bookmark" / "Save Job" — **INCONSISTENCY**: card uses "Bookmark job" button, single page uses "Save Job" button
- American English used consistently throughout

### Issues
| # | Issue | Fix |
|---|-------|-----|
| L-1 | "Bookmark job" (archive) vs "Save Job" (single page) | Standardize to "Save Job" everywhere or "Bookmark" everywhere |
| L-2 | "✓ Verified" (frontend) vs "Verified" (admin trust level) | Minor — acceptable |

---

## 6. PREMIUM UX PATTERNS

### Working
- Apply modal slide-in panel (not page redirect)
- Job alert one-click save with instant feedback
- Grid/List view toggle with state persistence
- Responsive bookmark toggle with icon change
- Company initials fallback when no logo

### Missing/Needed
| # | Issue | Priority |
|---|-------|----------|
| P-1 | No skeleton loading screens — content area is blank until data loads | Low |
| P-2 | No smooth transitions on view switches in dashboards | Low |
| P-3 | No image lazy loading on company logos | Low |
| P-4 | No keyboard shortcut hints | Low |

---

## Summary

| Category | Critical | Medium | Low |
|----------|----------|--------|-----|
| Accessibility | 2 | 6 | - |
| Translation (JS) | 5 | - | - |
| RTL | 3 | - | - |
| Usability | - | 3 | - |
| Language | - | 1 | 1 |
| Premium UX | - | - | 4 |

---

## Deep Audit Results (from code agents)

### Free Plugin — Additional Findings

**Accessibility (6 critical, 8 medium):**
- A1-C1: Job listings search input has no label
- A1-C2: Sort select has no label
- A1-C3: Dashboard search inputs have no label
- A1-C4: Apply panel missing `role="dialog"`, no focus trap
- A1-C5: `role="button"` div with partial keyboard support
- A1-C6: Resume title input has no label
- P6-C2: Slide animation missing `prefers-reduced-motion` media query

**Translation (3 critical):**
- T2-C1: All JS view.js files have hardcoded English (12+ strings across 5 files)
- T2-C2: Currency option labels not wrapped in `__()`
- T2-M2: "Up to" salary string hardcoded in job-listings (fixed in job-single)

**RTL (5 critical):**
- R3-C1: `background-position: right` on select dropdowns won't flip
- R3-C2: `padding-right` on selects won't flip
- R3-C3: 25+ directional CSS properties across 14 files, no RTL stylesheet
- R3-C4: List `padding-left` won't flip
- R3-C5: `text-align: right` should be `text-align: end`

**Usability:**
- U4-C2: Registration form has no password strength hint
- U4-M4: "Close Job" has no confirmation dialog
- U4-M5: Validation error `<p>` missing `role="alert"`

### Pro Plugin — Additional Findings

**Accessibility (5 high):**
- A-02: Resume builder labels have no for/id pairing
- A-04: Job alerts delete button is icon-only with no accessible name
- A-05: Kanban has no keyboard drag alternative (WCAG 2.1.1 + 2.5.7)
- A-06: Board switcher tabs missing `aria-selected`
- A-07: No `aria-live` regions on any Pro dynamic blocks

**Translation (3 high):**
- T-01: Duration "yr/yrs/mo/mos" not wrapped in `_n()`
- T-02: Job alerts JS has hardcoded "Remote", "$", "All Jobs"
- T-03: Kanban JS has hardcoded "Applicant #"

**RTL (1 high, 5 medium):**
- R-01: Resume timeline `left` positioning won't flip
- R-02 to R-07: Various `margin-left/right` not using logical properties

**Usability (2 high):**
- U-01: Credit balance history panel shows "Loading..." permanently (functional gap — no fetch)
- U-08: No confirmation dialogs on resume entry remove and alert delete

---

## Priority Matrix for v1.0.0

### Must Fix (blocks release)
| # | Category | Count | Effort |
|---|----------|-------|--------|
| 1 | JS i18n strings (Free + Pro) | 15+ strings | Medium — pass via `wp_interactivity_state()` |
| 2 | Critical a11y — form labels, dialog roles | 8 issues | Quick — add aria-labels/roles |
| 3 | `prefers-reduced-motion` on animations | 1 issue | Quick — 3 lines CSS |

### Should Fix (important for international/a11y users)
| # | Category | Count | Effort |
|---|----------|-------|--------|
| 4 | RTL CSS — migrate to logical properties | 30+ properties | Medium-large |
| 5 | Kanban keyboard alternative | 1 issue | Medium |
| 6 | Confirmation dialogs on destructive actions | 3 issues | Quick |
| 7 | Credit balance history fetch (broken) | 1 issue | Medium |

### Can Ship Without (polish for v1.1)
- Skeleton loading consistency
- Smooth view transitions
- Image lazy loading / srcset
- Language consistency (Bookmark vs Save, Sign In capitalization)
- Empty state messages on featured/candidate blocks
