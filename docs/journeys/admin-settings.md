---
feature: admin page — Career Board Settings (sidebar nav)
roles: admin
surface: admin page (wcb-settings) — Settings API + admin-post.php
last_walked: 2026-06-26
---

# Settings — full browser walkthrough

**What it is:** The single Settings screen with a left sidebar nav. Core groups are Job Listings, Pages, and Notifications; Emails and Import hang off the same sidebar, with greyed Pro teasers below.
**Where it lives:** `wp-admin/admin.php?page=wcb-settings` (cap `wcb_manage_settings`). Sidebar items are hash links (`#listings`, `#applications`, `#industries`, `#import`, `#signups`, `#privacy`, `#brand`, `#emails`, `#mobile-app`, `#pages`, `#integrations`, `#advanced`) toggled client-side by `settings-nav.js` — all panels are in the DOM at once.

## As admin
1. `?autologin=1` → `wp-admin/admin.php?page=wcb-settings` → expect 200, the sidebar with the briefcase brand mark, and Lucide icons per item (list / file-text / bell / send / upload / shield), not raw glyphs.
2. Click **Job Listings** → panel shows auto-publish, jobs-per-page, expiry days, deadline auto-close, allow-withdraw, salary currency, resume-required + max-MB, featured days, candidate-requires-role. Change one (e.g. jobs-per-page) → **Save Changes**.
3. The Listings / Pages / Notifications panels post to `options.php` (Settings API, option `wcb_settings`, group `wcb_settings_group`) → expect the "Settings saved." notice and the new value persisted on reload. Values run through `AdminSettings::sanitize()` (emails sanitized, ints clamped).
4. Click **Pages** → each app page (jobs archive, employer/candidate dashboard, company archive, post-job, employer registration, resume archive) is a page-picker → save → blocks now resolve their host pages.
5. Click **Notifications** → set From Name, From Email, and Notification (admin) Email; the required notification-email field rejects an empty save. These feed `wp_mail_from` / `wp_mail_from_name`.
6. Click **Advanced** → the **Visitor IP address** select (Direct connection / Cloudflare / X-Forwarded-For / X-Real-IP) with the two-layer help; saving it posts through the Settings API like every other tab.
7. Tab the sidebar links and focusable controls via keyboard → each shows a visible focus ring; the active nav item is marked.

## Themes & states
- Admin-only screen — verify at 1440px and 390px; the sidebar collapses cleanly above the panels on narrow widths (≤640px).
- Empty state: fresh install → defaults shown (no PHP notices); Pro teaser items render disabled with a Pro badge, not as broken links.

## Contracts guarded
- Every tab saves through the Settings API (`wcb_settings`).
- Sidebar nav is hash-based — every panel must be present in the DOM (server can't read the active tab), else the panel is invisible.
- a11y: nav items + inputs have `:focus-visible` rings; the active section is announced.
- Cap gate: only `wcb_manage_settings` (administrator) reaches the page and the save handlers.
