---
id: cron-events-removed-on-deactivate
priority: high
personas: varundubey
requires: mu:autologin
last_verified: 2026-10-26
needs: cli
---

# Deactivating the plugin removes every registered wcb_* cron event (no orphans)

**Why this journey exists:** orphaned cron events after deactivation fire against a missing plugin and pollute `debug.log` on sites that temporarily deactivate it. `Install::deactivate()` (`core/class-install.php`) loops `CronRegistry::all()` and calls `wp_clear_scheduled_hook()` for each hook; an earlier version cleared only `wcb_check_job_expiry`, leaving the reminder and featured-expiry events orphaned.

## Steps

1. Make sure the events exist: `wp plugin activate wp-career-board` then `wp cron event list --fields=hook,schedule` → expect rows for `wcb_check_job_expiry` (`hourly`), `wcb_send_deadline_reminders`, `wcb_expire_featured_jobs`, `wcb_prune_job_views`, `wcb_process_account_deletions` and `wcb_prune_logs` (all `daily`)
2. Capture the registry the teardown uses: `wp eval 'echo implode( "\n", \WCB\Core\CronRegistry::all() );'` → save as `<registry>` (8 hooks: the six above plus `wcb_private_files_migrate` and `wcb_index_job_search_text`)
3. Deactivate: `wp plugin deactivate wp-career-board` → success, no PHP error
4. For each hook in `<registry>`: `wp cron event list --fields=hook | grep -c "^<hook>$"` → `0`. In one command: `wp cron event list --fields=hook | grep -E '^wcb_(check_job_expiry|send_deadline_reminders|expire_featured_jobs|prune_job_views|process_account_deletions|prune_logs|private_files_migrate|index_job_search_text|backfill_candidate_role)$'` → no output
5. Belt-and-braces DB check: `wp eval 'foreach ( (array) get_option( "cron" ) as $t => $evs ) { if ( is_array( $evs ) ) { foreach ( array_keys( $evs ) as $h ) { if ( 0 === strpos( $h, "wcb_" ) ) { echo $h, "\n"; } } } }'` → no output
6. Reactivate: `wp plugin activate wp-career-board`, then `wp cron event list --fields=hook` → the recurring hooks from step 1 are back (cron re-registers cleanly)
7. **With Pro active** (skip otherwise): deactivate Pro (`wp plugin deactivate wp-career-board-pro`) and repeat steps 4-5 against `WCB\Pro\Core\CronRegistry::all()` (`wp eval 'echo implode( "\n", \WCB\Pro\Core\CronRegistry::all() );'`): it includes `wcb_send_daily_alerts`, `wcb_send_weekly_alerts`, `wcbp_reconcile_credit_holds`, `wcbp_reconcile_application_stages`, `wcbp_push_fanout` and the other `wcbp_*` / `wbcom_credits_*` hooks. Pro clears with `wp_unschedule_hook()` so events that carry args are removed too. Reactivate Pro afterwards.
8. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

```bash
wp plugin activate wp-career-board
wp plugin activate wp-career-board-pro   # only if it was active before step 7
```

## Notes

- The deactivation hook is `register_deactivation_hook( WCB_FILE, array( 'WCB\Core\Install', 'deactivate' ) )` in `wp-career-board.php`.
- Since 1.8.3 the Free teardown uses `wp_unschedule_hook()`, as Pro does, so args-carrying single events (`wcb_backfill_candidate_role`, queued with the last processed id) are cleared too. `wp_clear_scheduled_hook()` matched only events with no args.
- The registries are the contract: any new `wp_schedule_event()` / `wp_schedule_single_event()` in Free or Pro needs its hook added to the matching `CronRegistry::all()` and a row here and in `cron-events-scheduled-on-activate.md`.
