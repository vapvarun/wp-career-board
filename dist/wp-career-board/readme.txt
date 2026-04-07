=== WP Career Board ===
Contributors: wbcomdesigns
Tags: job board, jobs, employment, career, gutenberg
Requires at least: 6.9
Tested up to: 6.9
Stable tag: 1.0.1
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
