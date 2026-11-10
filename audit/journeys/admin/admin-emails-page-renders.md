---
id: admin-emails-page-renders
priority: high
personas: varundubey
requires: mu:autologin, seed:jobs
last_verified: 2026-10-26
needs: browser
---

# Admin views email templates and verifies the Emails settings tab renders

**Why this journey exists:** Guards that the `wcb-settings` Emails tab loads all registered transactional email templates (populated via the `wcb_registered_emails` filter), and that the per-template enable/subject controls render without a PHP error.

## Steps

1. As `varundubey`, navigate to `/wp-admin/admin.php?page=wcb-settings&tab=emails&autologin=1` → expect 200, Emails tab is active, no PHP error
2. Verify the "Email look" card renders:
   - A "Colour and Logo" row with NO upload or colour field of its own: it explains that emails use the site Brand colour and logo and links to the Brand tab (`data-wcb-goto-section="brand"`). Colour and logo are set once under Settings > Brand (`accent_color`, `logo_id` in `wcb_settings`); there is no `wcb_email_logo_id` option.
   - A "Footer Text" textarea (`#wcb-email-footer-text`, name `wcb_email[brand][footer_text]`), the only email-specific look setting
3. Verify the email list table renders with at least 5 registered emails:
   ```bash
   # Indirect check — registered emails come from wcb_registered_emails filter
   wp eval 'echo count( (array) apply_filters( "wcb_registered_emails", [] ) );'
   ```
   → output is ≥ 5 (Free registers many, among them ids `application-received`, `application-status-changed`, `job-approved`, `job-expired`, `welcome`; the full list is `NotificationsModule::register_emails()`)
4. Confirm each email row in the table has:
   - An "Enabled" toggle (checkbox `wcb_email[<id>][enabled]`)
   - A "Subject" text input (`wcb_email[<id>][subject]`)
   - A body editor (`wcb_email[<id>][body]`) behind a toggle, with merge-tag chips, a **Preview** button and a **Send test** button
5. Click the Emails tab's one "Save changes" button (or POST the form with no changes) → expect `settings-updated` in the redirect URL; page reloads on Emails tab
6. Verify persistence of at least one setting: set the notification email to `smoke-test@example.com` in the Notifications section (same settings page, `notification_email` field), save, reload, confirm the value reads back `smoke-test@example.com` via:
   ```bash
   wp option get wcb_settings --format=json | python3 -c "import json,sys; d=json.load(sys.stdin); print(d.get('notification_email',''))"
   ```
7. Restore notification email to original admin email:
   ```bash
   wp option patch update wcb_settings notification_email "$(wp option get admin_email)"
   ```
7a. **Log and preview read as an owner (card:10420281996, card:10407661790).** In the activity log every Template cell is plain text holding the template's title ("Job Rejected", "Application Received (Employer)"), not a `code` id; an id no longer registered shows the id. Preview any template: no "(test send)" in its subject or body. Send test of the same template: the email in Mailpit still carries "(test send)" on the sample job title.
7c. **One Save keeps the whole tab (card:10439586916).** The Emails tab has one form and one **Save changes** button, below the templates and above the activity log. Change From Name, the footer text and one template Subject together, press it → "Settings saved."; reload → all three kept. Before the fix the top Save changes saved only the sender and silently dropped the template edits. Restore the values.
7b. **Every default subject reads in full (card:10430711101).** At 1440 the Email templates table has five columns (Email, Subject, Message, Enabled, Test); each Email cell shows its recipient under the title ("Recipient: Employer"); no Subject field's text is wider than its box. At 390 the table scrolls inside its card, the page does not scroll sideways.
8. Diff `debug.log` → expect ZERO new fatal/warning/notice lines

## Teardown

```bash
# Reset notification_email to admin_email if step 6 dirtied it
ADMIN_EMAIL=$(wp option get admin_email)
wp option patch update wcb_settings notification_email "$ADMIN_EMAIL" 2>/dev/null || true
```

## Notes

- The `wcb-emails` slug in the admin menu (`class-admin.php:580`) redirects to `wcb-settings&tab=emails` — always navigate to the settings page directly.
- Pro registers additional email classes via the same `wcb_registered_emails` filter; with Pro active, count will be higher than 5.
- The "Preview" button opens a modal preview of the email template rendered with placeholder data; checking that the modal opens is outside the scope of this journey (Playwright coverage only).
