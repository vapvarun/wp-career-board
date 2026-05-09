---
id: moderation-approve-flagged-job
priority: high
personas: varundubey
requires: mu:autologin, seed:jobs
last_verified: 2026-05-09
needs: cli
---

# Admin approves a flagged / pending job

**Why this journey exists:** moderation actions must update the post status AND propagate to every public listing (search, archive, single-page). Tests the round-trip from "pending" to "publish" and verifies the moderation queue is the canonical surface for the action.

## Steps

1. As `varundubey` (admin), navigate to `/wp-admin/admin.php?page=wcb-jobs&autologin=1` → expect 200, plugin admin renders without PHP Notice/Warning
2. Set up the fixture: create a `wcb_job` with `post_status=pending`:
   ```bash
   wp post create --post_type=wcb_job --post_title='Smoke Pending Job' --post_status=pending --post_author=49 --porcelain
   ```
   Capture the returned ID as `<job-id>`
3. As anonymous, GET `/wp-json/wcb/v1/jobs` → expect response array does NOT contain `<job-id>` (pending jobs are not public)
4. As admin, navigate to the moderation queue (admin slug `wcb-jobs` filtered to `pending` OR a dedicated moderation page) → expect to see `Smoke Pending Job` in the list
5. Trigger approval: PATCH `/wp-json/wcb/v1/jobs/<job-id>` with body `{"status": "publish"}` (or whichever endpoint the moderation queue uses — read code) → expect HTTP 200 (or 204), response success
6. Verify status changed: `wp post get <job-id> --field=post_status` → expect `publish`
7. As anonymous, GET `/wp-json/wcb/v1/jobs` → expect `<job-id>` IS now in the response array
8. Reverse path — as admin, set the same job back to `closed` (regression guard for D.wcb-closed-status / Basecamp 9872024322): PATCH `/wp-json/wcb/v1/jobs/<job-id>` with body `{"status": "closed"}` → expect 200
9. As employer.figma (the author), navigate to employer dashboard → expect the job to APPEAR in the dashboard with status label "Closed" (NOT vanish — that's the bug being guarded)
10. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

```bash
wp post delete <job-id> --force
```

## Notes

- If the plugin's REST patch endpoint doesn't accept arbitrary `status` values, use the dedicated moderation REST route (likely `/wp-json/wcb/v1/jobs/<id>/approve` or `/moderate`).
- `closed` must be registered as a custom post status — verify via `wp post status list | grep closed`. If absent, that's the underlying bug from Basecamp 9872024322.
