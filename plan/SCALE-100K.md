# Scale audit: 100k jobs / resumes / companies

Generated 2026-05-13 via task #70. Goal: support 100k `wcb_job` + 100k `wcb_resume` + 100k `wcb_company` posts with sub-second listing TTFB and zero fatal queries on a stock LEMP server.

Every line item cites the offending `file:line` and the concrete fix.

## BLOCK (must ship before any 100k claim)

- **`blocks/company-archive/render.php:65`** — loads ALL jobs (`numberposts=-1`) on every Companies archive page just to count open positions per company. At 100k jobs this is a fatal query and a 100k-row in-memory loop. Fix: one aggregate SQL — `SELECT meta_value AS company_id, COUNT(*) FROM {wpdb->postmeta} pm JOIN {wpdb->posts} p ON p.ID=pm.post_id WHERE meta_key='_wcb_company_id' AND p.post_status='publish' AND p.post_type='wcb_job' AND meta_value IN (…visible co ids…) GROUP BY meta_value;` cached per request.

- **`blocks/company-archive/render.php:162`** — loads ALL company IDs (`numberposts=-1`) just to build the "Industry" filter dropdown's distinct-value list. At 100k companies this is 100k row IDs in memory + 100k `get_post_meta()` calls in the foreach at line 174-180. Fix: replace with one prepared aggregate `SELECT DISTINCT meta_value FROM postmeta WHERE meta_key='_wcb_industry'` cached as a transient (1h TTL, busted on `save_post_wcb_company`).

- **`blocks/company-archive/render.php:174-180`** — N+1: `foreach($wcb_all_co_ids){ get_post_meta() }` over the unbounded set above. Eliminated automatically when the previous item is fixed.

- **`api/endpoints/class-jobs-endpoint.php:890`** — REST endpoint with `posts_per_page=-1` in the request path. Fix: cap at `min($per_page, 100)` and require pagination.

- **`api/endpoints/class-companies-endpoint.php:378`** — same pattern; REST endpoint with `numberposts=-1`. Fix: cap + paginate.

- **`api/endpoints/class-employers-endpoint.php:727`** — REST endpoint with `posts_per_page=-1`. Fix: cap + paginate.

- **`api/endpoints/class-pipeline-endpoint.php:128`** (Pro) — pipeline endpoint with `posts_per_page=-1`. Fix: cap, paginate, or scope to one board/stage.

- **`blocks/job-listings/render.php:50`** — when `savedBy>0`, `numberposts=-1` returns every bookmarked job at once. A power user with 1k bookmarks blocks the whole request. Fix: cap at 50 + paginate via the existing `?savedBy=…&page=N` REST shape.

- **`blocks/my-applications/render.php:37`** (Pro) — applications block returns every application unbounded. Fix: cap + paginate, expose `perPage` attribute.

- **`blocks/board-switcher/render.php:18`** (Pro) — loads every board unbounded; on multi-tenant Pro installs with 100+ boards this slows every page that mounts the switcher. Fix: cap at 50, paginate, fall back to autocomplete past the cap.

- **`blocks/featured-companies/render.php:72`** (Pro) — `numberposts=-1` on the open-jobs grouping query. Same single-aggregate fix as company-archive line 65.

- **Search at scale** — `api/endpoints/class-jobs-endpoint.php:357` uses `LIKE '%term%'` on `post_title` + company name. O(n) full-scan at 100k. Fix: declare a FULLTEXT index on `wp_posts(post_title)` (or a sidecar `wcb_job_search` table) and switch to `MATCH() AGAINST()` once `strlen($term) >= 3`.

- **Pro `wcb_field_values` missing composite index** — `core/class-pro-install.php:541-544` declares `KEY post_id` and `KEY field_key` separately. The canonical query is "all field values for post_id X" — needs `KEY post_field (post_id, field_key)` for the index lookup. At 100k posts × ~10 fields = 1M rows this index is the difference between an index seek and a table scan.

## RISK (degrades at 100k, not fatal)

- **`blocks/job-listings/render.php:302`** — count query re-applies the full filter chain (board_id + meta_query + Pro filter listeners) and reads `found_posts`. At 100k rows with stacked meta_query joins the COUNT(*) is the slow path of the request. Fix: cache the filtered count under a `wcb_listings_count` group with `wp_cache_set_last_changed( 'posts' )` keyed by serialised query args.

