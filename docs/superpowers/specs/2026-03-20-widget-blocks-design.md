# WP Career Board — Sidebar Widget Blocks + Hero Search Blocks

**Date:** 2026-03-20
**Status:** Approved
**Scope:** wp-career-board (free) + wp-career-board-pro

---

## 1. Goal

Add 4 new static sidebar-widget blocks to the free plugin and 4 to the pro plugin, so site owners can drop premium-looking job/candidate/company widgets and full-width search heroes onto any page — homepage, blog sidebar, landing pages — without building anything custom.

Additionally: extend the existing `wp-career-board/featured-jobs` block with missing attributes, audit all i18n strings for consistency, and rename the two dashboard pages to shorter navigation-friendly titles.

---

## 2. Architecture

### 2.1 Plugin split — block namespaces

Free blocks use the `wp-career-board/` namespace. Pro blocks use the `wcb/` namespace — matching the existing split already in the codebase.

| Block | Plugin | Namespace | Text domain |
|-------|--------|-----------|-------------|
| `wp-career-board/recent-jobs` | wp-career-board | `wp-career-board/` | `wp-career-board` |
| `wp-career-board/featured-jobs` (extend) | wp-career-board | `wp-career-board/` | `wp-career-board` |
| `wp-career-board/job-stats` | wp-career-board | `wp-career-board/` | `wp-career-board` |
| `wp-career-board/job-search-hero` | wp-career-board | `wp-career-board/` | `wp-career-board` |
| `wcb/open-to-work` | wp-career-board-pro | `wcb/` | `wp-career-board-pro` |
| `wcb/featured-companies` | wp-career-board-pro | `wcb/` | `wp-career-board-pro` |
| `wcb/featured-candidates` | wp-career-board-pro | `wcb/` | `wp-career-board-pro` |
| `wcb/resume-search-hero` | wp-career-board-pro | `wcb/` | `wp-career-board-pro` |

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

Only pro `render.php` files that directly instantiate a Pro module class require the guard. Pro blocks that do not call a Pro module class directly (e.g. simple `get_posts()` queries) do not need a guard — the pro plugin is already bootstrapped before blocks render.

For files that do call a module class:

```php
if ( ! class_exists( '\WCB\Pro\Modules\Resume\ResumeModule' ) ) {
    return;
}
```

Use the specific class name, not a generic `'\WCB\Pro\...'` placeholder.

### 2.4 Block registration

Each plugin registers its own blocks via `register_block_type_from_metadata()` in its bootstrap, matching the existing pattern.

### 2.5 Empty states

All queried blocks (sidebar widgets) return silently (`return;`) when the query returns zero results — matching the pattern in `wp-career-board/featured-jobs/render.php`. No placeholder or error message is rendered to visitors. The Gutenberg editor should show a placeholder label via the editor script.

---

## 3. Free Blocks

### 3.1 `wp-career-board/recent-jobs`

**Query:** `wcb_job`, `post_status = publish`, `orderby = date`, `order = DESC`.

**Attributes:**

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `count` | integer | 5 | Number of jobs to display (3, 5, or 10) |
| `title` | string | `''` | Section heading override (empty = block default) |
| `showViewAll` | boolean | true | Show "View all →" link |
| `viewAllUrl` | string | `''` | URL for "View all →" (empty = jobs archive page from `wcb_settings`) |

**Card anatomy:**
- Company logo — retrieved via `get_user_meta( $post->post_author, '_wcb_company_id', true )` → `get_the_post_thumbnail_url( $company_id, 'thumbnail' )`, 16×16, fallback to first letter initial. Pre-fetch all company IDs for the card set in one pass before the render loop to avoid N+1; for the default count of 5 items, a single `get_posts()` with `author__in` of the job authors is sufficient.
- Company name from `_wcb_company_name` post meta
- Job title linked to `get_permalink()`
- Location badge (`wcb_location` term) + job type badge (`wcb_job_type` term)
- "Posted X days ago" — uses `human_time_diff()`

**Empty state:** `return;` (silent, no output).

### 3.2 `wp-career-board/featured-jobs` (extend existing)

The block already exists at `blocks/featured-jobs/`. Add three missing attributes to `block.json` and `index.js`:

| New attribute | Type | Default |
|---------------|------|---------|
| `title` | string | `''` |
| `showViewAll` | boolean | true |
| `viewAllUrl` | string | `''` |

Update `render.php` to consume the new attributes. No structural changes to the existing query or card layout.

### 3.3 `wp-career-board/job-stats`

**Queries:**
- Jobs: `wp_count_posts( 'wcb_job' )->publish`
- Companies: `wp_count_posts( 'wcb_company' )->publish`
- Candidates: `wp_count_posts( 'wcb_resume' )->publish`

**Attributes:**

| Attribute | Type | Default |
|-----------|------|---------|
| `showJobs` | boolean | true |
| `showCompanies` | boolean | true |
| `showCandidates` | boolean | true |

**Display:** Horizontal strip of up to 3 stat items. Each item: icon (inline SVG) + large number + label. Responsive: wraps to single column at ≤640px.

