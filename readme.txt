=== WP Career Board ===
Contributors: wbcomdesigns
Tags: job board, jobs, employment, career, gutenberg
Requires at least: 6.9
Tested up to: 6.9
Stable tag: 1.0.2
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
