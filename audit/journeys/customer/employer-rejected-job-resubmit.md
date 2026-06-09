---
id: employer-rejected-job-resubmit
priority: high
personas: morgan_moderator, employer.figma
requires: mu:autologin
last_verified: 2026-06-09
bug_ref: 9976849052
---

# Rejected job shows as "Rejected" and resubmit returns to moderation

**Why this journey exists:** a rejected job must read as "Rejected" in My Jobs (not "Draft"), and re-publishing it must go back to admin approval (`pending`), never straight live. Guards Basecamp 9976849052 (reject set `draft`, so rejected jobs were indistinguishable from drafts AND republishing a draft skipped moderation — a bypass).

## Steps

1. As `morgan_moderator`, reject a published job: POST `/wp-json/wcb/v1/jobs/<id>/reject` with `{"reason":"Missing salary range"}` → expect HTTP 200; the job becomes `draft` and `_wcb_rejection_reason` is set
2. As `employer.figma` (the job's owner), GET `/wp-json/wcb/v1/employers/me/jobs` → the rejected job reports `status: "rejected"`, `statusLabel: "Rejected"`, `rejected: true` (NOT `draft`)
3. On the dashboard My Jobs tab → the job renders under a **Rejected** filter pill with a "Rejected" badge and a **Resubmit** action (it does NOT appear under the Draft pill)
4. Click Resubmit (POST `/wp-json/wcb/v1/jobs/<id>` `{"status":"publish"}`) → expect HTTP 200; server overrides to `pending` and clears `_wcb_rejection_reason`
5. Verify `wp post get <id> --field=post_status` → expect `pending` (NOT `publish` — moderation was not bypassed)
6. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

```bash
wp post delete <id> --force
```

## Notes

- `EmployersEndpoint::is_rejected_job()` (draft + `_wcb_rejection_reason`) is the single source the My-Jobs builders use for the rejected flag/label.
- The republish→pending override lives in `JobsEndpoint::update_item()`; the dashboard `reopenJob` optimistic update mirrors it (rejected → "Pending", else → "Published").
