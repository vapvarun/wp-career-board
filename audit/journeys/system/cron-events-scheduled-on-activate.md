---
id: cron-events-scheduled-on-activate
priority: high
personas: varundubey
requires: mu:autologin
last_verified: 2026-10-26
needs: cli
---

# After activation and the first page load, every recurring wcb_* cron event is scheduled once

**Why this journey exists:** Free's recurring cron events are scheduled lazily by each module's `boot()` (not by the activation hook), so a module that stops scheduling, or schedules a second copy, silently breaks job expiry, deadline reminders, featured-job expiry and retention pruning. `CronRegistry::all()` (`core/class-cron-registry.php`) is the list the deactivation teardown clears; every hook that is scheduled must appear in it.

## Steps

1. Reset cron state by deactivating and reactivating, then load WordPress once so modules boot:
   ```bash
   wp plugin deactivate wp-career-board
   wp plugin activate wp-career-board
   wp cron event list --fields=hook,schedule,next_run_relative
   ```
   → each command succeeds with no PHP errors
2. In the step 1 output, expect exactly one row per recurring Free hook, with these schedules:

   | Hook | Schedule | Scheduled by |
   |---|---|---|
   | `wcb_check_job_expiry` | `hourly` | `modules/jobs/class-jobs-expiry.php` |
   | `wcb_send_deadline_reminders` | `daily` | `modules/jobs/class-deadline-reminders.php` |
   | `wcb_expire_featured_jobs` | `daily` | `modules/jobs/class-featured-expiry.php` |
   | `wcb_prune_job_views` | `daily` | `modules/jobs/class-job-views-retention.php` |
   | `wcb_process_account_deletions` | `daily` | `modules/account/class-account-deletion-service.php` |
   | `wcb_prune_logs` | `daily` | `modules/gdpr/class-gdpr-module.php` |
3. Count duplicates: `wp cron event list --fields=hook | grep -c '^wcb_check_job_expiry$'` → exactly `1` (repeat for each hook in the table; duplicate scheduling is wasted work)
4. One-shot hooks are not recurring and may or may not be present: `wcb_private_files_migrate` (re-armed on activation until the file migration finishes) and `wcb_index_job_search_text` (queued by the upgrade routine). If present, they have an empty schedule (single event). They are listed in `CronRegistry::all()` so deactivation clears them.
5. Confirm `wcb_application_status_changed` is NOT a cron hook (it is an action): `wp cron event list --fields=hook | grep -c wcb_application_status_changed` → `0`
6. Confirm every hook in the table is in the registry: `wp eval 'print_r( \WCB\Core\CronRegistry::all() );'` → contains all six table hooks plus `wcb_private_files_migrate`, `wcb_index_job_search_text` and `wcb_backfill_candidate_role` (9 hooks)
7. Confirm the callbacks run: `wp cron event run wcb_check_job_expiry` and `wp cron event run wcb_send_deadline_reminders` → exit code 0, no PHP fatal
8. **With Pro active** (`requires: pro`, skip otherwise): after the same reactivation and one page load, `wp cron event list --fields=hook,schedule` also shows `wcb_send_daily_alerts` (`daily`), `wcb_send_weekly_alerts` (`weekly`) and `wcbp_reconcile_credit_holds` (`daily`), one row each. Pro's list of everything it may schedule is `WCB\Pro\Core\CronRegistry::all()` in `wp-career-board-pro/core/class-cron-registry.php`.
9. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

None - the cron state is the correct post-activation state.

## Notes

- Activation itself only re-arms `wcb_private_files_migrate` (`Install::activate()`); the recurring events appear on the first WordPress load after activation. If step 1's list is empty, load any front-end page and list again before failing.
- `wcb_backfill_candidate_role` is an args-carrying single event queued by the 1.3.8 upgrade routine and re-armed per batch; it is in `CronRegistry::all()` since 1.8.3, so deactivation clears it.
- Free deactivation behaviour is covered by `cron-events-removed-on-deactivate.md`.
