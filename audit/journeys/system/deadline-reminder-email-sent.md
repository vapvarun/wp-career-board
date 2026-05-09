---
id: deadline-reminder-email-sent
priority: medium
personas: sarah.chen
requires: mu:autologin, seed:jobs
last_verified: 2026-05-09
needs: cli
---

# Deadline-approaching reminder email is sent when cron fires for a bookmarked job

**Why this journey exists:** deadline reminder emails are a candidate retention feature; a cron that marks reminders "sent" without actually sending, or sends to the wrong address, silently breaks the feature. Verifies the email reaches the configured local mail catcher (Mailpit/MailHog).

## Steps

1. Find or create a published job with a deadline exactly 1 day from now:
   ```bash
   TOMORROW=$(date -v+1d +%Y-%m-%d 2>/dev/null || date -d "+1 day" +%Y-%m-%d)
   JOB_ID=$(wp post create --post_type=wcb_job --post_title="Smoke Deadline Reminder" --post_status=publish --post_author=50 --porcelain)
   wp post meta update $JOB_ID _wcb_deadline "$TOMORROW"
   echo "Job $JOB_ID deadline set to $TOMORROW"
   ```
2. As `sarah.chen`, bookmark the job: POST `/wp-json/wcb/v1/jobs/$JOB_ID/bookmark` via `?autologin=sarah.chen` → expect HTTP 200, `{"bookmarked": true}`
3. Record the current Mailpit message count (or baseline the mail queue): note the count at `http://localhost:8025/api/v1/messages` (or equivalent) → capture as `<mail-count-before>`
4. Trigger the deadline-reminder cron event: `wp cron event run wcb_check_job_expiry` (or the specific reminder hook if different — check the cron manifest) → expect exit code 0
5. Check Mailpit for a new message to sarah.chen's address: GET `http://localhost:8025/api/v1/messages` → expect count > `<mail-count-before>`, and at least one message with `To` containing `sarah.chen@example.test` (or the registered email for sarah.chen: `wp user get sarah.chen --field=user_email`)
6. Verify the email subject references the job: inspect the message subject → expect it mentions "Smoke Deadline Reminder" or "deadline" or "expires soon"
7. Verify no duplicate email: trigger the same cron event again → confirm Mailpit count does NOT increase a second time for the same job (reminders must be idempotent per job per candidate)
8. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

```bash
wp post delete $JOB_ID --force
# Remove sarah's bookmark for this job (toggle off):
# POST /wp-json/wcb/v1/jobs/$JOB_ID/bookmark with autologin=sarah.chen (idempotent)
```

## Notes

- If no deadline-reminder cron hook exists in the Free plugin (it may be a Pro feature), steps 4-7 are `skipped: feature_not_in_free` and the journey priority should be re-evaluated. Record the absence.
- Mailpit default URL: `http://localhost:8025`. If the local environment uses MailHog, adjust to `http://localhost:8025` (same default port). Confirm with `wp eval 'echo get_option("admin_email");'` for the catch-all address.
- The idempotency check in step 7 is critical — double-sending reminder emails is a customer-complaint vector.
