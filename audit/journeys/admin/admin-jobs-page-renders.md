---
id: admin-jobs-page-renders
priority: high
personas: varundubey
requires: mu:autologin, seed:jobs
last_verified: 2026-05-09
needs: cli
---

# Admin views and interacts with the Jobs list table

**Why this journey exists:** Guards that the wcb-jobs list table renders all required columns, that per-status filters return correct subsets, and that row actions (edit/view/trash) are functional without PHP Notice or Warning output.

## Steps

1. As `varundubey` (admin), navigate to `/wp-admin/admin.php?page=wp-career-board&autologin=1` → expect 200, top-level Career Board dashboard renders with no PHP error
2. Create a fixture job via WP-CLI:
   ```bash
   JOB_ID=$(wp post create --post_type=wcb_job --post_title='Smoke Jobs Page Job' \
     --post_status=publish --post_author=1 --porcelain)
   echo "JOB_ID=$JOB_ID"
   ```
   Capture `$JOB_ID` for teardown
3. Navigate to `/wp-admin/edit.php?post_type=wcb_job&autologin=1` → expect 200, list table renders with columns: Title, Status, Company, Date; "Smoke Jobs Page Job" appears in the table
4. Click the "Pending" filter tab (add `&post_status=pending` to URL) → expect only pending-status jobs appear; "Smoke Jobs Page Job" (published) does NOT appear in this filtered view
5. Return to all-jobs view; hover over "Smoke Jobs Page Job" row → expect row actions "Edit | Quick Edit | Trash | View" are present in the DOM
6. Click "Edit" row action → expect `/wp-admin/post.php?action=edit&post=<JOB_ID>` loads with no fatal; job title is pre-populated in the title field
7. Navigate back; click "View" → expect the job's single-page URL loads (status=publish so it is publicly accessible); browser returns 200
8. Via WP-CLI verify the list-table query is scoped to `wcb_job` post type only:
   ```bash
   wp post list --post_type=wcb_job --post_status=publish --format=count
   ```
   → count is ≥ 1
9. Diff `wp-content/debug.log` captured before and after the test → expect ZERO new lines containing `PHP Fatal error`, `PHP Warning`, or `PHP Notice`

## Teardown

```bash
wp post delete $JOB_ID --force
```

## Notes

- The list table is the native WordPress CPT list view at `edit.php?post_type=wcb_job`, NOT a custom admin page at `wcb-jobs`. The manifest records `wcb-jobs` as a slug pointing to `edit.php?post_type=wcb_job` — the actual URL is the CPT edit screen.
- If seed data has no published jobs the step-3 assertion still passes because the fixture job was just created.
- "Quick Edit" row action is provided by WordPress core; verify it appears but do not exercise inline save (that is a core test, not a WCB contract).
