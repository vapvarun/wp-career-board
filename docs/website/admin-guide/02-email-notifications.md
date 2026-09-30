# Email Notifications

You can control every email WP Career Board sends: switch each one on or off, change its subject and message, and send yourself a test.

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

You can turn each email on or off, change its subject and rewrite its message. Go to **Career Board → Settings → Emails**. The **Email templates** list has one row per email, with **Subject**, **Message** (**Edit** opens the editor), **Enabled** and **Test** columns. Click **Save Email Settings** when you finish.

### Edit the message

Every email ships with a ready-made body. Leave the **Message body (HTML)** field blank to send that default, or type your own text. **Load default** puts the shipped wording into the field so you can edit from there. The branded header and footer are added automatically, so enter only the message body.

**Preview** renders the subject and body you have typed, including unsaved edits, inside the branded email without sending anything. The message body comes from the body you save here, then from a theme file at `{theme}/wp-career-board/emails/{email-id}.php`, then from the shipped default.

### Members can turn off optional emails

Members can switch off the optional emails they receive. In Free these are Application Deadline Reminder (candidates) and Job Ending Soon (employers). A member opens **Settings** in their dashboard and clears the box under **Email Notifications**. Emails about their account and applications always send. WP Career Board Pro adds more optional emails to the same list.

## Send test email

Each row has a **Send test** button. It sends a copy of that email to your admin address with sample values, so you can see the rendered email before any real applicant does.

The button works for turned-off emails too. Test sends are recorded in the activity log as **Sent (test)** or **Failed (test)**, so they stay separate from real delivery. The button shows **Sent** for a moment after a successful send.

If the button shows **Failed**, check:
- An SMTP plugin is configured (a local development mail handler often fails silently).
- Your admin user has a valid email address on their profile.
- The **Email activity log** at the bottom of the tab shows the most recent attempt.

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

In the **Sender** card at the top of **Settings → Emails**, you can set:
- **From Name** - the sender name shown in inboxes (defaults to your site name).
- **From Email** - the address all Career Board emails are sent from (defaults to the site admin email).
- **Admin Notification Email** - where admin alerts such as a new job pending review are sent (defaults to the site admin email).

The header colour and logo come from **Settings → Brand**, shared with the mobile app. The **Email look** card on the Emails tab holds the **Footer Text**.

## SMTP and deliverability

Use an SMTP plugin (WP Mail SMTP, FluentSMTP or similar) for reliable delivery. WordPress's built-in mail can land in spam without SMTP.

## Email activity log {#email-activity-log}

You can check whether emails are going out in the **Email activity log** card at the bottom of **Settings → Emails**. Each row shows when, the template, recipient, subject and status (**Sent**, **Failed**, **Sent (test)** or **Failed (test)**). Filter by template and status. Rows older than **Settings → Advanced → Keep Email History** (180 days by default, 0 keeps them forever) are deleted daily.

## Emails added by Pro

WP Career Board Pro adds these emails to the same **Emails** tab, with the same subject, message, on/off and test controls: Job Alert Digest, Confirm Job Alert (guest), Credit Top-Up Confirmation, Credit Purchase Receipt, Credit Refund, Low Credit Balance Warning and Featured Listing Ended. Read the Pro documentation for when each one is sent.

## Deadline reminders {#deadline-reminders}

Candidates who saved a job but have not applied get a reminder before its application deadline.

| When | Sent to |
|---|---|
| 3 days before the deadline | Candidates who saved the job |
| 1 day before the deadline | Candidates who saved the job |

A candidate gets each reminder once per job. Candidates who already applied for the job are skipped. Employers get the separate **Job Ending Soon** email 3 days before the deadline.

### Turn reminders off

Open **Settings → Emails** and switch off **Application Deadline Reminder**. Members can also switch it off for themselves, see [Members can turn off optional emails](#members-can-turn-off-optional-emails).

### Reminder wording

The default subject is "Application deadline approaching for {job_title}". Placeholders: `{job_title}`, `{company_name}`, `{days_left}`, `{deadline_date}`, `{job_url}`. Edit the wording under **Settings → Emails**. To replace the markup, add `deadline-reminder.php` to your theme's `wp-career-board/emails/` folder. A body saved under Settings → Emails wins over the theme file.
