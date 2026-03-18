# Job Directory Redesign Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Redesign the `wcb/job-listings` block with an integrated top-chip filter header, 2-line excerpt on job cards, sort control, active filter strip, and live results count.

**Architecture:** All filter/search/sort state is absorbed into the `job-listings` block itself — no more dependency on the separate `job-search`/`job-filters` blocks for the new page design. PHP seeds taxonomy options and total count; JS handles all interactive fetching. The separate blocks remain unchanged for backwards compatibility via the `wcb:search` event.

**Tech Stack:** PHP 8.1, WordPress Interactivity API (`@wordpress/interactivity`), vanilla CSS custom properties, REST API (`wcb/v1`).

---

## File Map

| File | What changes |
|------|-------------|
| `api/endpoints/class-jobs-endpoint.php` | Add `excerpt` field; add `orderby`/`order` query param support |
| `blocks/job-listings/render.php` | Seed filter options + `totalCount`; replace toolbar with chip header; add excerpt to card template |
| `blocks/job-listings/view.js` | New state fields, getters, actions, debounce, fetch refactor, wcb:search listener update |
| `blocks/job-listings/style.css` | Chip bar, sort select, active filter strip, excerpt, responsive tweaks |

All paths relative to `wp-content/plugins/wp-career-board/`.

---

## Task 1: REST API — `excerpt` field and `orderby`/`order` params

**Files:**
- Modify: `api/endpoints/class-jobs-endpoint.php`

- [ ] **Step 1: Add `excerpt` to `prepare_item_for_response_array()`**

Find the return array that begins with `'id' => $post->ID` (around line 722). Add `excerpt` after `description`:

```php
'description'      => $post->post_content,
'excerpt'          => wp_trim_words( strip_tags( $post->post_content ), 25, '…' ),
```

- [ ] **Step 2: Add `excerpt` to `get_item_schema()`**

Find the `properties` array in `get_item_schema()`. Add after the `description` property:

```php
'excerpt'     => array( 'type' => 'string', 'readonly' => true ),
```

- [ ] **Step 3: Add `orderby`/`order` support in `get_items()`**

Find the `$author` block (~line 195–198):
```php
$author = $request->get_param( 'author' );
if ( $author ) {
    $args['author'] = (int) $author;
}
```

Add immediately after it:
```php
$orderby_raw     = (string) ( $request->get_param( 'orderby' ) ?? 'date' );
$order_raw       = (string) ( $request->get_param( 'order' ) ?? 'DESC' );
$args['orderby'] = in_array( $orderby_raw, array( 'date' ), true ) ? $orderby_raw : 'date';
$args['order']   = in_array( strtoupper( $order_raw ), array( 'ASC', 'DESC' ), true )
    ? strtoupper( $order_raw ) : 'DESC';
```

- [ ] **Step 4: Register `orderby`/`order` in `get_collection_params()`**

In `get_collection_params()`, inside the params array, add after `'per_page'`:

```php
'orderby' => array( 'type' => 'string', 'enum' => array( 'date' ), 'default' => 'date' ),
'order'   => array( 'type' => 'string', 'enum' => array( 'ASC', 'DESC' ), 'default' => 'DESC' ),
```

- [ ] **Step 5: WPCS auto-fix and quality check**

```
mcp__wpcs__wpcs_fix_file   → api/endpoints/class-jobs-endpoint.php
mcp__wpcs__wpcs_phpstan_check
mcp__wpcs__wpcs_check_staged
mcp__wpcs__wpcs_quality_check
```

All must return 0 errors before proceeding.

- [ ] **Step 6: Verify manually**

Navigate to:
```
http://job-portal.local/wp-json/wcb/v1/jobs?per_page=1
```
Response object must contain an `excerpt` field (non-empty string).

Navigate to:
```
http://job-portal.local/wp-json/wcb/v1/jobs?orderby=date&order=ASC
```
The oldest job must be first in the array (compare `date` fields).

- [ ] **Step 7: Commit**

```bash
git add api/endpoints/class-jobs-endpoint.php
git commit -m "feat(wcb): T1 — add excerpt field and orderby/order params to jobs endpoint"
```

---

## Task 2: PHP Render — State + HTML

**Files:**
- Modify: `blocks/job-listings/render.php`

- [ ] **Step 1: Load filter taxonomy options**

After the `$wcb_bookmarks` block (around line 40), add:

