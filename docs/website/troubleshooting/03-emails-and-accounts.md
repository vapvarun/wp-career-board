# Troubleshooting: emails and accounts

Find your symptom below. Each entry gives the cause and the fix.

## Career Board emails are not arriving

Work down this list:

1. **Is the email switched on?** Each email has an **Enabled** switch under **Career Board > Settings > Emails**.
2. **Is it in the log?** The **Email activity log** on the Emails tab lists each send with its template, recipient and status (Sent, Failed, Sent (test), Failed (test)). A Sent row means WordPress handed the message to your mail system, so the problem is delivery. A Failed row means `wp_mail()` refused it. Use **Send test** on a template to check.
3. **Is delivery set up?** Install an SMTP plugin and connect a sending service. WordPress's default mail function is often rejected or filed as spam.
4. **Is the sender right?** Check **From Name** and **From Email** in the **Sender** card. Use an address on your own domain.
5. **Did the member opt out?** Two emails are optional: the application deadline reminder for candidates and the job ending soon email for employers. Members turn them off under **Settings > Email Notifications** in their dashboard. Emails about accounts, applications and payments always send.
6. **Was the row removed?** Log rows older than **Keep Email History (days)** under **Settings > Advanced** are deleted automatically. The default is 180 days.

## A new member did not get a welcome email

**Cause.** The Welcome email is not sent while **Email Verification** is on, because the confirmation email goes first and serves as the welcome.

**Fix.** Nothing is wrong. To send the welcome email, turn Email Verification off under **Settings > Sign-ups**.

## A new member cannot sign in

**Symptom.** Sign-in says "Please confirm your email address first."

**Cause.** **Email Verification** is on and the member has not opened the link in the confirmation email. The email may be in spam, or the link expired.

**Fix.** The sign-in message and the expired-link page both offer **Send me a new link**. A new link can be requested about once a minute. If the confirmation email never arrives, work through "Career Board emails are not arriving" above.

## A guest did not get status emails

**Cause.** Guests are emailed only if they gave an email address when applying. The employer dashboard says so when this is the case: "This applicant left no email address, so they were not notified."

## The mobile app cannot sign in with a password

**Cause.** **App Password Sign-In** is off by default. It also needs a secure (HTTPS) connection, and you should leave it off if you use two-factor authentication.

**Fix.** Turn it on under **Settings > Mobile App**, or have members use the app's **Connect with WordPress** option. That option sends them to your normal login page and needs no setting.

## A member asked to delete their account by mistake

**Fix.** The account is locked, not yet deleted, for a waiting period of 14 days by default. The member can sign in again and choose **Keep my account**. You can also cancel it for them with **Keep account** under **Settings > Privacy > Pending account deletions**. Both send an "Account Deletion Cancelled" email.

## A member cannot change their email

**Cause.** Changing the email needs the member's **Current password**. Without it the save is refused.

## The sign-up form says registration is closed

**Cause.** WordPress's **Anyone can register** setting under **Settings > General** is off, so the sign-up forms show a "registration closed" notice. **Open Sign-Up** under **Career Board > Settings > Sign-ups** shows the current state of that setting.
