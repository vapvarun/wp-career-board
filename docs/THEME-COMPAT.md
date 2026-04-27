# 3rd-Party Theme Compatibility Report

**Date:** 2026-04-28
**Method:** Switched theme via WP-CLI, navigated to Find Jobs (`/?p=222`) and Post a Job (`/?p=346`) at 1440×900 with `?autologin=1`, captured Playwright full-page screenshot.
**Context:** This is real testing across 10 themes — every screenshot in `docs/theme-compat/` is from a live activation, not a mockup.

## Summary table

| Theme | Find Jobs verdict | Severity | Screenshot |
|---|---|---|---|
| **Astra** | ✅ Clean — 3-col grid, blue buttons match Astra's default accent, footer wired | OK | `astra-find-jobs.png` |
| **GeneratePress** | ⚠️ Falls to 2-col (narrow content area) | Low | `generatepress-find-jobs.png` |
| **Kadence** | ✅ Clean — 3-col, our blue against Kadence neutral | OK | `kadence-find-jobs.png` |
| **Neve** | ❌ **Duplicate `<h1>`** ("Find Jobs" twice — page banner + block heading) | **High** | `neve-find-jobs.png` |
| **OceanWP** | ❌ **Duplicate `<h1>`** + cards squeezed to 2-col + breadcrumbs eat 60px above | **High** | `oceanwp-find-jobs.png` |
| **Blocksy** | ⚠️ Cards expand to 4-col on Blocksy's wider container — text wraps inconsistently | Medium | `blocksy-find-jobs.png` |
| **Hello Elementor** | ❌❌ **Job titles underlined links, chips render with red borders, "View Job"/"Alert me"/"Load more" buttons all render with red theme outline** — token cascade lost | **Critical** | `hello-elementor-find-jobs.png` |
| **Twenty Twenty-Five** | ❌ Container narrows to ~700px → 2-col only. Pages-list nav floods header with 23 menu items. | **High** | `twentytwentyfive-find-jobs.png` |
| **Twenty Twenty-Four** | ❌ Same nav flood + 2-col + "View Job" button breaks out of card padding | **High** | `twentytwentyfour-find-jobs.png` |
| **Twenty Twenty-Three** | ⚠️ Same nav flood + 2-col, slightly better card spacing than TT4 | Medium | `twentytwentythree-find-jobs.png` |

| Theme | Post a Job verdict | Screenshot |
|---|---|---|
| **Astra** | ✅ Excellent — clean form, stepper renders crisp, container width perfect | `astra-post-a-job.png` |
| **Hello Elementor** | ❌ **"Next: Details" button renders with RED border / red text** — Hello Elementor's `<button>` selector wins specificity over our `.wcb-btn--primary` | `hello-elementor-post-a-job.png` |

---

## Per-theme detail

### Astra ✅
- 3-column job grid
- Filter chips render correctly with our token border
- Buttons keep our primary blue
- Footer credit "Powered by Astra"
- Post a Job form polished — best non-Wbcom theme experience
- **No issues found**

### GeneratePress ⚠️
- Content area max-width is narrower (~960px) → cards force to 2-col
- All other styling intact
- Sort dropdown wraps awkwardly on narrow row
- **Fix:** Document container-width recommendation; OR use container queries on the listings grid

### Kadence ✅
- Clean 3-col, no breakages
- Theme search input border integrated with our chip-bar
- Footer credit "WordPress Theme by Kadence WP"
- **No issues found**

### Neve ❌
- **Duplicate `<h1>` "Find Jobs"** — Neve renders `entry-title`, our `wcb-job-listings__heading` block also renders `<h1>`
- Search input has black border box that doesn't match other themes (Neve doesn't reset input borders)
- Cards otherwise OK
- **Fix:** Pages with our blocks should suppress theme `entry-title` via the `wcb_app_page_ids` body class — **doesn't currently fire on user-mapped pages**. Need to broaden the page detection.

### OceanWP ❌
- **Duplicate `<h1>` + breadcrumbs** at top consume 80-100px before our content begins
- Cards forced to 2-col
- Sort select renders empty wide gap to the right of the search input — looks broken
- "View Job" button bleeds outside card padding on first card row
- **Fix:** Same `wcb-page` body class issue as Neve + investigate sort-select width on narrow themes

### Blocksy ⚠️
- 4-column grid (Blocksy gives full content area without sidebar)
- Cards work but text wraps inconsistently — "Senior PHP Developer" 1 line vs "Backend Engineer — Payments" 2 lines
- **Fix:** Add a `min-height` on card title or normalise wrap behaviour

