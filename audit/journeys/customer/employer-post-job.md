---
id: employer-post-job
priority: critical
personas: employer.figma
requires: mu:autologin
last_verified: 2026-05-09
needs: cli
bug_ref: 9871740742
---

# Employer posts a new job

**Why this journey exists:** post-a-job is the plugin's #1 employer-side action. Every release must verify it round-trips and the new job appears on the public board with correct company metadata (tagline, industry, size, HQ). Guards Basecamp 9871740742 (company tagline / industry / size / HQ were missing from the single-job render).

## Steps

1. As `employer.figma`, navigate to `/?autologin=employer.figma` → expect HTTP 200, employer is logged in
2. Navigate to the employer's "Post a Job" surface (front-end composer or admin `wp-admin/post-new.php?post_type=wcb_job`) → expect 200, form renders
3. POST `/wp-json/wcb/v1/jobs` with body `{"title": "Smoke Journey - PHP Engineer", "company_id": <figma's company id>, "description": "Smoke journey body", "deadline": "<+30 days>", "salary_min": 80000, "salary_max": 110000, "salary_currency": "USD", "remote": true, "type": "full-time"}` → expect HTTP 201, response contains job ID
4. Verify the job persists: `wp post get <job-id> --field=post_status` → expect `publish` (or `pending` if plugin is in moderation mode — record which)
5. Verify meta keys are in v1.1.0 shape: `wp post meta list <job-id>` → expect `_wcb_company_id`, `_wcb_deadline`, `_wcb_salary_min`, `_wcb_salary_max`, `_wcb_salary_currency`, `_wcb_remote`, `_wcb_company_name` all populated. NO `_wcb_job_company` (the v0.1.0 key) should be present
6. As anonymous, GET `/jobs/<job-slug>/` → expect HTTP 200, page renders title + description + company name + tagline + industry + size + HQ (all four company-meta fields must be visible per Basecamp 9871740742 contract)
7. As anonymous, GET `/wp-json/wcb/v1/jobs/<id>` → expect response body contains `tagline`, `industry`, `size_label`, `hq` keys with non-empty values. Response MUST NOT contain `apply_email` (per security journey `anonymous-job-no-email-leak`)
8. tail debug.log diff → expect ZERO new fatal / warning lines

## Teardown

```bash
wp post delete <job-id> --force
```

## Notes

- If Pro `credits` module is active, expect a `wcb_credit_ledger` row debited by 1 (or whatever the package amount is) — this is also covered by `system/credit-decrement.md`.
- Moderation mode: if `wcb_moderation_enabled` option is true, step 4 expects `pending`. Re-run as admin and approve to continue.
