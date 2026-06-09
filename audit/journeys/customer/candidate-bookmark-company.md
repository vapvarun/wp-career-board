---
id: candidate-bookmark-company
priority: high
personas: sarah.chen
requires: mu:autologin, seed:jobs
last_verified: 2026-06-09
bug_ref: 9977012701
---

# Saving a company from its profile page persists

**Why this journey exists:** the Save button on a company profile must bookmark the company and survive a reload. Guards Basecamp 9977012701 (the company-profile block set `apiBase` twice on one Interactivity store; the jobs root clobbered the companies root, so Save POSTed to `/jobs/{id}/bookmark` and the id landed under `_wcb_bookmark` instead of `_wcb_company_bookmark` — never persisted).

## Steps

1. As `sarah.chen`, navigate to a company profile page `/companies/<slug>/` → expect HTTP 200, the Save (bookmark) control reads "Save"
2. Click Save → the network request is `POST /wp-json/wcb/v1/companies/<company-id>/bookmark` (NOT `/jobs/...`) → expect HTTP 200
3. Verify persistence: `wp user meta get <user-id> _wcb_company_bookmark` contains the company id (and `_wcb_bookmark` does NOT)
4. Reload `/companies/<slug>/` → the Save control now reads "Saved"
5. "Open Positions" still loads: the company's jobs list / Load More fetches from `/wcb/v1/jobs` (the second store base, now keyed `jobsApiBase`) → expect 200, jobs render
6. The saved company appears under the candidate dashboard → Saved Companies
7. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

```bash
wp user meta update <user-id> _wcb_company_bookmark ''
```

## Notes

- Root cause was a duplicate `apiBase` across two `wp_interactivity_state()` calls on the `wcb-company-profile` store; the jobs base now uses the distinct key `jobsApiBase` so the bookmark keeps the companies root.
