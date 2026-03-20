# WP Career Board — Sidebar Widget Blocks + Hero Search Blocks

**Date:** 2026-03-20
**Status:** Approved
**Scope:** wp-career-board (free) + wp-career-board-pro

---

## 1. Goal

Add 5 new static sidebar-widget blocks to the free plugin and 4 to the pro plugin, so site owners can drop premium-looking job/candidate/company widgets and full-width search heroes onto any page — homepage, blog sidebar, landing pages — without building anything custom.

Additionally: extend the existing `wcb/featured-jobs` block with missing attributes, audit all i18n strings for consistency, and rename the two dashboard pages to shorter navigation-friendly titles.

---

## 2. Architecture

### 2.1 Plugin split

| Block | Plugin | Text domain |
|-------|--------|-------------|
| `wcb/recent-jobs` | wp-career-board | `wp-career-board` |
| `wcb/featured-jobs` (extend) | wp-career-board | `wp-career-board` |
| `wcb/job-stats` | wp-career-board | `wp-career-board` |
| `wcb/job-search-hero` | wp-career-board | `wp-career-board` |
| `wcb/open-to-work` | wp-career-board-pro | `wp-career-board-pro` |
| `wcb/featured-companies` | wp-career-board-pro | `wp-career-board-pro` |
| `wcb/featured-candidates` | wp-career-board-pro | `wp-career-board-pro` |
| `wcb/resume-search-hero` | wp-career-board-pro | `wp-career-board-pro` |

### 2.2 Block structure (per block)

```
blocks/{name}/
├── block.json      # apiVersion: 3, category: widgets, no viewScriptModule
├── render.php      # Static PHP — no wp_interactivity_state()
├── index.js        # Editor InspectorControls only
└── style.css       # Design tokens from theme.json
```

No `view.js`, no `viewScriptModule`, no `data-wp-interactive` — all blocks are fully static server-rendered.

### 2.3 Pro block guard

Every pro `render.php` opens with:

```php
if ( ! class_exists( '\WCB\Pro\...' ) ) {
    return;
}
```

Returns silently if pro plugin is inactive.

### 2.4 Block registration

Each plugin registers its own blocks via `register_block_type_from_metadata()` in its bootstrap, matching the existing pattern.

---

## 3. Free Blocks

### 3.1 `wcb/recent-jobs`

**Query:** `wcb_job`, `post_status = publish`, `orderby = date DESC`.

**Attributes:**

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `count` | integer | 5 | Number of jobs to display (3, 5, or 10) |
| `title` | string | `''` | Section heading override (empty = use block default) |
| `showViewAll` | boolean | true | Show "View all →" link |
| `viewAllUrl` | string | `''` | URL for "View all →" (defaults to jobs archive page) |

**Card anatomy:**
- Company favicon/logo (16×16, fallback to site icon) + company name
- Job title linked to `get_permalink()`
- Location badge + job type badge
- "Posted X days ago" — uses `human_time_diff()`

### 3.2 `wcb/featured-jobs` (extend existing)

The block already exists. Add three missing attributes to `block.json` and `index.js`:

| New attribute | Type | Default |
|---------------|------|---------|
| `title` | string | `''` |
| `showViewAll` | boolean | true |
| `viewAllUrl` | string | `''` |

Update `render.php` to consume the new attributes. No structural changes to the query or card layout.

### 3.3 `wcb/job-stats`

**Query:** Three separate `$wpdb` or `wp_count_posts()` calls for totals.

**Attributes:**

| Attribute | Type | Default |
|-----------|------|---------|
| `showJobs` | boolean | true |
| `showCompanies` | boolean | true |
| `showCandidates` | boolean | true |

**Display:** Horizontal strip of up to 3 stat items. Each item: icon (SVG inline) + large number + label. Responsive: wraps to single column at ≤640px.

Labels (translatable):
- Jobs: `__( 'Jobs', 'wp-career-board' )`
- Companies: `__( 'Companies', 'wp-career-board' )`
- Candidates: `__( 'Candidates', 'wp-career-board' )`

### 3.4 `wcb/job-search-hero`

**Description:** Combines a keyword search input with optional filter dropdowns in one block. Designed for full-width hero sections (inside a Cover or Group block) or as a standalone search widget.

**Attributes:**

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `layout` | string | `'horizontal'` | `'horizontal'` or `'vertical'` |
| `placeholder` | string | `''` | Search input placeholder (empty = translated default) |
| `buttonLabel` | string | `''` | Search button label (empty = translated default) |
| `showCategoryFilter` | boolean | true | Show job category dropdown |
| `showLocationFilter` | boolean | true | Show location dropdown |
| `showJobTypeFilter` | boolean | true | Show job type dropdown |

**Behaviour:** Submitting the form navigates to the jobs archive page with `?wcb_search=`, `?wcb_category=`, `?wcb_location=`, `?wcb_job_type=` query params — same params consumed by the existing `wcb/job-listings` and `wcb/job-filters` blocks. No JS required; pure HTML form GET.

**Layout — horizontal:** Single flex row. Search input grows to fill available space. Filter dropdowns at fixed width. Button at end. Wraps gracefully at ≤768px.

**Layout — vertical:** Stacked form. Full-width input, full-width selects, full-width button.

