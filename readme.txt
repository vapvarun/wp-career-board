=== WP Career Board ===
Contributors: wbcomdesigns
Tags: job board, jobs, employment, career, gutenberg
Requires at least: 6.9
Tested up to: 6.9
Stable tag: 1.4.2
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

= 1.4.2 - June 2026 =

Fix for the employer ban control.

* Fix      - Banning an employer now takes effect. The Employers admin screen gains Ban and Unban actions (row and bulk) plus a Status column; banning sets the flag the permission layer already enforced, so a banned employer immediately loses every Career Board ability. Previously the gate read a ban flag that no admin action ever set.

= 1.4.1 - June 2026 =

Maintenance release. Build and packaging hardening only, no functional changes.

* Dev      - Release packaging unified onto a single .distignore contract used for both development and release builds, with a completeness gate that verifies every required template, library, and config file is present in the package.

= 1.4.0 - June 2026 =

AI-assisted hiring on the dashboard (applicant ranking, TL;DR summaries, AI cover letters), a List/Board Kanban for applications, any logged-in member can apply, plus onboarding, layout, and email fixes.

* New      - Any logged-in member can apply to jobs, save jobs, build a resume, and use the candidate dashboard without a dedicated Candidate role - ideal when the job board is part of a community site.
* New      - "Require Candidate Role" setting (and the wcb_candidate_requires_role filter) to reserve the candidate experience for the Candidate role when you want stricter separation.
* Improve  - Apply with a saved resume in one tap: your most recent resume is pre-selected, and resumes built in the Resume Builder no longer have to be exported to PDF first - the PDF is generated and attached automatically on apply (Pro).
* Improve  - Employers can review an applicant's full resume on the site: the application view adds a "View Resume" link to the candidate's public profile alongside the download.
* Fix      - Resumes imported from WP Job Manager Resumes no longer appear blank - their experience and education now render on the public profile, in the PDF, and in the employer's application view.
* New      - Install or remove the demo/sample data straight from Career Board > Settings, without re-running the setup wizard.
* Fix      - "Generate with AI" on the job form now completes and fills the description editor - the request no longer aborts mid-generation, and the generated text is pushed into the rich editor.
* Fix      - Job descriptions (AI-generated and demo) now render as structured content - headings, paragraphs, and bullet lists - instead of one wall of text. The editor parses the structure and the sample data ships as clean HTML.
* Dev      - Sample-data install/remove now fire `wcb_sample_data_installed` / `wcb_sample_data_removed` so add-ons (Pro) can seed and clean up their own demo content alongside the sample set.
* Dev      - POST /wcb/v1/jobs now returns HTTP 201 on successful job creation, matching the resume endpoint and standard REST conventions.
* Improve  - Applying without a saved resume now shows a clear "Build a resume" link (to your dashboard) instead of a dead-end "No resume found" message - the file-upload option stays available.
* Improve  - Candidate dashboard empty states ("No applications yet", "No saved jobs yet") now link to "Browse jobs" so there is always an obvious next step.
* Improve  - Changing an applicant's status now confirms it saved ("Status updated. The candidate has been notified.") instead of silently updating the dropdown.
* Improve  - The Anti-Spam settings tab now uses the same card layout as the rest of Settings (it was rendering as bare headings and fields), and the reCAPTCHA fields link to where to get the keys.
* Improve  - Non-employers who open the Employer Dashboard or Post a Job now get a helpful message with a "Register as an employer" link (and a route back to the candidate dashboard) instead of a dead-end "no permission" message.
* Improve  - Resume options no longer look split between free and pro: the "Public Resume Archive" toggle moved to Settings > Resumes (Pro) with the other resume options, and the upload-size field was relabeled "Application Resume File Size" to make clear it controls the file attached to a job application (a free feature).
* Improve  - The Employer Dashboard overview now guides a new employer from the landing screen: "Set Up Company Profile" before a company exists, then "Post Your First Job" once it is ready (these calls to action were previously only on the My Jobs tab).
* Fix      - Employer Dashboard overview stat cards now count the employer's own jobs and applications, matching the lists below; they previously counted by company and could under-report when a job was not yet linked to the company.
* New      - Employer Dashboard: rank a job's applicants by AI fit (requires Pro and an AI provider). Each applicant shows a fit-score badge, the list sorts best-first, and the applicant detail shows the reasoning.
* New      - Candidate Dashboard: a "Recommended for you" set of AI-matched jobs on the overview (requires Pro and an embedding provider).
* New      - Employer Dashboard shows each applicant's AI fit score and a one-line TL;DR summary on load once scored, sorted best-first (requires Pro and an AI provider).
* New      - Apply panel: a "Write with AI" button drafts a cover letter from the candidate's resume and the job, ready to edit before applying (requires Pro and an AI provider).
* New      - Employer Dashboard Applications now has a List / Board toggle. The Board groups applicants into status columns (Submitted, Reviewing, Shortlisted, Hired, Rejected); drag a card to change an applicant's status, and the board, list, status emails, and AI ranking all stay in sync.
* Fix      - The admin email "Test Send" no longer logs a PHP warning when previewing the application-received template (a candidate_name preview variable was missing). Real notification sends were unaffected.
* Fix      - Apply drawer: the cover letter no longer touches the Submit button (restored the spacing the rich editor had dropped).
* New      - Delete a single notification or clear all of them from the dashboard Notifications panel.
* Improve  - Notifications panel redesign with clearer read and unread states, Mark all read and Clear all controls, and an always-visible per-row delete button (40px tap target on mobile).
* Fix      - Notifications that pointed at the homepage now render non-clickable instead of bouncing to the home page.
* Fix      - Dashboard block scripts and styles now cache-bust on each release, so updates reach browsers that cached an earlier version.
* Compat   - Aligned with WP Career Board Pro 1.4.0. Install both updates together.

