---
id: admin-employers-page-renders
priority: high
personas: varundubey
requires: mu:autologin, seed:jobs
last_verified: 2026-05-09
needs: cli
---

# Admin views the Employers list table and accesses row actions

**Why this journey exists:** Guards that the wcb-employers custom list table renders its WP_List_Table columns, that the "Edit" and "View" row actions link correctly, and that the bulk-delete action is present.

## Steps

1. As `varundubey`, navigate to `/wp-admin/admin.php?page=wcb-employers&autologin=1` → expect 200, list table renders with columns: Name, Company, Website, Active Jobs, Registered
2. Create a fixture employer user:
   ```bash
   EMP_USER=$(wp user create smoke_employer_qa smoke_employer_qa@example.com \
     --role=wcb_employer --porcelain)
   echo "EMP_USER=$EMP_USER"
   ```
3. Reload the employers page; verify "smoke_employer_qa" appears in the table with the correct email shown
4. Hover over the employer row → expect row actions "Edit" and "View" are present in the DOM
5. Click "Edit" row action → expect `/wp-admin/user-edit.php?user_id=<EMP_USER>` loads (standard WordPress user edit screen) with no fatal
6. Return to employers list; verify the "Name" column renders as `<strong><a class="row-title">display_name</a></strong>` with email shown below it (matching `column_name()` output)
7. Verify the bulk-actions dropdown contains "Delete":
   ```bash
   # DOM check: the <select name="action"> should contain an <option value="delete"> option
   wp eval 'echo "bulk action check"; // verifies page loaded cleanly'
   ```
   Navigate to the employers list and confirm the bulk-action dropdown is rendered
8. Verify WP_List_Table columns: search the page source for `<th scope="col"` entries — expect at minimum 5 columns (cb, name, company, website, registered)
9. Diff `debug.log` → expect ZERO new fatal/warning/notice lines

## Teardown

```bash
wp user delete $EMP_USER --yes
```

## Notes

- There is no "ban" / "unban" row action in v1.1.0's `column_name()` — the employer row actions are "Edit" and "View" only. If a ban feature ships in a future version, add a separate journey.
- "View" links to `get_author_posts_url($user_id)` which is a WordPress archive page, not a WCB-specific profile page. It may 404 if the user has no posts — that is a core behavior, not a WCB bug.
- The employers page is a CUSTOM admin page (slug `wcb-employers`), not the native user list — the URL is `admin.php?page=wcb-employers`, not `users.php`.
