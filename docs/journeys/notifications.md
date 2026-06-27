---
feature: module — transactional notifications on apply / status change
roles: candidate, employer, admin
surface: email (wp_mail) via wcb_registered_emails + wcb_notifications_log + admin Email Activity Log
last_walked: 2026-06-26
---

# Notifications — full browser walkthrough

**What it is:** Career Board fires transactional emails on application submit and on application status change. Both candidate and employer are reached, each via their own template; every send is recorded in `wcb_notifications_log`. (In-app bell notifications are a Pro surface — `wp-career-board-pro`; Free is email-based.)
**Where it lives:** Templates registered via `wcb_registered_emails` (`NotificationsModule`), wired to action hooks `wcb_application_submitted` / `wcb_application_status_changed`. Admin-visible record: **Email Activity Log** at `wp-admin/admin.php?page=wcb-settings#emails`.

## As candidate (apply)
1. `?autologin=wcb_demo_candidate` → open a published job → submit the apply form → `POST /wcb/v1/jobs/{id}/apply`.
2. The candidate receives **Application Confirmation** (`app_confirmation`) — a receipt for their application. A guest applicant instead gets **Application Guest** (`app_guest`) with a magic-link.
3. Each send respects its per-email Enabled toggle and custom subject from the Emails screen; a disabled template does not fire.

## As employer (apply received + status change)
1. The same submit fires **Application Received** (`app_received`) to the job's employer — they learn a new candidate applied (cross-check `employer-applications.md`).
2. `?autologin=wcb_demo_employer` → employer dashboard → Applications → change an application's status → `PUT /wcb/v1/applications/{id}/status` fires `wcb_application_status_changed`.
3. That fires **Application Status** (`app_status`) to the **candidate** (e.g. "Shortlisted"). The employer initiates the change, so no employer-side status email.

## As admin (verify delivery)
1. `?autologin=1` → `wp-admin/admin.php?page=wcb-settings#emails` → **Email Activity Log** → filter by template/status → confirm the apply + status-change rows show as **Sent** with the correct recipient.
2. From/From-Name come from Settings → Notifications (`wp_mail_from` / `wp_mail_from_name`); the admin notification address is `notification_email`.

## Themes & states
- Emails render with the brand header colour + logo from the Emails screen; the HTML template is readable in major mail clients (header/footer partials).
- Disabled-template state: toggle off → no row written for that event; the flow itself still succeeds.
- Failure: `wp_mail()` returning false writes a `failed` row so the admin can see non-delivery.

## Contracts guarded
- Both sides reached on apply: candidate (confirmation/guest) AND employer (received) from one `wcb_application_submitted`.
- Status change emails the candidate, not the actor.
- Every send (incl. failures) is logged to `wcb_notifications_log`; the Activity Log is the verification surface.
- Enable toggle + custom subject from the Emails screen are honoured per template.
