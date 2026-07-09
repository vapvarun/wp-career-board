---
id: walkthrough-account-and-notifications
priority: high
personas: sarah.chen, employer.figma, varundubey
requires: mu:autologin, seed:jobs
last_verified: 2026-06-29
---

# Walkthrough: Account Settings & Email Notifications — a candidate edits their account, the site owner tunes notification preferences, and the apply event fires the candidate + employer emails

**Why this journey exists:** This is the end-to-end walkthrough of self-service Account Settings plus the
email-notification pipeline. It traces the full happy path: a candidate updates their display name / email and
changes their password from the dashboard Settings tab (REST `/account`), the site owner reviews the per-email
enable/subject preferences on the Emails settings tab, and then a real application submit fires both the candidate
"application confirmation" and the employer "new application" emails through `wp_mail()` — each logged to
`wp_wcb_notifications_log`. The whole functionality is browser-coverable in one pass.

## Steps

1. As `sarah.chen`, navigate to `http://jobboard.local/candidate-dashboard/?autologin=sarah.chen` → expect HTTP 200 and the dashboard shell `div.wcb-dashboard[data-wp-interactive="wcb-candidate-dashboard"]` (NOT the `.wcb-db-gate` "Please sign in" fallback). _(blocks/candidate-dashboard/render.php:18-28, 268-401)_
2. Click the sidebar nav button `#wcb-tab-settings` (`actions.switchToSettings`) → expect the Account Settings panel `div.wcb-view-panel[aria-labelledby="wcb-tab-settings"]` to become active, showing `#wcb-account-name` (Display Name), `#wcb-account-email` (Email), and the "Save changes" button `.wcb-cbtn--primary[data-wp-on--click="actions.saveAccount"]`. _(render.php:376-381, 1039-1055)_
3. Type a new value into `#wcb-account-name` (e.g. "Sarah Chen-Reyes") — `data-wp-on--input` sets `state.accountName` — then click "Save changes" (`actions.saveAccount`) → expect a `POST {apiBase}/account` with JSON body `{ display_name, email }` and `X-WP-Nonce` header, an HTTP 200, and `.wcb-account-msg[data-type="success"]` showing "Account updated." (`apiBase` = `http://jobboard.local/wp-json/wcb/v1`). _(view.js:672-707; api/endpoints/class-account-endpoint.php:45-67, 109-188)_
4. In the "Change Password" card, fill `#wcb-account-curpw` (current password), `#wcb-account-newpw` (≥8 chars) and `#wcb-account-confpw` (matching), then click "Update password" `.wcb-cbtn--primary[data-wp-on--click="actions.changePassword"]` → expect a second `POST {apiBase}/account` with body `{ current_password, new_password }`, HTTP 200, and `.wcb-account-msg` (pw) showing "Password updated."; the JS swaps `state.nonce` to the fresh `data.nonce` so the session survives the password change. _(render.php:1058-1078; view.js:709-757; class-account-endpoint.php:139-185)_
5. Reload `http://jobboard.local/candidate-dashboard/?autologin=sarah.chen`, re-open `#wcb-tab-settings` → expect `#wcb-account-name` to still show the saved display name (a fresh `GET {apiBase}/account` / `render.php` seed confirms persistence to the WP user record). _(class-account-endpoint.php:87-96; render.php:197-198)_
6. As `varundubey`, navigate to `http://jobboard.local/wp-admin/admin.php?page=wcb-settings&tab=emails&autologin=varundubey` → expect HTTP 200 and the Email Notifications form (nonce field `#wcb_email_nonce`, action `wcb_email_settings_save`) listing each registered email with a subject text input `input[name="wcb_email[<id>][subject]"]` and an enable checkbox `input[name="wcb_email[<id>][enabled]"]`. _(admin/class-email-settings.php:103-200, 356-425; gated by `wcb_manage_settings` cap)_
7. Confirm the **Candidate Application Confirmation** row (`id=application-confirmation`) and the **Employer New Application** row (`id=application-received`) both have their enable checkbox checked; optionally edit a subject, then submit → expect the `notice-success` "Email settings saved." banner, persisting to `get_option('wcb_settings')['emails'][<id>]['enabled'|'subject']` (read back by `WCB\...\AbstractEmail::is_enabled()`). _(modules/notifications/emails/class-email-app-confirmation.php:31-32; class-email-app-received.php:31-32; class-email-settings.php:478-485; class-abstract-email.php:65-80, 93-95)_
8. As `employer.figma`, ensure they own a published job so the employer email has a recipient (the new-application email goes to the job's `post_author`): navigate to `http://jobboard.local/employer-dashboard/?autologin=employer.figma` and post one via the Post a Job flow (`POST {apiBase}/jobs`, CREATABLE), or reuse an existing `employer.figma`-owned `wcb_job`. Note its job ID `{jobId}`. _(api/endpoints/class-jobs-endpoint.php:48 CREATABLE; recipient logic class-email-app-received.php:79-83)_
9. As `sarah.chen` (logged in via `?autologin=sarah.chen`), submit an application to `{jobId}` she has not applied to before — `POST {apiBase}/jobs/{jobId}/apply` (CREATABLE; authenticated candidate needs no guest fields) → expect HTTP 200/201, an `wcb_application` post created, and the `wcb_application_submitted` action to fire with `( $app_id, $job_id, $candidate_id )`. _(class-applications-endpoint.php:39-43, 179-219, 366)_
10. Confirm both emails fired through `wp_mail()`: query `wp_wcb_notifications_log` (or the log list on the Emails tab) → expect a row `event_type='application-confirmation'`, `channel='email'`, `status='sent'`, `payload.to` = `sarah.chen`'s email, **and** a row `event_type='application-received'`, `status='sent'`, `payload.to` = `employer.figma`'s email — both keyed to the same submit. _(class-abstract-email.php:120-165; both emails hook `wcb_application_submitted` — class-email-app-confirmation.php:68, class-email-app-received.php:68)_
11. As `employer.figma`, reload `http://jobboard.local/employer-dashboard/?autologin=employer.figma` and open the applicants list for `{jobId}` → expect `sarah.chen`'s new application to appear, confirming the end-to-end apply → notify → surface loop. _(api/endpoints/class-employers-endpoint.php:102-112 `/employers/{id}/jobs`; employer-dashboard applicants panel)_
12. tail debug.log diff → expect ZERO new fatal/warning lines.

## Teardown
```bash
# Remove the application + log rows this walkthrough created, and (optionally) the
# throwaway job employer.figma posted. Re-runnable; only touches walkthrough artifacts.
wp --path="/Users/varundubey/Local Sites/jobboard/app/public" eval '
  $sarah = get_user_by("login","sarah.chen");
  if ( $sarah ) {
    $apps = get_posts( array(
      "post_type"   => "wcb_application",
      "post_status" => "any",
      "numberposts" => -1,
      "meta_key"    => "_wcb_candidate_id",
      "meta_value"  => $sarah->ID,
    ) );
    foreach ( $apps as $a ) { wp_delete_post( $a->ID, true ); echo "deleted application ".$a->ID."\n"; }
  }
'
# Clear the two log rows from the apply event (safe: notification log is audit-only):
wp --path="/Users/varundubey/Local Sites/jobboard/app/public" db query \
  "DELETE FROM wp_wcb_notifications_log WHERE event_type IN ('application-confirmation','application-received') AND status='sent' ORDER BY id DESC LIMIT 2"
# Account display-name / password edits are normal user actions; reset if desired:
# wp user update sarah.chen --display_name='Sarah Chen' --user_pass='<original>'
```

## Notes
- **Account settings surface** is the candidate dashboard "Settings" tab (nav `#wcb-tab-settings`, label "Settings", panel titled "Account Settings"); the same `/account` endpoint backs the employer dashboard Account panel — `api/endpoints/class-account-endpoint.php:35-67` registers `GET`/`POST {apiBase}/account` (no id in route; always the current user).
- **Page slugs verified live:** `/candidate-dashboard/` and `/employer-dashboard/` (auto-created pages holding the `wp-career-board/candidate-dashboard` / `employer-dashboard` blocks — `admin/class-setup-wizard.php:414-421`).
- **`apiBase`** is `untrailingslashit( rest_url('wcb/v1') )` → `http://jobboard.local/wp-json/wcb/v1`; every dashboard fetch sends `X-WP-Nonce` from `state.nonce` (`wp_create_nonce('wp_rest')`) — `render.php:164-165`. All `view.js` fetches route through `@wcb/fetch` (15s AbortController).
- **Notification preferences** in Free are site-owner-level, not per-user: the Emails tab (`admin.php?page=wcb-settings&tab=emails`, hash-based `#emails` client tab) writes per-email `enabled` + `subject` into `wcb_settings['emails']`; `AbstractEmail::is_enabled()`/`get_subject()` read them and `send()` short-circuits when disabled (`class-abstract-email.php:61-95`). Requires the `wcb_manage_settings` capability — hence `varundubey` for steps 6-7.
- **Email triggers grounded:** both emails register on the **same** action `wcb_application_submitted` (priority 10, 3 args) — candidate `application-confirmation` (`class-email-app-confirmation.php:68`) and employer `application-received` (`class-email-app-received.php:68`); the action is fired once in `class-applications-endpoint.php:366`. The employer recipient = the job's `post_author` email (`class-email-app-received.php:79-83`), so step 8's job must be owned by `employer.figma` for that email to reach them.
- **Delivery is logged**, not asserted via an inbox: every `send()` writes a `wp_wcb_notifications_log` row (`user_id`, `event_type`=email id, `channel='email'`, `payload` JSON with `to`+`subject`, `status` `sent`/`failed`, `sent_at`) — `class-abstract-email.php:120-165`. The Emails tab also renders a recent-log table for the same data.
- **Seed dependency:** step 9 needs a job `sarah.chen` has not yet applied to (duplicate guard returns HTTP 409 — `class-applications-endpoint.php:202-207`). Seeded `wcb_job` posts (IDs 16-23) are owned by `varundubey`; to route the **employer** email to `employer.figma` specifically, post a job as `employer.figma` first (step 8) and apply to that one.
