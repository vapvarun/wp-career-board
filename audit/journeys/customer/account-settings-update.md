---
id: account-settings-update
priority: high
personas: sarah.chen
requires: mu:autologin
last_verified: 2026-06-09
bug_ref: 9976177430
---

# Account Settings — update name/email and change password in-place

**Why this journey exists:** the dashboard Account Settings panel must let a user edit their display name + email and change their password without leaving the dashboard, and a password change must not log them out. Guards the rebuild of the panel (was a read-only email + a bounce-away "Reset Password" link). Same panel ships in both candidate and employer dashboards.

## Steps

1. As `sarah.chen`, open the dashboard → Account Settings (Settings tab) → expect HTTP 200; Display Name + Email inputs are pre-filled, and a Change Password section (current/new/confirm) renders. NO bounce-away "Reset Password" link
2. GET `/wp-json/wcb/v1/account` → expect `{ display_name, email }` for the current user
3. Change the Display Name + Save → POST `/wp-json/wcb/v1/account` `{display_name, email}` → expect HTTP 200, inline "Account updated." success; `wp user get <id> --field=display_name` reflects the new value
4. Email validation: POST an email already used by another account → expect HTTP 409; POST a malformed email → expect HTTP 400 (no change persisted)
5. Password change: submit mismatched new/confirm → client blocks with "do not match" (no request). Submit wrong current password → expect HTTP 403 `wcb_bad_current_password`. Submit correct current + valid new (>= 8 chars) → expect HTTP 200, `password_changed: true`, a fresh `nonce` in the response, and the session stays logged in (subsequent REST writes succeed)
6. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

```bash
# restore the persona's original name/email/password if changed
wp user update <id> --display_name="<original>" --user_email="<original>"
wp user update <id> --user_pass="<original test password>"
```

## Notes

- Endpoint: `AccountEndpoint` (`/wcb/v1/account`), current-user scoped, shared by both dashboards.
- Password change re-issues the auth cookie + returns a new `wp_rest` nonce; the JS swaps `state.nonce` so the session survives. Full activation/round-trip verified in-browser at 390px too.
