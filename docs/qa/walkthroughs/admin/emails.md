---
id: walkthrough-admin-emails
priority: high
personas: varundubey
requires: mu:autologin
last_verified: 2026-07-08
covers: admin/admin-emails-page-renders, admin/admin-emails-template-merge-tags
---

# Walkthrough: Emails — render the Emails admin, edit a configurable message body, confirm defaults + merge tags resolve in a test send

**Why this journey exists:** The Emails tab is where a site owner brands and configures every transactional email. In 1.5.1 each template gains a **configurable HTML message body** with a shipped, ready-to-use default: the admin can edit the body inline, insert merge-tag chips, or "Load default" to start from the production copy — and if the body is left blank the email still sends the default. A regression here means either the body field silently drops, the default fails to reach the sent email, or merge tags go out as raw `{candidate_name}` syntax. This walkthrough exercises render → edit body → save → default fallback → test send, and is the human-runnable form of the two emails sentinels.

## Steps

1. As `varundubey`, navigate to `http://jobboard.local/wp-admin/admin.php?page=wcb-emails&autologin=varundubey` → expect an immediate redirect to `http://jobboard.local/wp-admin/admin.php?page=wcb-settings&tab=emails` (the `wcb-emails` submenu is a redirect stub, `admin/class-admin.php:649-656`), HTTP 200, Emails section active.
2. Confirm the Emails section renders (`EmailSettings::render_form()`, embedded via `do_action('wcb_settings_tab_emails')`): expect a **Brand Settings** card (Header Color `input[name="wcb_email[brand][header_color]"]`, Logo picker `#wcb-logo-upload` writing `#wcb-email-logo-id`, Footer Text `textarea[name="wcb_email[brand][footer_text]"]`) and an **Email Templates** table with header columns **Email, Recipient, Subject, Message, Enabled, Test** (`admin/class-email-settings.php:164-173`).
3. Confirm the registered templates populate the table: `wp eval 'echo count((array) apply_filters("wcb_registered_emails", array()));' --path=/Users/varundubey/Local Sites/jobboard/app/public` → expect `>= 5` (Free registers Application Received, Application Status, Job Approved, Job Pending, Job Expired, etc. via the `wcb_registered_emails` filter). Each row shows a `<strong>` title, the recipient (Employer/Candidate/Admin/Guest), a Subject input `input[name="wcb_email[<id>][subject]"]` whose placeholder is `get_default_subject()`, an Enabled checkbox `input[name="wcb_email[<id>][enabled]"]`, and a "Send test" button `.wcb-email-test-btn[data-email-id="<id>"]`.
4. **1.5.1 — Open the configurable message body.** In the **Application Received (Employer)** row (`id=application-received`), click the Message-column "Edit" toggle `button.wcb-email-body-toggle[data-target="wcb-email-body-application-received"]` (`aria-expanded="false"` → `true`) → expect its body row `tr#wcb-email-body-application-received` (initially `hidden`) to reveal a textarea `#wcb-email-body-field-application-received` (`name="wcb_email[application-received][body]"`), a row of merge-tag chips, and a "Load default" button (`admin/class-email-settings.php:194-227`).
5. **1.5.1 — Verify the shipped default is available.** The textarea is empty (placeholder: "Leave blank to send the ready-made default message…"). Click "Load default" `button.wcb-email-load-default[data-target="wcb-email-body-field-application-received"]` → its `data-default` attribute carries `get_default_body()`, and the JS fills the textarea with the production copy: a "New application received" heading, the paragraph "**{candidate_name}** has applied for **{job_title}**…", and a "View in Dashboard" button linking `{dashboard_url}` (`EmailAppReceived::get_default_body()`, `modules/notifications/emails/class-email-app-received.php:67-76`).
6. **1.5.1 — Verify merge-tag chips.** Expect one `button.wcb-email-tag-chip[data-tag]` per key from `get_merge_tags()` — for this template `{candidate_name}`, `{job_title}`, `{dashboard_url}` (`modules/notifications/emails/class-email-app-received.php:83-89`). Click the `{candidate_name}` chip → expect its literal text (`{candidate_name}`) inserted into the body textarea at the cursor.
7. **1.5.1 — Edit and save a custom body.** Replace the loaded body with `<p>Custom: {candidate_name} applied for {job_title}.</p>`, set the Subject to `Smoke: {candidate_name} applied`, and click "Save Email Settings" (`submit_button`) → expect a page reload with the "Email settings saved." success notice. The save (`EmailSettings::save()` on `admin_init`) verifies nonce `wcb_email_nonce`/`wcb_email_settings_save`, gates on ability `wcb/manage-settings`, sanitizes the body with `wp_kses_post`, and writes it into `wcb_settings['emails']` (`admin/class-email-settings.php:460-509`).
8. Verify the body persisted: `wp option get wcb_settings --format=json` → the `emails['application-received']['body']` value contains `Custom: {candidate_name} applied for {job_title}.`. Reopen the body row on reload → expect the textarea pre-filled with the saved custom body (`AbstractEmail::get_body()` returns the saved body when non-empty, else `get_default_body()` — `modules/notifications/class-abstract-email.php:118-122`).
9. **Default fallback contract.** Clear the body (empty the textarea) and Save again → verify `emails['application-received']['body']` is now empty. This does NOT break the email: `render_body()` falls back to the shipped default (precedence: admin-saved body → theme override file → `get_default_body()`, `modules/notifications/class-abstract-email.php:363-377`), always wrapped in the branded header/footer.
10. **Merge tags resolve in a test send.** Click the row's "Send test" `button.wcb-email-test-btn[data-email-id="application-received"]` → expect `POST http://jobboard.local/wp-json/wcb/v1/admin/emails/test` body `{email_id:"application-received"}` returning HTTP 200 `{ sent: <bool>, to: "<admin email>", logged: <int> }` (`AdminEndpoint::test_send_email()`, gated on ability `wcb/manage-settings`, `api/endpoints/class-admin-endpoint.php:57-233`). Assert `logged` is `>= 1`.
11. Verify the test wrote an isolated log row: `wp db query "SELECT status, payload FROM wp_wcb_notifications_log WHERE event_type='application-received' ORDER BY id DESC LIMIT 1" --skip-column-names` → expect `status = sent_test` (NOT `sent`) and `payload` JSON containing `"is_test":true`. The subject in the payload must read `Smoke: <a real name> applied` (or the resolved sample name) — NOT the raw `{candidate_name}` (subjects run through `AbstractEmail::render_string()` which substitutes both `{key}` and `{{key}}`; the test uses sample vars including `candidate_name`, `job_title`, `dashboard_url` — `api/endpoints/class-admin-endpoint.php:179-203`).
12. Confirm the Email Activity Log surface renders below the form: expect `#wcb-email-activity-log` with a Template/Status filter toolbar and a table that loads rows via `GET /wcb/v1/admin/emails/log` (`admin/class-email-settings.php:301-370`); the just-sent test appears with a "Sent (test)" status pill.
13. tail `wp-content/debug.log` diff over the whole run → expect ZERO new fatal/warning lines.