= 1.3.0 - June 2026 =

Account self-service in the dashboard, clearer job moderation, and a batch of My Jobs / applications data fixes.

* New      - Account Settings now lets candidates and employers update their display name and email and change their password directly in the dashboard, instead of being sent off to wp-login.
* New      - Rejected job listings show as "Rejected" (not "Draft") in the employer dashboard, with a Resubmit action; resubmitting sends the job back for admin approval instead of publishing it directly.
* Improve  - Resume Builder no longer renders a doubled card frame when embedded in the dashboard, and the remove-entry icon now renders reliably.
* Improve  - Consistent spacing above the single-job sidebar widget titles.
* Fix      - Employer "My Applications" no longer surfaced another employer's application when you had none; the job-to-application lookup is now type-safe.
* Fix      - A job posted before you saved a company profile is now adopted into My Jobs when the company is created, instead of staying invisible.
* Fix      - A newly posted job now appears in My Jobs immediately, without a manual page reload.
* Fix      - Saving a company from its profile page now persists across reloads.
* Compat   - Aligned with WP Career Board Pro 1.3.0. Install both updates together.

= 1.2.0 - May 2026 =

First public release after 1.0.x. Adds the single-page job form, bulk applicant CSV export, salary range slider, application deadline reminders, guest resume uploads, automatic featured-listing expiry, a rebuilt admin Edit Application screen, modular widgets, rich RSS, WPML / Polylang config, page-builder compatibility for every shortcode, REST envelope cleanup, mobile reading-order fixes, and the bug-fix and BuddyPress-integration roll-up that was queued behind 1.1.x.

