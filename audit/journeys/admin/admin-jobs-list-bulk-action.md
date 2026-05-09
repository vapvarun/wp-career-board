---
id: admin-jobs-list-bulk-action
priority: high
personas: varundubey
requires: mu:autologin, seed:jobs
last_verified: 2026-05-09
needs: cli
---

# Admin bulk-closes N jobs; status updates and public listing reflects

**Why this journey exists:** bulk-close is the most common admin moderation shortcut; a bulk action that updates `post_status` in the DB but fails to clear the public-listing cache leaves closed jobs visible to candidates. Verifies both the DB write and the cache-busting propagation.

## Steps

1. As `varundubey` (admin), navigate to `/wp-admin/admin.php?page=edit.php?post_type=wcb_job&autologin=1` (or `/wp-admin/edit.php?post_type=wcb_job`) → expect HTTP 200, jobs list table renders
2. Create 2 smoke jobs in `publish` status for bulk-close:
   ```bash
   JOB1=$(wp post create --post_type=wcb_job --post_title="Smoke Bulk Close 1" --post_status=publish --post_author=50 --porcelain)
   JOB2=$(wp post create --post_type=wcb_job --post_title="Smoke Bulk Close 2" --post_status=publish --post_author=50 --porcelain)
   echo "jobs: $JOB1 $JOB2"
   ```
   Capture both IDs.
3. Verify both are public before the action: GET `/wp-json/wcb/v1/jobs` as anonymous → expect `$JOB1` and `$JOB2` both appear in the response
4. Apply bulk status change: PATCH `/wp-json/wcb/v1/jobs/$JOB1` and PATCH `/wp-json/wcb/v1/jobs/$JOB2` each with body `{"status": "closed"}` (or use WP-CLI `wp post update <id> --post_status=closed` if the REST route does not expose bulk) → expect HTTP 200 for each
5. Verify DB: `wp post get $JOB1 --field=post_status` → expect `closed`. `wp post get $JOB2 --field=post_status` → expect `closed`
6. As anonymous, GET `/wp-json/wcb/v1/jobs` → expect neither `$JOB1` nor `$JOB2` appear in the response (closed jobs are not public per the listing endpoint contract)
7. Navigate to `/wp-admin/edit.php?post_type=wcb_job?autologin=1` → filter by status `closed` → expect both smoke jobs appear in the closed list (they must not vanish from admin — D.wcb-closed-status guard)
8. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

```bash
wp post delete $JOB1 $JOB2 --force
```

## Notes

- `closed` must be a registered custom WP post status. Confirm with `wp post status list | grep closed` before step 4. If absent, that is the bug from Basecamp 9872024322.
- The REST bulk endpoint may not exist; use individual PATCHes or WP-CLI as fallback for the DB write — the key assertion is the cache bust in step 6.