```php
$wcb_type_terms_all = get_terms( array( 'taxonomy' => 'wcb_job_type', 'hide_empty' => true ) );
$wcb_exp_terms_all  = get_terms( array( 'taxonomy' => 'wcb_experience', 'hide_empty' => true ) );
$wcb_type_opts      = is_wp_error( $wcb_type_terms_all ) ? array() : array_map(
	static fn( $t ) => array( 'slug' => $t->slug, 'name' => $t->name, 'count' => $t->count ),
	$wcb_type_terms_all
);
$wcb_exp_opts       = is_wp_error( $wcb_exp_terms_all ) ? array() : array_map(
	static fn( $t ) => array( 'slug' => $t->slug, 'name' => $t->name, 'count' => $t->count ),
	$wcb_exp_terms_all
);
```

- [ ] **Step 2: Add excerpt to each job in the foreach loop**

Inside the `foreach ( $wcb_jobs_raw as $wcb_job_post )` loop, in the `$wcb_jobs_state[]` array, add after `'bookmarked'`:

```php
'excerpt'    => wp_trim_words( strip_tags( $wcb_job_post->post_content ), 25, '…' ),
```

- [ ] **Step 3: Add `totalCount` seed query**

Before the `$wcb_state = array(...)` block, add:

```php
$wcb_count_query = new WP_Query(
	array(
		'post_type'      => 'wcb_job',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
	)
);
$wcb_total_count = (int) $wcb_count_query->found_posts;
```

- [ ] **Step 4: Update `wp_interactivity_state()` array**

Replace the existing `$wcb_state = array(...)` block (which currently has `'jobs'`, `'page'`, `'perPage'`, `'layout'`, `'loading'`, `'hasMore'`, `'apiBase'`, `'nonce'`) with:

```php
$wcb_state = array(
	'jobs'          => $wcb_jobs_state,
	'page'          => 1,
	'perPage'       => $wcb_per_page,
	'layout'        => $wcb_layout,
	'loading'       => false,
	'hasMore'       => count( $wcb_jobs_raw ) >= $wcb_per_page,
	'totalCount'    => $wcb_total_count,
	'searchQuery'   => '',
	'activeFilters' => (object) array(),
	'sortBy'        => 'date_desc',
	'filterOptions' => array(
		'types'       => $wcb_type_opts,
		'experiences' => $wcb_exp_opts,
	),
	'apiBase'       => (string) apply_filters( 'wcb_job_listings_api_base', rest_url( 'wcb/v1/jobs' ) ),
	'nonce'         => wp_create_nonce( 'wp_rest' ),
);
```

- [ ] **Step 5: Replace toolbar HTML with chip header**

Find the entire `.wcb-listings-toolbar` div (lines 157–179 in the original file) and replace it with:

