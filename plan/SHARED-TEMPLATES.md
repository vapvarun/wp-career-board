# Shared template architecture — 8 major surfaces

Generated 2026-05-13 via task #76. Drives the migration from
"copy-paste per block + match drift by hand" to "one canonical
partial per shared building block."

## Surfaces in scope (5463 LOC today)

| # | Surface | Block | render.php LOC | Plugin |
|---|---|---|---|---|
| 1 | Find Jobs | `wp-career-board/job-listings` | 723 | Free |
| 2 | Companies | `wp-career-board/company-archive` | 468 | Free |
| 3 | Find Candidates | `wcb/resume-archive` | 359 | Pro |
| 4 | Single Job | `wp-career-board/job-single` | 1022 | Free |
| 5 | Single Company | `wp-career-board/company-profile` | 372 | Free |
| 6 | Single Resume | `wcb/resume-single` | 584 | Pro |
| 7 | Candidate dashboard | `wp-career-board/candidate-dashboard` | 992 | Free |
| 8 | Employer dashboard | `wp-career-board/employer-dashboard` | 943 | Free |

## What's already shared

| Layer | Where | Notes |
|---|---|---|
| Tokens | `assets/css/frontend-tokens.css` | `--wcb-primary / --wcb-on-primary / --wcb-radius-* / --wcb-space-* / --wcb-shadow-* / --wcb-success / --wcb-warning / --wcb-danger`. Theme-override-aware via `var(--wcb-primary, fallback)`. |
| Buttons | `assets/css/wcb-ui.css` | `.wcb-cbtn / --ghost / --primary / --danger` at (0,3,1) parent-prefix specificity. |
| Bookmark | `assets/css/wcb-ui.css` | `.wcb-bookmark-btn` absolute top-right + `.wcb-bookmarked` filled state. |
| Empty state | `assets/css/wcb-ui.css` | `.wcb-empty-state` + `.wcb-empty-state__icon / __title / __body`. |
| Load more | `assets/css/frontend-components.css` | `.wcb-load-more-wrap / .wcb-load-more-btn` + `.wcb-load-more-loading` toggle. |
| Card title | `assets/css/wcb-ui.css` | `.wcb-card-title / .wcb-ca-name / .wcb-ra-card-name` typography at (0,2,1). |
| Archive layout | `assets/css/wcb-ui.css` | `.wcb-archive-layout` 2-col grid (filter sidebar + main). |
| Theme defense | `assets/css/frontend-components.css` | Hide theme sidebar / entry-title / button cascade on plugin pages. |
| Dashboard panel | `blocks/{candidate,employer}-dashboard/styles/main.css` | `.wcb-view-panel.wcb-view-active` card chrome (c613654). |
| Accessibility | `assets/css/wcb-ui.css` | `.wcb-hidden` Interactivity API utility + `prefers-reduced-motion` rule. |

## What's duplicated (the actual problem)

### HTML (PHP)

| Pattern | Copies | Surfaces |
|---|---|---|
| Search + sort row markup | 3 | 3 archives |
| Listings toolbar (results count + view-switcher + alert-me slot) | 3 | 3 archives |
| Archive layout shell + filter panel | 3 | 3 archives |
| Card outer wrapper + bookmark button placement | 3 | 3 archive cards |
| Load more `<div class="wcb-load-more-wrap">` markup | 3 | 3 archives |
| Empty state card markup | ~6 | 3 archives + Saved tabs on both dashboards |
| Single hero (logo + title + subtitle + meta chips + save) | 3 | 3 single pages |
| Single 2-col layout (main + sidebar) | 3 | 3 single pages |
| Sidebar card chrome (Details / About the Company / etc.) | ~10 | 3 single pages × ~3 sidebar cards each |
| Theme defense markup (`.wcb-{type}-page` body class) | 3 | 3 single pages |
| Dashboard sidebar nav (with `MY ACTIVITY`, `MY SAVES`, `ACCOUNT` groups) | 2 | 2 dashboards |
| Dashboard view-panel + page-header per tab | ~20 | 2 dashboards |
| Bookmark row markup (Saved tabs - jobs/companies/resumes) | 6 | 2 dashboards × 3 saved types |

### JS (view.js)

