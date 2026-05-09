---
id: job-expiry-cron-closes-old-jobs
priority: high
personas: varundubey
requires: mu:autologin, seed:jobs
last_verified: 2026-05-09
needs: cli
bug_ref: 9872024322
---

# Job expiry cron flips an overdue job to closed status

**Why this journey exists:** guards Basecamp 9872024322 (D.wcb-closed-status) — jobs past their deadline must be auto-closed by the `wcb_check_job_expiry` cron hook, and the resulting `closed` status must be a registered custom post status that persists without vanishing from the employer's dashboard.

## Steps

1. Create a smoke job with a deadline in the past:
   ```bash
   JOB_ID=$(wp post create --post_type=wcb_job --post_title="Smoke Expiry Test" --post_status=publish --post_author=50 --porcelain)
   wp post meta update $JOB_ID _wcb_deadline "2020-01-01"
   echo "Created job: $JOB_ID"
   ```
2. Verify the job is currently `publish`: `wp post get $JOB_ID --field=post_status` → expect `publish`
3. Verify `closed` is a registered custom post status (D.wcb-closed-status guard): `wp post status list | grep closed` → expect at least one row containing `closed` — if absent, FAIL immediately with note "closed custom post status not registered, Basecamp 9872024322 regression"
4. Trigger the expiry cron event manually: `wp cron event run wcb_check_job_expiry` → expect exit code 0, no PHP fatal in output
5. Verify the job's status changed to `closed`: `wp post get $JOB_ID --field=post_status` → expect `closed`
6. As anonymous, GET `/wp-json/wcb/v1/jobs` → expect `$JOB_ID` does NOT appear in the response (closed jobs are not public)
7. As `employer.figma`, navigate to `/employer-dashboard/?autologin=employer.figma` → expect the job appears in the employer's job list with status label "Closed" (NOT vanished from the dashboard — this is the specific bug from Basecamp 9872024322)
8. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

```bash
wp post delete $JOB_ID --force
```

## Notes

- `_wcb_deadline` must be in `Y-m-d` or `Y-m-d H:i:s` format — read the expiry module to confirm the expected format before step 1.
- If `wcb_check_job_expiry` is not in the cron event list, the cron was not scheduled on activation — run `cron-events-scheduled-on-activate.md` first.
- The `closed` post status must be registered via `register_post_status()` — confirm in `JobsModule` or `class-install.php`.
