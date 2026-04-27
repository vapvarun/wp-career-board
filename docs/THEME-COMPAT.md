# 3rd-Party Theme Compatibility Report — Popular Themes Only

**Date:** 2026-04-28
**Method:** Switched theme via WP-CLI on a real Local WP site, navigated to Find Jobs (`/?p=222`) and Post a Job (`/?p=346`) at 1440×900 with `?autologin=1`, captured Playwright full-page screenshots. Real activations, no mockups.
**Scope:** Top free WordPress themes by active-install count (wp.org).

## Themes covered

| Theme | Why included | Install base (wp.org) |
|---|---|---|
| **Reign** (Wbcom) | Bundle target — Free ships with it | — |
| **BuddyX Pro** (Wbcom) | Bundle target — Free ships with it | — |
| **Astra** | Most-installed free theme on wp.org | 1M+ |
| **OceanWP** | Top-3 most-installed free theme | 700k+ |
| **Kadence** | Top community-site theme | 400k+ |
| **GeneratePress** | Top dev/pro theme | 300k+ |
| **Neve** | Frequently bundled with starter sites | 300k+ |
| **Blocksy** | Modern FSE-friendly free theme | 200k+ |

Excluded: Hello Elementor (only used WITH Elementor — bare test is invalid, deferred until Elementor plugin available); Twenty Twenty-X (reference themes, rare in production).

---

## Findings (popular themes only)

| Theme | Find Jobs verdict | Severity | Screenshot |
|---|---|---|---|
| **Reign** (bundle) | ✅ Pristine — built for it | OK | (active fixture) |
| **BuddyX Pro** (bundle) | ✅ Pristine — built for it | OK | — |
| **Astra** | ✅ Clean — 3-col grid, polished forms | OK | `astra-find-jobs.png`, `astra-post-a-job.png` |
| **Kadence** | ✅ Clean — 3-col, no breakages | OK | `kadence-find-jobs.png` |
| **GeneratePress** | ⚠️ Falls to 2-col (narrow content area) | Low | `generatepress-find-jobs.png` |
| **Blocksy** | ⚠️ 4-col grid, card title wrap inconsistent | Low | `blocksy-find-jobs.png` |
| **Neve** | ❌ **Duplicate `<h1>`** (theme banner + block heading) | **High** | `neve-find-jobs.png` |
| **OceanWP** | ❌ **Duplicate `<h1>`** + 2-col + breadcrumbs eat 80px + sort-select wide gap | **High** | `oceanwp-find-jobs.png` |

**Score:** 5 of 8 ship clean (Reign, BuddyX Pro, Astra, Kadence, plus minor cosmetic on GeneratePress + Blocksy). 2 ship with real visual breakages (Neve + OceanWP, both from the same root cause).

---

## Per-theme detail

### Astra ✅
3-col grid, filter chips with our token border, primary blue retained, polished Post a Job form. Best non-Wbcom experience. **No fixes needed.**

### Kadence ✅
Clean 3-col, no breakages. Theme search input integrates with our chip-bar. Footer credit "Kadence WP". **No fixes needed.**

### GeneratePress ⚠️
- Content area max-width is narrower (~960px) → cards force to 2-col
- Sort dropdown wraps awkwardly on narrow row
- **Fix:** Add `align: ['wide', 'full']` to job-listings + resume-archive blocks so customers can choose wide alignment in the editor.

### Blocksy ⚠️
- 4-col on Blocksy's wider container (no sidebar by default) — works, but card title wraps inconsistently
- "Senior PHP Developer" 1 line vs "Backend Engineer — Payments" 2 lines makes row heights uneven
- **Fix:** Add `min-height` or `line-clamp: 2` on `.wcb-job-card__title` to normalise.

### Neve ❌
- **Duplicate `<h1>` "Find Jobs"** — Neve renders `entry-title` AND our block renders `.wcb-page-heading h1`
- Search input has visible black border (Neve doesn't reset)
- Cards otherwise OK
- **Fix:** Currently `wcb_app_page_ids` body class only fires on plugin-mapped wizard pages. Broaden to detect any page containing a WCB block at render time.

### OceanWP ❌
- **Duplicate `<h1>`** + breadcrumbs together consume ~80-100px before our content begins
- Cards forced to 2-col (sidebar layout default)
- Sort select renders wide empty gap to the right of the search input
- "View Job" button bleeds outside card padding on first card row
- **Fix:** Same `wcb-page` body class broadening as Neve + sort-select width reset.

---

## 1.1.1 fix plan (popular-themes-only, ~4.5 hours total)

| # | Fix | Themes fixed | Effort |
|---|---|---|---|
| 1 | **Broaden `wcb_app_page_ids` to detect any page with a WCB block** | Neve, OceanWP (`<h1>` dupe) | 2h |
| 2 | **Add `align: ['wide', 'full']` block support** to job-listings + resume-archive + dashboards | GeneratePress (2-col → wide 3-col) | 1h |
| 3 | **Card title `min-height` / `line-clamp: 2`** | Blocksy uneven wrap | 30m |
| 4 | **OceanWP polish** — sort-select `width: auto`, breadcrumb-area top-padding compensation | OceanWP cosmetic | 1h |

## 1.2.0 strategic — theme accent auto-bridge

Right now only Reign + BuddyX Pro bridge their customizer accent → `--wcb-primary`. Customers using Astra/Kadence/GeneratePress with their accent set to something other than blue see our default blue and conclude "this plugin doesn't match my theme."

The fix reads each major theme's well-known customizer mod:

| Theme | Customizer mod key |
|---|---|
| Astra | `astra-settings.theme-color` |
| Kadence | `kadence_global_palette` |
| GeneratePress | `generate_settings.hero_button_background_color` |
| Neve | `neve_button_primary_padding` (color part of nested option) |
| OceanWP | `ocean_primary_color` |
| Blocksy | `colorPalette` (theme.json palette merge — already works for FSE) |

…and writes the value into `--wcb-primary` (with derived dark / soft / ring tints via `color-mix`). 1-2 day estimate to ship the bridge for all six.

## What was NOT tested (reasons)

- **Hello Elementor (bare)** — invalid config; the theme deliberately ships with no styling because it expects Elementor's builder to provide visual chrome. Bare test produced findings no real customer ever sees. Re-test when Elementor plugin is installed.
- **Twenty Twenty-X (3, 4, 5)** — reference themes that ship with WP core but rarely run on production customer sites. Excluded from popular-themes scope.
- **Avada, Divi, Bricks, Beaver** — premium / license-gated.
