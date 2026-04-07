# Email Notifications

WP Career Board sends automatic emails for key events. All emails use WordPress's built-in `wp_mail()` function and are fully customizable.

![Email Notifications Settings](../images/settings-notifications.png)

## Notification Events

| Email | Sent To | Trigger |
|---|---|---|
| **New Application Received** | Employer | A candidate submits an application |
| **Application Status Changed** | Candidate | Employer updates application status (Reviewed, Shortlisted, Closed) |
| **Application Withdrawn** | Employer | Candidate withdraws their application |
| **Job Approved** | Employer | Admin approves a pending job listing |
| **Job Rejected** | Employer | Admin rejects a pending job listing |
| **Job Expiry Reminder** | Employer | Job is about to expire (sent 3 days before) |
| **Job Expired** | Employer | Job has expired and been closed |
| **New Employer Registration** | Admin | A new user registers as an employer |
| **New Candidate Registration** | Admin | A new user registers as a candidate |

## Managing Notifications

Go to **WP Career Board → Settings → Emails**.

Each notification can be:
- **Enabled or disabled** — toggle the switch to turn it on or off
- **Customized** — edit the email subject and body text

Click the email name to expand the editor for that notification.

## Email Placeholders

Use these placeholders in email subjects and bodies — they are replaced with real values when the email sends:

| Placeholder | Value |
|---|---|
| `{job_title}` | The job listing title |
| `{company_name}` | The employer's company name |
| `{candidate_name}` | The applicant's full name |
| `{application_status}` | Current status of the application |
| `{dashboard_url}` | Link to the employer or candidate dashboard |
| `{job_url}` | Link to the job listing page |
| `{site_name}` | Your WordPress site name |

## Email From Name and Address

Go to **WP Career Board → Settings → Notifications** to set:
- **From Name** — the sender name shown in inboxes (e.g. "Career Board")
- **From Email** — the reply-to address for all WCB emails
- **Admin Notification Email** — where new job and registration alerts are sent

## SMTP / Deliverability

For reliable email delivery, use an SMTP plugin (WP Mail SMTP, FluentSMTP, or similar). WordPress's built-in mail function can land in spam without SMTP configuration.

---

## Pro Email Notifications (Pro)

WP Career Board Pro extends the email system with three additional transactional emails. You can customise the subject line and enable or disable each one from **Career Board -> Settings -> Emails**.

### Job Alert Digest

- **Recipient:** Candidate
- **Trigger:** Fired when the Job Alerts module finds new jobs matching a candidate's saved search
- **Content:** A list of matching job titles with direct links

### Credit Top-Up Confirmation

- **Recipient:** Employer
- **Trigger:** When a credit purchase completes via a supported payment gateway (WooCommerce, Paid Memberships Pro, or MemberPress)
- **Content:** Confirmation of the purchase and updated balance

### Low Credit Balance Warning

- **Recipient:** Employer
- **Trigger:** Fired when an employer's credit balance reaches zero
- **Content:** Balance warning and a link to the Employer Dashboard to purchase more credits

### Email Template Customisation

All Pro emails use the same templating system as Free emails. To override a template, copy the relevant file from `modules/notifications-pro/templates/emails/` into your theme's `woocommerce/` folder or use the `wcb_email_template_dirs` filter to add a custom template directory.

## In-App Notification Bell (Pro)

The notification bell appears in the Employer Dashboard and Candidate Dashboard. It shows a live unread count and drops down to display a list of recent notifications, each with a message and a link to the relevant page.

### Events That Trigger Bell Notifications

| Event | Who Receives It | Message Example |
|-------|----------------|----------------|
| Application submitted | Employer | "Jane Doe applied for Senior PHP Developer" |
| Application submitted | Candidate | "Your application for Senior PHP Developer was submitted" |
| Application status changed | Candidate | "Your application for Senior PHP Developer is now Shortlisted" |
| Job approved | Employer | "Your job 'Senior PHP Developer' has been approved" |
| Job rejected | Employer | "Your job 'Senior PHP Developer' was not approved" |
| Job expired | Employer | "Your job 'Senior PHP Developer' has expired" |

All notifications are stored in the `wcb_notifications` database table. The `is_read` flag is set to `0` on insert. The bell badge count reflects the number of unread rows for the current user.