```html
<div class="wcb-listings-header">

	<div class="wcb-search-sort-row">
		<div class="wcb-search-wrap">
			<svg class="wcb-search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
				<circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
			</svg>
			<input
				type="search"
				class="wcb-listings-search"
				placeholder="<?php esc_attr_e( 'Search jobs\u2026', 'wp-career-board' ); ?>"
				data-wp-bind--value="state.searchQuery"
				data-wp-on--input="actions.updateSearch"
				aria-label="<?php esc_attr_e( 'Search jobs', 'wp-career-board' ); ?>"
			/>
		</div>
		<select
			class="wcb-sort-select"
			aria-label="<?php esc_attr_e( 'Sort jobs', 'wp-career-board' ); ?>"
			data-wp-on--change="actions.changeSort"
		>
			<option value="date_desc"><?php esc_html_e( 'Newest first', 'wp-career-board' ); ?></option>
			<option value="date_asc"><?php esc_html_e( 'Oldest first', 'wp-career-board' ); ?></option>
		</select>
	</div>

	<div class="wcb-chip-bar" role="group" aria-label="<?php esc_attr_e( 'Filter jobs', 'wp-career-board' ); ?>">
		<button
			type="button"
			class="wcb-chip"
			data-wp-on--click="actions.clearFilters"
			data-wp-class--wcb-chip-active="state.noActiveFilters"
		><?php esc_html_e( 'All', 'wp-career-board' ); ?></button>

		<template data-wp-each--type="state.filterOptions.types" data-wp-each-key="context.type.slug">
			<button
				type="button"
				class="wcb-chip"
				data-wp-on--click="actions.toggleTypeChip"
				data-wp-class--wcb-chip-active="state.isTypeActive"
				data-wp-text="context.type.name"
			></button>
		</template>

		<div class="wcb-chip-divider" aria-hidden="true"></div>

		<button
			type="button"
			class="wcb-chip"
			data-wp-on--click="actions.toggleRemote"
			data-wp-class--wcb-chip-active="state.isRemoteActive"
		><?php esc_html_e( 'Remote', 'wp-career-board' ); ?></button>

		<div class="wcb-chip-divider" aria-hidden="true"></div>

		<template data-wp-each--exp="state.filterOptions.experiences" data-wp-each-key="context.exp.slug">
			<button
				type="button"
				class="wcb-chip"
				data-wp-on--click="actions.toggleExpChip"
				data-wp-class--wcb-chip-active="state.isExpActive"
				data-wp-text="context.exp.name"
			></button>
		</template>
	</div>

	<div class="wcb-active-filters" data-wp-class--wcb-shown="state.hasActiveFilters" aria-live="polite">
		<span class="wcb-active-filters-label"><?php esc_html_e( 'Filters:', 'wp-career-board' ); ?></span>
		<template data-wp-each--filter="state.activeFilterChips" data-wp-each-key="context.filter.key">
			<div class="wcb-active-chip">
				<span data-wp-text="context.filter.label"></span>
				<button
					type="button"
					class="wcb-active-chip-remove"
					data-wp-on--click="actions.removeFilter"
					aria-label="<?php esc_attr_e( 'Remove filter', 'wp-career-board' ); ?>"
				>&#xd7;</button>
			</div>
		</template>
		<button
			type="button"
			class="wcb-clear-all"
			data-wp-on--click="actions.clearFilters"
		><?php esc_html_e( 'Clear all', 'wp-career-board' ); ?></button>
	</div>

	<p class="wcb-results-count" data-wp-text="state.resultsLabel" aria-live="polite"></p>

</div>
```

- [ ] **Step 6: Add excerpt to the job card template**

Inside the `<article class="wcb-job-card">` `<template>`, find the `.wcb-card-badges` div closing tag (`</div>`) that comes just before the `.wcb-card-footer` div. Add the excerpt paragraph between them:

```html
<p class="wcb-card-excerpt"
   data-wp-class--wcb-shown="context.job.excerpt"
   data-wp-text="context.job.excerpt"
></p>
```

- [ ] **Step 7: WPCS auto-fix and quality check**

```
mcp__wpcs__wpcs_fix_file   → blocks/job-listings/render.php
mcp__wpcs__wpcs_phpstan_check
mcp__wpcs__wpcs_check_staged
mcp__wpcs__wpcs_quality_check
```

All must return 0 errors before proceeding.

- [ ] **Step 8: Smoke test PHP render**

Navigate to `http://job-portal.local/jobs/?autologin=1` (or wherever the job listings page is). The page should load without PHP errors. Open browser DevTools → Application → look for the `wcb-job-listings` Interactivity API store state. Confirm it contains `filterOptions.types`, `filterOptions.experiences`, `totalCount` (integer > 0), `searchQuery: ''`, `activeFilters: {}`, `sortBy: 'date_desc'`.

- [ ] **Step 9: Commit**

```bash
git add blocks/job-listings/render.php
git commit -m "feat(wcb): T2 — update job-listings render — chip header, excerpt, filter state"
```

---

## Task 3: JS Store — New State, Getters, Actions

**Files:**
- Modify: `blocks/job-listings/view.js`

This is a significant rewrite of the store. Replace the entire file contents following the pattern below. Read the existing file first to preserve `import` statement and store name.

- [ ] **Step 1: Replace `view.js` with the new store**

The new file structure:

```js
/**
 * WP Career Board — job-listings block Interactivity API store.
 *
 * Actions:
 *   updateSearch     — debounced search input; triggers applyFilters after 350ms.
 *   toggleTypeChip   — toggle wcb_job_type filter chip (single-select).
 *   toggleExpChip    — toggle wcb_experience filter chip (single-select).
 *   toggleRemote     — toggle remote meta filter.
 *   *removeFilter    — remove one active filter chip and refetch.
 *   clearFilters     — reset all filters and search; refetch.
 *   changeSort       — change sort order; refetch.
 *   setGrid/setList  — toggle layout (preserved for backwards compat).
 *   *loadMore        — fetch next page, appending to state.jobs.
 *   *fetchJobs       — shared fetch generator used by all filter/sort/load actions.
 *   *applyFilters    — reset to page 1 then call fetchJobs.
 *   toggleBookmark   — POST to /bookmark and flip context.job.bookmarked.
 *
 * Event listener:
 *   wcb:search  — fired by legacy job-search / job-filters blocks; resets chip
 *                 filters and re-fetches with the event's query/filters payload.
 *
 * @package WP_Career_Board
 */
import { store, getContext } from '@wordpress/interactivity';

let searchDebounceTimer = null;

const { state, actions } = store( 'wcb-job-listings', {
	state: {
		// ── Layout ────────────────────────────────────────────────────────────
		get isGrid() {
			return state.layout === 'grid';
		},
		get isList() {
			return state.layout === 'list';
		},

		// ── Filter state ──────────────────────────────────────────────────────
		get noActiveFilters() {
			return Object.keys( state.activeFilters ).length === 0 && ! state.searchQuery;
		},
		get hasActiveFilters() {
			return ! state.noActiveFilters;
		},

		// ⚠️ isTypeActive and isExpActive rely on data-wp-each loop context.
		// Only use inside data-wp-each--type / data-wp-each--exp templates respectively.
		get isTypeActive() {
			const ctx = getContext();
			return state.activeFilters.type === ctx.type?.slug;
		},
		get isExpActive() {
			const ctx = getContext();
			return state.activeFilters.experience === ctx.exp?.slug;
		},
		get isRemoteActive() {
			return state.activeFilters.remote === '1';
		},

		// ── Active filter chips for the dismissible strip ─────────────────────
		get activeFilterChips() {
			const chips = [];
			const f     = state.activeFilters;
			if ( f.type ) {
				const match = state.filterOptions.types.find( t => t.slug === f.type );
				chips.push( { key: 'type', label: match ? match.name : f.type } );
			}
			if ( f.experience ) {
				const match = state.filterOptions.experiences.find( e => e.slug === f.experience );
				chips.push( { key: 'experience', label: match ? match.name : f.experience } );
			}
			if ( f.remote ) {
				chips.push( { key: 'remote', label: 'Remote' } );
			}
			if ( state.searchQuery ) {
				chips.push( { key: 'search', label: '"' + state.searchQuery + '"' } );
			}
			return chips;
		},

		// ── Results label ─────────────────────────────────────────────────────
		get resultsLabel() {
			const shown = state.jobs.length;
			const total = state.totalCount || shown;
			return shown + ' of ' + total + ' jobs';
		},

		// ── Bookmark label (used in card) ─────────────────────────────────────
		get bookmarkLabel() {
			const ctx = getContext();
			return ctx.job && ctx.job.bookmarked ? 'Remove bookmark' : 'Bookmark job';
		},
		get hasNoJobs() {
			return ! state.loading && state.jobs.length === 0;
		},
	},

	actions: {
		// ── Layout ────────────────────────────────────────────────────────────
		setGrid() {
			state.layout = 'grid';
		},
		setList() {
			state.layout = 'list';
		},

		// ── Search ────────────────────────────────────────────────────────────
		updateSearch( event ) {
			state.searchQuery = event.target.value;
			clearTimeout( searchDebounceTimer );
			searchDebounceTimer = setTimeout( () => {
				store( 'wcb-job-listings' ).actions.applyFilters();
			}, 350 );
		},

		// ── Chip filters ──────────────────────────────────────────────────────
		*toggleTypeChip() {
			const ctx  = getContext();
			const slug = ctx.type?.slug;
			if ( state.activeFilters.type === slug ) {
				const next = { ...state.activeFilters };
				delete next.type;
				state.activeFilters = next;
			} else {
				state.activeFilters = { ...state.activeFilters, type: slug };
			}
			yield actions.applyFilters();
		},

		*toggleExpChip() {
			const ctx  = getContext();
			const slug = ctx.exp?.slug;
			if ( state.activeFilters.experience === slug ) {
				const next = { ...state.activeFilters };
				delete next.experience;
				state.activeFilters = next;
			} else {
				state.activeFilters = { ...state.activeFilters, experience: slug };
			}
			yield actions.applyFilters();
		},

		*toggleRemote() {
			if ( state.activeFilters.remote ) {
				const next = { ...state.activeFilters };
				delete next.remote;
				state.activeFilters = next;
			} else {
				state.activeFilters = { ...state.activeFilters, remote: '1' };
			}
			yield actions.applyFilters();
		},

		*removeFilter() {
			const ctx = getContext();
			const key = ctx.filter?.key;
			if ( key === 'search' ) {
				state.searchQuery = '';
			} else {
				const next = { ...state.activeFilters };
				delete next[ key ];
				state.activeFilters = next;
			}
			yield actions.applyFilters();
		},

		*clearFilters() {
			state.activeFilters = {};
			state.searchQuery   = '';
			yield actions.applyFilters();
		},

		*changeSort( event ) {
			state.sortBy = event.target.value;
			yield actions.applyFilters();
		},

		// ── Core fetch generators ─────────────────────────────────────────────
		*applyFilters() {
			state.page = 1;
			yield actions.fetchJobs();
		},

		*fetchJobs() {
			if ( state.loading ) {
				return;
			}
			state.loading = true;

			const url = new URL( state.apiBase );
			url.searchParams.set( 'page',     String( state.page ) );
			url.searchParams.set( 'per_page', String( state.perPage ) );

			if ( state.searchQuery ) {
				url.searchParams.set( 'search', state.searchQuery );
			}

			const f = state.activeFilters;
			if ( f.type )       url.searchParams.set( 'type',       f.type );
			if ( f.experience ) url.searchParams.set( 'experience', f.experience );
			if ( f.remote )     url.searchParams.set( 'remote',     '1' );

			const [ orderby, order ] = state.sortBy === 'date_asc'
				? [ 'date', 'ASC' ]
				: [ 'date', 'DESC' ];
			url.searchParams.set( 'orderby', orderby );
			url.searchParams.set( 'order',   order );

			try {
				const response = yield fetch( url.toString() );

				if ( ! response.ok ) {
					return;
				}

				const jobs = yield response.json();

				if ( state.page === 1 ) {
					state.jobs = jobs;
				} else {
					state.jobs.push( ...jobs );
				}

				state.totalCount = parseInt( response.headers.get( 'X-WCB-Total' ) || '0', 10 );
				state.hasMore    = jobs.length === state.perPage;
			} catch {
				// fetch failed — leave existing jobs visible
			} finally {
				state.loading = false;
			}
		},

		*loadMore() {
			if ( state.loading ) {
				return;
			}
			state.page++;
			yield actions.fetchJobs();
		},

		// ── Bookmark ──────────────────────────────────────────────────────────
		*toggleBookmark() {
			const ctx = getContext();
			const job = ctx.job;

			try {
				const response = yield fetch(
					state.apiBase + '/' + String( job.id ) + '/bookmark',
					{
						method: 'POST',
						headers: {
							'X-WP-Nonce': state.nonce,
							'Content-Type': 'application/json',
						},
					}
				);

				if ( ! response.ok ) {
					return;
				}

				const data     = yield response.json();
				job.bookmarked = data.bookmarked;
			} catch {
				// Bookmark toggle failed silently — no UI disruption needed.
			}
		},
	},
} );

// ── Legacy wcb:search event (from job-search / job-filters blocks) ─────────
document.addEventListener( 'wcb:search', function( event ) {
	const detail  = event.detail ?? {};
	const query   = detail.query   ?? '';
	const filters = detail.filters ?? {};

	state.searchQuery   = query;
	state.activeFilters = {};
	state.page          = 1;
	state.loading       = true;
	state.jobs          = [];

	const url = new URL( state.apiBase );
	url.searchParams.set( 'page',     '1' );
	url.searchParams.set( 'per_page', String( state.perPage ) );
	if ( query ) {
		url.searchParams.set( 'search', query );
	}
	Object.keys( filters ).forEach( function( k ) {
		url.searchParams.set( k, filters[ k ] );
	} );

	fetch( url.toString() )
		.then( function( response ) {
			if ( ! response.ok ) {
				state.loading = false;
				return undefined;
			}
			state.totalCount = parseInt( response.headers.get( 'X-WCB-Total' ) || '0', 10 );
			return response.json();
		} )
		.then( function( jobs ) {
			if ( ! jobs ) {
				return;
			}
			state.jobs    = jobs;
			state.hasMore = jobs.length === state.perPage;
			state.loading = false;
		} )
		.catch( function() {
			state.loading = false;
		} );
} );
```

