---
id: walkthrough-admin-taxonomies-and-roles
priority: high
personas: varundubey
requires: mu:autologin
last_verified: 2026-07-08
covers: admin/admin-categories-and-types-crud, admin/admin-user-role-change
---

# Walkthrough: Taxonomies & Roles — CRUD every job taxonomy, then promote a subscriber to employer and confirm the capability lands immediately

**Why this journey exists:** The five job taxonomies (categories, types, locations, experience, tags) must be registered with the right hierarchy and reachable from the Career Board menu's taxonomy submenu links. Separately, a role change must grant capability on the very next request — a delayed grant causes the classic "I assigned the employer role but they still can't post" support ticket. This is the human-runnable form of the two taxonomy/role sentinels.

## Steps

1. As `varundubey`, verify all five taxonomies are registered with the correct hierarchy: `wp taxonomy list --fields=name,hierarchical --path=/Users/varundubey/Local Sites/jobboard/app/public | grep -E 'wcb_category|wcb_job_type|wcb_location|wcb_experience|wcb_tag'` → expect 5 rows; `wcb_category` and `wcb_location` show `hierarchical: 1`; `wcb_job_type`, `wcb_experience`, `wcb_tag` show `hierarchical: 0` (registered in `modules/jobs/class-jobs-module.php`).
2. Confirm the taxonomy submenu links exist under the Career Board menu: on any admin page, expect the "Career Board" menu to list **Job Categories, Job Types, Job Locations, Experience Levels, Job Tags**, each pointing at `edit-tags.php?taxonomy=<tax>&post_type=wcb_job` (appended to the `$submenu` global, `admin/class-admin.php:137-152`).
3. **Create a term in each taxonomy** (via the term screens or WP-CLI): `wp term create wcb_category 'Smoke Category' --slug=smoke-category --porcelain`, and likewise `wcb_job_type 'Smoke Type'`, `wcb_tag 'Smoke Tag'`, `wcb_location 'Smoke Location'`, `wcb_experience 'Smoke Experience'` → capture each term ID.
4. Navigate to `http://jobboard.local/wp-admin/edit-tags.php?taxonomy=wcb_category&post_type=wcb_job&autologin=varundubey` → expect HTTP 200, "Smoke Category" in the table, and a **Parent** dropdown present (hierarchical). The Career Board top-level menu stays highlighted (`highlight_parent_for_taxonomies()`, `admin/class-admin.php:166-180`).
5. Navigate to `http://jobboard.local/wp-admin/edit-tags.php?taxonomy=wcb_job_type&post_type=wcb_job&autologin=varundubey` → expect HTTP 200, "Smoke Type" present, and NO Parent dropdown (flat taxonomy).
6. **Hierarchy CRUD.** Create a child category: `wp term create wcb_category 'Smoke Child' --parent=<cat-id> --porcelain`; reload the category term list → expect "Smoke Child" indented beneath "Smoke Category".
7. **Edit.** On the category term edit screen (`term.php?taxonomy=wcb_category&tag_ID=<cat-id>&post_type=wcb_job`) change the description and save → `wp term get wcb_category <cat-id> --field=description` reflects the new text.
8. **Delete.** Delete "Smoke Type" via its row action → `wp term list wcb_job_type --slug=smoke-type --format=count` → `0`.
9. **Role change.** Create a subscriber: `UID=$(wp user create smoke.role smoke.role@example.test --role=subscriber --user_pass=Test1234! --porcelain ...)`. As `varundubey`, navigate to `http://jobboard.local/wp-admin/user-edit.php?user_id=<UID>&autologin=varundubey` → expect HTTP 200. Change Role to "Employer" (`wcb_employer`) and Update (or `wp user set-role <UID> wcb_employer`).
10. Verify the role and capability landed immediately: `wp user get <UID> --field=roles` → contains `wcb_employer`; `wp eval 'echo user_can(<UID>, "wcb_post_jobs") ? "yes" : "no";'` → `yes` (the `wcb_employer` role + `wcb_post_jobs` cap are defined in `core/class-roles.php`).
11. Confirm the freshly promoted employer can post on the next request: as `smoke.role`, `POST http://jobboard.local/wp-json/wcb/v1/jobs` with a minimal `{title,description}` via `?autologin=smoke.role` → expect HTTP 201 (no cache flush / re-login needed).
12. tail `wp-content/debug.log` diff over the whole run → expect ZERO new fatal/warning lines.

## Teardown

```bash
SITE='/Users/varundubey/Local Sites/jobboard/app/public'
wp term delete wcb_category   "$TID_CAT" "$TID_CHILD" --path="$SITE" 2>/dev/null || true
wp term delete wcb_job_type   "$TID_TYPE"             --path="$SITE" 2>/dev/null || true
wp term delete wcb_tag        "$TID_TAG"              --path="$SITE" 2>/dev/null || true
wp term delete wcb_location   "$TID_LOC"              --path="$SITE" 2>/dev/null || true
wp term delete wcb_experience "$TID_EXP"             --path="$SITE" 2>/dev/null || true
# Delete the promoted user's jobs, then the user.
wp post delete $(wp post list --post_type=wcb_job --author="$UID" --field=ID --path="$SITE") --force --path="$SITE" 2>/dev/null || true
wp user delete "$UID" --reassign=1 --yes --path="$SITE" 2>/dev/null || true
```

## Notes
- Rewrite slugs: `job-category`, `job-type`, `job-tag`, `job-location`, `job-experience`. The `&post_type=wcb_job` query arg is required so the term screen loads in the correct CPT context and the Career Board submenu stays highlighted (`highlight_submenu_for_taxonomies()`, `admin/class-admin.php:192-206`).
- Hierarchical taxonomies (`wcb_category`, `wcb_location`) get the Parent dropdown; the three flat ones do not — that difference is the primary registration assertion.
- The role change is enforced through the Abilities API (`wcb/post-jobs` → cap `wcb_post_jobs`); if the role does not exist on a fresh test box, confirm with `wp role list | grep wcb_employer` (roles register on activation).
- No 1.5.1-new surface in this walkthrough.
