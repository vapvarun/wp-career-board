# What's New in 1.1.0

Released 10 May 2026. This release adds the lightweight, embed-anywhere
surfaces and the workflow improvements customers asked for most: a
one-screen job form, bulk applicant CSV export, salary range filtering,
deadline-reminder automation, and proper page-builder support across
every block.

## For employers

| What | Where to read |
|---|---|
| Drop a one-screen job posting form into any sidebar / modal / partner page | [Quick Job Form (single-page)](../for-employers/08-quick-job-form.md) |
| Export selected applications to UTF-8 CSV from the admin list table | [Bulk CSV Export](../for-employers/09-csv-export.md) |
| Re-built Edit Application admin screen + reusable widget shortcodes | [Application Editor](../admin-guide/13-application-editor.md) |
| Use any shortcode in Elementor / Divi / Bricks / Beaver Builder / classic editor | [Page-builder embeds](../for-employers/11-page-builder-embeds.md) |

## For candidates

| What | Where to read |
|---|---|
| Salary range slider on the Find Jobs page with active-range chip pills | [Salary range filter](../for-candidates/08-salary-filter.md) |
| Apply as a guest — upload a resume without creating an account first | [Apply as a guest](../for-candidates/09-guest-apply.md) |

## For site admins

| What | Where to read |
|---|---|
| Auto-expire Featured listings after a configurable duration | [Featured listing expiry](../admin-guide/08-featured-expiry.md) |
| Rich RSS feed at `/jobs/feed/` with full job metadata | [Jobs RSS feed](../admin-guide/09-rss-feed.md) |
| WPML / Polylang support out of the box | [Multilingual config](../admin-guide/10-wpml-polylang.md) |
| Application deadline reminder emails (3-day + 1-day-out cron) | [Deadline reminder emails](../admin-guide/02-email-notifications.md#deadline-reminders) |
| `?meta_<key>=<value>` REST queries with safelist filter | [REST meta filters](../admin-guide/11-rest-meta-filters.md) |
| Declarative custom-field filters on every form | [Custom fields](../admin-guide/12-custom-fields.md) |

## Critical fixes

- **MariaDB 11.7+ / MySQL 9+ compatibility** — fixed the AI-vectors
  table creation that was silently failing on those server versions.
  Pre-1.1.0 sites running MariaDB 11.7+ or MySQL 9+ will get the table
  on next plugin upgrade.
- **Verify-before-bump gate** — silent dbDelta failures no longer
  mask themselves. If a table create fails, the plugin's stored DB
  version stays behind the file constant, so the next activation /
  upgrade-in-place gets to retry.
- **PWA manifest** — no longer 404s on a missing icon image. Now uses
  the WordPress Site Icon at 192px and 512px; gracefully omits the
  icons key when no Site Icon is configured.
- **Console errors gone** — every page that uses our blocks now
  renders icons server-side, so the Interactivity API hydrator no
  longer logs DOM-mismatch warnings on each page load.

## Improvement

- **Site-wide design token system extension** — status color triplets,
  transition tokens, avatar size scale, theme-aware primary tints.
  Reign + BuddyX integrators override one place to restyle every block.
- **REST list endpoints** return a structured envelope (`total`,
  `pages`, `has_more`); legacy `X-WCB-Total` headers stay populated
  for one cycle.

## Pair release

Pro `1.1.0` ships in lockstep — both plugins must be at the same
version. See Pro's [What's New](../../../wp-career-board-pro/docs/website/getting-started/04-whats-new.md)
for the BuddyPress integration features added on the Pro side.