Labels (translatable):
- `__( 'Jobs', 'wp-career-board' )`
- `__( 'Companies', 'wp-career-board' )`
- `__( 'Candidates', 'wp-career-board' )`

Note: `wcb_resume` is registered by the free plugin (candidates module), but resume functionality is only meaningful when the pro plugin is active. The `showCandidates` attribute defaults to `true`, but implementers should be aware that this stat only reflects meaningful data on sites with the pro plugin. No conditional logic is required — it is the site owner's responsibility to toggle it off if unused.

**Empty state:** Renders even when counts are zero — always shows the stat strip.

### 3.4 `wp-career-board/job-search-hero`

**Description:** Combines a keyword search input with optional filter dropdowns in one block. Designed for full-width hero sections (inside a Cover or Group block) or as a standalone search widget.

**Attributes:**

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `layout` | string | `'horizontal'` | `'horizontal'` or `'vertical'` |
| `placeholder` | string | `''` | Search input placeholder (empty = translated default) |
| `buttonLabel` | string | `''` | Search button label (empty = translated default) |
| `showCategoryFilter` | boolean | true | Show job category (`wcb_category`) dropdown |
| `showLocationFilter` | boolean | true | Show location (`wcb_location`) dropdown |
| `showJobTypeFilter` | boolean | true | Show job type (`wcb_job_type`) dropdown |

**GET params submitted (must match `wp-career-board/job-listings` and `wp-career-board/job-filters` consumption):**

| Field | GET param |
|-------|-----------|
| Keyword search | `wcb_search` |
| Category | `wcb_category` |
| Location | `wcb_location` |
| Job type | `wcb_job_type` |

**Behaviour:** Pure HTML `<form method="GET">` pointing to the jobs archive page (from `wcb_settings['jobs_archive_page']`). No JavaScript required.

**Placement note:** This block is intended for pages that do NOT also contain `wp-career-board/job-search` or `wp-career-board/job-filters` (e.g. a homepage hero, a landing page). Placing it on the same page as those blocks is valid but the pre-populated GET values will be shared — the hero form should read and pre-populate from existing GET params so the two blocks remain in sync.

**Layout — horizontal:** Single flex row. Search input grows (`flex: 1`). Filter dropdowns at fixed width (`160px`). Button at end. Stacks to vertical at ≤640px.

**Layout — vertical:** Stacked form. Full-width input, full-width selects, full-width button.

**Styling:** Inherits text/background color from parent block. No hardcoded background colors. Uses `--wcb-primary` token for button background.

---

## 4. Pro Blocks

### 4.1 `wcb/open-to-work`

**Query pattern:** `_wcb_open_to_work` is stored in `wp_usermeta`, not post meta. Follow the pattern in `ResumeModule::list_public_resumes()`:
1. Query `wp_usermeta` for `user_id` values where `meta_key = '_wcb_open_to_work'` AND `meta_value = '1'`
2. If zero user IDs found, `return;` immediately (empty state, no output)
3. Pass the resulting `user_ids` as `author__in` to `WP_Query` with `post_type = wcb_resume`, `post_status = publish`, meta `_wcb_resume_public = 1`

**Attributes:** `count` (integer, default 5), `title` (string, default `''`), `showViewAll` (boolean, default true), `viewAllUrl` (string, default `''`).

**Card anatomy:**
- 40px avatar — `get_avatar_url( $user_id, [ 'size' => 40 ] )` (BuddyPress hooks into `pre_get_avatar_data` automatically when active; no explicit BP check needed), fallback: CSS initials
- Display name linked to resume single permalink
- Headline / current job title — first entry of `_wcb_resume_experience` serialised array (`[0]['job_title']`); empty string if not set
- Top 3 skill pills (`wcb_resume_skill` terms via `wp_get_object_terms()`)
- Green "Open to Work" dot indicator

**Empty state:** `return;` (silent, no output).

**Pro guard:** Not required — no Pro module class is instantiated directly. The `wp_usermeta` query is a plain `$wpdb` query.

### 4.2 `wcb/featured-companies`

**Query:** `wcb_company`, `post_status = publish`, meta `_wcb_featured = 1`, `orderby = date`, `order = DESC`, up to `count` results.

**Prerequisite — add Featured flag to company meta box:** `_wcb_featured` is not currently saved on `wcb_company` posts. As part of this task, add a "Featured company" checkbox to `class-admin-meta-boxes.php::save_company_meta()` (matching the existing `_wcb_featured` field on `wcb_job`). Without this write path, the query will always return empty.

**Open roles count:** Pre-scope to the company post authors on the current page. Collect `post_author` IDs from the fetched company posts, then call `get_posts( [ 'post_type' => 'wcb_job', 'post_status' => 'publish', 'author__in' => $author_ids, 'numberposts' => -1, 'fields' => 'ids' ] )` and group results by `post_author` in PHP — matching `company-archive/render.php`. Do NOT query all jobs site-wide without `author__in` scope. Do NOT issue a separate query per company card (N+1).

**Attributes:** `count` (integer, default 5), `title` (string, default `''`), `showViewAll` (boolean, default true), `viewAllUrl` (string, default `''`).

