# Troubleshooting: emails and accounts

Each entry gives the symptom, the cause and the fix.

## Career Board emails are not arriving

**Cause and fix.** Work down this list:

1. **Is the email switched on?** Each email has an **Enabled** switch under **Career Board → Settings → Emails**.
2. **Is it in the log?** The **Email activity log** at the bottom of the Emails tab lists each send with its template, recipient and status (`sent`, `failed`, `sent_test`, `failed_test`). A `sent` row means WordPress handed the message to your mail system, so the problem is delivery. A `failed` row means `wp_mail()` refused it. Use **Send test** on a template to check.
3. **Is delivery set up?** Install an SMTP plugin and connect a sending service. WordPress's default mail function is often rejected or filed as spam.
4. **Is the sender right?** Check **From Name** and **From Email** in the **Sender** card. Use an address on your own domain.
5. **Did the member opt out?** Deadline reminders and Job Ending Soon are optional. A member can turn them off in their dashboard under **Settings → Email Notifications**. Emails about accounts, applications and payments always send.
6. **Was the row pruned?** Log rows older than **Keep Email History** (180 days by default) are deleted daily.

## A new member did not get a welcome email

**Cause.** The Welcome email is skipped when **Email Verification** is on, because the "Confirm your email" message goes first.

**Fix.** Nothing is wrong. If you want both, turn Email Verification off under **Settings → Sign-ups**.

## A new member cannot sign in

**Symptom.** Sign-in says "Please confirm your email address first."

**Cause.** **Email Verification** is on and the member has not opened the link in the confirmation email. The email may be in spam, or the link expired.

**Fix.** The sign-in message and the expired-link page both offer **Send me a new link**. A new link can be requested about once a minute, and a connection can request five an hour. If the confirmation email never arrives, work through "Career Board emails are not arriving" above. You can also turn Email Verification off under **Settings → Sign-ups**; accounts that are still waiting stay unconfirmed until they use a link.

## A guest did not get status emails

**Cause.** Guests are emailed only if they gave an email address when applying. The dashboard tells the employer when this is the case: "This applicant left no email address, so they were not notified."

## The mobile app cannot sign in with a password

**Cause.** **App Password Sign-In** is off by default. It also needs HTTPS, and it does not work for members whose account uses two-factor authentication.

**Fix.** Either turn it on under **Settings → Mobile App**, or have members use the app's **Connect with WordPress** option, which sends them to your normal login page and needs no setting.

## A member asked to delete their account by mistake

**Fix.** The account is locked, not yet deleted, for 14 days. The member can sign in again and choose **Keep my account**. You can also cancel it for them with **Keep account** under **Settings → Privacy → Pending account deletions**. Both send an "account will not be deleted" email.

## A member cannot change their email

**Cause.** Changing the email needs the member's **Current password**. Without it the save is refused.

## Sign-ups are refused or the sign-up form says registration is closed

**Cause.** WordPress's **Anyone can register** setting (Settings → General) is off, so the forms show "registration closed". A separate hourly limit applies per IP; see [Too many sign-ups from your network](./02-jobs-and-applications.md#too-many-sign-ups-from-your-network).
