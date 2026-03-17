# Search Enhancement & REST Caching Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close three frontend/backend gaps — salary+remote filter UI, company-name keyword search, and REST API caching — with zero changes to existing filter logic or data schema.

**Architecture:** All filter state lives in the shared `wcb-search` Interactivity API namespace; job-filters block adds salary/remote inputs that push into the same event bus already consumed by job-listings; search extension hooks `posts_search` temporarily per-request; caching layer wraps `get_items()` with transients keyed by a busted version number.

**Tech Stack:** WordPress Interactivity API (`@wordpress/interactivity`), `WP_Query`, `$wpdb->prepare()`, `set_transient` / `get_transient`, PHP 8.1+, WPCS.

---

## File Map

### Task T27a — Salary + Remote Filter UI
| File | Change |
|------|--------|
| `blocks/job-filters/render.php` | Read salary/remote GET params; add number inputs + checkbox to filters row; seed into state |
| `blocks/job-filters/view.js` | Extend `updateFilter()` to handle checkbox type; no new actions needed |
| `blocks/job-filters/style.css` | Style salary range pair + remote checkbox inline with existing filter row |

### Task T27b — Company Name in Keyword Search
| File | Change |
|------|--------|
| `api/endpoints/class-jobs-endpoint.php` | Add temporary `posts_search` filter in `get_items()` that OR-extends the WHERE clause with `_wcb_company_name` meta subquery |

### Task T28 — REST API Caching
| File | Change |
|------|--------|
| `api/endpoints/class-jobs-endpoint.php` | Transient cache wrap in `get_items()`; version-based cache key; `Cache-Control` headers on both `get_items()` and `get_item()`; `save_post_wcb_job` hook to bump cache version |

### Task T29 — Extension Hooks for Pro (radius search prerequisite)
| File | Change |
|------|--------|
| `api/endpoints/class-jobs-endpoint.php` | Wrap `get_collection_params()` return in `apply_filters('wcb_jobs_collection_params', ...)`; wrap post-query job array in `apply_filters('wcb_jobs_post_filter', $jobs, $query, $request)` |

---

## Task T27a: Salary Range + Remote Filter UI

**Files:**
- Modify: `blocks/job-filters/render.php`
- Modify: `blocks/job-filters/view.js`
- Modify: `blocks/job-filters/style.css`

### Why this works with no backend changes
`job-listings/view.js` already forwards ALL URL params to the REST API:
```js
const searchParams = new URLSearchParams( window.location.search );
for ( const [ key, val ] of searchParams ) {
    url.searchParams.set( key, val );
}
```
`job-filters/view.js` already sets URL params and dispatches `wcb:search` with `state.filters`. We only need to add inputs that write `salary_min`, `salary_max`, `remote` into `state.filters` — the rest of the pipeline works unchanged.

---

- [ ] **Step 1: Extend `render.php` — read GET params**

In `blocks/job-filters/render.php`, after the existing `$wcb_filter_exp` block (line ~31), add:

```php
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$wcb_filter_salary_min = isset( $_GET['salary_min'] )
	? (int) $_GET['salary_min']
	: 0;
$wcb_filter_salary_max = isset( $_GET['salary_max'] )
	? (int) $_GET['salary_max']
	: 0;
$wcb_filter_remote     = ! empty( $_GET['remote'] );
// phpcs:enable WordPress.Security.NonceVerification.Recommended
```

- [ ] **Step 2: Extend `render.php` — include in `$wcb_active_filters`**

Change the `$wcb_active_filters` block to:

```php
$wcb_active_filters = (object) array_filter(
	array(
		'wcb_category'   => $wcb_filter_category,
		'wcb_job_type'   => $wcb_filter_type,
		'wcb_location'   => $wcb_filter_location,
		'wcb_experience' => $wcb_filter_exp,
		'salary_min'     => $wcb_filter_salary_min ?: '',
		'salary_max'     => $wcb_filter_salary_max ?: '',
		'remote'         => $wcb_filter_remote ? '1' : '',
	)
);
```

- [ ] **Step 3: Extend `render.php` — add salary + remote markup**

Inside `<div class="wcb-filters-row">`, after the last `</select>` (experience dropdown), add:

```php
		<div class="wcb-filter-salary">
			<input
				type="number"
				class="wcb-filter-input"
				name="salary_min"
				min="0"
				step="1000"
				placeholder="<?php esc_attr_e( 'Min salary', 'wp-career-board' ); ?>"
				value="<?php echo esc_attr( $wcb_filter_salary_min ? (string) $wcb_filter_salary_min : '' ); ?>"
				aria-label="<?php esc_attr_e( 'Minimum salary', 'wp-career-board' ); ?>"
				data-wp-on--change="actions.updateFilter"
				data-wcb-filter="salary_min"
			/>
			<input
				type="number"
				class="wcb-filter-input"
				name="salary_max"
				min="0"
				step="1000"
				placeholder="<?php esc_attr_e( 'Max salary', 'wp-career-board' ); ?>"
				value="<?php echo esc_attr( $wcb_filter_salary_max ? (string) $wcb_filter_salary_max : '' ); ?>"
				aria-label="<?php esc_attr_e( 'Maximum salary', 'wp-career-board' ); ?>"
				data-wp-on--change="actions.updateFilter"
				data-wcb-filter="salary_max"
			/>
		</div>

		<label class="wcb-filter-remote">
			<input
				type="checkbox"
				name="remote"
				value="1"
				<?php checked( $wcb_filter_remote ); ?>
				data-wp-on--change="actions.updateFilter"
				data-wcb-filter="remote"
			/>
			<?php esc_html_e( 'Remote only', 'wp-career-board' ); ?>
		</label>
```

- [ ] **Step 4: Update `view.js` — handle checkbox type**

In `blocks/job-filters/view.js`, replace the first line of `updateFilter()`:

```js
// Before:
const value = event.target.value;

// After:
const value = event.target.type === 'checkbox'
    ? ( event.target.checked ? '1' : '' )
    : event.target.value;
```

Full updated `updateFilter` action:

```js
updateFilter( event ) {
    const key   = event.target.dataset.wcbFilter;
    const value = event.target.type === 'checkbox'
        ? ( event.target.checked ? '1' : '' )
        : event.target.value;

    const filters = Object.assign( {}, state.filters );

    if ( value ) {
        filters[ key ] = value;
    } else {
        delete filters[ key ];
    }

    state.filters = filters;

    const params = new URLSearchParams( window.location.search );

    if ( value ) {
        params.set( key, value );
    } else {
        params.delete( key );
    }

    const wcbFilterQs = params.toString();
    window.history.pushState( {}, '', wcbFilterQs ? '?' + wcbFilterQs : window.location.pathname );

    document.dispatchEvent(
        new CustomEvent( 'wcb:search', {
            detail: {
                query:   state.query,
                filters: state.filters,
            },
        } )
    );
},
```

- [ ] **Step 5: Add CSS to `style.css`**

Append to `blocks/job-filters/style.css`:

```css
.wcb-filter-salary {
	display: flex;
	gap: 6px;
}

.wcb-filter-input {
	width: 120px;
	padding: 8px 10px;
	border: 1px solid #ddd;
	border-radius: 4px;
	font-size: 14px;
}

.wcb-filter-remote {
	display: flex;
	align-items: center;
	gap: 6px;
	font-size: 14px;
	cursor: pointer;
	white-space: nowrap;
}

.wcb-filter-remote input[type="checkbox"] {
	width: 16px;
	height: 16px;
	cursor: pointer;
}
```

- [ ] **Step 6: Build assets**

```bash
cd "/Users/varundubey/Local Sites/job-portal/app/public/wp-content/plugins/wp-career-board"
npm run build
```

Expected: no errors; `blocks/job-filters/view.js` (built) updated.

- [ ] **Step 7: WPCS check**

```
mcp__wpcs__wpcs_fix_file  → blocks/job-filters/render.php
mcp__wpcs__wpcs_check_staged
```

Expected: 0 errors.

- [ ] **Step 8: Manual verify**

Navigate to a page with job-filters + job-listings blocks. Enter `50000` in Min salary → listings update via `wcb:search`. Check Remote only → listings show only remote jobs. Reload page → filters are preserved from URL params.

- [ ] **Step 9: Commit**

```bash
git add blocks/job-filters/render.php blocks/job-filters/view.js blocks/job-filters/style.css
git commit -m "feat(wcb): T27a — salary range + remote filter UI in job-filters block"
```

---

## Task T27b: Company Name in Keyword Search

**Files:**
- Modify: `api/endpoints/class-jobs-endpoint.php` — `get_items()` method

### Why `posts_search` filter is the right approach
`WP_Query s=` builds a `$search` string that looks like:
```sql
AND (((wp_posts.post_title LIKE '%term%') OR (wp_posts.post_content LIKE '%term%')))
```
The `posts_search` filter receives this complete string. We append an OR subquery against `wp_postmeta` for `_wcb_company_name`. Using a subquery (`EXISTS`) avoids duplicate rows that a JOIN would produce.

---

- [ ] **Step 1: Add private method `extend_search_to_company()`**

In `class-jobs-endpoint.php`, add as a private method after `get_items()`:

