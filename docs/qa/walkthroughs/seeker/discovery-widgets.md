---
id: seeker-discovery-widgets
priority: medium
personas: anonymous, admin
requires: mu:autologin, seed:jobs
last_verified: 2026-07-08
covers: featured-jobs block, recent-jobs block, job-stats block, wcb_featured_jobs shortcode, wcb_recent_jobs shortcode, wcb_job_stats shortcode, [wcb_widget] shortcode
---

# Discovery widgets — featured / recent / stats blocks render, and shortcodes work off the block editor

**Why this journey exists:** the discovery widgets (Featured Jobs, Recent Jobs, Job Stats) are the static
top-of-funnel surfaces a site owner drops on a home page or sidebar to pull candidates into the funnel. A
meaningful share of sites are classic-editor / Elementor / Divi / Bricks, so each block also has a shortcode
wrapper — and the generic `[wcb_widget]` shortcode exposes any registered widget on a non-block page. This
expands `customer/shortcode-renders-without-block-editor` to cover all three discovery blocks and grounds the
`[wcb_widget]` shortcode in its actual registration site.

## Steps

1. As `anonymous`, open a page that hosts the Featured Jobs block → expect the wrapper `.wcb-featured-jobs` with
   a `.wcb-featured-grid` containing `article.wcb-featured-card` items (each a `.wcb-featured-card-title a` job
   link). The block server-queries `wcb_job` where `_wcb_featured = 1`
   (`blocks/featured-jobs/render.php:32-40,56-90`). With no featured jobs seeded, a logged-in editor sees the
   `.wcb-featured-empty` hint and anonymous visitors see nothing (early `return`, `:42-54`).
2. Open a page hosting the Recent Jobs block → expect `.wcb-recent-jobs` with a `ul.wcb-job-widget-list` of
   `li.wcb-job-widget-item` rows (each a `.wcb-job-widget-link` to the job, `.wcb-job-widget-name`, and a
   `.wcb-job-widget-age` "N ago"). Server-queries the newest published `wcb_job` posts ordered by date DESC
   (`blocks/recent-jobs/render.php:25-33,58-118`).
3. Open a page hosting the Job Stats block → expect `.wcb-job-stats` with `.wcb-stat-item` tiles, each carrying a
   `.wcb-stat-count` number and `.wcb-stat-label` (Jobs / Companies / Candidates). Counts come from
   `wp_count_posts('wcb_job'|'wcb_company'|'wcb_resume')->publish` (`blocks/job-stats/render.php:19-41,47-56`);
   confirm the Jobs count matches `wp post list --post_type=wcb_job --post_status=publish --format=count`.
4. **Shortcode-off-block-editor — Featured Jobs.** As `admin`, create a Page whose `post_content` is ONLY
   `[wcb_featured_jobs]` (no block markup). Publish and open it → expect HTTP 200 and
   `document.querySelector('.wcb-featured-jobs')` to exist — the wrapper in
   `core/class-plugin.php::register_shortcodes()` maps `wcb_featured_jobs` → `wp-career-board/featured-jobs` and
   forwards attrs to `do_blocks()` (`core/class-plugin.php:386,446-486`).
5. Repeat step 4 with `[wcb_recent_jobs]` (→ `wp-career-board/recent-jobs`, `class-plugin.php:385`) and
   `[wcb_job_stats]` (→ `wp-career-board/job-stats`, `:384`) on separate Pages → expect `.wcb-recent-jobs` and
   `.wcb-job-stats` respectively to render with identical DOM + styling to the block version. Confirm
   `<body class="…">` contains `wcb-page` (shortcode detection fired via
   `apply_filters('wcb_search_active_shortcodes', …)`) and the block stylesheet auto-enqueued.
6. **`[wcb_widget]` generic shortcode.** The `[wcb_widget]` shortcode is registered separately from the block
   wrappers, at `core/widgets/class-widget-shortcode.php:37` (`add_shortcode('wcb_widget', …)`), and renders a
   `WidgetRegistry`-registered widget by `id`. Create a Page with `post_content` = `[wcb_widget]` (no `id`) →
   expect it to render NOTHING (empty string) rather than an error — the handler returns `''` when `id` is empty
   (`class-widget-shortcode.php:51-54`).
7. Create a Page with a valid registered widget id, e.g.
   `[wcb_widget id="application/applicant-card" application_id="<app-id>"]` (registered ids include
   `application/applicant-card`, `application/cover-letter`, `application/status-timeline` —
   `modules/applications/widgets/*`), open it → expect the widget markup to render and, because the id starts
   `application/`, the `wcb-application-detail` CSS/JS bundle to auto-enqueue
   (`class-widget-shortcode.php:64-68,97-123`). Every non-`id` attribute is forwarded to the widget's render args
   (`:56-62`).
8. tail debug.log diff → expect ZERO new fatal/warning lines.

## Teardown (safe to re-run)

```bash
# Delete the shortcode test pages by title (idempotent — no-op if absent).
for T in "wcb-featured-jobs-test" "wcb-recent-jobs-test" "wcb-job-stats-test" \
         "wcb-widget-empty-test" "wcb-widget-applicant-test"; do
  ID=$(wp post list --post_type=page --name="$T" --field=ID 2>/dev/null)
  [ -n "$ID" ] && wp post delete $ID --force
done
```

## Notes

- Two DISTINCT shortcode mechanisms are covered here, do not conflate them:
  1. **Per-block wrappers** (`wcb_featured_jobs`, `wcb_recent_jobs`, `wcb_job_stats`, and 15 more) built by the
     loop in `core/class-plugin.php:371-486` — generic, no per-block logic, just `do_blocks('<!-- wp:<name> … /-->')`.
     Because there is no per-block branching, proving one renders standalone proves all do (the rationale in
     `customer/shortcode-renders-without-block-editor`).
  2. **`[wcb_widget]`** (`core/widgets/class-widget-shortcode.php:37`) — a general host for `WidgetRegistry`
     widgets namespaced with a slash (e.g. `application/applicant-card`). Those widgets are primarily
     employer/admin application-detail surfaces, so `[wcb_widget]` is rarely a job-seeker-facing control; it is
     covered here as the generic shortcode contract requested by the catalog's discovery row.
- Featured / Recent / Stats blocks are fully STATIC server renders (no Interactivity API, no REST) — they read
  `get_posts()` / `wp_count_posts()` at render time (`featured-jobs/render.php:32`, `recent-jobs/render.php:25`,
  `job-stats/render.php:19-41`). That is why they carry no `data-wp-interactive` attribute, unlike job-listings.
- The catalog flags this row ⚠ "partial" (only Job Listings was previously covered by the shortcode journey).
  This walkthrough closes featured/recent/stats + `[wcb_widget]`; it does not re-cover the interactive
  job-listings shortcode (see `customer/shortcode-renders-without-block-editor`).
</content>
</invoke>
