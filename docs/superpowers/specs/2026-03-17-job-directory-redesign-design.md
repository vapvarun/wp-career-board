# Phase 2 — Job Directory Redesign

**Date:** 2026-03-17
**Status:** Ready for implementation

---

## Context

The existing job directory uses three separate blocks: `wcb/job-search` (search input), `wcb/job-filters` (dropdown selects), and `wcb/job-listings` (card grid). The three blocks coordinate via a `wcb:search` CustomEvent. The current card shows title, company, badges, salary, and date — no excerpt.

This redesign consolidates the header UI into the `job-listings` block itself and upgrades the card to show a 2-line job description excerpt. The separate `job-search` and `job-filters` blocks are left unchanged for backwards compatibility.

---

## Design Decisions

- **Layout:** Top filter chips in the `job-listings` block header. Full-width card list below.
- **Card:** Standard card with 2-line excerpt (25-word trim). Logo, title, meta, excerpt, tags row, View Job CTA.
- **Filters:** Chip groups for job type, remote toggle, and experience level. Single-select within each group.
- **Sort:** Newest / Oldest dropdown (date only in v1).
- **Active strip:** Dismissible chips beneath the chip bar when any filter is active.
- **Results count:** "Showing X of Y jobs" using `X-WCB-Total` response header.

---

## Files to Modify

| File | Change |
|------|--------|
| `api/endpoints/class-jobs-endpoint.php` | Add `excerpt` to response; add `orderby`/`order` param support |
| `blocks/job-listings/render.php` | Add excerpt + filter taxonomy options to state; replace toolbar HTML with chip header |
| `blocks/job-listings/view.js` | New filter state, getters, actions; update `*fetchJobs`; keep `wcb:search` listener |
| `blocks/job-listings/style.css` | Chip bar, active filter strip, excerpt, sort select styles |

All paths relative to `wp-content/plugins/wp-career-board/`.

---

## API Changes

### 1. Add `excerpt` field

In `prepare_item_for_response_array()` (~line 722), add after `'description'`:

```php
'excerpt' => wp_trim_words( strip_tags( $post->post_content ), 25, '…' ),
```

Add to `get_item_schema()` under `properties`:

```php
'excerpt' => array( 'type' => 'string', 'readonly' => true ),
```

### 2. Add `orderby` / `order` params

In `get_items()`, after the `$author` param block (~line 198):

```php
$orderby_raw = (string) ( $request->get_param( 'orderby' ) ?? 'date' );
$order_raw   = (string) ( $request->get_param( 'order' ) ?? 'DESC' );
$args['orderby'] = in_array( $orderby_raw, array( 'date' ), true ) ? $orderby_raw : 'date';
$args['order']   = in_array( strtoupper( $order_raw ), array( 'ASC', 'DESC' ), true )
    ? strtoupper( $order_raw ) : 'DESC';
```

In `get_collection_params()`, add:

```php
'orderby' => array( 'type' => 'string', 'enum' => array( 'date' ), 'default' => 'date' ),
'order'   => array( 'type' => 'string', 'enum' => array( 'ASC', 'DESC' ), 'default' => 'DESC' ),
```

**Cache key note:** `get_items_cache_key()` hashes `$args`, so `orderby`/`order` are automatically included.

**`X-WCB-Total` header note:** The existing `get_items()` already emits `$response->header( 'X-WCB-Total', (string) $query->found_posts )` (for both cache hits and live queries). No new header code is needed. The `*fetchJobs` generator and `wcb:search` listener both read this header via `response.headers.get('X-WCB-Total')`.

---

## PHP Render Changes (`blocks/job-listings/render.php`)

### 1. Load filter taxonomy terms

After the `$wcb_bookmarks` block, load terms for chip options:

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

### 2. Add `excerpt` to job state items

Inside the `foreach` loop, add to `$wcb_jobs_state[]`:

```php
'excerpt' => wp_trim_words( strip_tags( $wcb_job_post->post_content ), 25, '…' ),
```

### 3. New state fields in `wp_interactivity_state()`

Before the state array, run a count query for the initial total:

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

Replace the existing `$wcb_state` array with:

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

### 4. Replace toolbar HTML

Replace the `.wcb-listings-toolbar` div (lines 157–179) with the new chip header:

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
        placeholder="<?php esc_attr_e( 'Search jobs…', 'wp-career-board' ); ?>"
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
        >×</button>
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

