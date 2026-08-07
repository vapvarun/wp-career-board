---
id: app-password-signin-is-opt-in-and-revocable
priority: critical
personas: demo
requires: mu:autologin
last_verified: 2026-08-07
bug_ref: 1.7.1 auth wave (wp-career-board 70dd3fb)
---

# Signing in to the app never opens a door the owner did not open, and signing out closes it

**Why this journey exists:** `POST /wcb/v1/auth/app-password` accepts a real
account password, which makes it the one route on this plugin that is a
brute-force oracle in a way wp-login.php is not. Four things have to stay true
about it, and each one was wrong at some point in 1.7.1: it must be off unless
the owner turned it on, the owner must be able to turn it off from the admin,
signing out must end the credential rather than merely forget it, and the
per-IP throttle must not collapse every visitor onto one bucket behind a proxy.

## Steps

1. **Off by default.** On a site that has never saved the setting, call
   `POST /wcb/v1/auth/app-password` with a correct username and password →
   expect `403 wcb_app_passwords_off`, and expect NO new row in
   `wp_usermeta` `_application_passwords` for that user. A correct password
   must not mint anything while the switch is off.

2. **The switch is reachable.** As an administrator, open
   **Career Board → Settings → Job Listings** → expect a visible
   "App Password Sign-In" toggle, off. Turn it on, Save Changes, reload →
   expect it still on, and expect neighbouring listings settings
   (`jobs_per_page`, `candidate_requires_role`) unchanged. A setting the
   admin cannot reach is the same as no setting: this step is the whole
   reason the toggle exists.

3. **The app reflects the site.** With the switch OFF, open the app sign-in
   screen for this site → expect "Connect with WordPress" offered and
   **no** "Sign in with your website password" option. The app must lead with
   the browser hand-off, not show a button that 403s.

4. **Exchange works when enabled.** With the switch ON, repeat step 1 →
   expect `200` carrying `user_login` + `password`, exactly one new
   application-password row, and `Cache-Control: no-store` on the response.

5. **Wrong password and unknown user are indistinguishable.** Call with a
   wrong password, then with a username that does not exist → expect the same
   error code AND the same message bytes for both. Any difference is an
   account-enumeration oracle.

6. **Sign-out revokes server-side.** Signed in on the app, note the credential
   count for the user. Sign out → expect the count to drop by exactly one, and
   expect the credential that was in use to be the one gone (other credentials
   for the same user survive). Then attempt `wp_authenticate_application_password()`
   with the revoked value → expect a `WP_Error`. A sign-out that only clears the
   device leaves a lost phone authorised forever.

7. **Revocation is safe to call when there is nothing to revoke.** Call
   `DELETE /wcb/v1/auth/app-password` under cookie auth → expect
   `200 {"revoked": false}`, not an error. Sign-out must never fail.

8. **The IP bucket is not the proxy.** With no filter set, confirm
   `AppCredentials::client_ip()` returns `REMOTE_ADDR` even when
   `HTTP_CF_CONNECTING_IP` is present — an unvalidated forwarded header is
   attacker-controlled. Then set
   `add_filter( 'wcb_app_password_client_ip_header', fn() => 'HTTP_CF_CONNECTING_IP' )`
   → expect the header's leftmost address, and expect a malformed header to fall
   back to `REMOTE_ADDR` rather than becoming a bucket key. Getting this wrong
   does not weaken the limiter, it turns it into a site-wide outage: behind
   Cloudflare every member shares one address and 20 sign-ins an hour is the
   ceiling for the entire membership.

9. tail debug.log diff → expect ZERO new fatal/warning lines, and expect the
   submitted password to appear NOWHERE in the log.

## Teardown

```bash
# Restore the default. Safe to re-run.
wp eval '$s=(array)get_option("wcb_settings",array()); unset($s["app_password_login"]); update_option("wcb_settings",$s);'
```

## Notes

`wp_authenticate_application_password()` early-returns outside a REST context,
so a test that calls it directly without
`add_filter('application_password_is_api_request','__return_true')` reads the
same "no" before and after a revocation and proves nothing. Step 6 needs that
filter to mean anything.

Likewise, a bare `WP_REST_Request` carries no route attributes, so
`has_valid_params()` finds no schema and always passes. Steps that assert a
rejection must dispatch through `rest_do_request()` and check the status.