- [ ] **Step 2: Verify JS file in browser DevTools**

Navigate to the job listings page. Open DevTools console. Run:
```js
wp.data && console.log('WP data available')
```
There should be no JS errors in console. The job cards should render correctly.

- [ ] **Step 3: Test chip interactions manually**

Using Playwright MCP or browser:
1. Click a job type chip (e.g. "Full-time") → Network tab should show a request to `/wcb/v1/jobs?type=full-time&...`
2. Click "Remote" chip → Network tab shows `remote=1` added
3. The active filter strip should appear with "Full-time ×" and "Remote ×"
4. Click "Full-time ×" → strip updates, jobs reload without type filter
5. Type in search box → after ~350ms, a fetch fires with `search=...`

- [ ] **Step 4: Commit**

```bash
git add blocks/job-listings/view.js
git commit -m "feat(wcb): T3 — refactor job-listings store — chip filters, sort, excerpt, debounce search"
```

---

## Task 4: CSS — Chip Bar, Excerpt, Filter Strip

**Files:**
- Modify: `blocks/job-listings/style.css`

- [ ] **Step 1: Add new CSS custom properties to `:root`**

Find the `:root` block at the top of the file (lines 1–9) and append inside it:

```css
--wcb-chip-h:            32px;
--wcb-chip-radius:       20px;
--wcb-chip-px:           14px;
--wcb-chip-font-size:    0.8125rem;
--wcb-chip-active-bg:    var( --wcb-primary, #2563eb );
--wcb-chip-active-color: #fff;
```