```php
/**
 * Extend keyword search to include the denormalized company name meta.
 *
 * Appended as a temporary posts_search filter in get_items() so the
 * subquery only fires on wcb_job searches that have an 's' param.
 *
 * @since 1.0.0
 *
 * @param string    $search  Existing SQL search clause.
 * @param \WP_Query $query   Current WP_Query instance.
 * @return string
 */
private function extend_search_to_company( string $search, \WP_Query $query ): string {
    global $wpdb;

    if ( ! $search || 'wcb_job' !== $query->get( 'post_type' ) ) {
        return $search;
    }

    $term   = $query->get( 's' );
    $like   = '%' . $wpdb->esc_like( (string) $term ) . '%';

    // Append OR EXISTS subquery — avoids duplicate rows from a JOIN.
    $search .= $wpdb->prepare(
        " OR EXISTS (
            SELECT 1 FROM {$wpdb->postmeta} pm
            WHERE pm.post_id = {$wpdb->posts}.ID
              AND pm.meta_key = '_wcb_company_name'
              AND pm.meta_value LIKE %s
        )",
        $like
    );

    return $search;
}
```

- [ ] **Step 2: Wire the filter in `get_items()`**

In `get_items()`, immediately before `$query = new \WP_Query( $args );`, add:

```php
if ( ! empty( $args['s'] ) ) {
    add_filter( 'posts_search', array( $this, 'extend_search_to_company' ), 10, 2 );
}
```

Immediately after `$query = new \WP_Query( $args );`, add:

```php
remove_filter( 'posts_search', array( $this, 'extend_search_to_company' ), 10 );
```

- [ ] **Step 3: WPCS + PHPStan check**

```
mcp__wpcs__wpcs_fix_file  → api/endpoints/class-jobs-endpoint.php
mcp__wpcs__wpcs_check_staged
mcp__wpcs__wpcs_phpstan_check
```

Expected: 0 errors.

- [ ] **Step 4: Manual verify**

Using a REST client or browser: `GET /wp-json/wcb/v1/jobs?search=Acme` — should return jobs where `_wcb_company_name` contains "Acme" even if the job title does not. Also confirm jobs whose title matches still appear (existing behaviour intact).

- [ ] **Step 5: Commit**

```bash
git add api/endpoints/class-jobs-endpoint.php
git commit -m "feat(wcb): T27b — extend keyword search to include company name meta"
```

---

## Task T28: REST API Caching Layer

**Files:**
- Modify: `api/endpoints/class-jobs-endpoint.php` — `get_items()`, `get_item()`, `register_routes()`

### Caching strategy
- **Version-keyed transients**: cache key = `wcb_jobs_{version}_{md5(args)}`. On `save_post_wcb_job`, increment `wcb_jobs_cache_v` option. Old transients expire in 5 min naturally — no need to enumerate and delete them.
- **Only cache public listing queries**: skip cache when authenticated params are present (e.g. `author` scoped to current user). In practice, the listing endpoint is `permission_callback => '__return_true'` so all requests are eligible.
- **`Cache-Control` headers**: public listing = `public, max-age=300`; single job = `public, max-age=3600`. These allow CDN/Nginx to serve without hitting PHP at all.
- **Single job** (`get_item`): WordPress object cache already caches the post + meta. Only add the `Cache-Control` header — no transient needed.

---

- [ ] **Step 1: Add `get_items_cache_key()` private method**

```php
/**
 * Build a version-namespaced transient key for a job listing query.
 *
 * The version is bumped on every job save, causing clients to fetch fresh
 * data immediately while stale transients expire within their TTL.
 *
 * @since 1.0.0
 *
 * @param array<string, mixed> $args WP_Query args array.
 * @return string Transient key (max 172 chars, well within 191-char limit).
 */
private function get_items_cache_key( array $args ): string {
    $version = (int) get_option( 'wcb_jobs_cache_v', 0 );
    return 'wcb_jobs_' . $version . '_' . md5( (string) wp_json_encode( $args ) );
}
```

- [ ] **Step 2: Add cache-bust hook in `register_routes()`**

At the end of `register_routes()`, add:

```php
add_action(
    'save_post_wcb_job',
    static function (): void {
        $v = (int) get_option( 'wcb_jobs_cache_v', 0 );
        update_option( 'wcb_jobs_cache_v', $v + 1, false );
    }
);
```

- [ ] **Step 3: Wrap `get_items()` with transient cache**

At the **top** of `get_items()`, after building `$args` and before the `posts_search` filter block, add:

```php
$cache_key    = $this->get_items_cache_key( $args );
$cached_value = get_transient( $cache_key );

if ( false !== $cached_value && is_array( $cached_value ) ) {
    $response = rest_ensure_response( $cached_value['jobs'] );
    $response->header( 'X-WCB-Total', $cached_value['total'] );
    $response->header( 'X-WCB-TotalPages', $cached_value['pages'] );
    $response->header( 'Cache-Control', 'public, max-age=300' );
    return $response;
}
```

