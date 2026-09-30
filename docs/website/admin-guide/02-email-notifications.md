# Email Notifications

WP Career Board sends automatic emails for key events. All emails use WordPress's built-in `wp_mail()` function and are fully customizable.

![Email Notifications Settings](../images/settings-notifications.png)

## Notification events

Free sends these emails. Each one can be switched off and edited under **Career Board → Settings → Emails**.

| Email | Sent to | When |
|---|---|---|
| **New Job Pending Review** | Admin | An employer submits a job that needs approval |
| **Report Received** | Admin | A job or member is reported for the first time, and when reports hide a job |
| **Job Approved** | Employer | A moderator approves a pending job |
| **Job Rejected** | Employer | A moderator rejects a pending job |
| **Job Ending Soon (Employer)** | Employer | 3 days before a job's deadline, once per deadline, with a link to extend it |
| **Job Expired** | Employer | A job passes its deadline (only jobs that ended in the last 7 days, so turning expiry on for an old site does not email a backlog) |
| **Application Received (Employer)** | Employer | A candidate or guest applies |
| **Application Withdrawn (Employer)** | Employer | A candidate withdraws an application |
| **Application Confirmation (Candidate)** | Candidate | A registered candidate applies |
| **Application Confirmation (Guest)** | Guest | A guest applies |
| **Application Status Changed** | Candidate or guest | The employer sets Reviewing, Shortlisted or Hired, or a job closes (Position closed). Saving the same status again sends nothing. |
| **Application Not Selected** | Candidate or guest | The employer sets Rejected. A gentler message than the generic status email. |
| **Application Deadline Reminder** | Candidate | 3 days and 1 day before the deadline of a job they saved. See [Deadline reminders](#deadline-reminders). |
| **Confirm Your Email** | New member | Sign-up while **Email Verification** is on. Also sent again from the sign-in message or the expired-link page (one a minute). |
| **Welcome** | New member | After sign-up. Skipped when Email Verification is on, because the confirmation email comes first. |
| **Account Deletion Requested** | Member | They ask to delete their account. The email states the deletion date. |
| **Account Deletion Cancelled** | Member | They (or an admin) cancel the deletion |
| **Account Deleted** | Member | Just before the account is deleted, at the end of the grace period |

Guests who applied with an email address get the guest confirmation, status and not-selected emails. A guest who left no email cannot be notified.

Every email is written in the recipient's own language: dates and status names follow the recipient's locale, not the locale of whoever triggered it.

## Managing notifications

Go to **WP Career Board → Settings → Emails**.

Each notification can be:
- **Enabled or disabled** - toggle the switch to turn it on or off
- **Customized** - edit the email subject and body text

Click the email name to expand the editor for that notification.

### Editable body per template

Every notification ships with a ready-to-use default body, and since
1.6.0 that body is fully editable per template from this screen. Leave
the body field blank to send the shipped default, or type your own
text to override it - a **Load default** button next to the field
loads the shipped wording back in as a starting point if you want to
edit from there instead of writing from scratch. If a template's body
is left empty, the email still sends with its sensible default rather
than going out blank.

### Preview

Each email has a **Preview** button next to **Load default**. It renders the subject and body you have typed, including unsaved edits, inside the branded email wrapper without sending anything. Order of precedence for the message body: the body you save here, then a theme file at `{theme}/wp-career-board/emails/{email-id}.php`, then the shipped default.

### Members can turn off optional emails

Deadline reminders and Job Ending Soon are optional. Members switch them off under **Settings → Email Notifications** in their own dashboard. Emails about accounts, applications and payments always send.

## Send test email

Each template ships with a **Send test** button on the right of the row. Clicking it dispatches a one-shot copy of that email to the admin user's address with sample merge-tag values, so you can preview the rendered template before any real applicant sees it.

![Send test email button in the Sent state](../images/test-email-sent-state.png)

The button works for both enabled and disabled templates - disabled templates are still rendered and dispatched for preview, but their log rows are tagged `sent_test` in the activity log so admin previews stay separate from production delivery metrics. A green check + "Sent" label appears for 2.5 seconds after a successful dispatch, then resets.

If the button shows "Failed", check:
- An SMTP plugin is configured (the local dev mail handler often fails silently)
- The admin user has a valid email address on their profile
- The Email Activity Log row says `sent_test` for the most recent attempt - if the row is missing, see the [self-heal note](#email-activity-log) below

## Email placeholders

Use placeholders in subjects and bodies. They are replaced when the email sends. The **Insert tag** buttons in the editor show exactly the tags each email supports.

| Email | Placeholders |
|---|---|
| Application Confirmation, Application Received, Application Withdrawn | `{candidate_name}` `{job_title}` `{dashboard_url}` |
| Application Confirmation (Guest) | `{guest_name}` `{job_title}` `{job_url}` |
| Application Status Changed | `{candidate_name}` `{job_title}` `{new_status}` `{dashboard_url}` |
| Application Not Selected | `{candidate_name}` `{job_title}` `{jobs_url}` `{dashboard_url}` |
| Application Deadline Reminder | `{job_title}` `{company_name}` `{days_left}` `{deadline_date}` `{job_url}` |
| Job Approved | `{job_title}` `{job_url}` |
| Job Rejected | `{job_title}` `{reason}` |
| Job Ending Soon | `{job_title}` `{deadline_date}` `{days_left}` `{edit_url}` `{job_url}` |
| Job Expired | `{job_title}` `{repost_url}` |
| New Job Pending Review | `{job_title}` `{approve_url}` |
| Report Received | `{item}` `{reason}` `{report_count}` `{hidden_note}` `{review_url}` |
| Confirm Your Email | `{display_name}` `{verify_url}` |
| Welcome, Account Deletion Cancelled, Account Deleted | `{user_name}` `{site_name}` `{login_url}` |
| Account Deletion Requested | `{user_name}` `{delete_date}` `{login_url}` |

## Email from name and address

Go to **WP Career Board → Settings → Emails**, in the **Sender** card at the top of the tab, to set:
- **From Name** - the sender name shown in inboxes (e.g. "Career Board")
- **From Email** - the address all Career Board emails are sent from
- **Admin Notification Email** - where admin alerts (e.g. new job pending review) are sent

The header colour and logo come from **Settings → Brand**, shared with the mobile app. The **Email look** card on the Emails tab holds the **Footer Text**.

## SMTP / deliverability

For reliable email delivery, use an SMTP plugin (WP Mail SMTP, FluentSMTP, or similar). WordPress's built-in mail function can land in spam without SMTP configuration.

## Email activity log {#email-activity-log}

Every dispatched email writes a row to `wp_wcb_notifications_log` and surfaces on the **Activity Log** tab at the bottom of Settings → Emails. Rows show the template, recipient, subject, status (`sent` / `failed` / `sent_test` / `failed_test`), and timestamp. You can filter by template and status. Rows older than **Settings → Advanced → Keep Email History** (180 days by default, 0 keeps them forever) are deleted daily.

The log table is created on plugin activation. If for any reason the table is missing (e.g. a database migration dropped it, or the plugin was installed pre-1.0.x and skipped the activation routine), the dispatch path self-heals the table on first send rather than failing silently - your previously missing log entries will start populating from the next dispatch onward.

---

## Pro email notifications (Pro)

WP Career Board Pro extends the email system with three additional transactional emails. You can customise the subject line and enable or disable each one from **Career Board -> Settings -> Emails**.

### Job alert digest

- **Recipient:** Candidate
- **Trigger:** Fired when the Job Alerts module finds new jobs matching a candidate's saved search
- **Content:** A list of matching job titles with direct links

### Credit top-up confirmation

- **Recipient:** Employer
- **Trigger:** When a credit purchase completes via a supported payment gateway (WooCommerce, Paid Memberships Pro, or MemberPress)
- **Content:** Confirmation of the purchase and updated balance

### Low credit balance warning

- **Recipient:** Employer
- **Trigger:** Fired when an employer's credit balance reaches zero
- **Content:** Balance warning and a link to the Employer Dashboard to purchase more credits

### Email template customisation

All Pro emails use the same templating system as Free emails. To override a template, copy the relevant file into your theme's `wp-career-board/emails/` folder (the same override location Free uses), or register a custom template directory with the `wcb_email_template_dirs` filter.

## In-app notification bell (Pro)

The notification bell appears in the Employer Dashboard and Candidate Dashboard. It shows a live unread count and drops down to display a list of recent notifications, each with a message and a link to the relevant page.

### Events that trigger bell notifications

| Event | Who Receives It | Message Example |
|-------|----------------|----------------|
| Application submitted | Employer | "Jane Doe applied for Senior PHP Developer" |
| Application submitted | Candidate | "Your application for Senior PHP Developer was submitted" |
| Application status changed | Candidate | "Your application for Senior PHP Developer is now Shortlisted" |
| Job approved | Employer | "Your job 'Senior PHP Developer' has been approved" |
| Job rejected | Employer | "Your job 'Senior PHP Developer' was not approved" |
| Job expired | Employer | "Your job 'Senior PHP Developer' has expired" |

All notifications are stored in the `wcb_notifications` database table. The `is_read` flag is set to `0` on insert. The bell badge count reflects the number of unread rows for the current user.

## Deadline reminders {#deadline-reminders}

Candidates who saved a job but haven't applied get
automated reminders before the application deadline closes.

### Reminder schedule

| When | Email |
|---|---|
| **3 days** before the deadline | "Your saved job is closing soon" reminder |
| **1 day** before the deadline | "Last chance to apply" final reminder |

Both reminders are skipped if:

- The candidate has already submitted an application for that job, OR
- The candidate has un-saved the job, OR
- The job has been closed / removed before the cron fires.

### Cron event

Registered as `wcb_send_deadline_reminders`, runs daily.

WordPress's wp-cron triggers it on the next page load after the
scheduled time - for low-traffic sites, install a real cron job that
hits `wp-cron.php` to keep timing accurate.

To trigger manually:

```bash
wp cron event run wcb_send_deadline_reminders
```

### Disabling deadline reminders

The deadline reminder is one of the email templates on the **Career
Board → Settings → Emails** tab. Toggle its **Enabled** switch off to
stop the reminders. The cron stays scheduled (so re-enabling is one
click) but the disabled template is not dispatched.

Toggling the template off is the supported way to stop the reminders and
is all most sites need.

To stop the cron entirely as well (for example, on a staging environment),
unschedule the event with WP-CLI:

```bash
wp cron event delete wcb_send_deadline_reminders
```

Or unschedule it in code:

```php
$timestamp = wp_next_scheduled( 'wcb_send_deadline_reminders' );
if ( $timestamp ) {
    wp_unschedule_event( $timestamp, 'wcb_send_deadline_reminders' );
}
```

The plugin re-schedules the event on the next page load, so deleting it is
mainly useful when the plugin is also being deactivated.

### Email template

The email uses the Brand colour and logo. The default subject is "Application deadline approaching for {job_title}". Placeholders: `{job_title}`, `{company_name}`, `{days_left}`, `{deadline_date}`, `{job_url}`.

Edit the wording under **Settings → Emails**. To replace the markup instead, copy `modules/notifications/templates/emails/deadline-reminder.php` into your theme's `wp-career-board/emails/` folder. A body saved under Settings → Emails wins over the theme file.
