---
id: seeker-register-candidate-account
priority: high
personas: anonymous, sarah.chen
requires: mu:autologin
last_verified: 2026-07-08
covers: POST /wcb/v1/candidates/register, GET+POST /wcb/v1/account, candidate-dashboard block, wcb_candidate role
---

# Register a candidate account — sign up, then update account settings

**Why this journey exists:** self-registration is the top-of-funnel for every new candidate, and the shared
Account Settings panel is how they keep name/email/password current afterward. This consolidates
`customer/candidate-register` (the register REST → WP user + `wcb_resume` transaction) and
`customer/account-settings-update` (the in-place name/email/password change that must not log the user out) into
one human-runnable pass.

## Steps

1. As `anonymous`, `GET /wp-json/wcb/v1/candidates/register` → expect HTTP 405 (only `CREATABLE`/POST is
   registered; a GET must not 200) (`api/endpoints/class-candidates-endpoint.php:90-96`).
2. As `anonymous`, `POST /wp-json/wcb/v1/candidates/register` with body
   `{"username":"smoke.candidate.reg","email":"smoke.candidate.reg@example.test","password":"Test1234!"}` →
   expect HTTP 201 and a body containing `user_id` (integer > 0). Route:
   `/candidates/register`, `permission_callback => '__return_true'`
   (`class-candidates-endpoint.php:90-96`); handler `register_candidate()` fires `do_action('wcb_candidate_registered', $user_id)`
   (`:172,272`).
3. Verify the WP user exists with the candidate role:
   `wp user get smoke.candidate.reg --field=user_email` = `smoke.candidate.reg@example.test`;
   `wp user get smoke.candidate.reg --field=roles` contains `wcb_candidate`.
4. Verify the linked `wcb_resume` CPT record was created for the new user:
   `wp post list --post_type=wcb_resume --author=$(wp user get smoke.candidate.reg --field=ID) --field=ID` →
   expect at least one row (the `wcb_resume` CPT is the candidate-profile record).
5. Attempt a duplicate: `POST /wp-json/wcb/v1/candidates/register` again with the same email → expect HTTP 400 or
   409 with a non-empty error `code` (NOT 200/500). Registering an email already tied to an employer account
   returns the "already registered as an employer" error (`class-candidates-endpoint.php:158-165`).
6. As the new user (autologin), `GET /candidate-dashboard/?autologin=smoke.candidate.reg` → expect HTTP 200 and
   the dashboard shell `div.wcb-dashboard[data-wp-interactive="wcb-candidate-dashboard"]` (NOT a login redirect,
   NOT the `.wcb-db-gate` fallback) (`blocks/candidate-dashboard/render.php:18-28`).
7. Switch to the seeded candidate for the settings half: open `/candidate-dashboard/?autologin=sarah.chen`, click
   `#wcb-tab-settings` (`actions.switchToSettings`) → expect the Account Settings panel active with `#wcb-account-name`,
   `#wcb-account-email`, and the Change Password fields `#wcb-account-curpw`/`#wcb-account-newpw`/`#wcb-account-confpw`;
   NO bounce-away "Reset Password" link (`blocks/candidate-dashboard/render.php:376-381,1038-1111`).
8. `GET /wp-json/wcb/v1/account` → expect `{ display_name, email }` for the current user
   (`api/endpoints/class-account-endpoint.php:36-43,92`).
9. Change the Display Name + Save → `POST /wp-json/wcb/v1/account` `{display_name, email}` → expect HTTP 200 and
   an inline "Account updated." confirmation; `wp user get $(wp user get sarah.chen --field=ID) --field=display_name`
   reflects the new value (`class-account-endpoint.php:46-58,114-116`).
10. Email validation: `POST /wp-json/wcb/v1/account` with an email already used by another account → expect HTTP
    409; with a malformed email → expect HTTP 400 (no change persisted).
11. Password change: submit a wrong current password → expect HTTP 403 `wcb_bad_current_password`
    (`class-account-endpoint.php:143-149`); a new password under 8 chars → expect `wcb_weak_password`
    (`:150-153`); correct current + a valid ≥8-char new password → expect HTTP 200, `password_changed: true`, a
    fresh `nonce` in the response, and the session stays logged in (the endpoint re-issues the auth cookie —
    `:157-158,176-181`; subsequent REST writes still succeed).
12. tail debug.log diff → expect ZERO new fatal/warning lines.

## Teardown (safe to re-run)

```bash
# Delete the registration smoke user + its resume CPT record.
REG_ID=$(wp user get smoke.candidate.reg --field=ID 2>/dev/null)
if [ -n "$REG_ID" ]; then
  RES=$(wp post list --post_type=wcb_resume --author="$REG_ID" --field=ID 2>/dev/null)
  [ -n "$RES" ] && wp post delete $RES --force
  wp user delete "$REG_ID" --reassign=1 --yes
fi

# Restore sarah.chen's original name/email/password if the settings half changed them.
SARAH_ID=$(wp user get sarah.chen --field=ID)
wp user update "$SARAH_ID" --display_name="Sarah Chen" --user_email="<original>"
wp user update "$SARAH_ID" --user_pass="<original test password>"
```

## Notes

- `AccountEndpoint` (`/wcb/v1/account`) is current-user scoped and shared by BOTH the candidate and employer
  dashboards, so this settings coverage doubles for the employer side.
- If the install requires email verification before the role is granted, step 3 may return `subscriber` instead
  of `wcb_candidate` — record which, and confirm the unverified state does not grant `wcb_apply_jobs`.
- Registration is gated by WP's `users_can_register` option unless multisite (`class-candidates-endpoint.php:187`);
  if step 2 returns a registration-disabled error, enable open registration first.
- The autologin mu-plugin is the login trigger — append `?autologin=<login>` to any URL.
</content>
</invoke>
