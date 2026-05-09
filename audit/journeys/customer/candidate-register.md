---
id: candidate-register
priority: high
personas: anonymous
requires: mu:autologin
last_verified: 2026-05-09
needs: cli
---

# Anonymous visitor registers as a candidate

**Why this journey exists:** self-registration is the first touchpoint for every new candidate; a broken registration means zero top-of-funnel. Verifies that the POST `/wcb/v1/candidates/register` endpoint creates the WordPress user + linked `wcb_resume` CPT record in one transaction, then lands the new user on the candidate dashboard.

## Steps

1. As anonymous, GET `/wp-json/wcb/v1/candidates/register` → expect HTTP 405 (only POST is allowed; GET must not 200)
2. As anonymous, POST `/wp-json/wcb/v1/candidates/register` with body `{"username": "smoke.candidate.reg", "email": "smoke.candidate.reg@example.test", "password": "Test1234!"}` → expect HTTP 201, response contains `user_id` (integer >0)
3. Verify the WP user exists: `wp user get smoke.candidate.reg --field=user_email` → expect `smoke.candidate.reg@example.test`
4. Verify the user has the `wcb_candidate` role: `wp user get smoke.candidate.reg --field=roles` → expect output contains `wcb_candidate`
5. Verify a `wcb_resume` CPT record was created for the new user: `wp post list --post_type=wcb_resume --author=$(wp user get smoke.candidate.reg --field=ID) --field=ID` → expect at least one row returned
6. As `smoke.candidate.reg` (autologin), GET `/candidate-dashboard/?autologin=smoke.candidate.reg` → expect HTTP 200, candidate dashboard renders (NOT a redirect to login, NOT a 403)
7. Attempt duplicate registration: POST `/wp-json/wcb/v1/candidates/register` again with the same email → expect HTTP 400 or 409 with a non-empty error `code` (must not be HTTP 200 or 500)
8. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

```bash
wp user delete $(wp user get smoke.candidate.reg --field=ID) --reassign=1 --yes 2>/dev/null
wp post delete $(wp post list --post_type=wcb_resume --author=$(wp user get smoke.candidate.reg --field=ID 2>/dev/null) --field=ID 2>/dev/null) --force 2>/dev/null
```

## Notes

- If the plugin requires email verification before the role is granted, step 4 may return `subscriber` — record which and confirm that a confirmed-but-unverified state does not grant `wcb_apply_jobs` capability.
- The `wcb_resume` CPT is labelled "Resumes" in admin but serves as the candidate-profile CPT (confirmed from manifest, CPT slug `wcb_resume`).
- The autologin mu-plugin is the autologin trigger; append `?autologin=<login>` to any URL.
