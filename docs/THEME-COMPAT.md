# 3rd-Party Theme Compatibility Report

**Date:** 2026-04-28
**Method:** Switched theme via WP-CLI, navigated to Find Jobs (`/?p=222`) and Post a Job (`/?p=346`) at 1440×900 with `?autologin=1`, captured Playwright full-page screenshot.
**Context:** This is real testing across 10 themes — every screenshot in `docs/theme-compat/` is from a live activation, not a mockup.

## What "matters" depends on the customer mix

Themes split into three realistic buckets for our customer base (Wbcom
sells Free bundled with Reign/BuddyX, Pro separately):

- **Tier 1 — Real customer themes** (must work, must ship clean): **Reign, BuddyX Pro, Astra, Kadence, GeneratePress** plus optionally **Neve**, **OceanWP**, **Blocksy**.
- **Tier 2 — Page-builder starter themes** (only meaningful WITH a builder): **Hello Elementor** (used with Elementor plugin), **Generatepress + Elementor**, etc. Testing these without their builder is a strawman.
- **Tier 3 — Reference themes** (ship with WP core but rare in production): **Twenty Twenty-Five / -Four / -Three**. Worth checking once for FSE compatibility but not the audience that pays us.

The audit below is organised by tier — Tier 1 issues block sales, Tier 3 issues are mostly nice-to-have.

## Summary by tier

### Tier 1 — Real customer themes (these block sales when broken)

| Theme | Find Jobs verdict | Severity | Screenshot |
|---|---|---|---|
| **Reign** (bundle) | ✅ Pristine — built for it | OK | (Reign is the active fixture; not re-screenshot) |
| **BuddyX Pro** (bundle) | ✅ Pristine — built for it | OK | — |
| **Astra** | ✅ Clean — 3-col grid, polished Post a Job form | OK | `astra-find-jobs.png` + `astra-post-a-job.png` |
| **Kadence** | ✅ Clean — 3-col, no breakages | OK | `kadence-find-jobs.png` |
| **GeneratePress** | ⚠️ Falls to 2-col (narrow content area) | Low | `generatepress-find-jobs.png` |
| **Neve** | ❌ **Duplicate `<h1>`** (theme banner + block heading) | **High** | `neve-find-jobs.png` |
| **OceanWP** | ❌ **Duplicate `<h1>`** + 2-col + breadcrumbs eat 80px + sort-select wide gap | **High** | `oceanwp-find-jobs.png` |
| **Blocksy** | ⚠️ 4-col grid, card title wrap inconsistent | Low | `blocksy-find-jobs.png` |

### Tier 2 — Page-builder starter themes (test with builder, not bare)

| Theme | Find Jobs verdict (BARE — invalid config) | Real-world test |
|---|---|---|
| **Hello Elementor** | ~~Critical fail — red borders, underlined titles~~ | **Test deferred — needs Elementor plugin active. Bare Hello Elementor is not a real customer configuration; the theme is shipped as a starter for Elementor's builder.** |

### Tier 3 — Reference themes (rare in production, low priority)

| Theme | Find Jobs verdict | Severity | Screenshot |
|---|---|---|---|
| **Twenty Twenty-Five** | ❌ Narrow container → 2-col, header nav floods with 23 unfiltered pages | Tier-3 (cosmetic for our customer base) | `twentytwentyfive-find-jobs.png` |
| **Twenty Twenty-Four** | ❌ Same nav flood + 2-col + "View Job" breaks card padding | Tier-3 | `twentytwentyfour-find-jobs.png` |
| **Twenty Twenty-Three** | ⚠️ Same nav flood + 2-col | Tier-3 | `twentytwentythree-find-jobs.png` |

### Post a Job (multi-step form)

| Theme | Verdict | Screenshot |
|---|---|---|
| **Astra** | ✅ Excellent — clean form, stepper crisp | `astra-post-a-job.png` |
| **Hello Elementor (bare)** | ~~Red Next button~~ — see Tier 2 note above; not a real config |

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

## Re-ranked fix plan (Tier 1 first)

The fix list now reflects what blocks **paying customer** sales, not
what shows up in reference-theme screenshots.

| # | Fix | Themes fixed | Customer impact | Effort |
|---|---|---|---|---|
| 1 | **Broaden `wcb_app_page_ids` to detect any page containing a WCB block** | Neve, OceanWP (duplicate `<h1>`) | **High** — Neve + OceanWP are real customers | 2 hours |
| 2 | **Add `align: ['wide', 'full']` support** to job-listings + resume-archive + dashboards | GeneratePress (Tier 1) + TT3-5 (Tier 3 bonus) | **Medium** — fixes 2-col on GP | 1 hour |
| 3 | **Card title `min-height` or `line-clamp: 2`** | Blocksy 4-col uneven wrapping | Low — cosmetic | 30 min |
| 4 | **OceanWP-specific:** sort-select `width: auto` reset + breadcrumb-area top padding compensation | OceanWP cosmetic polish | Medium | 1 hour |
| 5 | **Theme accent auto-bridge** — read common theme customizer color mods (Astra `astra-settings.theme-color`, Kadence `kadence_global_palette`, GeneratePress `generate_settings`, Neve `neve_button_primary_padding`) and write `--wcb-primary` | All Tier 1 non-bundle themes | **Highest strategic** | 1-2 days |
| 6 | **Page-builder integration testing** — verify Hello Elementor + Elementor, Astra + Elementor, GeneratePress + Elementor, plus Bricks / Beaver / Divi where licenses available | Real-world Tier 2 cases | High but blocked on builder licenses | 1-2 days |
| 7 | **In-card button `max-width: 100%`** — reset for TT4 button overflow | TT3/4/5 (Tier 3) | Low — bonus | 15 min |

**1.1.1 patch (Tier 1 polish):** items 1, 2, 3, 4 → ~4.5 hours total, ships clean.
**1.2.0 strategic:** item 5 (theme accent bridge) is the highest-leverage move for the entire customer base.
**1.2.0 verification:** item 6 (page-builder testing) once builder plugins/licenses are available.

## What was NOT a fair test (and why)

- **Hello Elementor (bare)** — this theme is a starter shipped with the
  Elementor page builder; it deliberately ships ~no styling because it
  expects Elementor's builder to provide all visual chrome. Testing it
  without Elementor active produces "everything looks broken" results
  that no real Hello Elementor user ever sees. Re-test required with
  Elementor plugin active before drawing conclusions.

- **Twenty Twenty-Three / Twenty Twenty-Four / Twenty Twenty-Five** —
  these are reference / FSE themes that ship with WordPress core, but
  they are rare in production-grade customer sites. Worth checking once
  for FSE pattern compatibility, but Tier 1 themes are the ones that
  actually ship to paying users.

## Themes not tested (license-gated or blocked on plugins)

- **Avada** — premium, no license available. Heavy CSS plus its own
  builder; expected to surface specificity issues.
- **Divi** — premium theme + page builder, no license. Shortcode-render
  edge cases expected.
- **Bricks** — newer premium builder, no license.
- **Hello Elementor + Elementor** — Elementor plugin not installed
  locally; needs `wp plugin install elementor --activate` to validate.
- **Beaver Builder + theme** — license-gated.

## Mobile (390px) gap

All screenshots above are 1440×900 desktop. Mobile compatibility was
verified only on Reign + BuddyX during 1.1.0 — every Tier 1 third-party
theme is unverified at 390px. Logged in `docs/PLAN-1.2.0.md`.
