---
id: moderator-redirected-to-queue
priority: high
personas: morgan_moderator, varundubey
requires: mu:autologin, seed:jobs
last_verified: 2026-06-09
needs: cli
---

# Job Moderator lands on the Jobs queue silently; admins are unaffected

**Why this journey exists:** guards Basecamp 9895526464 (QA 9-June re-test). A Job Moderator (`wcb_board_moderator`: has `wcb/moderate-jobs`, not `wcb/manage-settings`) has no use for the WP Dashboard or the Career Board dashboard. The fix redirects them to the Jobs queue on `admin_init` — BEFORE any output — covering both `/wp-admin/` and `admin.php?page=wp-career-board`. The old redirect lived inside the dashboard render callback, so it (1) only fired on `page=wp-career-board`, never `/wp-admin/`, and (2) ran after output had started, throwing "Cannot modify header information - headers already sent". Moderators must also NOT see the Pro license / credit-setup nag notices (they can't act on them).

## Steps

1. Skip cleanly if the moderator persona is absent: `wp user get morgan_moderator --field=ID` (or `wcb-mod-test`) -> if missing, mark `skipped: no_moderator_persona`
2. As the moderator, navigate to `/wp-admin/?autologin=morgan_moderator` -> expect the final URL to be `/wp-admin/admin.php?page=wcb-jobs` (redirected off the WP Dashboard), HTTP 200, title "Jobs"
3. On that page, assert NO "Cannot modify header information" / "headers already sent" text anywhere in the body (Issue 2 regression guard)
4. As the moderator, navigate to `/wp-admin/admin.php?page=wp-career-board` -> expect the final URL to redirect to `...page=wcb-jobs` silently (no warning) — the Career Board dashboard is not theirs
5. On the Jobs queue, assert NO license/credit nag notice is present: no `.notice` whose text matches `activate license` / `configure your license` / `needs setup` / `configure ... credit` (Issue 3 regression guard)
6. Confirm the moderator CAN act: the Jobs list renders with Approve (and Reject for pending) row/bulk actions, and no Edit/Trash/Restore (admin-only) — see the moderation-approve journey for the full action contract
7. **Admin regression:** as `varundubey` (admin), navigate to `/wp-admin/?autologin=1` -> expect to STAY on the WP Dashboard (NOT redirected); navigate to `admin.php?page=wp-career-board` -> expect the Career Board dashboard to render (title "WP Career Board"), NOT a redirect. Admins manage settings, so they are never bounced.
8. tail debug.log diff -> expect ZERO new fatal/warning lines (especially no "headers already sent")

## Teardown

_None — read-only navigation._

## Notes

- Redirect: `Admin::redirect_moderator_to_queue()` on `admin_init` (admin/class-admin.php). Fires only for moderate-jobs-without-manage-settings, only on `index.php` (no `page`) or `page=wp-career-board`, and `exit`s after `wp_safe_redirect`. admin_init is before output, so no headers warning.
- Notice gating: Pro `ProAdmin::maybe_show_license_notice()` + `ProSetupWizard::maybe_show_setup_notice()` now early-return unless `wp_is_ability_granted('wcb/manage-settings')`, so non-admins (moderators) never see the license/credit nags.
- Free is keyless and shows no license notice regardless; the credit/setup notices are Pro-only.
