---
id: admin-companies-page-renders
priority: high
personas: varundubey
requires: mu:autologin, seed:jobs
last_verified: 2026-05-09
needs: cli
bug_ref: D.company-tagline-missing
---

# Admin views the Companies list table with v1.1.0 column set

**Why this journey exists:** Guards the 8-column company list table that shipped in v1.1.0 — specifically that all columns from the `get_columns()` definition are visible: Company Name, Employer, Website, Active Jobs, Trust Level, Status, Date. (The bug reference `D.company-tagline-missing` concerns a prior version; this journey locks in the current column set.)

## Steps

1. As `varundubey`, navigate to `/wp-admin/edit.php?post_type=wcb_company&autologin=1` → expect 200, list table renders with no PHP error
2. Create a fixture company:
   ```bash
   COMP_ID=$(wp post create --post_type=wcb_company --post_title='Smoke Company Ltd' \
     --post_status=publish --post_author=1 --porcelain)
   echo "COMP_ID=$COMP_ID"
   ```
3. Reload the list view; verify "Smoke Company Ltd" appears in the table
4. Assert that ALL of the following column headings are present in the rendered `<thead>` (inspect via browser or DOM snapshot):
   - Company Name
   - Employer
   - Website
   - Active Jobs
   - Trust Level
   - Status
   - Date
5. Verify status tabs render: the tab bar shows at minimum "All", "Published", "Draft"
6. Filter to `draft`: navigate to `edit.php?post_type=wcb_company&post_status=draft` → expect "Smoke Company Ltd" does NOT appear (it was published)
7. Filter to `publish` → expect "Smoke Company Ltd" appears
8. Click "Edit" row action on "Smoke Company Ltd" → expect the company edit screen loads with post title pre-populated
9. Diff `debug.log` → expect ZERO new fatal/warning/notice lines

## Teardown

```bash
wp post delete $COMP_ID --force
```

## Notes

- The columns `tagline`, `industry`, `size`, `hq` from the original bug report are NOT columns in the list table in v1.1.0 — they live as post meta visible on the edit screen. The `D.company-tagline-missing` Basecamp card was about a front-end display gap, not the admin list. If the future list table adds those columns, update this journey.
- Trust Level column renders a badge (e.g. "Verified" / "Standard"). If no trust meta is set, the badge may be empty — that is acceptable.
- Companies do not have a `pending` moderation status by design (`class-admin-companies.php:170`); do not assert a Pending tab.