**Card anatomy:**
- 40px company logo — `get_the_post_thumbnail_url( $post_id, 'thumbnail' )`, fallback: coloured CSS initial (first letter of company name)
- Company name linked to company profile permalink
- Industry / tagline from `_wcb_tagline` post meta
- "N open roles" badge (from grouped jobs count)

**Empty state:** `return;` (silent, no output).

### 4.3 `wcb/featured-candidates`

**Query:** `wcb_resume`, `post_status = publish`, meta `_wcb_featured = 1` AND `_wcb_resume_public = 1`, `orderby = date`, `order = DESC`.

**Prerequisite — add Featured flag to resume admin meta box (pro plugin):** `_wcb_featured` is not currently saved on `wcb_resume` posts. As part of this task, add a "Featured candidate" checkbox to the resume admin meta box in `wp-career-board-pro`. Without this write path, the query will always return empty.

**Attributes:** `count` (integer, default 5), `title` (string, default `''`), `showViewAll` (boolean, default true), `viewAllUrl` (string, default `''`).

**Card anatomy:**
- 40px avatar — `get_avatar_url( $user_id, [ 'size' => 40 ] )`, fallback: CSS initials
- Display name linked to resume single permalink
- Headline / current job title — first entry of `_wcb_resume_experience` serialised array (`[0]['job_title']`); empty string if not set
- Top 3 skill pills (`wcb_resume_skill` terms via `wp_get_object_terms()`)

**Empty state:** `return;` (silent, no output).

### 4.4 `wcb/resume-search-hero`

**Mirror of `wp-career-board/job-search-hero` for the resume/candidate search vertical.**

**Attributes:**

| Attribute | Type | Default | Notes |
|-----------|------|---------|-------|
| `layout` | string | `'horizontal'` | `'horizontal'` or `'vertical'` |
| `placeholder` | string | `''` | Search input placeholder |
| `buttonLabel` | string | `''` | Button label |
| `showSkillFilter` | boolean | true | Show skill (`wcb_resume_skill`) dropdown |
| `showOpenToWorkFilter` | boolean | false | Show "Open to Work only" checkbox |

Note: `showLocationFilter` is excluded — `wcb/resume-archive` does not consume a location param. If resume location filtering is added to `wcb/resume-archive` in future, this attribute can be added then.

**GET params submitted (must match `wcb/resume-archive` consumption):**

| Field | GET param |
|-------|-----------|
| Keyword search | `wcb_resume_search` |
| Skill | `wcb_resume_skill` |
| Open to work | `wcb_open_to_work` (value: `1`) |

**Behaviour:** Pure HTML `<form method="GET">` pointing to the resume archive page (from `wcb_settings['resume_archive_page']`). No JavaScript required. Layout and styling identical to `wp-career-board/job-search-hero`.

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

**Single responsive breakpoint: `≤640px`** — used consistently across all new blocks. Hero blocks stack filter dropdowns below the search input at this breakpoint.

---

## 6. i18n Audit

Scan both plugins for:

1. **Missing text domain** — `__( 'string' )` with no second argument
2. **Wrong text domain** — `'wp-career-board'` in pro files, or `'wp-career-board-pro'` in free files
3. **Unescaped i18n** — `_e()` used for HTML output instead of `esc_html_e()`
4. **Hardcoded JS strings** — `view.js`/`index.js` files outputting English without `@wordpress/i18n`'s `__`
5. **Missing strings** — button labels, aria-labels, empty-state messages, error messages, placeholder text not wrapped in i18n functions

Fix all findings in-place. No string content changes — only wrapping and correct text domains.

---

## 7. Page Name Updates

| Current title | New title | Page ID |
|---------------|-----------|---------|
| Employer Dashboard | Hiring | 10 |
| Candidate Dashboard | Career | 11 |

Apply via WP-CLI:
```bash
wp post update 10 --post_title="Hiring"
wp post update 11 --post_title="Career"
```

Update Reign navigation menu item labels separately — `wp post update` does NOT update menu items (they store their own label copy):

```bash
# Find menu item IDs first:
wp menu item list <menu-name> --fields=db_id,title --format=table
# Then update each:
wp menu item update <db_id> --title="Hiring"
wp menu item update <db_id> --title="Career"
```

No PHP/JS hardcodes these page titles — blocks resolve via `wcb_settings` page ID options — so no code changes are required beyond the page titles and menu labels.

---

## 8. Out of Scope

- Model site content setup (separate spec)
- Any interactive (JS-driven) behaviour in the new widget blocks
- New design tokens
- Pro features in the free plugin
- Resume location filtering (requires extending `wcb/resume-archive` separately)

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

**wp-career-board/admin/class-admin-meta-boxes.php** — add `_wcb_featured` checkbox + save to `save_company_meta()` (prerequisite for `wcb/featured-companies`)

**wp-career-board-pro/** (resume admin meta box file) — add `_wcb_featured` checkbox + save on `wcb_resume` (prerequisite for `wcb/featured-candidates`)

**i18n:** any PHP/JS files with findings from the audit

**Page titles:** WP-CLI commands in Section 7