### 5. Add excerpt to job card template

Inside the `<article class="wcb-job-card">` template, after `.wcb-card-badges` and before `.wcb-card-footer`, add:

```html
<p class="wcb-card-excerpt"
   data-wp-class--wcb-shown="context.job.excerpt"
   data-wp-text="context.job.excerpt"
></p>
```

---

## JS Changes (`blocks/job-listings/view.js`)

### New state fields (initial values from PHP via `wp_interactivity_state`)

```
totalCount    : 0
searchQuery   : ''
activeFilters : {}   // { type: 'full-time', experience: 'senior', remote: '1' }
sortBy        : 'date_desc'
filterOptions : { types: [], experiences: [] }
```

### New getters

```js
get noActiveFilters() {
    return Object.keys( state.activeFilters ).length === 0 && ! state.searchQuery;
},
get hasActiveFilters() {
    return ! state.noActiveFilters;
},
// ⚠️ These getters rely on loop context from data-wp-each.
// isTypeActive must only be used inside data-wp-each--type templates.
// isExpActive must only be used inside data-wp-each--exp templates.
// Using either getter outside its respective template will return false silently.
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
    if ( f.remote )          chips.push( { key: 'remote', label: 'Remote' } );
    if ( state.searchQuery ) chips.push( { key: 'search', label: '"' + state.searchQuery + '"' } );
    return chips;
},
get resultsLabel() {
    const shown = state.jobs.length;
    const total = state.totalCount || shown;
    return shown + ' of ' + total + ' jobs';
},
```

### New actions

**`updateSearch( event )`** — debounced search input handler:
```js
updateSearch( event ) {
    state.searchQuery = event.target.value;
    // debounce: clear and reset a 350ms timer, then call *fetchJobs
    // Use a module-level debounce variable; do NOT store timers in state.
},
```

**`toggleTypeChip()`**:
```js
toggleTypeChip() {
    const ctx = getContext();
    const slug = ctx.type?.slug;
    if ( state.activeFilters.type === slug ) {
        delete state.activeFilters.type;
    } else {
        state.activeFilters = { ...state.activeFilters, type: slug };
    }
    // trigger fetch (via helper, see below)
},
```

**`toggleExpChip()`** — same pattern as `toggleTypeChip` but for `experience`.

**`toggleRemote()`**:
```js
toggleRemote() {
    if ( state.activeFilters.remote ) {
        delete state.activeFilters.remote;
    } else {
        state.activeFilters = { ...state.activeFilters, remote: '1' };
    }
    // trigger fetch
},
```

**`*removeFilter()`** — generator (must `yield` to trigger fetch for all key types):
```js
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
```

**`clearFilters()`**:
```js
clearFilters() {
    state.activeFilters = {};
    state.searchQuery   = '';
    // trigger fetch
},
```

**`changeSort( event )`**:
```js
changeSort( event ) {
    state.sortBy = event.target.value;
    // trigger fetch
},
```

### Refactored `*fetchJobs()` generator

Extract fetch logic into a shared generator. All filter/sort actions call `yield actions.applyFilters()` which is a thin wrapper that resets `page` to 1 and calls `yield actions.fetchJobs()`.

```js
*fetchJobs() {
    if ( state.loading ) return;
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
        if ( ! response.ok ) return;

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

*applyFilters() {
    state.page = 1;
    yield actions.fetchJobs();
},
```

**`*loadMore()`** — simplified now that logic is in `fetchJobs`:
```js
*loadMore() {
    state.page++;
    yield actions.fetchJobs();
},
```

**Debounce** — implement as a module-level variable outside the `store()` call:

```js
let searchDebounceTimer = null;

// Inside updateSearch (non-generator, synchronous action):
updateSearch( event ) {
    state.searchQuery = event.target.value;
    clearTimeout( searchDebounceTimer );
    searchDebounceTimer = setTimeout( () => {
        // store( 'wcb-job-listings' ).actions.applyFilters() returns a Promise
        // because generator actions are Promise-returning in WP 6.9+.
        store( 'wcb-job-listings' ).actions.applyFilters();
    }, 350 );
},
```

`store( 'wcb-job-listings' )` returns the registered store object (same `{ state, actions, ... }` reference). Calling a generator action this way is safe and documented for WP 6.9+. Do NOT call `yield` from within a `setTimeout` — the `setTimeout` callback calls `applyFilters()` as a regular Promise, not a delegated generator.