- [ ] **Step 2: Append all new CSS rules to the end of the file**

Add after the final `@media ( max-width: 640px )` block:

```css
/* ── Listings header ──────────────────────────────────────────────────────── */
.wcb-listings-header {
	background: #fff;
	border-bottom: 1px solid #e2e8f0;
	padding: 1.25rem 0 0;
	margin-bottom: 1.25rem;
}

.wcb-search-sort-row {
	display: flex;
	gap: 0.625rem;
	margin-bottom: 0.875rem;
	align-items: center;
}

.wcb-search-wrap {
	position: relative;
	flex: 1;
}

.wcb-search-icon {
	position: absolute;
	left: 0.75rem;
	top: 50%;
	transform: translateY( -50% );
	color: #94a3b8;
	pointer-events: none;
}

.wcb-listings-search {
	width: 100%;
	height: 40px;
	padding: 0 0.75rem 0 2.375rem;
	border: 1px solid #e2e8f0;
	border-radius: 8px;
	font-size: 0.875rem;
	color: #111827;
	background: #fff;
	outline: none;
}

.wcb-listings-search:focus {
	border-color: var( --wcb-primary, #2563eb );
	box-shadow: 0 0 0 3px rgba( 37, 99, 235, 0.1 );
}

.wcb-sort-select {
	height: 40px;
	padding: 0 0.875rem;
	border: 1px solid #e2e8f0;
	border-radius: 8px;
	font-size: 0.8125rem;
	color: #374151;
	background: #fff;
	cursor: pointer;
	white-space: nowrap;
	outline: none;
}

/* ── Chip bar ─────────────────────────────────────────────────────────────── */
.wcb-chip-bar {
	display: flex;
	flex-wrap: wrap;
	gap: 0.5rem;
	align-items: center;
	padding-bottom: 0.875rem;
}

.wcb-chip {
	height: var( --wcb-chip-h, 32px );
	padding: 0 var( --wcb-chip-px, 14px );
	border: 1px solid #e2e8f0;
	border-radius: var( --wcb-chip-radius, 20px );
	font-size: var( --wcb-chip-font-size, 0.8125rem );
	font-weight: 500;
	font-family: inherit;
	color: #374151;
	background: #fff;
	cursor: pointer;
	display: inline-flex;
	align-items: center;
	gap: 0.3rem;
	transition: border-color 0.12s, color 0.12s, background 0.12s;
	white-space: nowrap;
}

.wcb-chip:hover {
	border-color: var( --wcb-primary, #2563eb );
	color: var( --wcb-primary, #2563eb );
	background: #eff6ff;
}

.wcb-chip.wcb-chip-active {
	background: var( --wcb-chip-active-bg, #2563eb );
	color: var( --wcb-chip-active-color, #fff );
	border-color: var( --wcb-chip-active-bg, #2563eb );
}

.wcb-chip-divider {
	width: 1px;
	height: 20px;
	background: #e2e8f0;
	margin: 0 0.25rem;
	flex-shrink: 0;
}

/* ── Active filter strip ──────────────────────────────────────────────────── */
.wcb-active-filters {
	display: none;
	flex-wrap: wrap;
	gap: 0.375rem;
	align-items: center;
	padding-bottom: 0.75rem;
}

.wcb-active-filters.wcb-shown {
	display: flex;
}

.wcb-active-filters-label {
	font-size: 0.75rem;
	color: #6b7280;
	margin-right: 0.25rem;
}

.wcb-active-chip {
	display: inline-flex;
	align-items: center;
	gap: 0.3rem;
	height: 26px;
	padding: 0 0.625rem;
	background: #eff6ff;
	color: var( --wcb-primary, #2563eb );
	border: 1px solid #bfdbfe;
	border-radius: 20px;
	font-size: 0.75rem;
	font-weight: 500;
}

.wcb-active-chip-remove {
	background: none;
	border: none;
	font-size: 0.875rem;
	line-height: 1;
	color: inherit;
	cursor: pointer;
	padding: 0;
	opacity: 0.7;
}

.wcb-active-chip-remove:hover { opacity: 1; }

.wcb-clear-all {
	background: none;
	border: none;
	font-size: 0.75rem;
	color: #6b7280;
	text-decoration: underline;
	cursor: pointer;
	padding: 0;
	font-family: inherit;
}

.wcb-clear-all:hover { color: #374151; }

/* ── Results count (inside header) ───────────────────────────────────────── */
.wcb-listings-header .wcb-results-count {
	font-size: 0.8125rem;
	color: #6b7280;
	margin: 0;
	padding-bottom: 0.75rem;
}

/* ── Card excerpt ─────────────────────────────────────────────────────────── */
/* Hidden by default. .wcb-shown enables 2-line clamp. */
.wcb-card-excerpt {
	display: none;
	font-size: 0.875rem;
	color: #4b5563;
	line-height: 1.55;
	margin: 0;
}

.wcb-card-excerpt.wcb-shown {
	display: -webkit-box;
	overflow: hidden;
	-webkit-line-clamp: 2;
	-webkit-box-orient: vertical;
}

/* ── Chip bar responsive ──────────────────────────────────────────────────── */
@media ( max-width: 640px ) {
	.wcb-chip-bar {
		gap: 0.375rem;
	}

	.wcb-chip {
		height: 30px;
		padding: 0 0.75rem;
		font-size: 0.75rem;
	}

	.wcb-sort-select {
		display: none;
	}
}
```

