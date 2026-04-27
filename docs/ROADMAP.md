# WP Career Board Roadmap

## Current State (v1.1.0 — shipped 2026-04-27)

Both plugins are released and bundled with Reign + BuddyX Pro themes.
Pro is sold separately on wbcomdesigns.com via EDD Software Licensing.

### What's Done — Free Plugin (v1.1.0)

**Foundation (1.0.0):**

- 5 CPTs (job, company, application, resume, board)
- Full REST API (7 endpoint groups), WP-CLI commands (status / job / application / WPJM migration)
- Email notifications (8 email types), anti-spam (reCAPTCHA v3 + Turnstile)
- SEO (JobPosting schema, OG tags), GDPR compliance (export + erase)
- Job moderation queue, setup wizard with sample data
- BuddyPress integration, Reign + BuddyX Pro theme compatibility

**Added in 1.1.0:**

- 15 Gutenberg blocks (added `job-form-simple` — single-page sibling of the wizard)
- Modular widget registry (`WCB\Core\Widgets\WidgetRegistry`) + `[wcb_widget id="..."]` shortcode — application detail rebuilt as 6 composable widgets
- Full REST envelope contract — every list endpoint returns `{<resource>, total, pages, has_more}`
- Page-builder shortcode parity — every block-shortcode now forwards attributes to the underlying block (Elementor / Divi / Bricks compatible)
- Declarative custom-field filters — `wcb_job_form_fields`, `wcb_application_form_fields_groups`, `wcb_resume_form_fields` on every form
- Bulk applicant CSV export, salary range slider, deadline reminder cron
- Resume upload for guest applicants, REST list envelope, design token registry expansion
- Local CI gate (`bin/ci-local.sh`) — PHP lint + WPCS + PHPStan + size-limit
- `docs/HOOKS.md`, `docs/SHORTCODES.md` — customer-facing extension reference

### What's Done — Pro Plugin (v1.1.0)

**Foundation (1.0.0):**

- Credit system (append-only ledger, WooCommerce + PMPro + MemberPress adapters)
- Custom field builder (group/field CRUD, field values per entity)
- Multi-board engine, application pipeline (Kanban stages)
- AI matching (embeddings, ranked applications, AI job descriptions)
- Resume builder (7 sections, PDF export via DomPDF)
- Job alerts (daily/weekly cron digests)
- Job & resume maps (Leaflet, Google Maps, Mapbox drivers)
- In-app notifications (bell + read/unread), PWA (service worker, offline, install prompt)
- CSV import, BuddyPress integration (profile tabs, notifications), EDD Software Licensing

**Added in 1.1.0:**

- Single-page resume form block (`wcb/resume-form-simple` + `[wcbp_resume_form_simple]`)
- BuddyPress group-scoped boards — every BP group gets a "Jobs" tab; auto-creates a `wcb_board` per group
- BuddyPress activity stream entries (job approved → activity post, hires → celebratory entry)
- BuddyPress notifications for candidates on every application status change
- Member directory filters — "Open to work" + "Hiring" chips on `/members/`
- Tiered credit pricing matrix — per BP member type / PMPro level / MemberPress level
- Group-scoped job moderation — BP group admins can approve/reject jobs on their group's board
- `featured_upgrade` credit consumer — spend credits to upgrade existing job to featured
- Page-builder shortcodes — `[wcbp_resume_form_simple]`, `[wcbp_resume_archive]`, `[wcbp_credit_balance]`, `[wcbp_job_alerts]`
- `docs/HOOKS.md`, `docs/SHORTCODES.md` — Pro extension reference

## v1.2.0 Roadmap (next)

Detailed plan: [`docs/PLAN-1.2.0.md`](PLAN-1.2.0.md). Highlights:

- [ ] Elementor widgets (job listings, search, company archive) for native page-builder UX
- [ ] Email template customizer (visual editor)
- [ ] Advanced analytics dashboard (views, applications, conversion rates)
- [ ] Slack / Discord notifications integration
- [ ] Indeed / LinkedIn job feed export
- [ ] WPML / Polylang multilingual deep integration
- [ ] CSV bulk-import wizard with column mapping UI

## v2.0.0 Long-Term Vision

- [ ] AI-powered resume parser (upload PDF -> auto-fill resume fields)
- [ ] Video interviews (WebRTC integration)
- [ ] Assessment/screening questions per job
- [ ] Team collaboration (multiple recruiters per company)
- [ ] Applicant tracking system (ATS) mode
- [ ] White-label mode (custom branding per board)
- [ ] REST API v2 (OpenAPI spec, webhook support)
- [ ] Mobile app (React Native, shared Interactivity API stores)
