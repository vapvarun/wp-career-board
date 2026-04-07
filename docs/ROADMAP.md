# WP Career Board Roadmap

## Current State (v1.0.0)

Both plugins are feature-complete for v1.0.0 release. Distributed via wbcomdesigns.com (NOT WordPress.org).

### What's Done --- Free Plugin

- 5 CPTs (job, company, application, resume, board)
- 14 Gutenberg blocks (job-listings, job-search, job-search-hero, job-filters, job-single, job-form, job-stats, featured-jobs, recent-jobs, employer-dashboard, candidate-dashboard, employer-registration, company-archive, company-profile)
- Full REST API (7 endpoint groups)
- WP-CLI commands (status, job manage, application manage, WPJM migration)
- Email notifications (8 email types: app confirmation, guest apply, app received, status change, job approved/rejected/expired/pending)
- Anti-spam (reCAPTCHA v3 + Cloudflare Turnstile)
- SEO (JobPosting schema, OG tags)
- GDPR compliance (WP Privacy API export + erase)
- Job moderation queue
- Setup wizard with rich sample data (3 companies, 8 jobs)
- BuddyPress integration
- Theme compatibility (Reign, BuddyX Pro)

### What's Done --- Pro Plugin

- Credit system (append-only ledger, WooCommerce + PMPro + MemberPress adapters)
- Custom field builder (group/field CRUD, field values per entity)
- Multi-board engine
- Application pipeline (kanban stages)
- AI matching (embeddings, ranked applications, AI job descriptions)
- Resume builder (7 sections, PDF export via DomPDF)
- Job alerts (daily/weekly cron digests)
- Job & resume maps (Leaflet, Google Maps, Mapbox drivers)
- In-app notifications (bell + read/unread)
- PWA (service worker, offline, install prompt)
- CSV import
- BuddyPress integration (profile tabs, notifications)
- EDD Software Licensing

## Pre-Release Checklist

- [ ] Fix WPCS errors (run phpcbf for auto-fixable)
- [ ] Fix PHPStan errors (type mismatches, undefined template vars)
- [ ] Fix a11y issues (outline:none -> focus-visible, empty links, missing alt)
- [ ] Rebuild assets (npm run build) in both plugins
- [ ] Run composer install --no-dev for release
- [ ] Add LICENSE files (GPL-2.0-or-later)
- [ ] Verify EDD SDK integration
- [ ] Run full test suite (seed -> CLI -> REST Free -> REST Pro -> cleanup)
- [ ] Browser test all pages at 390px viewport
- [ ] Set up CI (GitHub Actions: PHPUnit, PHPStan, WPCS, PHP Lint)

## v1.1.0 Roadmap (Post-Launch)

- [ ] Elementor widgets (job listings, search, company archive)
- [ ] Email template customizer (visual editor)
- [ ] Application form builder (custom fields per board)
- [ ] Advanced analytics dashboard (views, applications, conversion rates)
- [ ] Slack/Discord notifications integration
- [ ] Indeed/LinkedIn job feed export
- [ ] Multilingual support (WPML/Polylang compatibility)
- [ ] Bulk job import (CSV with mapping UI)

## v2.0.0 Long-Term Vision

- [ ] AI-powered resume parser (upload PDF -> auto-fill resume fields)
- [ ] Video interviews (WebRTC integration)
- [ ] Assessment/screening questions per job
- [ ] Team collaboration (multiple recruiters per company)
- [ ] Applicant tracking system (ATS) mode
- [ ] White-label mode (custom branding per board)
- [ ] REST API v2 (OpenAPI spec, webhook support)
- [ ] Mobile app (React Native, shared Interactivity API stores)