| Pattern | Copies | Surfaces |
|---|---|---|
| `setGridLayout / setListLayout` + localStorage persistence | 3 | 3 archives |
| `updateSearch` (debounced) | 3 | 3 archives |
| `changeSort` action | 3 | 3 archives |
| Filter URL builder (`?page= per_page= search= orderby= order= industry[]= ...`) | 3 | 3 archives |
| `loadMore` action with `state.page++` + fetch + append | 3 | 3 archives |
| Reactive `resultsLabel / hasMore / visibleCount` getters | 3 | 3 archives |
| Bookmark toggle (POST /wcb/v1/{kind}/{id}/bookmark) | 3 | 3 archives + 3 singles + dashboard rows = 9+ usages |
| Tab routing via `location.hash` | 2 | 2 dashboards |
| Empty/loading/error state plumbing | ~20 | 2 dashboards × ~10 tabs each |
| Saved-X loader (Saved Jobs / Companies / Resumes) | 6 | 2 dashboards × 3 saved types |

## Extraction targets

### A. PHP partials (live in `templates/parts/`)

Each partial accepts a typed args array and renders a `<div>` /
`<section>` with the canonical markup. Each archive / single /
dashboard `include`s the partials it needs instead of hand-copying.

```
wp-career-board/templates/parts/
├── archive-toolbar.php          (#1, #2, #3)
├── archive-layout.php           (#1, #2, #3)
├── archive-card-shell.php       (#1, #2, #3)
├── archive-empty-state.php      (#1, #2, #3 + Saved tabs in #7, #8)
├── archive-load-more.php        (#1, #2, #3)
├── single-hero.php              (#4, #5, #6)
├── single-layout.php            (#4, #5, #6)
├── single-sidebar-card.php      (#4, #5, #6 × ~3 sidebar cards each)
├── dashboard-sidebar.php        (#7, #8)
└── dashboard-panel.php          (#7, #8 × ~20 panels)
```

Pro reuses the same partials via `include WCB_DIR . 'templates/parts/X.php';`
since Free is always loaded when Pro is active (dependency guard).

### B. JS modules (live in `assets/js/lib/`)

ES modules exporting helpers each block's view.js imports.

```
wp-career-board/assets/js/lib/
├── archive-store-mixin.js       (#1, #2, #3)
│   - setGridLayout / setListLayout
│   - layout localStorage round-trip
│   - updateSearch (debounced 250ms)
│   - changeSort (resets to page 1)
│   - buildUrl(page, baseFilters, activeFilters)
│   - loadMore action
│   - resultsLabel getter
│
├── dashboard-store-mixin.js     (#7, #8)
│   - state.isTab{Name} getters
│   - tab routing via hash + history.replaceState
│   - switchTo{Tab} actions
│   - Saved-X loaders (jobs/companies/resumes)
│
└── bookmark-action.js           (used everywhere)
    - bookmarkToggle(kind, id, btnEl)
    - Optimistic UI + rollback
    - POST /wcb/v1/{kind}/{id}/bookmark
```

Pro view.js files import via `/wp-content/plugins/wp-career-board/assets/js/lib/...`.

### C. PHP helper class (live in `core/`)

```
wp-career-board/core/class-archive-context.php
```

A small DTO + factory that each archive render.php constructs to
hand to the partials. Captures `$kind`, `$singular`, `$plural`,
`$pluralTitle`, `$post_type`, `$rest_base`, `$state_namespace`,
`$show_alert_button` (Pro-driven), `$show_view_switcher` —
everything the partials read. Eliminates "pass 9 separate args to
6 different partials" boilerplate.

## Per-surface migration

### Archives (#1, #2, #3)

Each archive's render.php today carries 300-700 LOC of largely
copied markup. After migration:

```php
// blocks/job-listings/render.php (truncated example)
$ctx = new ArchiveContext( /* args from $attributes */ );
$state = build_jobs_state( $attributes );
wp_interactivity_state( 'wcb-job-listings', $state );

include WCB_DIR . 'templates/parts/archive-toolbar.php';
include WCB_DIR . 'templates/parts/archive-layout.php';
// archive-layout.php internally includes filter sidebar +
// archive-card-shell.php in a loop + archive-load-more.php +
// archive-empty-state.php.
```