- [ ] **Step 3: Verify visual appearance**

Using browser (Playwright MCP or manual):

1. Job directory page loads with the chip bar row visible at top
2. "All" chip is active (blue) by default
3. Clicking a chip turns it blue/active; "All" loses active state
4. Job cards show a 2-line excerpt below the badges
5. When any filter is active, the active filter strip appears below the chip bar with blue dismissible chips
6. Clicking × on an active chip removes it and the strip disappears if it was the last filter
7. Chip bar wraps correctly on narrow viewports; sort select hides on mobile

- [ ] **Step 4: Commit**

```bash
git add blocks/job-listings/style.css
git commit -m "feat(wcb): T4 — add chip bar, excerpt, active filter strip, sort select CSS"
```

---

## Final Verification

- [ ] **Full flow test**

1. Visit job listings page → shows chip bar + card excerpts + results count
2. Click "Full-time" → only full-time jobs shown, "Full-time ×" in filter strip
3. Click "Remote" → both filters active, filter strip shows both chips
4. Click × on "Full-time" → only remote filter remains
5. Type "developer" in search → jobs update after 350ms debounce, search chip appears
6. Click "Clear all" → full job list restores, chip bar resets to "All"
7. Change sort to "Oldest first" → jobs reload in ascending date order
8. Click "Load more" → next page of jobs appends with current filters preserved
9. Verify `GET /wcb/v1/jobs?orderby=date&order=ASC` returns correct order
10. Verify a page with the old `wcb/job-filters` + `wcb/job-listings` still works via `wcb:search` event

- [ ] **WPCS final gate**

```
mcp__wpcs__wpcs_check_staged
mcp__wpcs__wpcs_quality_check
```
