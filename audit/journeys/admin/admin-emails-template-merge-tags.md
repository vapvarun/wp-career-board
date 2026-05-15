---
id: admin-emails-template-merge-tags
priority: medium
personas: varundubey
requires: mu:autologin
last_verified: 2026-05-15
needs: cli
bug_ref: Basecamp 9895205013
---

# Admin views and edits an email template; merge tags resolve in a test send

**Why this journey exists:** email template editing with merge-tag preview is a common admin task; an editor that saves but silently drops merge tags, or a test-send that sends the raw tag syntax `{{candidate_name}}` instead of the resolved value, is a real support vector.

## Steps

1. As `varundubey` (admin), navigate to `/wp-admin/admin.php?page=wcb-emails&autologin=1` → expect HTTP 200, emails list renders with at least one registered email template
2. Inspect the page for known email keys from the settings manifest (`wcb_settings.emails`): expect at least one template (e.g. "Application Received" or "Job Approved") is listed
3. Navigate to the edit screen for one template (click its edit link or navigate to the email settings tab) → expect the template edit form renders with a `subject` field and a `body` or `message` field
4. Record the current subject: capture the current subject line text
5. Update the subject to include a merge tag: edit the subject field to `Smoke Test - {{candidate_name}} applied` (or the plugin's documented merge-tag syntax) → save → expect HTTP 200 or 302 (no error flash)
6. Verify the edit persisted: reload `/wp-admin/admin.php?page=wcb-emails` → navigate back to the same template → expect the subject field shows `Smoke Test - {{candidate_name}} applied`
7. Click "Send Test" → expect `POST /wcb/v1/admin/emails/test` returns HTTP 200 with body `{sent: bool, to: string, logged: int}`.
8. Assert `sent === true` and `logged` is an integer > 0.
9. Query the notification log: `wp db query "SELECT status, payload FROM wp_wcb_notifications_log WHERE id = <logged>" --skip-column-names` → expect `status` = `sent_test` (NOT `sent`) and `payload` JSON contains `"is_test":true`.
10. If Mailpit is available, open the received email → confirm merge tags are resolved (NOT raw `{{candidate_name}}` syntax).
11. Restore original subject: update the subject back to the captured value from step 4.
12. tail debug.log diff → expect ZERO new fatal/warning lines.

## Teardown

```bash
# If test email was sent, purge it from Mailpit:
# Navigate to Mailpit UI and delete the test message (no WP-CLI equivalent)
```

## Notes

- The emails admin page slug is `wcb-emails` per the manifest (admin/class-admin.php:499).
- Merge-tag syntax is `{{tag}}` (double-brace). Single-brace `{job_title}` is inconsistent and may not substitute in all paths; flag if seen.
- Since 1.2.0, test-send routes through `AbstractEmail::test_send()` and the shared `dispatch()` helper. Log rows use `sent_test` / `failed_test` to isolate admin previews from production delivery metrics.
