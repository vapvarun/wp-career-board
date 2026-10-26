---
id: deadline-reminder-email-sent
priority: medium
personas: sarah.chen
requires: mu:autologin, seed:jobs
last_verified: 2026-10-26
needs: cli
---

# Deadline-approaching reminder email is sent when cron fires for a bookmarked job

**Why this journey exists:** `DeadlineReminders::sweep()` (`modules/jobs/class-deadline-reminders.php`, cron hook `wcb_send_deadline_reminders`, daily) emails candidates who bookmarked a job but did not apply, 3 days and 1 day before its deadline. Deadline reminder emails are a candidate retention feature; a cron that marks reminders "sent" without actually sending, or sends to the wrong address, silently breaks the feature. Verifies the email reaches the configured local mail catcher (Mailpit/MailHog).

## Steps

1. Find or create a published job with a deadline exactly 1 day from now:
   ```bash
   TOMORROW=$(date -v+1d +%Y-%m-%d 2>/dev/null || date -d "+1 day" +%Y-%m-%d)
   JOB_ID=$(wp post create --post_type=wcb_job --post_title="Warehouse Operations Lead - Leeds" --meta_input='{"_wcb_qa_smoke":"1"}' --post_status=publish --post_author=50 --porcelain)
   wp post meta update $JOB_ID _wcb_deadline "$TOMORROW"
   echo "Job $JOB_ID deadline set to $TOMORROW"
   ```
2. As `sarah.chen`, bookmark the job: POST `/wp-json/wcb/v1/jobs/$JOB_ID/bookmark` via `?autologin=sarah.chen` → expect HTTP 200, `{"bookmarked": true}`
3. Record the current Mailpit message count (or baseline the mail queue): note the count at `http://localhost:8025/api/v1/messages` (or equivalent) → capture as `<mail-count-before>`
4. Trigger the deadline-reminder cron event: `wp cron event run wcb_send_deadline_reminders` → expect exit code 0 (it matches jobs whose `_wcb_deadline` is exactly today + 1 day and today + 3 days)
5. Check Mailpit for a new message to sarah.chen's address: GET `http://localhost:8025/api/v1/messages` → expect count > `<mail-count-before>`, and at least one message with `To` containing `sarah.chen@example.test` (or the registered email for sarah.chen: `wp user get sarah.chen --field=user_email`)
6. Verify the email subject: the default subject is "Application deadline approaching for {job_title}" → expect "Application deadline approaching for Warehouse Operations Lead - Leeds"
7. Verify no duplicate email: trigger the same cron event again → confirm Mailpit count does NOT increase a second time for the same job (a per-candidate user-meta flag `_wcb_deadline_reminded_<job>_<days>` makes reminders idempotent per job per bucket; also check `wp user meta get sarah.chen _wcb_deadline_reminded_<job-id>_1` is `1`)
8. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

```bash
wp post delete $JOB_ID --force
# The bookmark route is a toggle; remove the saved row directly.
wp user meta delete sarah.chen _wcb_bookmark $JOB_ID
```

## Notes

- The reminder is a Free feature: `wcb_send_deadline_reminders` is scheduled by `DeadlineReminders::maybe_schedule()` on `init` and fires the `wcb_deadline_reminder` action, which `EmailDeadlineReminder` turns into the email. A candidate who already applied to the job gets no reminder. At the 3-day bucket the job's employer also gets a one-time `wcb_job_expiring_soon` warning.
- Mailpit default URL: `http://localhost:8025`. If the local environment uses MailHog, adjust to `http://localhost:8025` (same default port). Confirm with `wp eval 'echo get_option("admin_email");'` for the catch-all address.
- The idempotency check in step 7 is critical — double-sending reminder emails is a customer-complaint vector.