## Teardown

```bash
SITE='/Users/varundubey/Local Sites/jobboard/app/public'
# Reset the application-received template's subject + body back to defaults (blank = ship default).
wp eval '
$s = (array) get_option("wcb_settings", array());
if ( isset($s["emails"]["application-received"]) ) {
  $s["emails"]["application-received"]["subject"] = "";
  $s["emails"]["application-received"]["body"]    = "";
  update_option("wcb_settings", $s);
}
' --path="$SITE" 2>/dev/null || true

# The sent_test rows in wp_wcb_notifications_log are a deliberate audit trail — leave them.
# If Mailpit captured the test email, delete it from the Mailpit UI (no WP-CLI equivalent).
```

## Notes
- **1.5.1-new (HIGH scrutiny):** the per-template **configurable HTML message body** is the new surface. Grounding: the Message column + inline body editor (`admin/class-email-settings.php:170,194-227`), the body save through `wp_kses_post` into `wcb_settings['emails'][<id>]['body']` (`:498,507-509`), the read/fallback in `AbstractEmail::get_body()` (`:118-122`) and `render_body()` precedence (`:363-377`), and the shipped defaults in each `modules/notifications/emails/class-email-*.php::get_default_body()`. Every default body is authored to ship untouched (production-ready), contains only the inner body (header/footer added by `wrap_body()`), and uses `{merge_tag}` placeholders.
- The old standalone `wcb-emails` page is only a redirect; the real UI is the Emails **settings tab** (`EmailSettings::render_form()`). `EmailSettings::render()` is a legacy full-page renderer that lacks the Message column and is not reached through the menu — do not test against it.
- Test-send routes through `AbstractEmail::test_send()` → `dispatch(..., is_test:true)`, which always writes a `*_test` log row so the endpoint's row-delta reports `sent:true` even when `wp_mail()` returns false (mail transport may be absent on the QA box). The row-count delta — not actual delivery — is the assertion.
- `enqueue_assets()` loads `emails.js` on the whole settings page (not gated on `?tab=emails`, which never matched the hash-based tabs) — a 1.5.1-adjacent fix; if the Send-test button or activity log do nothing, that gate regressed (`admin/class-email-settings.php:47-58`).
- Merge-tag syntax: templates use single-brace `{tag}` in default bodies; `render_string()` also accepts `{{tag}}`. Both substitute — flag only if a raw brace reaches a recipient.