**`wcb:search` event listener** — keep for backwards compatibility. Rewrite using the same plain-`fetch` pattern as the existing listener (not `yield`), and update `state.totalCount` from the `X-WCB-Total` response header:

```js
document.addEventListener( 'wcb:search', function( event ) {
    const detail  = event.detail ?? {};
    const query   = detail.query   ?? '';
    const filters = detail.filters ?? {};

    // Legacy event overrides all chip filters.
    state.searchQuery   = query;
    state.activeFilters = {};
    state.page          = 1;
    state.loading       = true;
    state.jobs          = [];

    const url = new URL( state.apiBase );
    url.searchParams.set( 'page',     '1' );
    url.searchParams.set( 'per_page', String( state.perPage ) );
    if ( query ) url.searchParams.set( 'search', query );
    Object.keys( filters ).forEach( k => url.searchParams.set( k, filters[ k ] ) );

    fetch( url.toString() )
        .then( function( response ) {
            if ( ! response.ok ) { state.loading = false; return undefined; }
            state.totalCount = parseInt( response.headers.get( 'X-WCB-Total' ) || '0', 10 );
            return response.json();
        } )
        .then( function( jobs ) {
            if ( ! jobs ) return;
            state.jobs    = jobs;
            state.hasMore = jobs.length === state.perPage;
            state.loading = false;
        } )
        .catch( function() { state.loading = false; } );
} );
```

---

## CSS Changes (`blocks/job-listings/style.css`)

### New local tokens (append to `:root`)

```css
--wcb-chip-h:            32px;
--wcb-chip-radius:       20px;
--wcb-chip-px:           14px;
--wcb-chip-font-size:    0.8125rem;
--wcb-chip-active-bg:    var( --wcb-primary, #2563eb );
--wcb-chip-active-color: #fff;
```

### New rule blocks (append to file)

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

/* ── Results count (in header) ────────────────────────────────────────────── */
.wcb-listings-header .wcb-results-count {
    font-size: 0.8125rem;
    color: #6b7280;
    margin: 0;
    padding-bottom: 0.75rem;
}

/* ── Card excerpt ─────────────────────────────────────────────────────────── */
/* Hidden by default; .wcb-shown enables the 2-line clamp display. */
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

/* ── Responsive ──────────────────────────────────────────────────────────── */
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
        display: none; /* collapsed on mobile — show via overflow scroll */
    }
}
```

---

## Backwards Compatibility

- `wcb/job-search` and `wcb/job-filters` blocks are not modified. They continue to work via the `wcb:search` event.
- The layout toggle (`setGrid`, `setList`, `.wcb-layout-toggle`) is removed from `render.php` HTML but the `isGrid`/`isList` getters and `setGrid`/`setList` actions remain in `view.js`. The `.wcb-grid` / `.wcb-list` CSS stays.
- Any page using the old three-block layout still works. Pages upgraded to the new layout use `wcb/job-listings` only.

---

## Verification Checklist

1. **Excerpt shows:** Visit job directory → cards show a 2-line job description excerpt
2. **Type chip filter:** Click "Full-time" chip → jobs list reloads showing only full-time roles; "All" chip is deactivated
3. **Toggle off:** Click the active chip again → filter clears, all jobs reload
4. **Remote chip:** Click "Remote" → API called with `remote=1`; active filter strip shows "Remote ×"
5. **Experience chip:** Click "Senior" → API called with `experience=senior`
6. **Stacked filters:** Click "Full-time" then "Remote" → both show in active filter strip; API called with both params
7. **Remove filter:** Click × on "Full-time" in active strip → that filter removed, list refreshes
8. **Clear all:** Active filters present → click "Clear all" → all filters cleared, full list loads
9. **Search:** Type in search box → after 350ms debounce, list updates; search term shows in active strip
10. **Sort:** Change to "Oldest first" → list reloads with oldest jobs first
11. **Results count:** Shows "X of Y jobs" where Y matches X-WCB-Total header
12. **Load more:** Scroll to bottom → "Load more" still works, appends next page with current filters preserved
13. **Backwards compat:** A page with separate `wcb/job-filters` + `wcb/job-listings` blocks still filters correctly via `wcb:search` event
14. **REST endpoint:** `GET /wcb/v1/jobs?orderby=date&order=ASC` returns oldest-first results
15. **REST excerpt:** `GET /wcb/v1/jobs` response objects include `excerpt` field (25-word trim)