Estimated LOC reduction per archive: ~250 LOC -> ~80 LOC.
Total archive LOC: 1550 -> ~500.

### Single pages (#4, #5, #6)

Each single page has hero + main content + sidebar cards. After
migration, hero + sidebar cards come from partials; only the
domain-specific content (e.g. "About This Role" + Requirements +
Apply panel for jobs, Work Experience + Education + Skills for
resumes) stays per-block.

Estimated LOC reduction: 1978 -> ~1000.

### Dashboards (#7, #8)

Each dashboard's 20 tabs follow the same shape: page-header h1 +
optional loading state + populated list panel + empty state. After
migration, every tab becomes:

```php
$panel = new DashboardPanel( /* id, title, state_key */ );
$panel->open();
include WCB_DIR . 'templates/parts/archive-empty-state.php';
// tab-specific list rows
$panel->close();
```

Estimated LOC reduction: 1935 -> ~900.

## Phased rollout

Ship-order picked so each phase is independently testable and
reversible. No phase touches more than ~3 files in the same plugin.

| Phase | What | Affected blocks | Risk | Days |
|---|---|---|---|---|
| 0 | Create `templates/parts/` + `assets/js/lib/` dirs; add `ArchiveContext` DTO | none (additive) | nil | 0.5 |
| 1 | Extract `archive-empty-state.php` + `archive-load-more.php` | 3 archives + 6 dashboard tabs | low | 0.5 |
| 2 | Extract `archive-toolbar.php` | 3 archives | medium | 1 |
| 3 | Extract `archive-card-shell.php` + `archive-layout.php` | 3 archives | medium | 1 |
| 4 | Extract `archive-store-mixin.js` + `bookmark-action.js` | 3 archives' view.js | medium-high | 1.5 |
| 5 | Extract `single-hero.php` + `single-layout.php` + `single-sidebar-card.php` | 3 singles | medium | 1.5 |
| 6 | Extract `dashboard-sidebar.php` + `dashboard-panel.php` | 2 dashboards | medium | 1.5 |
| 7 | Extract `dashboard-store-mixin.js` | 2 dashboards' view.js | high | 2 |

Total: ~9.5 days of focused work for ~50% LOC reduction across
the 8 surfaces, eliminating the per-block parity drift class
of bugs permanently.

## Guardrails

- **One render at a time** ([[feedback_test_one_render_at_a_time]]): every phase verified on an isolated test page, not the dev "Test:" combo pages.
- **Browser-verify per surface**: Playwright snapshot each surface before/after each phase. Visual regression = phase rolled back, no merge.
- **WPCS + PHPStan green** on every phase. No `--no-verify` bypass.
- **wppqa baseline preserved**: re-run `wppqa_check_plugin_dev_rules / a11y / rest_js_contract` after each phase, must stay at 0 failures.
- **Pro stays in lockstep**: Pro's `WCBP_VERSION == WCB_VERSION` invariant means each Free phase that ships needs a matching Pro tag, even if Pro itself didn't change.
- **No new !important** per [[feedback_no_important_in_plugin_css]]. Partials inherit the existing (0,2,1)-(0,3,1) selector pattern.
- **Theme-defense rules don't move** per [[feedback_wbcom_themes_not_integrations]]. They stay in `frontend-components.css` so Reign/BuddyX/BuddyX Pro integration shims remain near their cause.

## Open questions

1. **Pro plugin loading Free partials** — Pro's `include WCB_DIR . 'templates/parts/X.php'` assumes `WCB_DIR` is defined at the moment Pro's block render runs. It is (Free boots first). But hard-coding `WCB_DIR` in Pro creates a Free dependency that's already implicit; surface it explicitly via a constant alias?
2. **Block patterns vs. partials** — Should some of these (especially the archive layout) ship as a Gutenberg block pattern too, so a site builder can compose without writing PHP? Out of scope for the refactor itself but worth thinking about for v1.4.x.
3. **REST endpoint shape unification** — Phase 4 assumes all 3 archives use the same `?page= per_page= search= orderby= order=` REST contract. Today the resume archive endpoint accepts `?skill=` while Jobs accepts `?type= experience= board= ...`. The mixin's URL builder should accept a per-archive `filterMap` so the shared core handles pagination/sort/search and each archive ships its own filter mapping.