**Styling:** Inherits text/background color from parent block. No hardcoded background colors. Uses `--wcb-primary` token for button.

---

## 4. Pro Blocks

### 4.1 `wcb/open-to-work`

**Query:** `wcb_resume`, `post_status = publish`, meta `_wcb_open_to_work = 1` AND `_wcb_resume_public = 1`, `orderby = date DESC`.

**Attributes:** `count` (default 5), `title`, `showViewAll`, `viewAllUrl`.

**Card anatomy:**
- 40px avatar (BuddyPress avatar or CSS initials fallback)
- Display name linked to resume single
- Headline / current job title
- Top 3 skill pills (`wcb_resume_skill` terms)
- Green "Open to Work" dot indicator

### 4.2 `wcb/featured-companies`

**Query:** `wcb_company`, `post_status = publish`, meta `_wcb_featured = 1`, `orderby = date DESC`.

**Attributes:** `count` (default 5), `title`, `showViewAll`, `viewAllUrl`.

**Card anatomy:**
- 40px company logo (fallback: coloured initial)
- Company name linked to company profile
- Industry / tagline (from `_wcb_company_tagline`)
- "N open roles" count badge (live `wp_count_posts` filtered by company)

### 4.3 `wcb/featured-candidates`

**Query:** `wcb_resume`, `post_status = publish`, meta `_wcb_featured = 1` AND `_wcb_resume_public = 1`, `orderby = date DESC`.

**Attributes:** `count` (default 5), `title`, `showViewAll`, `viewAllUrl`.

**Card anatomy:** Same as `wcb/open-to-work` but without the Open to Work indicator.

### 4.4 `wcb/resume-search-hero`

**Mirror of `wcb/job-search-hero` for the resume/candidate search vertical.**

**Attributes:**

| Attribute | Type | Default |
|-----------|------|---------|
| `layout` | string | `'horizontal'` |
| `placeholder` | string | `''` |
| `buttonLabel` | string | `''` |
| `showSkillFilter` | boolean | true |
| `showLocationFilter` | boolean | true |
| `showOpenToWorkFilter` | boolean | false |

**Behaviour:** Submits GET to resume archive page with `?wcb_resume_search=`, `?wcb_resume_skill=`, `?wcb_open_to_work=` — same params consumed by `wcb/resume-archive`.

---

## 5. Design System

All new blocks use existing CSS custom properties from `theme.json`:

```css
--wcb-primary          /* button background, badge accent */
--wcb-bg-subtle        /* card background */
--wcb-bg-hover         /* card hover state */
--wcb-border           /* card border */
--wcb-text             /* body text */
--wcb-text-muted       /* secondary text, meta */
--wcb-radius           /* border-radius */
--wcb-shadow           /* card box-shadow */
```

No new design tokens introduced. Card hover: `transform: translateY(-1px)` + `box-shadow` lift — same pattern as existing job cards.

Each block's `style.css` scoped to its own wrapper class (e.g. `.wcb-recent-jobs`, `.wcb-job-stats`).

---

## 6. i18n Audit

Scan both plugins for:

1. **Missing text domain** — `__( 'string' )` with no second arg
2. **Wrong text domain** — `'wp-career-board'` used in pro files, or `'wp-career-board-pro'` used in free files
3. **Unescaped i18n** — `_e()` instead of `esc_html_e()` for HTML output
4. **Hardcoded JS strings** — `view.js`/`index.js` files that output English without `@wordpress/i18n`'s `__`
5. **Missing strings** — button labels, aria-labels, empty-state messages, error messages, placeholder text not wrapped in i18n functions

Fix all findings in-place. No string changes — only wrapping and correct text domains.

---

## 7. Page Name Updates

| Current title | New title | Page ID |
|---------------|-----------|---------|
| Employer Dashboard | Hiring | 10 |
| Candidate Dashboard | Career | 11 |

Update via `wp_update_post()` in a one-time migration or directly via WP-CLI. Update Reign navigation menu labels to match. No PHP/JS hardcodes these page titles — blocks use `get_the_title()` or the `wcb_settings` page ID option — so no code changes required beyond the page title itself.

---

## 8. Out of Scope

- Model site content setup (separate spec)
- Any interactive (JS-driven) behaviour in the new widget blocks
- New design tokens
- Pro features in the free plugin

---

## 9. File Checklist

**wp-career-board/blocks/**
- `recent-jobs/block.json` + `render.php` + `index.js` + `style.css` — new
- `featured-jobs/block.json` + `render.php` + `index.js` — extend (3 new attributes)
- `job-stats/block.json` + `render.php` + `index.js` + `style.css` — new
- `job-search-hero/block.json` + `render.php` + `index.js` + `style.css` — new

**wp-career-board-pro/blocks/**
- `open-to-work/block.json` + `render.php` + `index.js` + `style.css` — new
- `featured-companies/block.json` + `render.php` + `index.js` + `style.css` — new
- `featured-candidates/block.json` + `render.php` + `index.js` + `style.css` — new
- `resume-search-hero/block.json` + `render.php` + `index.js` + `style.css` — new

**i18n:** any PHP/JS files with findings from the audit

**Page titles:** WP-CLI or migration script
