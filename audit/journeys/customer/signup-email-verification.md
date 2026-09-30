---
id: signup-email-verification
priority: critical
personas: anonymous
requires: mu:autologin
last_verified: 2026-09-27
needs: cli, mailpit
bug_ref: 10340678963, 10340678827, 10344034506
---

# New members confirm their email, sign-ups are spam-gated, and signing up never strips a role

**Why this journey exists:** the sign-up routes skipped the anti-spam gate and core's `registration_errors`, signed every new account in with an unconfirmed address, and replaced the role of a logged-in admin or editor who signed up. A pending account deletion also lifted an administrator's ban when cancelled.

## Steps

1. Settings > Listings: Email Verification on, Settings > General: "Anyone can register" on
2. Logged out, open the registration page, create an account → "Check your inbox" panel with a "Resend the link" button; no auth cookie; header still shows Login
3. `wp eval "echo is_wp_error(wp_authenticate('<login>','<pw>'))?'blocked':'ok';"` → `blocked` (`wcb_email_unverified`)
4. Mailpit: "Confirm your email address" arrives; its link `/?wcb_verify=<id>.<token>` signs in and lands on the dashboard; opening it again goes to the login page, not an error
5. Click "Resend the link" before confirming → "We sent you a new link…" and a second email arrives; POST `/wp-json/wcb/v1/auth/verify-email/resend` for an unknown address gives the same generic answer
6. Six sign-ups from one IP within an hour → the sixth is 429 `wcb_rate_limited`; a filled honeypot is refused
7. Logged in as an editor, sign up as a candidate → roles are `editor, wcb_candidate`; a subscriber signing up as employer becomes `wcb_employer` only
8. Ban an employer, request account deletion, cancel it → the ban stays, POST `/wp-json/wcb/v1/jobs` still 403
9. Registration off → the block shows "New account registration is closed on this site." and a Sign in link, no form
10. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

```bash
wp user delete <test users> --yes
wp transient delete --all
```
