# Theme Compatibility Matrix — WP Career Board

> Pre-release verification across the WordPress.org top 10 free themes plus
> Wbcom's BuddyX / BuddyX Pro / Reign. Each cell records pass / fail / fix
> needed status with notes.

## Themes under test

1. **Reign** — Wbcom flagship community theme (BuddyX-based)
2. **BuddyX** — Wbcom community / BuddyPress theme (free)
3. **BuddyX Pro** — Wbcom Pro version
4. **Astra** — wordpress.org top theme by installs
5. **Hello Elementor** — Elementor's bundled theme
6. **OceanWP**
7. **Twenty Twenty-Four** — current default
8. **Twenty Twenty-Three**
9. **GeneratePress**
10. **Kadence**
11. **Neve**
12. **Storefront** — WooCommerce default
13. **Blocksy**

## Frontend pages under test

| # | URL | Block / Source |
|---|---|---|
| P1 | `/post-a-job/` | `wcb/job-form` (multi-step) |
| P2 | `/post-a-job/?edit=N` | `wcb/job-form` edit mode |
| P3 | `/employer-dashboard/` (Overview) | `wcb/employer-dashboard` |
| P4 | `/employer-dashboard/?wcb_tab=profile` | `wcb/employer-dashboard` profile tab |
| P5 | `/candidate-dashboard/` | `wcb/candidate-dashboard` |
| P6 | `/employer-registration/` | `wcb/employer-registration` |
| P7 | `/jobs/` | `wcb_job` archive — `wcb/job-listings` |
| P8 | `/jobs/{slug}/` | `wcb_job` single — `wcb/job-single` |
| P9 | `/companies/` | `wcb_company` archive — `wcb/company-archive` |
| P10 | `/companies/{slug}/` | `wcb_company` single — `wcb/company-profile` |

## Test protocol

For each (theme, page) cell:

1. `wp theme activate <slug>`
2. Navigate to the page at viewport 1614 × 1070 and 390 × 844
3. Capture a full-page screenshot
4. Inspect for:
   - layout integrity (cards span content, no orphan rows, no overflow)
   - link styling (no theme-default underlines on nav-style links)
   - button styling (matches our token system, not theme defaults)
   - form input styling (heights consistent, padding intact)
   - rich-text rendered content rhythm (h3/list/blockquote spaced)
   - sidebar widget areas not stealing dashboard width
5. Record status in the matrix below

## Status legend

- 🟢 — Pass, premium UX
- 🟡 — Pass with minor cosmetic gap (acceptable for ship)
- 🔴 — Fail, blocking
- ⏳ — Not yet tested

## Matrix

| Theme \ Page | P3 EmpDash | P7 JobsArchive | P8 JobSingle | P9 CompArchive | P10 CompSingle |
|---|---|---|---|---|---|
| Reign | 🟢 | ⏳ | 🟢 | ⏳ | ⏳ |
| BuddyX | 🟢 | 🟢 | ⏳ | 🟢 | ⏳ |
| BuddyX Pro | 🟢 | ⏳ | ⏳ | ⏳ | ⏳ |
| Astra | 🟢 | 🟢 | 🟢 | 🟢 | 🟢 |
| Hello Elementor | 🟡 | ⏳ | ⏳ | ⏳ | ⏳ |
| OceanWP | 🟢 | ⏳ | ⏳ | ⏳ | ⏳ |
| GeneratePress | 🟢 | 🟢 | ⏳ | ⏳ | ⏳ |
| Kadence | 🟢 | ⏳ | ⏳ | ⏳ | ⏳ |
| Neve | 🟢 | ⏳ | ⏳ | ⏳ | ⏳ |
| Storefront | 🟢 | ⏳ | ⏳ | ⏳ | ⏳ |
| Blocksy | 🟢 | ⏳ | ⏳ | ⏳ | ⏳ |

**Themes skipped per stakeholder direction:** Twenty Twenty-Four, Twenty Twenty-Three (block-only themes; user requested not in scope).

**Hello Elementor 🟡 note:** Sidebar tabs / nav-items / card-links no longer pick up theme-default magenta border. Some text-only "View all →" / "Manage all →" buttons render as inherited theme color rather than `--wcb-primary` because Hello Elementor is intentionally an unstyled starter theme — customers using Hello Elementor are expected to build with Elementor templates anyway. Acceptable for ship.

## Known cross-theme issues + fixes

### F1 — Companies/Jobs archive constrained by theme sidebar layout
**Themes affected:** Astra, OceanWP, GeneratePress, Storefront, Twenty Twenty-Three (any theme with a default archive sidebar).
**Symptom:** `/companies/` and `/jobs/` show our archive block in a narrow content column (~600 px) on a 1614 px viewport.
**Fix:** ship a custom archive template (`templates/archive-wcb_company.php`, `templates/archive-wcb_job.php`) that bypasses the theme's archive sidebar layout and renders the block in a full-width main element via `get_header()` / `get_footer()`. Wired via the `archive_template` filter.
**Status:** queued.

### F2 — Theme-default underlines bleed through onto nav-style links
**Themes affected:** Astra, Twenty Twenty-Four, GeneratePress.
**Symptom:** Card titles, sidebar tabs, role-picker cards, dashboard menu items inherit blue underline from `entry-content a { text-decoration: underline }`.
**Fix:** global theme-link defence rule in `assets/css/frontend-components.css` scoped under `[class*="wp-block-wp-career-board"]` and `[class*="wp-block-wcb-"]`.
**Status:** ✅ shipped in `8b3100c`.

### F3 — Editor.js-authored rich content renders without rhythm
**Themes affected:** All (this is our CSS, not theme).
**Symptom:** h3 / ul / blockquote inside `.wcb-cp-desc` and `.wcb-job-description` had no top margin so headings collided with section titles.
**Fix:** explicit margins for h2 / h3 / h4 / ul / ol / li / blockquote / cite / code in both stylesheets, with `:first-child / :last-child` margin trims.
**Status:** ✅ shipped in `8b3100c`.

### F4 — Profile grid collapsed on Reign-with-sidebar layout
**Themes affected:** Reign / BuddyX / any theme that takes a sidebar widget area on dashboard pages.
**Symptom:** `.wcb-profile-grid` was hardcoded `1fr 340px` so the form column shrank to ~200 px when the theme's sidebar took ~290 px out of a 1140 px page.
**Fix:** container queries on `.wcb-view-panel` so the grid stacks form / preview vertically when the panel is < 880 px.
**Status:** ✅ shipped in `167d97e`.

### F5 — Form primitives only enqueued from one block
**Themes affected:** All.
**Symptom:** The registration page rendered raw browser-default inputs because `.wcb-field-*` rules lived only inside `blocks/employer-dashboard/style.css`.
**Fix:** hoisted to `assets/css/frontend-components.css` so any WCB block on a page picks them up.
**Status:** ✅ shipped in `167d97e`.

## Pages that produce content visible to applicants/employers (must look premium on every theme)

These must pass on every row of the matrix or it isn't a release candidate:

- P3 Employer Dashboard Overview — first impression for paying employers
- P4 Employer Profile — where company info is captured
- P8 Single Job — what every applicant sees
- P9 Companies archive — employer discovery surface
- P10 Single Company — employer brand page

## Out of scope for this matrix (separate audits)

- wp-admin admin screens (handled in `class-admin-job-editor.php` audit)
- Translation / i18n / RTL — separate i18n QA pass
- Accessibility (screen reader, keyboard) — separate a11y audit
- Performance (Core Web Vitals on each theme) — separate perf audit
