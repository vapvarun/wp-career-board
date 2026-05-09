---
id: anonymous-can-apply-cleanly
priority: high
personas: anonymous
requires: seed:jobs
last_verified: 2026-05-09
needs: cli
bug_ref: F-1
---

# Anonymous guest can apply, but the public REST never leaks apply_email

**Why this journey exists:** guest applications are an intentional product feature (toggle `guest_apply` defaults to `true`, code at `class-applications-endpoint.php:702` returns `true` for `! is_user_logged_in()`). The contract is NOT "anon must be rejected" — the contract is "anon CAN apply cleanly AND the job's public REST response never exposes the recruiter's `apply_email` (would let scrapers harvest inboxes)".

> Renamed from `anonymous-cant-apply` after smoke walk on 2026-05-09 surfaced the design intent. The earlier title was wrong.

## Steps

1. As anonymous (no cookie), GET `/wp-json/wcb/v1/jobs?per_page=5` → expect HTTP 200, response is a JSON envelope `{jobs:[…], total:N, …}`
2. Iterate every job in `jobs[]` → assert NO entry contains key `apply_email` with a non-empty value (any plaintext recruiter address is a fail per F-1)
3. Pick a published job ID from step 1
4. As anonymous, GET `/wp-json/wcb/v1/jobs/<id>` → expect HTTP 200, response body must NOT contain `apply_email` (single-job endpoint inherits the same redaction rule)
5. As anonymous, POST `/wp-json/wcb/v1/jobs/<id>/apply` with valid body (`name`, `email`, optional `cover_letter`, optional `resume_attachment_id`) → expect HTTP 200/201, response is `{success: true, application_id: <int>}`
6. Verify the guest application persisted: `wp post get <application_id> --field=post_status` → expect `publish` (or `submitted` per the `_wcb_status` allowlist), `wp post meta get <application_id> _wcb_candidate_id` → expect `0` (guest, no user account)
7. As employer (the job's owner), GET `/wp-json/wcb/v1/employers/<company-id>/applications` → expect the new guest application appears with the candidate's submitted name + email (separately stored from `apply_email`, NOT exposed via public job REST)
8. tail debug.log diff → expect ZERO new fatal lines (guest path must not log warnings either)

## Teardown

```bash
# Delete the guest application created during this run
wp post delete <application_id> --force
```

## Notes

- If site has `guest_apply` toggled OFF in settings, the apply endpoint MUST return 401/403 with a clear "login required" message. Add a separate journey `anonymous-cant-apply-when-disabled.md` to cover that branch when the setting is added to admin UI.
- The `apply_email` field is part of the SCHEMA but should be filtered out of public-context responses. If it's defined as private in `get_item_schema()` but still leaks, that's the bug class F-1 protects against.
