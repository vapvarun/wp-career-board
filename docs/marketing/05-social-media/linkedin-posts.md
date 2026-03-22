# LinkedIn Posts — WP Career Board

---

## Post 1: Launch Announcement

We built a job board plugin for WordPress communities — and it's free.

WP Career Board runs entirely on Gutenberg blocks and the WordPress Interactivity API. No shortcodes. No jQuery. No page reloads.

What the free version includes:
- Employer and candidate frontends (no wp-admin access needed)
- Live job search with keyword, location, salary, and remote filters
- Application tracking: Submitted → Reviewing → Shortlisted → Rejected / Hired
- Guest applications (no account required)
- BuddyPress activity stream integration
- reCAPTCHA v3, GDPR compliance, and JobPosting schema — all included

If you're running a BuddyPress community or a Reign Theme site and want to add a job board, this is it.

Download: [link]

---

## Post 2: Bundled with Reign Theme

Big news for Reign Theme users: WP Career Board is now included with Reign.

Install the plugin, run the Setup Wizard, and your community site has a full job board — styled to match Reign automatically, with BuddyPress activity integration out of the box.

No separate subscription. No add-on to configure. One wizard.

If you're already on Reign, download WP Career Board from your Wbcom account today.

---

## Post 3: Technical — Interactivity API

A job board built on the WordPress Interactivity API.

Job filters update live without a page reload. Application status changes without refreshing. The job form submits via a typed REST endpoint. None of it requires jQuery.

The full stack:
- Gutenberg blocks (no shortcodes, no template overrides)
- Interactivity API stores — same pattern as the WP Query Loop block
- REST API at /wp-json/wcb/v1/ — every route has permission_callback, validate_callback, sanitize_callback
- Abilities API — no current_user_can() calls anywhere
- schema.org/JobPosting on every job page (valid structured data, no SEO plugin needed)

If you're building WordPress plugins and want to see what a modern architecture looks like, the source is worth reading.

---

## Post 4: Free vs SaaS

Why pay $99/month for a job board SaaS when WordPress can do it?

WP Career Board is free and handles:
- Employer job posting + management
- Candidate applications + status tracking
- Company profiles
- Email notifications
- Job moderation
- GDPR data requests

For sites that need more, WP Career Board Pro adds a Kanban pipeline, multiple boards, resume search, and a Stripe credit system — one license, no recurring platform fee.

---

## Post 5: Community Focus

Most job board plugins treat BuddyPress as an afterthought.

WP Career Board was built around it.

When BuddyPress is active, WP Career Board:
- Posts job activity to the site-wide activity stream
- Registers employer and candidate member types (so you can filter the member directory)
- Connects candidate profiles to their BuddyPress member profile
- Works with BuddyBoss Platform and BuddyX Pro

The job board isn't a separate island — it's part of the community.
