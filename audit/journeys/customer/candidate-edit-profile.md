---
id: candidate-edit-profile
priority: high
personas: sarah.chen
requires: mu:autologin, seed:resumes
last_verified: 2026-05-09
needs: cli
---

# Candidate edits name, bio, skills, and avatar; changes persist and appear on public profile

**Why this journey exists:** profile-edit is the second most common candidate action after applying; a save that returns 200 but fails to persist is a silent data-loss bug. Verifies the PATCH round-trip, DB persistence, and public-profile propagation.

## Steps

1. As `sarah.chen`, navigate to `/?autologin=sarah.chen` → expect HTTP 200, candidate is logged in
2. PATCH `/wp-json/wcb/v1/candidates/<sarah-id>` with body `{"display_name": "Sarah Chen (Smoke)", "bio": "Smoke journey bio text", "skills": ["PHP", "WordPress", "REST API"]}` where `<sarah-id>` is sarah.chen's user ID (`wp user get sarah.chen --field=ID`) → expect HTTP 200, response body echoes the updated fields
3. Verify display_name persisted: `wp user get sarah.chen --field=display_name` → expect `Sarah Chen (Smoke)`
4. Verify bio persisted: `wp post get $(wp post list --post_type=wcb_resume --author=$(wp user get sarah.chen --field=ID) --field=ID --posts_per_page=1) --field=post_content` → expect contains `Smoke journey bio text`
5. Reload the candidate public profile (or GET `/wp-json/wcb/v1/candidates/<sarah-id>`) → expect `display_name` is `Sarah Chen (Smoke)` and `skills` array contains `PHP`
6. Upload a new avatar: POST `/wp-json/wcb/v1/employers/<sarah-id>/logo` (or the candidate-specific avatar endpoint — read `CandidatesEndpoint`) with a valid PNG file multipart body → expect HTTP 200, response contains an `avatar_url` with a non-empty URL
7. Verify the avatar URL is served: GET the `avatar_url` from step 6 → expect HTTP 200 and `Content-Type: image/*`
8. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

```bash
# Restore original display name
wp user update $(wp user get sarah.chen --field=ID) --display_name="Sarah Chen"
```

## Notes

- sarah.chen is user ID 51 on the job-portal.local seed. Confirm with `wp user get sarah.chen --field=ID`.
- Skills may be stored as serialized meta on the `wcb_resume` post — check `_wcb_skills` meta key if the REST response omits them.
- Avatar upload may delegate to WP core's media library; the `attachment_id` returned should be a valid attachment post.