- **`api/endpoints/class-jobs-endpoint.php:294`**, **`api/endpoints/class-companies-endpoint.php:220`**, **`api/endpoints/class-applications-endpoint.php:521`** — all return `total = found_posts` on every page. Same caching fix as above; today every paginated request re-runs the count.

- **`blocks/job-stats/render.php:21,29,37`** — three `wp_count_posts()` calls on a public block. WP's `wp_count_posts` is cached internally via `wp_cache_get_last_changed('posts')`, so per-request this is fine — but flag for monitoring; some object-cache backends mis-bust the group.

- **`blocks/employer-dashboard/render.php:66`** — `wp_count_posts('wcb_job')` returns counts across the whole site, not just this employer's posts. At 100k jobs the answer is correct but the response includes statuses the employer doesn't own. Fix: scope via author counts (`COUNT(*) FROM posts WHERE author=X GROUP BY post_status`) cached per user.

- **Pro `wcb_job_alerts` index** — `core/class-pro-install.php:560` has `KEY user_id` only. Daily cron sweep filters by `frequency` + `last_sent_at`; needs `KEY frequency_sent (frequency, last_sent_at)` so the cron picks up due alerts without a full-table scan.

- **Pro `wcb_field_groups` index** — `core/class-pro-install.php:511` has `KEY board_id` only. Canonical query is `(board_id, entity_type)` — needs composite.

- **Pro `wcb_job_boards` reverse-index** — `core/class-pro-install.php:554` has `UNIQUE (job_id, board_id)`. Forward lookup is fast; reverse "all jobs on board X" needs a separate `KEY board_id (board_id)`.

- **`integrations/buddypress/class-bp-pro-integration.php:131-133,239-241`** — `Posted <span>%d</span>` and `Saved <span>%d</span>` counters generated for every tab render. Currently these query `wp_count_posts(author=…)` and `count(get_user_meta(_wcb_bookmark))`. Fine for 99% of users, but for an employer with 1k+ posted jobs viewed from BP profile every page load, cache per user with `last_changed('posts')` busting.

- **`blocks/job-form/render.php:148`** — board picker calls `get_posts('wcb_board', posts_per_page=50)`. Fine at 50, but Pro multi-tenant sites with 100+ boards silently truncate. Decide: cap and surface a "filtered" UI hint, or paginate the picker.

## MINOR (polish)

- Several admin pages still issue `found_posts` for the WP list table even with a cached `wp_count_posts`. Low blast radius (admin only) but worth one transient pass.
- `blocks/company-archive/render.php:38` — query already sets the right shape; the count branch (`found_posts`) is hot but small if BLOCK items are fixed first.
- `modules/gdpr/class-gdpr-module.php:102,172` — `numberposts=-1` in the data-subject-access path. Rare execution, OK to leave but cap the response for safety.

## What's already solid

- `blocks/job-listings/render.php:91-100` primes `update_postmeta_cache()` + `update_object_term_cache()` before the row loop. The card-build loop at line 110+ then reads from cache, not DB. This is the canonical pattern; replicate to every other archive block.
- REST envelope is `{items, total, pages, has_more}` and `has_more = (offset+count)<total` (not the wrong `count===limit`). This is correct.
- `Pro wcb_notifications` declares `KEY user_id_read (user_id, is_read)` composite — the right shape for the bell-icon "unread for me" query.
- The shortcode-CSS enqueue fix in commit `48de947` closes the surface where shortcode-only pages skipped frontend-components.css, which itself was a scale-on-page-builder hazard.

## Suggested execution order

1. **Cap every `-1` in the request path** (BLOCK list, ~12 sites). Mechanical, low-risk, immediately unblocks deploys.
2. **Refactor the company-archive open-jobs aggregate** to one SQL with index lookup — the single biggest visible-page win.
3. **Add composite indexes** to Pro tables (`wcb_field_values(post_id,field_key)`, `wcb_field_groups(board_id,entity_type)`, `wcb_job_alerts(frequency,last_sent_at)`, `wcb_job_boards(board_id)`). One migration, no breaking change.
4. **Wrap the listings COUNT(*)** in a `wp_cache_set_last_changed('posts')`-keyed cache. ~30 lines.
5. **FULLTEXT search index** + `MATCH() AGAINST()` switch behind a `strlen($term) >= 3` gate. Falls back to LIKE for short terms.
6. **Re-run wppqa_audit_plugin** post-fix, confirm `failed=0` on every check, then ship.

A first-pass implementation following this order can land in two PRs (Free + Pro) and pass smoke without a re-design.