At the **end** of `get_items()`, replace the existing `return $response;` with:

```php
set_transient(
    $cache_key,
    array(
        'jobs'  => $jobs,
        'total' => (string) $query->found_posts,
        'pages' => (string) $query->max_num_pages,
    ),
    5 * MINUTE_IN_SECONDS
);

$response = rest_ensure_response( $jobs );
$response->header( 'X-WCB-Total', (string) $query->found_posts );
$response->header( 'X-WCB-TotalPages', (string) $query->max_num_pages );
$response->header( 'Cache-Control', 'public, max-age=300' );
return $response;
```

- [ ] **Step 4: Add `Cache-Control` header to `get_item()`**

In `get_item()`, replace the final `return rest_ensure_response(...)` with:

```php
$single_response = rest_ensure_response( $this->prepare_item_for_response_array( $post ) );
$single_response->header( 'Cache-Control', 'public, max-age=3600' );
return $single_response;
```

- [ ] **Step 5: WPCS + PHPStan check**

```
mcp__wpcs__wpcs_fix_file  → api/endpoints/class-jobs-endpoint.php
mcp__wpcs__wpcs_check_staged
mcp__wpcs__wpcs_phpstan_check
```

Expected: 0 errors.

- [ ] **Step 6: Manual verify**

1. `GET /wp-json/wcb/v1/jobs` — response includes `Cache-Control: public, max-age=300`.
2. Hit it again — second response is immediate (transient hit); response body identical.
3. Publish a new job in wp-admin — `wcb_jobs_cache_v` option increments.
4. `GET /wp-json/wcb/v1/jobs` — new job appears (cache miss due to version bump).
5. `GET /wp-json/wcb/v1/jobs/{id}` — response includes `Cache-Control: public, max-age=3600`.

- [ ] **Step 7: Commit**

```bash
git add api/endpoints/class-jobs-endpoint.php
git commit -m "feat(wcb): T28 — REST API caching (transients + Cache-Control headers) for job listings"
```

---

## Task T29: Extension Hooks for Pro (radius search prerequisite)

**Files:**
- Modify: `api/endpoints/class-jobs-endpoint.php` — `get_collection_params()`, `get_items()`

Pro's geo-radius feature needs to:
1. Register `lat`, `lng`, `radius` as accepted REST params (without touching free plugin's schema)
2. Post-filter the query results by Haversine distance

Both are done via filters. Free plugin wraps two spots in its code; Pro hooks them. Zero free-plugin logic changes — just two `apply_filters()` calls.

---

- [ ] **Step 1: Wrap `get_collection_params()` return**

In `get_collection_params()`, change the final `return array( ... );` to:

```php
return (array) apply_filters( 'wcb_jobs_collection_params', array(
    // ... existing params unchanged ...
) );
```

- [ ] **Step 2: Wrap post-query job array in `get_items()`**

After `$jobs = array_map( array( $this, 'prepare_item_for_response_array' ), $query->posts );`, add:

```php
/**
 * Filters the prepared job items before the response is built.
 *
 * Pro uses this to apply Haversine geo-radius post-filtering when
 * lat/lng/radius params are present in the request.
 *
 * @param array<int, array<string, mixed>> $jobs    Prepared job items.
 * @param \WP_Query                        $query   The underlying WP_Query.
 * @param \WP_REST_Request                 $request Full request object.
 */
$jobs = (array) apply_filters( 'wcb_jobs_post_filter', $jobs, $query, $request );
```

- [ ] **Step 3: WPCS check**

```
mcp__wpcs__wpcs_fix_file  → api/endpoints/class-jobs-endpoint.php
mcp__wpcs__wpcs_check_staged
```

Expected: 0 errors.

- [ ] **Step 4: Verify filters fire**

Add a temporary `add_filter('wcb_jobs_collection_params', function($p){ error_log('PARAMS:' . count($p)); return $p; });` in a mu-plugin, hit the endpoint, confirm error_log shows the param count. Remove test code.

- [ ] **Step 5: Commit**

```bash
git add api/endpoints/class-jobs-endpoint.php
git commit -m "feat(wcb): T29 — wcb_jobs_collection_params + wcb_jobs_post_filter hooks for Pro extension"
```

---

## Progress Tracker Updates

After completing all tasks, update `docs/PLAN.md`:

```
| T27a | Salary range + remote filter UI in job-filters block | ✅ {date} · `{hash}` |
| T27b | Company name included in keyword search | ✅ {date} · `{hash}` |
| T28  | REST API caching (transients + Cache-Control headers) | ✅ {date} · `{hash}` |
| T29  | wcb_jobs_collection_params + wcb_jobs_post_filter hooks | ✅ {date} · `{hash}` |
```
