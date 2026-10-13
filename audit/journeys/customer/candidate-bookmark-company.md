---
id: candidate-bookmark-company
priority: high
personas: sarah.chen
requires: mu:autologin, seed:jobs
last_verified: 2026-10-13
bug_ref: 9977012701, 10389295295, 10394629393, 10394637826
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
7. **Save Company gate and failure (1.8.1, Basecamp 10389295295).** As `employer.figma` open `/companies/<slug>/` → no `.wcb-cp-hero-save` button in the DOM (same `wcb/bookmark-jobs` gate as the Save Job button); as `sarah.chen` it renders. With the bookmark request aborted (Playwright route `**/wcb/v1/companies/*/bookmark`) click Save as `sarah.chen` → the label reads "Could not save" for about 3 seconds and then returns to "Save" (or "Saved" if it was saved before); `_wcb_company_bookmark` is unchanged.
8. **Saved Companies shows logo or initial and industry (1.8.1, Basecamp 10394629393).** Give company A a logo and leave company B without one; save both. Open `$WCB_SITE/candidate-dashboard/#saved-companies` as `sarah.chen` → each `.wcb-cd-bookmark-row` starts with `.wcb-avatar`: A shows its logo `<img>` (class `wcb-avatar--logo` on the avatar, `src` = the company logo URL), B shows its first letter in uppercase (the `<img>` is hidden). The meta line under the title now shows the company's industry label (for example "Technology") followed by the HQ; before the fix the industry was blank because the template read `industry_label` while REST sends `industry`. `GET /wp-json/wcb/v1/candidates/<sarah-id>/saved-companies` returns `logo`, `initial` and `industry` per item.
9. **Saved Resumes avatar (1.8.1, Basecamp 10394637826; Pro resumes).** Save a candidate resume, open `#saved-resumes` (candidate dashboard) → each row starts with a round avatar `.wcb-avatar--round img` whose `src` is the candidate's avatar URL; `GET /wp-json/wcb/v1/candidates/<id>/saved-resumes` items carry `avatar`. The employer dashboard (`/employer-dashboard/#saved-companies`, `#saved-resumes`) uses the same row markup and the same routes.
10. Negative: a company with an empty title or no logo never renders a broken image icon (the `<img>` is `hidden` when `logo` is empty).
11. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

```bash
wp user meta update <user-id> _wcb_company_bookmark ''
```

## Notes

- Root cause was a duplicate `apiBase` across two `wp_interactivity_state()` calls on the `wcb-company-profile` store; the jobs base now uses the distinct key `jobsApiBase` so the bookmark keeps the companies root.
