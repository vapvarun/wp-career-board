# Changelog

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
