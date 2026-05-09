---
id: subscriber-cant-access-employer-dashboard
priority: critical
personas: anonymous
requires: mu:autologin
last_verified: 2026-05-09
needs: cli
---

# Subscriber cannot access the employer dashboard (redirect or 403, not 200)

**Why this journey exists:** capability boundary between subscriber and employer roles must be enforced at the page level; a subscriber who navigates directly to `/employer-dashboard/` must be redirected to login or shown a 403, never a 200 that silently renders employer-only data. A broken gate here exposes pipeline data to any logged-in user.

## Steps

1. Create a smoke subscriber user if none exists: `wp user create smoke.subscriber smoke.sub@example.test --role=subscriber --user_pass=Test1234! --porcelain` → capture as `<sub-uid>`
2. Verify the user has NO `wcb_post_jobs` or `wcb_employer`-level capability: `wp eval 'echo user_can(<sub-uid>, "wcb_post_jobs") ? "yes" : "no";'` → expect `no`
3. As `smoke.subscriber`, navigate to `/employer-dashboard/?autologin=smoke.subscriber` → expect HTTP status is 302 (redirect to login or access-denied page) OR 403 — NOT 200
4. Verify the response body does NOT contain employer-specific UI elements: if status is 200 (the bug state), fail the journey; if 302 or 403, confirm the redirect target is a login page or a "You do not have permission" message
5. As `smoke.subscriber`, attempt to POST `/wp-json/wcb/v1/jobs` with a valid payload → expect HTTP 403, code `rest_forbidden` or `wcb_insufficient_permissions`
6. As `smoke.subscriber`, attempt to GET `/wp-json/wcb/v1/employers/50/applications` → expect HTTP 403
7. As `smoke.subscriber`, navigate to `/candidate-dashboard/?autologin=smoke.subscriber` → expect the candidate dashboard also requires the `wcb_candidate` role — expect 302 or 403 (subscriber is neither employer nor candidate)
8. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

```bash
wp user delete <sub-uid> --reassign=1 --yes
```

## Notes

- The employer dashboard is rendered by the `wp-career-board/employer-dashboard` block. The block's render.php or the page template should check `wp_is_ability_granted('wcb/post-jobs')` or equivalent before rendering employer-only content.
- If the page returns 200 but renders an empty shell (no data, a "please log in as employer" message), that is borderline acceptable — record the exact behaviour and flag for UX review, but do not fail the journey solely on status 200 if the content is appropriately gated.
