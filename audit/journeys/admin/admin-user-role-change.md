---
id: admin-user-role-change
priority: high
personas: varundubey
requires: mu:autologin
last_verified: 2026-05-09
needs: cli
---

# Admin promotes a subscriber to wcb_employer; capability is honoured immediately

**Why this journey exists:** role changes must take effect on the very next request without a cache flush or plugin deactivation cycle; delayed capability grants cause support tickets ("I assigned the employer role but the user can't post jobs"). Verifies both the role assignment and the immediate capability enforcement.

## Steps

1. Create a smoke subscriber user: `wp user create smoke.rolechange smoke.rolechange@example.test --role=subscriber --user_pass=Test1234! --porcelain` → capture ID as `<test-uid>`
2. As `varundubey` (admin), navigate to `/wp-admin/admin.php?page=wcb-employers&autologin=1` → expect HTTP 200, employer management page renders
3. Promote the subscriber: `wp user set-role <test-uid> wcb_employer` (or use the admin UI role-change action) → expect success output with no error
4. Verify the role: `wp user get <test-uid> --field=roles` → expect output contains `wcb_employer`
5. Verify the `wcb_post_jobs` capability is now available: `wp eval 'echo user_can(<test-uid>, "wcb_post_jobs") ? "yes" : "no";'` → expect `yes`
6. As `smoke.rolechange`, POST `/wp-json/wcb/v1/jobs` with a minimal job payload (title, description) via `?autologin=smoke.rolechange` → expect HTTP 201 (the newly promoted employer can post)
7. Verify subscriber-level pages are still accessible: navigate to `/candidate-dashboard/?autologin=smoke.rolechange` → expect either 200 (if the plugin allows multi-role access) or a clean redirect (NOT a 500 or PHP fatal)
8. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

```bash
wp post delete $(wp post list --post_type=wcb_job --author=<test-uid> --field=ID) --force 2>/dev/null
wp user delete <test-uid> --reassign=1 --yes
```

## Notes

- The `wcb_employer` role and its capabilities (`wcb_post_jobs`, etc.) are defined in `class-roles.php`. The ability check uses the `wcb/post-jobs` namespace/slug format per the architecture rules.
- If the plugin uses a custom role registration that only fires on plugin activation, the role may not exist on the test environment — check `wp role list | grep wcb_employer` first.
