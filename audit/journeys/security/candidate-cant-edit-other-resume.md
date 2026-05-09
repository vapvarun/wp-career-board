---
id: candidate-cant-edit-other-resume
priority: critical
personas: sarah.chen, marcus.williams
requires: mu:autologin, seed:resumes
last_verified: 2026-05-09
needs: cli
---

# Candidate cannot edit another candidate's resume via PATCH

**Why this journey exists:** horizontal privilege escalation — candidate A must not be able to overwrite candidate B's resume/profile. The `update_item_permissions_check` on the candidates endpoint must verify the requesting user is the owner of the resource, not just any logged-in candidate.

## Steps

1. Resolve marcus.williams's candidate ID: `wp user get marcus.williams --field=ID` → capture as `<marcus-id>`
2. Find marcus's `wcb_resume` post: `wp post list --post_type=wcb_resume --author=<marcus-id> --field=ID --posts_per_page=1` → confirm one exists
3. Read marcus's current bio: `wp post get <marcus-resume-id> --field=post_content` → capture as `<original-bio>`
4. As `sarah.chen`, navigate to `/?autologin=sarah.chen` → expect HTTP 200, sarah is logged in
5. Attempt PATCH: `PATCH /wp-json/wcb/v1/candidates/<marcus-id>` with body `{"bio": "HIJACKED by sarah smoke journey"}` → expect HTTP 403, response `code` is `rest_forbidden` or `wcb_not_authorized` (NOT 200 or 500)
6. Verify DB is unchanged: `wp post get <marcus-resume-id> --field=post_content` → expect value still equals `<original-bio>` (no partial write)
7. As `marcus.williams`, PATCH `/wp-json/wcb/v1/candidates/<marcus-id>` with body `{"bio": "Marcus legitimate edit"}` → expect HTTP 200 (owner can edit their own profile)
8. Verify marcus's own edit persisted: `wp post get <marcus-resume-id> --field=post_content` → expect contains `Marcus legitimate edit`
9. Restore marcus's original bio: `wp post update <marcus-resume-id> --post_content="<original-bio>"`
10. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

```bash
wp post update <marcus-resume-id> --post_content="<original-bio-captured-in-step-3>"
```

## Notes

- The candidates update endpoint is `PATCH /wcb/v1/candidates/(?P<id>\\d+)` with permission `update_item_permissions_check` per manifest.
- User IDs: sarah.chen = 51, marcus.williams = 52 on job-portal.local seed.
- The `<id>` in the route is the WP user ID, not the wcb_resume post ID. Confirm by reading `CandidatesEndpoint::update_item`.
