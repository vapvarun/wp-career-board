---
id: admin-jobs-list-bulk-action
priority: high
personas: varundubey
requires: mu:autologin, seed:jobs
last_verified: 2026-10-26
bug_ref: 10359867436
needs: cli
---

# Admin bulk-closes N jobs; status updates and public listing reflects

**Why this journey exists:** bulk-close is the most common admin moderation shortcut; a bulk action that updates `post_status` in the DB but fails to clear the public-listing cache leaves closed jobs visible to candidates. Verifies both the DB write and the cache-busting propagation.

## Steps

1. As `varundubey` (admin), navigate to `/wp-admin/admin.php?page=wcb-jobs&autologin=1` → expect HTTP 200, jobs list table renders
2. Create 2 smoke jobs in `publish` status for bulk-close:
   ```bash
   JOB1=$(wp post create --post_type=wcb_job --post_title="Support Engineer, Billing - Austin" --meta_input='{"_wcb_qa_smoke":"1"}' --post_status=publish --post_author=50 --porcelain)
   JOB2=$(wp post create --post_type=wcb_job --post_title="Support Engineer, Onboarding - Denver" --meta_input='{"_wcb_qa_smoke":"1"}' --post_status=publish --post_author=50 --porcelain)
   echo "jobs: $JOB1 $JOB2"
   ```
   Capture both IDs.
3. Verify both are public before the action: GET `/wp-json/wcb/v1/jobs` as anonymous → expect `$JOB1` and `$JOB2` both appear in the response
4. Apply bulk status change: PATCH `/wp-json/wcb/v1/jobs/$JOB1` and PATCH `/wp-json/wcb/v1/jobs/$JOB2` each with body `{"status": "closed"}` (or use WP-CLI `wp post update <id> --post_status=wcb_closed` as a fallback). The REST value `closed` is stored as the `wcb_closed` post status (`JobsEndpoint::update_item()`) → expect HTTP 200 for each
5. Verify DB: `wp post get $JOB1 --field=post_status` → expect `wcb_closed`. `wp post get $JOB2 --field=post_status` → expect `wcb_closed`
6. As anonymous, GET `/wp-json/wcb/v1/jobs` → expect neither `$JOB1` nor `$JOB2` appear in the response (closed jobs are not public per the listing endpoint contract)
7. Navigate to `/wp-admin/admin.php?page=wcb-jobs&post_status=wcb_closed` → expect both smoke jobs appear under the "Closed" tab (they must not vanish from admin — D.wcb-closed-status guard)
8. On `admin.php?page=wcb-jobs` with two `pending` jobs, approve one inline → the `.subsubsub` Pending/Published counts match `wp post list --post_type=wcb_job --post_status=pending --format=count` and `--post_status=publish --format=count` immediately, with no reload. Then run a bulk action on the other row and reload → the counts still match the database.
9. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

```bash
wp post delete $JOB1 $JOB2 --force
```

## Notes

- `wcb_closed` must be a registered custom WP post status (`JobsExpiry::register_closed_status()`). Confirm with `wp eval 'var_dump( isset( get_post_stati()["wcb_closed"] ) );'` before step 4 → `bool(true)`. If absent, that is the bug from Basecamp 9872024322.
- The REST bulk endpoint may not exist; use individual PATCHes or WP-CLI as fallback for the DB write — the key assertion is the cache bust in step 6.
