---
id: admin-emails-page-renders
priority: high
personas: varundubey
requires: mu:autologin, seed:jobs
last_verified: 2026-05-09
needs: browser
---

# Admin views email templates and verifies the Emails settings tab renders

**Why this journey exists:** Guards that the `wcb-settings` Emails tab loads all registered transactional email templates (populated via the `wcb_registered_emails` filter), and that the per-template enable/subject controls render without a PHP error.

## Steps

1. As `varundubey`, navigate to `/wp-admin/admin.php?page=wcb-settings&tab=emails&autologin=1` → expect 200, Emails tab is active, no PHP error
2. Verify the email brand-settings section renders — look for these fields in the DOM:
   - Logo upload field (`wcb_email_logo_id`)
   - Brand colour picker or hex input
3. Verify the email list table renders with at least 5 registered emails:
   ```bash
   # Indirect check — registered emails come from wcb_registered_emails filter
   wp eval 'echo count( (array) apply_filters( "wcb_registered_emails", [] ) );'
   ```
   → output is ≥ 5 (Free registers: application_received, application_status_changed, job_approved, job_expired, employer_welcome — verify count ≥ 5)
4. Confirm each email row in the table has:
   - A "Enabled" toggle (checkbox `wcb_email[slug][enabled]`)
   - A "Subject" text input (`wcb_email[slug][subject]`)
   - A "Preview" or "Edit" link
5. Click the "Save Settings" button for the Emails tab (or POST the form with no changes) → expect `settings-updated=1` in the redirect URL; page reloads on Emails tab
6. Verify persistence of at least one setting: set the notification email to `smoke-test@example.com` in the Notifications section (same settings page, `notification_email` field), save, reload, confirm the value reads back `smoke-test@example.com` via:
   ```bash
   wp option get wcb_settings --format=json | python3 -c "import json,sys; d=json.load(sys.stdin); print(d.get('notification_email',''))"
   ```
7. Restore notification email to original admin email:
   ```bash
   ORIG_EMAIL=$(wp option get admin_email)
   wp eval 'WCB\Admin\AdminSettings::get_currency_catalog();' 2>/dev/null || true
   # Safer: use settings API directly
   wp option get wcb_settings --format=json | python3 -c "import json,sys; d=json.load(sys.stdin); d['notification_email']='$(wp option get admin_email)'; print(json.dumps(d))" | xargs -d '\n' wp option update wcb_settings
   ```
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
- The "Preview" link opens a modal preview of the email template rendered with placeholder data; checking that the modal opens is outside the scope of this journey (Playwright coverage only).
