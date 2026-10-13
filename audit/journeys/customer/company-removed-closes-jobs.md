---
id: company-removed-closes-jobs
priority: high
personas: employer.vercel
requires: mu:autologin, seed:jobs
last_verified: 2026-10-13
bug_ref: 10400597851
---

# Removing a company closes its live jobs, drafts its unreviewed ones, and blocks posting to it

**Why this journey exists:** deleting a company left its jobs live and orphaned, and the employer could no longer save edits to them. Owner rule: live jobs close (applicants told, history kept); jobs awaiting review go to draft so reopening can't skip moderation; restoring the company does not reopen anything.

## Steps

1. As `employer.vercel`, add a company "Fernbrook Studio" from the Companies tab and post two jobs under it: "Brand Designer" (published) and "Motion Designer" (left pending: post it on a board with moderation on).
2. Companies tab → Fernbrook "..." → Remove → confirm → expect `DELETE /wp-json/wcb/v1/employers/<id>` 200 and the company is in trash.
3. `wp post get <brand-designer-id> --field=post_status` → expect `wcb_closed`, `_wcb_closed_reason` set. `wp post get <motion-designer-id> --field=post_status` → expect `draft` (not `wcb_closed`).
4. My Jobs → expect "Brand Designer" shows the closed note naming the removed company. Its "..." → Reopen → expect a refusal message (`PATCH /wp-json/wcb/v1/jobs/<id>` status=publish → 409 `wcb_company_removed`), not a silent failure.
5. Edit "Brand Designer", pick Northpeak Cloud, save → expect 200 (the edit form stays saveable), then Reopen succeeds.
6. `POST /wp-json/wcb/v1/jobs` with `company_id` = the trashed company → expect a validation error, never a job listed under a page that is gone.
7. As admin, restore Fernbrook from the trash → expect its jobs stay closed/draft.
8. tail debug.log diff → expect ZERO new fatal/warning lines.

## Teardown

```bash
for t in "Brand Designer" "Motion Designer"; do wp post delete --force $(wp post list --post_type=wcb_job --title="$t" --format=ids --post_status=any); done
wp post delete --force $(wp post list --post_type=wcb_company --title="Fernbrook Studio" --format=ids --post_status=any)
```

## Notes

- Same rule runs when an employer deletes their account (`retire_owned_listings`).
- Covered at the REST layer by `tests/test-job-company-picker.php`.
