# CSS Dedup Audit — 2026-05-06

> Pass through the plugin's CSS to find selectors defined in multiple files,
> identify true duplicates vs intentional component-specific variants, and
> consolidate the duplicates into shared global rules.

## Summary

- **3 dedup commits**, **-225 lines** removed from block stylesheets
- **+90 lines** added to `assets/css/frontend-components.css` as canonical
  global rules
- Net reduction: **-135 lines** of CSS, with one source of truth per
  consolidated primitive

## Hoisted to `assets/css/frontend-components.css`

### `.wcb-hidden` + `.wcb-shown` — visibility utilities

8 identical copies removed. Single canonical definition in the global
components stylesheet. Used everywhere via Interactivity API
`data-wp-class--wcb-hidden`.

Commit: `1424b99`.

### `.wcb-load-more-wrap` + `.wcb-load-more-btn` + `.wcb-loading-label`

3 near-identical copies across `company-archive`, `company-profile` (open
positions tab), and `job-listings` blocks. The job-listings copy had drift
(0.65 rem padding instead of `--wcb-space-md`, plus the legacy
`var(--wcb-border, var(--wp--preset--color--wcb-border))` fallback chain
that's no longer needed). Standardised on the cleaner company-archive
shape.

Commit: `0f138d4`.

### `.wcb-panel-header` + `.wcb-panel-body`

Identical 6-line / 3-line definitions across `candidate-dashboard`,
`employer-dashboard`, and `job-single`. Hoisted.

Commit: `26c491a`.

## Reviewed but not deduped

These selectors appear in multiple files but have intentional variants:

| Selector | Variants | Reason to keep separate |
|---|---|---|
| `.wcb-panel-title` | `1.375rem` (job-single) vs `text-base` (dashboards) | Job-single uses larger panel titles in apply modal; dashboards use smaller titles in overview cards |
| `.wcb-panel-link` | `--wcb-blue` (candidate-dashboard) vs `--wcb-primary` (employer-dashboard) | Candidate dashboard uses brand-blue accent specifically; employer uses primary |
| `.wcb-status-badge` | Different paddings + colors per block | Each block tints status badges differently (jobs vs applications vs candidates) |
| `.wcb-stat-label` | Defined in admin + 3 frontend blocks | Admin variant has different colour/sizing |
| `.wcb-modal-*` (overlay/title/msg/actions) | `wcb-confirm-modal.css` (frontend) + `admin.css` (admin) | Frontend uses different z-index stack than admin metabox modals; merging would risk overlay regressions |

## admin.css / admin-rtl.css duplicates

Many `wcb-*` selectors appear in BOTH `assets/css/admin.css` and
`assets/css/admin-rtl.css`. These are NOT true duplicates — `admin-rtl.css`
is the auto-built RTL counterpart of `admin.css` (compiled output, not
source). The `wp_style_add_data($handle, 'rtl', 'replace')` enqueue path
swaps in the RTL file when the site direction is RTL. Both files need the
same selectors.

## Cross-component primitives that already live globally

These were correctly placed in `frontend-components.css` from the start of
the recent layout work and didn't need dedup:

- `.wcb-field-group` / `.wcb-field-row` / `.wcb-field-label` /
  `.wcb-field-input` / `.wcb-field-textarea` / `.wcb-field-select` —
  hoisted in `167d97e`
- `.wcb-listings-header` / `.wcb-search-sort-row` / `.wcb-search-wrap` /
  `.wcb-search-icon` / `.wcb-listings-search` / `.wcb-sort-select` —
  hoisted in `d1ac670`
- `.wcb-archive-shell` (canonical container) — `9ab4347`
- Theme-link defence + theme-button defence — `8b3100c`, `df2c57b`,
  `8448dba`

## 1.2.0 update — Chevron SVG → Lucide attribute (2026-05-15)

The hand-rolled inline SVG chevrons in `blocks/job-listings/render.php` and `blocks/company-archive/render.php` were replaced with `<i data-lucide="chevron-down">` (commit `e5b7020`). This is not a CSS dedup — it is an HTML markup change — but is noted here because the inline SVG contained embedded CSS stroke/fill values. Moving to Lucide data-attribute rendering eliminates those embedded values and keeps icon styling under the shared Lucide CSS token. No block stylesheet changes were needed. Basecamp 9891577445.

## Recommended follow-ups (not in this pass)

1. **Audit `.wcb-status-badge` and `.wcb-stat-label`** more carefully —
   the per-block variants might be unintentional drift rather than
   deliberate design choices. Worth a separate review pass with the
   designer.

2. **Decide if `.wcb-modal-*` should consolidate** — currently the admin
   modal styles in `wcb-confirm-modal.css` (frontend) overlap
   significantly with the metabox modal styles in `admin.css`. Could
   share a base class with admin-only / frontend-only modifiers.

3. **Token audit** — there are still some hardcoded hex colours in block
   stylesheets (`#0f172a`, `#1e293b`, `#e2e8f0`, etc.) used as fallbacks.
   These were left for safety when `--wcb-*` tokens are unavailable, but
   most sites now have the tokens. A future pass could standardise the
   fallback shape (or drop fallbacks now that the tokens stylesheet is
   guaranteed to load on every WCB page).
