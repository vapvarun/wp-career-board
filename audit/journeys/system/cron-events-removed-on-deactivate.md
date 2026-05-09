---
id: cron-events-removed-on-deactivate
priority: high
personas: varundubey
requires: mu:autologin
last_verified: 2026-05-09
needs: cli
---

# Deactivating the plugin removes all wcb_* cron events (no orphans)

**Why this journey exists:** orphaned cron events after deactivation fire against a missing plugin, producing fatal errors on every cron run and polluting the debug.log of sites that temporarily deactivate the plugin (e.g. for debugging). The deactivation hook must call `wp_clear_scheduled_hook` for every registered cron event.

## Steps

1. Activate the plugin to ensure cron events are registered: `wp plugin activate wp-career-board` → expect success
2. Verify at least one `wcb_` cron event is present before deactivation: `wp cron event list | grep wcb_` → expect 1 or more rows
3. Capture the list of all `wcb_` events pre-deactivation: `wp cron event list --fields=hook | grep wcb_` → save as `<pre-deactivate-list>`
4. Deactivate the plugin: `wp plugin deactivate wp-career-board` → expect success output, no PHP error
5. Check for orphaned cron events: `wp cron event list | grep wcb_` → expect ZERO rows (all `wcb_*` events must be gone)
6. Verify the WP cron option directly: `wp eval 'print_r( array_keys( (array) get_option("cron") ) );' | grep wcb` → expect no keys containing `wcb` (belt-and-braces DB check)
7. Reactivate the plugin: `wp plugin activate wp-career-board` → expect success; `wp cron event list | grep wcb_` → expect the same hooks from step 3 are re-registered (cron re-registers cleanly)
8. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

```bash
wp plugin activate wp-career-board
```

## Notes

- The deactivation hook is registered in the main plugin file via `register_deactivation_hook()`. Confirm by reading `wp-career-board.php`.
- Pro cron events (`wcb_send_daily_alerts`, `wcb_send_weekly_alerts`, `wcbp_credit_reconcile`) are the Pro plugin's responsibility and are covered by `pro-cron-events-isolated.md`. This journey only verifies Free events.
- If WP-CLI cron list is empty after step 4 (including core events like `wp_update_plugins`), there may be a bug in the cron option cleanup — use the `wp eval` check in step 6 to distinguish.
