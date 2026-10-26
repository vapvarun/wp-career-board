---
id: job-expiry-cron-closes-old-jobs
priority: high
personas: varundubey
requires: mu:autologin, seed:jobs
last_verified: 2026-10-26
needs: cli
bug_ref: 9872024322
---

# Job expiry cron flips an overdue job to the Expired status

**Why this journey exists:** guards Basecamp 9872024322 - a job past its deadline must be moved to `wcb_expired` by the hourly `wcb_check_job_expiry` sweep (`JobsExpiry::expire_jobs()` in `modules/jobs/class-jobs-expiry.php`), and that registered custom status must not make the job vanish from the employer's dashboard. `wcb_closed` is a different status: the employer's manual Close (filled, withdrawn), not the sweep.

## Steps

1. Make sure the sweep is on: `wp option get wcb_settings --format=json | python3 -c "import json,sys; print(json.load(sys.stdin).get('deadline_auto_close'))"` → `True` (sites installed on 1.8.0+ have it on; the sweep returns early when it is off). Record the value as `<orig-auto-close>`; if it is off, patch it on for this run: `wp option patch update wcb_settings deadline_auto_close 1`
2. Create a published job with a deadline in the past:
   ```bash
   JOB_ID=$(wp post create --post_type=wcb_job --post_title="Office Administrator, Part-time - Cork" --post_status=publish --post_author=50 --porcelain)
   wp post meta update $JOB_ID _wcb_deadline "2020-01-01"
   echo "Created job: $JOB_ID"
   ```
3. Verify the job is currently `publish`: `wp post get $JOB_ID --field=post_status` → expect `publish`
4. Verify the schedule: `wp cron event list --fields=hook,schedule | grep wcb_check_job_expiry` → exactly one row, schedule `hourly`
5. Verify `wcb_expired` and `wcb_closed` are both registered post statuses: `wp eval 'var_dump( isset( get_post_stati( array(), "objects" )["wcb_expired"], get_post_stati( array(), "objects" )["wcb_closed"] ) );'` → `bool(true)`
6. Run the sweep: `wp cron event run wcb_check_job_expiry` → expect exit code 0, no PHP fatal
7. Verify the status: `wp post get $JOB_ID --field=post_status` → expect `wcb_expired` (NOT `wcb_closed`, NOT `trash`)
8. As anonymous, GET `/wp-json/wcb/v1/jobs` → expect `$JOB_ID` does NOT appear (expired jobs are not listed)
9. As `employer.figma` (the job's owner), open the employer dashboard My Jobs → expect the job still listed with the status label "Expired" (`GET /wp-json/wcb/v1/employers/<company-id>/jobs` returns `statusLabel` "Expired")
10. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

```bash
wp post delete $JOB_ID --force
# Restore the setting only if it was off before step 1:
# wp option patch update wcb_settings deadline_auto_close 0
```

## Notes

- `_wcb_deadline` is a `Y-m-d` date; the sweep selects `publish` jobs via `JobDeadline::passed_jobs_meta_query()` in batches of 200 and re-arms itself a minute later when a batch is full.
- If `wcb_check_job_expiry` is missing from the cron list, load any front or admin page once (`JobsExpiry::boot()` schedules it on load, not on activation), then re-check; see `cron-events-scheduled-on-activate.md`.
- The employer dashboard shows an expired job's public page as an "ended" page, not a 404 (`JobsExpiry::serve_ended_job()`).
