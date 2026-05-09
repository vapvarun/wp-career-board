---
id: cron-events-scheduled-on-activate
priority: high
personas: varundubey
requires: mu:autologin
last_verified: 2026-05-09
needs: cli
---

# Fresh plugin activation schedules all expected wcb_* cron events

**Why this journey exists:** cron registration on activation is one-shot; if the `register_activation_hook` callback fails to schedule events (or the schedule is duplicated), job expiry reminders and notification digests never fire, silently breaking time-sensitive features.

## Steps

1. Deactivate and reactivate the plugin to reset cron state:
   ```bash
   wp plugin deactivate wp-career-board
   wp plugin activate wp-career-board
   ```
   → expect each command returns success output with no PHP errors
2. List all scheduled cron events: `wp cron event list --fields=hook,next_run_relative,schedule` → capture output
3. Verify `wcb_check_job_expiry` is scheduled: grep the output from step 2 for `wcb_check_job_expiry` → expect at least one entry with a non-null next run time and a recurrence schedule (e.g. `daily` or `twicedaily`)
4. Verify `wcb_application_status_changed` is NOT a cron hook (it is an action, not a scheduled event) → confirm no entry named `wcb_application_status_changed` appears in step 2 output (it fires on-demand, not on a schedule)
5. Verify at least one `wcb_` prefixed cron event is present: `wp cron event list | grep wcb_` → expect 1 or more rows (any `wcb_` prefixed hooks confirm activation wired the scheduler)
6. Verify no duplicate schedule entries: run `wp cron event list | grep wcb_check_job_expiry | wc -l` → expect exactly 1 (not 2 or more — duplicate scheduling is a resource waste)
7. Trigger a test run to confirm the callback is callable: `wp cron event run wcb_check_job_expiry` → expect exit code 0 and no PHP fatal in debug.log
8. tail debug.log diff → expect ZERO new fatal/warning lines (notice-level "WP_DEBUG" lines are acceptable if whitelisted)

## Teardown

None — the cron state is the correct post-activation state.

## Notes

- The manifest documents 3 cron hooks for Free: the runbook refers to `wcb_check_job_expiry` as the primary expiry hook. Confirm the exact hook names by running `wp cron event list | grep wcb_` on a fresh activation and reconcile with the manifest.
- If the plugin uses `wp_schedule_event` with `WP_CRON_LOCK_TIMEOUT`, a race condition on first activation may cause a missed schedule — run step 2 twice with a short delay if the first result is empty.
