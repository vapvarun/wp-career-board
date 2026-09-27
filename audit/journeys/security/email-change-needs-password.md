---
id: email-change-needs-password
priority: high
personas: marcus.williams
requires: mu:autologin
last_verified: 2026-09-27
bug_ref: 10344034506
---

# Changing the account email needs the current password

**Why this journey exists:** anyone holding a signed-in session (a shared computer, a stolen cookie) could change the email and then reset the password, taking the account over.

## Steps

1. As `marcus.williams`, Dashboard > Settings → the Account Settings form shows "Current password" with the hint "Needed only if you change your email."
2. POST `/wp-json/wcb/v1/account` with a new `email` and no `current_password` → 403 `wcb_bad_current_password`
3. Same with a wrong `current_password` → 403
4. Same with the right password → 200 and the new address is stored; the field clears
5. Change only the display name with no password → 200
6. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

```bash
wp user update marcus.williams --user_email=<original>
```
