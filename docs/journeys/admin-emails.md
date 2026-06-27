---
feature: admin page — Email Notifications (brand + per-email + send-test)
roles: admin
surface: admin page (wcb-settings#emails) + REST (POST /admin/emails/test)
last_walked: 2026-06-26
---

# Emails — full browser walkthrough

**What it is:** Brand settings (header colour, logo, footer text) plus a per-email table — enable toggle, custom subject, and a one-click **Send test** — followed by a live Email Activity Log.
**Where it lives:** `wp-admin/admin.php?page=wcb-settings#emails` (the legacy `?page=wcb-emails` slug 302-redirects here). Rendered by `EmailSettings::render_form()`; rows come from the `wcb_registered_emails` filter so Pro emails appear automatically.

## As admin
1. `?autologin=1` → `wp-admin/admin.php?page=wcb-settings#emails` → expect the Brand Settings card and an Email Templates table.
2. **Brand:** pick a header colour, click **Choose Image** → the WP media frame opens, select a logo → preview shows; set Footer Text → **Save Email Settings**. Values persist under `wcb_settings['emails']['brand']` (colour via `sanitize_hex_color`, footer via `wp_kses_post`).
3. The table lists all 9 Free emails (Job Pending/Approved/Rejected/Expired, Application Received/Confirmation/Guest/Status, Deadline Reminder) with Recipient (candidate / employer / admin), an editable Subject (placeholder = default), and an Enabled checkbox. Untick one, edit a subject → save → "Email settings saved." and the new values reload.
4. Click a row's **Send test** (paper-plane icon) → button shows "Sending…" → `POST /wp-json/wcb/v1/admin/emails/test {email_id}` fires `AbstractEmail::test_send()` to the current admin's email, bypassing the enable toggle → button flips to "Sent" (or "Failed"). The mail lands in the admin inbox.
5. Scroll to **Email Activity Log** → filter by Template and Status (Sent / Failed / Sent (test) / Failed (test)) → **Refresh** → the just-fired test appears as a **Sent (test)** pill, so test sends never pollute production delivery metrics. Page through with Previous / Next.

## As candidate / employer
- No access — the page is gated by `wcb_manage_settings`; the REST test route requires the same ability.

## Themes & states
- Admin-only — 1440px + 390px. The templates table and the log table wrap horizontally on narrow widths instead of overflowing.
- Empty log: fresh install → "No emails logged for the current filters." not a spinner stuck on "Loading…".
- A disabled template still sends via **Send test** (test bypasses `is_enabled()`), but will not fire in production.

## Contracts guarded
- REST↔JS: `/admin/emails/test` returns `{sent, to, logged}`; `emails.js` reads it to flip the button — a key rename breaks the Sent/Failed state.
- `emails.js` enqueues on the whole settings page (not gated on `?tab=emails`), or the Send-test button + log silently do nothing (the 1.1.1 fix).
- Log status `sent_test` / `failed_test` keeps admin previews out of production metrics.
- a11y: each Send-test button has an aria-label naming its template; focus rings on filters/pagination.