* New      - Single-page job posting form. Drop a one-screen form into any sidebar, modal, partner page, or page builder via the new "Job Form (Single-Page)" block or [wcb_job_form_simple] shortcode. Sits alongside the existing multi-step wizard.
* New      - Bulk applicant CSV export. Select applications in the admin list table and download a UTF-8 spreadsheet with applicant name, email, job, status, applied date, cover letter, and resume URL.
* New      - Salary range slider on the Find Jobs page so candidates can filter by minimum and maximum salary live, with chip pills showing the active range.
* New      - Application deadline reminder emails. Candidates who saved a job but have not applied get a 3-day and 1-day-out reminder automatically via daily cron.
* New      - Resume upload on the apply form for guests. The previous build only accepted resumes from logged-in candidates. PDF, DOC, and DOCX, with configurable required-vs-optional and size cap up to 20 MB.
* New      - Featured listings now expire automatically after a configurable duration (default 30 days), setting up Featured as a real time-bound paid SKU.
* New      - Rebuilt Edit Application admin screen. Applicant card with avatar and contact info, cover letter, resume preview with Open / Download, status changer, quick-action buttons (Shortlist / Mark Hired / Reject / Message), and full status history. Replaces the previously empty native post-edit screen.
* New      - Modular widget system. Every component on the rebuilt application screen also works as a [wcb_widget id="..."] shortcode on any page.
* New      - Rich RSS at /jobs/feed/ with company, salary (currency, period, min, max), location, type, category, tags, experience, deadline, and apply URL. Works in any RSS reader, IFTTT, or Zapier flow.
* New      - WPML / Polylang config so multilingual sites can translate job board CPTs, taxonomies, and key strings out of the box.
* New      - Page-builder compatibility. Every shortcode ([wcb_job_listings], [wcb_job_form], [wcb_job_form_simple], etc.) now accepts attributes and forwards them to the block, so Elementor, Divi, Bricks, Beaver Builder, and classic editor users can scope blocks (e.g. [wcb_job_listings boardId="42" perPage="6"]).
* New      - boardId and metaFilter attributes on the Job Listings block. Render a board-scoped or custom-relationship-scoped listing anywhere without writing custom code.
* New      - REST ?meta_<key>=<value> query support for the jobs endpoint. Any _wcb_* namespaced meta key is allowed by default; custom meta opts in via the wcb_jobs_allowed_meta_filters filter.
* New      - Custom-field flexibility on every form. Declarative filters (wcb_job_form_fields, wcb_company_form_fields, wcb_candidate_form_fields, wcb_application_form_fields_groups) take a single field-group schema. Add custom fields with one add_filter call.
* New      - Local CI runner via npm run ci. Runs PHP lint, WPCS, PHPStan, and size-limit on the dev workstation before every push.
* Improve  - Site-wide design token system extended with status color triplets, transition tokens, avatar size scale, and theme-aware primary tints. All blocks now token-driven so Reign and BuddyX integrators override one place to restyle the whole plugin.
* Improve  - REST API list endpoints now return a structured envelope with total, pages, and has_more. Legacy X-WCB-Total and X-WCB-TotalPages response headers stay populated for one release cycle for back-compat.
* Improve  - Mobile single-job page reading order corrected. "About This Role" now appears first on phones, previously sat below Job Details / Share / Company. Apply panel z-index lifted above theme toggles so light/dark switchers no longer overlap form fields.
* Improve  - Setup wizard now centers between the admin sidebar and the right edge on wide viewports. Was previously narrow and left-aligned.
* Improve  - Active filter pills on Find Jobs now sit with a comfortable bottom margin above the job cards. Pre-1.2.0 the pill row was flush against the cards.
* Improve  - Company cards on the Companies archive now align their meta chips at the same y-position across the grid regardless of how long each tagline is.
* Improve  - Plugin Check (PCP) and WPCS gates added to GitHub Actions CI alongside the existing PHP lint and PHPStan jobs.
* Improve  - Custom database tables now declare ENGINE=InnoDB explicitly so the plugin works on hosts whose MySQL default storage engine is still MyISAM.
* Improve  - Daily cron now schedules deadline reminders and featured-listing expiry sweeps automatically; deactivation cleanly clears scheduled events.
* Improve  - Filter-panel chevron icons on Find Jobs and Companies now route through the Lucide icon system instead of hand-rolled inline SVGs, so they pick up the same stroke and color tokens as the rest of the UI.
* Fix      - Test Email button on the Emails settings tab now succeeds even when the template is toggled off. Disabled templates were short-circuiting in send() so the row-delta check returned {sent:false} and the JS button painted "Failed". A new AbstractEmail::test_send() public bridge bypasses is_enabled() and marks the log row sent_test so admin previews do not pollute production delivery metrics.
* Fix      - Single job page now lists the Apply Email and routes Apply Now to the external Apply URL when those fields are set on the job.
* Fix      - Boards picker in the job form now hides BuddyPress group boards the employer is not a member of. Pro delivers the filtered list via the new wcb_board_options_for_employer hook.
* Fix      - Buy Credits link in the job form is suppressed when the credit purchase URL is not configured, so the button no longer points at the current page.
* Fix      - Pre-publish credit message ("Posting deducts N credits. Balance after: X (currently Y).") now reads its templates from translated PHP strings, with cost and balance interpolated live from the Wbcom Credits SDK.
* Fix      - Uninstall.php now uses $wpdb->prepare('DROP TABLE IF EXISTS %i', $table) with the WP 6.2+ identifier placeholder.
* Fix      - Removed all manage_options capability fallbacks from REST permission checks. Abilities API is now the single permission source.
* Fix      - Per-page parameter on the jobs list endpoint is now clamped to 100 in the handler. The schema-only maximum was previously a warning, not an enforced cap.
* Fix      - posts_per_page = -1 removed from frontend job query paths (replaced with bounded queries).
* Fix      - BuddyPress group Jobs tab now correctly filters jobs to that group's board. The previous defaultBoardId block attribute was undeclared and silently dropped.
* Fix      - Visual alignment issues on the single-page job form (salary row breaking awkwardly, remote-deadline pair misaligning, button placement).
* Dev      - New filter wcb_board_options_for_employer( array $options, int $user_id ) lets Pro and integrators restrict the job-form Boards picker.
* Dev      - New filter wcb_page_needs_frontend_assets( bool $needs ) lets contexts that render WCB blocks outside post_content (BuddyPress profile and group tabs, page builders that lazy-render) opt into the shared frontend stylesheets so primitives like .wcb-hidden resolve.
* Compat   - Aligned with WP Career Board Pro 1.2.0. Install both updates together.

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
