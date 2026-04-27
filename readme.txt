=== WP Career Board ===
Contributors: wbcomdesigns
Tags: job board, jobs, employment, career, gutenberg
Requires at least: 6.9
Tested up to: 6.9
Stable tag: 1.1.0
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A complete job board for WordPress — built on Gutenberg blocks and the WordPress Interactivity API.

== Description ==

WP Career Board is a full-featured job board plugin built on Gutenberg blocks and the WordPress Interactivity API. Employer dashboards, candidate applications, company profiles, BuddyPress integration, and full applicant tracking — free. Included with Reign Theme.

WP Career Board is fully functional as a free plugin. [Pro](https://store.wbcomdesigns.com/wp-career-board-pro/) extends it with advanced hiring tools, monetisation, and automation.

== Pro Features ==

[WP Career Board Pro](https://store.wbcomdesigns.com/wp-career-board-pro/) adds:

* **Application Pipeline** — Drag-and-drop Kanban board with custom hiring stages per job
* **Resume Builder** — Rich candidate resume profiles with education, experience, and skills
* **Credits System** — Sell job posting credits to employers via Stripe
* **AI Job Descriptions** — Auto-generate compelling job posts with AI
* **Multi-Board Engine** — Run multiple independent job boards from a single install
* **Custom Field Builder** — Add custom fields to jobs, applications, and company profiles
* **Job Alerts** — Candidates receive email alerts for new matching jobs
* **Job Feed** — Publish jobs as RSS/XML feeds for aggregators
* **Maps Integration** — Display job locations on an interactive map
* **Priority Support** — Direct support from the Wbcom Designs team

== Installation ==

1. Upload the plugin zip via Plugins → Add New → Upload Plugin.
2. Activate the plugin.
3. Go to Career Board → Settings and run the Setup Wizard to create all required pages.

== Frequently Asked Questions ==

= Does this plugin require WooCommerce? =
No. WP Career Board is a standalone job board — no e-commerce plugin is needed.

= Can candidates apply without creating an account? =
Yes. Guest applications are supported out of the box with name, email, and cover letter fields.

= Does it work with block themes? =
Yes. All frontend UI uses Gutenberg blocks with the WordPress Interactivity API, compatible with both classic and block themes.

= How do I migrate from WP Job Manager? =
Go to Career Board → Import and use the built-in one-click migration tool. Your original WPJM data is never modified.

== Screenshots ==

1. Homepage with hero search, featured jobs, and industry categories.
2. Find Jobs page with keyword search, type filters, and grid/list toggle.
3. Single job page with apply panel, company sidebar, and share buttons.
4. Employer dashboard with job stats, recent applications, and active jobs.
5. Candidate dashboard with applications tracker and saved jobs.
6. Admin settings with tabbed configuration panels.

== Changelog ==

= 1.1.0 =
* New: Single-page job posting form — drop a one-screen form into any sidebar, modal, partner page, or page builder using the new "Job Form (Single-Page)" block or `[wcb_job_form_simple]` shortcode. Sits alongside the existing multi-step wizard.
* New: Bulk applicant CSV export — select applications in the admin list table and download a UTF-8 spreadsheet with applicant name, email, job, status, applied date, cover letter, and resume URL.
* New: Salary range slider on the Find Jobs page so candidates can filter by minimum and maximum salary live, with chip pills showing the active range.
* New: Application deadline reminder emails — candidates who saved a job but haven't applied get a 3-day and 1-day-out reminder automatically via daily cron.
* New: Resume upload on the apply form for guests too — the previous version only accepted resumes from logged-in candidates. PDF, DOC, and DOCX, with configurable required-vs-optional and size cap up to 20 MB.
* New: Featured listings now expire automatically after a configurable duration (default 30 days). Sets up Featured as a real time-bound paid SKU.
* New: Re-built Edit Application admin screen — applicant card with avatar and contact info, cover letter, resume preview with Open / Download buttons, status changer, quick-action buttons (Shortlist / Mark Hired / Reject / Message), and full status history. Replaces the previously-empty native post-edit screen.
* New: Modular widget system — every component on the rebuilt application screen also works as a `[wcb_widget id="..."]` shortcode on any page (e.g. partner profile pages, candidate-facing application detail pages).
* New: Rich RSS at /jobs/feed/ with company, salary (currency + period + min + max), location, type, category, tags, experience, deadline, apply URL — works in any RSS reader, IFTTT, Zapier flow.
* New: WPML / Polylang config so multilingual sites can translate job board CPTs, taxonomies, and key strings out of the box.
* New: Page-builder compatibility — every shortcode (`[wcb_job_listings]`, `[wcb_job_form]`, `[wcb_job_form_simple]`, etc.) now accepts attributes and forwards them to the block, so Elementor, Divi, Bricks, Beaver Builder, and classic editor users can scope blocks (e.g. `[wcb_job_listings boardId="42" perPage="6"]`).
* New: `boardId` and `metaFilter` attributes on the Job Listings block — render a board-scoped or custom-relationship-scoped listing anywhere without writing custom code.
* New: REST `?meta_<key>=<value>` query support for the jobs endpoint with safelist filter (`wcb_jobs_allowed_meta_filters`) so integrators can filter listings by registered custom meta from the frontend.
* New: Custom-field flexibility on every form — declarative filters (`wcb_job_form_fields`, `wcb_company_form_fields`, `wcb_candidate_form_fields`, `wcb_application_form_fields_groups`) take a single field-group schema documented in `docs/HOOKS.md`. Add custom fields with one `add_filter` call.
* New: Local CI runner via `npm run ci` — runs PHP lint, WPCS, PHPStan, and size-limit on the dev workstation before every push.
* Improvement: Site-wide design token system extended with status color triplets (`--wcb-{status}-fg`, `-bg-soft`, `-border`), `--wcb-transition-snappy`, avatar size scale, theme-aware primary tints (`--wcb-primary-soft`, `--wcb-primary-ring` via `color-mix`). All blocks now token-driven so theme overrides cascade automatically — Reign and BuddyX integrators override one place to restyle the whole plugin.
* Improvement: REST API list endpoints now return a structured envelope with `total`, `pages`, and `has_more` (legacy `X-WCB-Total` and `X-WCB-TotalPages` headers kept populated for one release cycle).
* Improvement: Mobile single-job page reading order corrected — "About This Role" now appears first on phones, was previously below Job Details / Share / Company. Apply panel z-index lifted above theme toggles so light/dark switchers no longer overlap form fields.
* Improvement: Plugin Check (PCP) and WPCS gates added to GitHub Actions CI alongside the existing PHP lint and PHPStan jobs. Branch trigger pattern expanded so release branches (1.1.0, 1.2.0, etc.) run CI on push.
* Improvement: Custom database tables now declare `ENGINE=InnoDB` explicitly so the plugin works on hosts whose MySQL default storage engine is still MyISAM.
* Improvement: Daily cron now schedules deadline reminders and featured-listing expiry sweeps automatically; deactivation cleanly clears scheduled events.
* Fix: Uninstall.php now uses `$wpdb->prepare('DROP TABLE IF EXISTS %i', $table)` with the WP 6.2+ identifier placeholder.
* Fix: Removed all `manage_options` capability fallbacks from REST permission checks — Abilities API is now the single permission source.
* Fix: Per-page parameter on the jobs list endpoint is now clamped to 100 in the handler (schema-only `maximum: 100` was previously a warning, not an enforced cap).
* Fix: `posts_per_page = -1` removed from frontend job query paths (replaced with bounded queries).
* Fix: BuddyPress group Jobs tab now correctly filters jobs to that group's board — the previous `defaultBoardId` block attribute wasn't declared and was silently dropped.
* Fix: Various visual alignment issues on the single-page job form (salary row breaking awkwardly, remote-deadline pair misaligning, button placement).

= 1.0.2 =
* New: Reign Theme dark-mode support — every WCB component (Find Jobs, Find Candidates, employer/candidate dashboards, companies archive, job and resume singles) re-colors cleanly when Reign's `html.dark-mode` class is active, driven by a new `html.dark-mode` token layer in `frontend.css` that maps `--wcb-*` to dark values.
* New: BuddyX Pro theme integration — WCB now inherits BuddyX Pro's theme palette (buttons, cards, borders, text, backgrounds) in both light and dark mode via a token bridge in `integrations/buddyxpro/assets/buddyx-compat.css` that re-maps `--wcb-*` to BuddyX Pro's own variables. WCB primary buttons pick up BuddyX Pro's customizer button color automatically.
* Fix: BuddyX Pro integration never actually loaded — the directory `integrations/buddyx-pro/` was hyphenated but the PSR-4 autoloader expected the case-folded namespace segment `buddyxpro`, so `BuddyxProIntegration::boot()` silently never ran and no BuddyX compatibility CSS was enqueued on BuddyX Pro sites. Directory renamed and paths updated.
* Fix: BuddyX Pro enqueue gate only matched `wcb_job` singular / archive — now mirrors the Reign gate and enqueues compatibility CSS on every WCB CPT (`wcb_job`, `wcb_application`, `wcb_company`, `wcb_resume`) and any page embedding a `wp-career-board/*` or `wcb/*` block.
* Fix: Find-jobs page heading (`.wcb-page-heading`) was flush against the filter chip bar — added `margin-block-start: var(--wcb-space-2xl)` (`lg` on ≤640px) so the heading has breathing room above the search + chips row.
* Fix: Avatar initials on job cards, company cards, job single header, and company profile headers were rendering dark-on-dark in dark mode because the letter color bound to `var(--wcb-base)` which flips to the card color. Letters now hardcoded to white — avatar backgrounds are always a dark slate regardless of mode.
* Fix: `.wcb-page-heading` hardcoded `color: #0f172a` — now uses `var(--wcb-contrast)` so it flips with dark mode instead of staying dark on a dark body.
* Fix: Reign compatibility stylesheet hardcoded `color: #475569 !important` on `.wcb-load-more-btn` and `color: #0f172a` on `.wcb-clear-all:hover` — now token-driven via `var(--wcb-text-secondary)` and `var(--wcb-contrast)` so dark-mode styling flows through.
* Fix: `.wcb-cbadge--type`, `.wcb-cbadge--exp`, and `.wcb-cbadge--location` on job cards hardcoded `#475569` text — now token-driven so the dark-mode surface/text pair stays readable.
* Fix: Current-page WCB block detector (`current_page_has_wcb_block()`) only matched `wp-career-board/*` block comments — now also matches pro `wcb/*` block comments so pages that embed only pro blocks correctly enqueue `wcb-frontend`, `wcb-frontend-tokens`, and `wcb-frontend-components`.
* Fix: Reign integration enqueue gate used a hardcoded list of four free-plugin block names — now triggers on any WCB CPT or any `wp-career-board/*` / `wcb/*` block comment so `reign-compat.css` loads on pro-only pages too.
* Fix: Candidate dashboard had two cyclic CSS custom property declarations (`--wcb-bg-subtle: var(--wcb-bg-subtle, #f8fafc)` and the equivalent for `--wcb-text-secondary`) that resolved to the guaranteed-invalid value in Chrome and left `.wcb-main` with a transparent background, so the dashboard wrapper looked unframed next to the employer dashboard. Local re-declarations removed — both dashboards now cascade identical tokens from `:root`.
* Improvement: Semantic dark-mode token layer — `--wcb-bg-subtle`, `--wcb-bg-hover`, `--wcb-text-secondary`, `--wcb-text-tertiary`, `--wcb-avatar-bg` all get dark variants under `html.dark-mode`, plus translucent `rgba(...)` variants of `--wcb-success-bg`, `--wcb-warning-bg`, `--wcb-danger-bg`, and `--wcb-info-bg` so status badges stay readable on dark cards.
* Fix: Candidate dashboard now gates access via the `wcb_access_candidate_dashboard` ability — employers can no longer reach the candidate dashboard.
* Fix: Job listings search input filtering on the Find Jobs page now matches the `wcb:search` event contract dispatched by the search block (`params.query`), and the listings hydrate from the `wcb_search` URL parameter.
* Fix: Company industry field is now a single source of truth — both the employer dashboard and the admin meta box render the same dropdown of 16 predefined industries from `WCB\Core\Industries::all()`.
* Fix: `wcb-btn--primary` and other variant text colors no longer get clobbered by the wrapper-scoped theme isolation reset — the reset no longer overrides per-variant `color`.
* Fix: EDD SL SDK `plugins_api_filter` no longer fatals on PHP 8+ when the licensing API call fails and the cache is empty (`Attempt to assign property "plugin" on false`).
* Improvement: Listings card typography rebalanced — title bumped to ~19px, description shrunk and muted, card body gap widened, badge contrast retuned to WCAG AA on every background, and the title link tap target raised above the WCAG 2.5.5 AAA threshold.
* Improvement: Accessibility pass — replaced 33 `outline: none` focus-strip sites with `outline: 2px solid transparent` so focus indicators show in Windows High Contrast / forced-colors mode, added missing form labels and ARIA labels across admin and block templates, and gave Interactivity API anchor stubs static aria-labels that hydrate at runtime.

= 1.0.1 =
* Fix: WPCS formatting cleanup across entire codebase (tabs, braces, spacing).
* Fix: Hardcoded localhost URLs replaced with home_url() in seed data.
* Improvement: PHPStan config optimized with explicit source paths.
* Improvement: CI pipeline streamlined to PHP Lint + PHPStan.

= 1.0.0 =
* New: Initial release.
* New: Gutenberg block-first job board with WordPress Interactivity API.
* New: Employer and candidate frontend dashboards.
* New: Full application tracking with five statuses.
* New: Guest applications support.
* New: Company profiles.
* New: BuddyPress and Reign Theme integration.
* New: reCAPTCHA v3 and GDPR export/erasure support.
* New: JobPosting schema.org structured data.
* New: REST API with WordPress Abilities API permissions.
* New: Automatic updates via wbcomdesigns.com.
