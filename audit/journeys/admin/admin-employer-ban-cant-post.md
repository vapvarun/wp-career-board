---
id: admin-employer-ban-cant-post
priority: high
personas: varundubey, employer.figma
requires: mu:autologin
last_verified: 2026-05-09
needs: cli
---

# Admin bans an employer; that employer's POST /wcb/v1/jobs returns 403

**Why this journey exists:** employer banning must immediately gate all job-posting capability; a ban that sets a flag in the DB but leaves the REST permission callback unaware means banned employers keep posting. Verifies the ban propagates to the REST layer on the very next request.

## Steps

1. As `varundubey` (admin), navigate to `/wp-admin/admin.php?page=wcb-employers&autologin=1` → expect HTTP 200, employer list renders with employer.figma (user 50) visible
2. Record employer.figma's current capability state: `wp eval 'echo user_can(50, "wcb_post_jobs") ? "yes" : "no";'` → expect `yes`
3. Apply ban: `wp user update 50 --user_status=1` OR use the plugin's ban mechanism (look for a `_wcb_employer_banned` meta key or equivalent):
   ```bash
   wp user meta update 50 _wcb_employer_banned 1
   ```
   → expect success output
4. Immediately verify capability is revoked: `wp eval 'echo user_can(50, "wcb_post_jobs") ? "yes" : "no";'` → expect `no` (the permission check must inspect the ban flag)
5. As `employer.figma`, POST `/wp-json/wcb/v1/jobs` with a valid job payload `{"title": "Should Not Post", "description": "Banned employer test"}` via `?autologin=employer.figma` → expect HTTP 403, response `code` is NOT `rest_no_route` (the route must exist — the permission check must reject it)
6. Verify no job was created: `wp post list --post_type=wcb_job --post_author=50 --post_status=any --search="Should Not Post" --field=ID` → expect zero rows
7. Unban the employer: `wp user meta delete 50 _wcb_employer_banned` (or `wp user update 50 --user_status=0`) → verify capability restored: `wp eval 'echo user_can(50, "wcb_post_jobs") ? "yes" : "no";'` → expect `yes`
8. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

```bash
# Ensure employer.figma is unbanned (run unconditionally)
wp user meta delete 50 _wcb_employer_banned
wp user update 50 --user_status=0
```

## Notes

- The exact ban mechanism depends on the plugin implementation — check `EmployersModule` or `class-roles.php` for how the ban flag is stored and read. The meta key `_wcb_employer_banned` is the most likely candidate; adapt step 3 to match what the code reads.
- The permission check that inspects the ban must be part of `create_item_permissions_check` on the `JobsEndpoint`, not just a UI-level guard.
