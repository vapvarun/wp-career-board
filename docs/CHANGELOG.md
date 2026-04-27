# Changelog

## 1.1.0 — 2026-04-27

### Added

- **Single-page job form** — new `wp-career-board/job-form-simple` block with `[wcb_job_form_simple]` shortcode, sibling of the multi-step wizard.
- **Bulk applicant CSV export** in the Applications admin list table.
- **Salary range slider** popover on the Find Jobs filter chip bar.
- **Application deadline reminder cron** — daily sweep, fires `wcb_deadline_reminder` for bookmarked-but-unapplied jobs at 3-day and 1-day buckets. New `EmailDeadlineReminder` class.
- **Featured timed expiry** — `WCB\Modules\Jobs\FeaturedExpiry` daily cron, configurable `apply_featured_days` (default 30).
- **Resume upload for guests** — multipart-aware path in `submit_application()`. Settings `apply_resume_required` and `apply_resume_max_mb`.
- **Application detail rebuild** — empty native edit screen replaced by composite metabox of six modular widgets via the new `WCB\Core\Widgets\WidgetRegistry`.
- **Modular widget system** — `[wcb_widget id="..."]` shortcode renders any registered widget on any page.
- **REST list envelope** — every list endpoint returns `{<resource>, total, pages, has_more}` shape. Legacy `X-WCB-Total` headers populated one cycle.
- **`boardId` and `metaFilter` attributes** on `job-listings` block.
- **`wcb_jobs_allowed_meta_filters` REST allowlist** for `?meta_<key>=<value>`.
- **Page-builder shortcode-attribute forwarding** — every WCB shortcode now passes attributes to the underlying block.
- **WPML / Polylang config** (`wpml-config.xml`).
- **Rich RSS feed** at `/jobs/feed/` with `wcb:` namespace and 12 per-item fields.
- **`wcb_application_form_fields_groups` filter** — declarative custom fields on the apply modal.
- **Local CI runner** — `bin/ci-local.sh` and `npm run ci`. Mirrors GitHub Actions: PHP lint, WPCS, PHPStan, size-limit.
- **GitHub Actions expansion** — WPCS, Plugin Check (PCP), size-limit jobs added; release-branch trigger pattern.
- **`docs/HOOKS.md`** — single-page reference for every customer-facing extension point.
- **`docs/PLAN-1.2.0.md`** — persisted roadmap for the next release.

### Changed

- **Token registry expansion** — `assets/css/frontend-tokens.css` adds `--wcb-transition-snappy`, status color quintets (`--wcb-{status}-fg/-bg-soft/-border`), teal accent family, avatar size scale, theme-aware primary tints. Both plugins swept for hardcoded literals.
- **REST permissions** — removed `current_user_can('manage_options')` fallback from `RestController::check_ability()` and `ApplicationsEndpoint::submit_permissions_check()`.
- **Per_page enforcement** — `JobsEndpoint::get_items()` clamps `per_page` to `[1, 100]` in the handler.
- **Custom DB tables** — `dbDelta` calls now declare `ENGINE=InnoDB` explicitly.
- **Single-job mobile reading order** — "About This Role" stays the first heading on phones (was inverted by `order: -1` on the sidebar).
- **Apply panel z-index** — 100000/100001 so Reign theme toggles no longer overlay form fields.

### Fixed

- **Uninstall SQL** — `uninstall.php` uses `$wpdb->prepare('DROP TABLE IF EXISTS %i', $table)` (WP 6.2+ `%i`).
- **Single-page form alignment** — salary 4-column row, classification 2×2 grid, deadline on own row.

### Hooks added

- Filters: `wcb_application_form_fields_groups`, `wcb_resume_form_fields`, `wcb_resume_form_initial_state`, `wcb_jobs_allowed_meta_filters`, `wcb_job_form_simple_initial_state`, `wcb_moderate_jobs_ability_check`
- Actions: `wcb_job_form_simple_extra_fields`, `wcb_resume_form_simple_extra_fields`, `wcb_deadline_reminder`, `wcb_featured_expired`

## 1.0.0 — 2026-03-25

Initial release.

### Features

- **Job Board Engine** — multi-entity system with Jobs, Companies, Candidates, and Applications CPTs
- **14 Gutenberg Blocks** — job-listings, job-single, job-form, job-search, job-search-hero, job-stats, recent-jobs, employer-dashboard, candidate-dashboard, company-archive, company-single, employer-registration, candidate-registration, application-form — all built with Interactivity API
- **REST API** — full CRUD for jobs, companies, candidates, and applications with auth gates via WordPress Abilities API
- **Admin UI** — custom list tables for Jobs (with moderation), Applications, Employers, Candidates, and Companies with bulk actions, search, and status filters
- **Email Notifications** — 6 events: application received, application status change, job approved, job rejected, new candidate registration, job expiry warning
- **Search & Filtering** — keyword, location, job type, experience level, salary range with Interactivity API-powered live filtering
- **SEO** — JobPosting schema.org LD+JSON on job singles, Open Graph meta tags, social sharing buttons
- **GDPR** — privacy data exporter and eraser for candidate applications and resumes
- **BuddyPress Integration** — member types for employers/candidates, activity stream posts on job publish and application submit
- **Setup Wizard** — guided onboarding for first-time configuration
- **Anti-Spam** — honeypot and rate limiting on public forms
- **Theme Integration** — Starter templates, BuddyX and Flavor theme compatibility
