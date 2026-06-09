---
id: report-a-job
priority: high
personas: sarah.chen, morgan_moderator
requires: mu:autologin, seed:jobs
last_verified: 2026-06-09
needs: cli
---

# Logged-in user reports a job; moderator resolves the flag

**Why this journey exists:** the Report-a-Job feature (1.2.0) is the standalone moderation surface that lets any authenticated visitor flag a listing for review. It must (1) record exactly one flag per user per job (dedupe), (2) fire `wcb_job_reported` so consumers can react, and (3) let a moderator clear the flag via `resolve-flag`. A report that double-counts the same reporter, or a flag a moderator can't clear, silently corrupts the flagged-jobs queue. Guards the standalone report contract (no BuddyPress dependency).

## Steps

1. Find a published job: `wp post list --post_type=wcb_job --post_status=publish --field=ID --posts_per_page=1` → capture as `<job-id>`
2. As anonymous, POST `/wp-json/wcb/v1/jobs/<job-id>/report` with body `{"reason":"spam"}` → expect HTTP 401/403 (reporting requires a logged-in user)
3. As `sarah.chen`, navigate to `/jobs/?autologin=sarah.chen` → expect 200, candidate logged in
4. Open the single job (`/?p=<job-id>`) → expect 200, a "Report this job" control renders in the job footer/meta for the logged-in user
5. POST `/wp-json/wcb/v1/jobs/<job-id>/report` (with REST nonce) body `{"reason":"spam"}` → expect HTTP 200, response shows flag count = 1 and `already_reported: false`
6. Verify postmeta: `wp post meta get <job-id> _wcb_flag_count` → expect `1`; `wp post meta get <job-id> _wcb_flag_status` → expect `open`
7. Re-report as the SAME user: POST the same route again → expect HTTP 200 with `already_reported: true`; `wp post meta get <job-id> _wcb_flag_count` → still `1` (dedupe, no double-count)
8. As `morgan_moderator` (the real Job Moderator persona — NOT admin; proves the role reaches the queue without `wcb_manage_settings`), navigate to `/wp-admin/admin.php?page=wcb-jobs&wcb_flag=open&autologin=morgan_moderator` → expect 200, the Flagged view lists `<job-id>` with its flag count + top reason
9. Resolve the flag: POST `/wp-json/wcb/v1/jobs/<job-id>/resolve-flag` body `{"action":"dismiss"}` → expect HTTP 200
10. Verify cleared: `wp post meta get <job-id> _wcb_flag_status` → expect `resolved` (or empty); `wp post meta get <job-id> _wcb_flag_count` → expect `0` or unset
11. As a plain candidate (no `wcb_moderate_jobs`), POST `/wp-json/wcb/v1/jobs/<job-id>/resolve-flag` → expect HTTP 403 (only moderators resolve)
12. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

```bash
wp post meta delete <job-id> _wcb_flag_count
wp post meta delete <job-id> _wcb_flag_reasons
wp post meta delete <job-id> _wcb_flag_reporters
wp post meta delete <job-id> _wcb_flag_status
```

## Notes

- Routes: `POST /wcb/v1/jobs/{id}/report` (any logged-in user), `POST /wcb/v1/jobs/{id}/resolve-flag` (`wcb_moderate_jobs`). Reason slugs come from `ModerationModule::report_reasons()` — `scam|spam|expired|inaccurate|offensive`.
- `report_job()` fires `do_action( 'wcb_job_reported', $job_id, $reason, $user_id )`; `resolve_job_flags()` fires `wcb_job_flag_resolved`. The `unpublish` resolve action sets the job to `pending` instead of clearing — covered by the moderation-approve journey's reverse path.
- Dedupe key is the reporter's user id in `_wcb_flag_reporters`; an empty-string meta must NOT produce a phantom user-0 reporter (regression guard).
- `morgan_moderator` (role `wcb_board_moderator`, display "Job Moderator") is seeded by `bin/seed-qa-fixtures.php`. The resolve steps use this real moderator — NOT admin — so this journey is the moderation coverage that proves the role reaches the queue without `wcb_manage_settings` (Basecamp 9895526464 AC).