### Hello Elementor ❌❌ (worst case)
- `<a>` underlines never reset — every job title in the grid is underlined
- Filter chip buttons render with **red borders** (Hello Elementor button reset bleeds through)
- "Alert me" button has red outline
- "Load more jobs" button is a red-outlined chip
- **Post a Job:** "Next: Details" button is also red-outlined
- This is a CSS cascade specificity problem — Hello Elementor strips ~95% of base CSS, then their own `<button>` rule wins because we use `.wcb-btn--primary` (single class) without higher specificity
- **Fix:** Hard-set `text-decoration: none` on `.wcb-btn` and `.wcb-card a`; bump button selector specificity to `.wp-block-wcb-job-listings .wcb-btn--primary` or use `:where()` with required match

### Twenty Twenty-Five ❌
- **Container narrows to ~700px** — TT5 default is content-only, no wide alignments by default. Cards forced to 2-col.
- **Header flood:** When no nav menu is set, TT5 falls back to a flat list of every published page → 23+ menu items wrap across 4 lines
- Search input shows visible black border (TT5 doesn't reset)
- Footer pulls in default TT5 pattern with placeholder content (Blog, Events, About, Shop, etc.) the customer never edited
- **Fix:** Recommend setting up a primary nav menu in setup wizard. Add `.alignwide` support to job-listings block so TT5 grants more width.

### Twenty Twenty-Four ❌
- Same nav flood as TT5 (23+ unfiltered page menu)
- 2-col only
- **"View Job" button breaks out of card padding** — TT4's button base styles override our card-internal button max-width
- **Fix:** Same nav recommendation; constrain `.wcb-card .wcb-btn` to inherit card padding

### Twenty Twenty-Three ⚠️
- Nav flood (same)
- 2-col only
- Cards render OK without padding break (slightly looser than TT4)

---

## Root-cause categories (across all themes)

| Category | Themes affected | Cause |
|---|---|---|
| **Duplicate `<h1>`** | Neve, OceanWP | `wcb_app_page_ids` body-class injection only fires on plugin-mapped pages, not user-created pages with our blocks pasted in |
| **Container width forces 2-col** | GeneratePress, OceanWP, TT3, TT4, TT5 | Themes with narrow default content (~700-960px). We're built for ~1140px. |
| **Button styling lost (red borders)** | Hello Elementor, partially TT4 | CSS specificity — theme `<button>` rule wins against `.wcb-btn--primary` (one class) |
| **Underlined links inside cards** | Hello Elementor | Theme doesn't reset `<a>` underlines; we don't override at card scope |
| **Theme nav flood** | TT3, TT4, TT5 | When customer hasn't built a menu, default themes show every page (admin issue, not ours, but customer impression suffers) |
| **Search/sort input borders** | Neve, TT5 | Themes don't reset native input borders; our `.wcb-search-input` doesn't reset hard enough |
| **Theme accent color not flowing** | All themes except Reign + BuddyX | We have no bridge from theme customizer → `--wcb-primary` |

---

## Severity-ranked fix plan

| # | Fix | Themes fixed | Effort |
|---|---|---|---|
| 1 | **`text-decoration: none` on `.wcb-card a` and `.wcb-btn` + `:where()` specificity bump** | Hello Elementor (critical) | 1 hour |
| 2 | **Broaden `wcb_app_page_ids` to detect any page containing a WCB block at render time** | Neve, OceanWP (duplicate `<h1>`) | 2 hours |
| 3 | **Add `align: ['wide', 'full']` support to `wcb/job-listings`, `wcb/resume-archive` and the dashboards** | GeneratePress, TT3-5 (2-col→3-col) | 1 hour |
| 4 | **Constrain in-card buttons** — `.wcb-card .wcb-btn { max-width: 100%; }` | TT4 (button overflow) | 15 min |
| 5 | **Reset card-link decoration via `:where()`** — `.wcb-card :where(a, a:hover) { text-decoration: none; }` | Hello Elementor | 30 min |
| 6 | **Search/sort input border reset** — `.wcb-search-input { border: 1px solid var(--wcb-border); }` with explicit reset | Neve, TT5 | 30 min |
| 7 | **Card title `min-height` or `line-clamp`** | Blocksy | 30 min |
| 8 | **Theme accent auto-bridge** — read common theme customizer color mods and write `--wcb-primary` | All non-Reign/BuddyX themes | 1-2 days |

Items 1-7 are tactical, can ship in a 1.1.1 patch (~6 hours total). Item 8 is the strategic fix and belongs in 1.2.0.

## Themes NOT tested (would require licenses)

- **Avada** — known for heavy CSS, would likely surface specificity issues similar to Hello Elementor
- **Divi** — page builder + theme; shortcode-rendering edge cases expected
- **GeneratePress Premium** — PR features beyond Free
- **Kadence Pro** — likely same as Free version (no breakages observed)

## Themes not yet tested with mobile breakpoints

All screenshots above are 1440×900. Mobile (390px) compatibility was tested only on Reign + BuddyX in 1.1.0 — every other theme is unverified at mobile. Adding to 1.2.0 work.
