---
id: apply-to-job
priority: critical
personas: sarah.chen
requires: mu:autologin, seed:jobs, seed:resumes
last_verified: 2026-05-09
needs: cli
bug_ref: 9818132111
---

# Candidate applies to a published job

**Why this journey exists:** the apply flow is the plugin's #1 customer-visible action. Every release must verify it round-trips end-to-end. Specifically guards Basecamp 9818132111 (resume-required default was silently false on fresh installs) and the broader "apply succeeds + persists + employer sees it" contract.

## Steps

1. As `sarah.chen`, navigate to `/jobs/?autologin=sarah.chen` → expect HTTP 200, candidate is logged in
2. Open any published `wcb_job` post (find a candidate via `wp post list --post_type=wcb_job --post_status=publish --field=ID --posts_per_page=1`) → expect 200, page renders title + description + Apply CTA
3. Click "Apply" CTA → expect modal/form with resume picker, cover-letter field
4. POST `/wp-json/wcb/v1/jobs/<id>/apply` with body `{"resume_id": <sarah's resume id>, "cover_letter": "Smoke journey test"}` → expect HTTP 200, response contains `application_id` (integer >0)
5. Verify the application persists: `wp post get <application_id> --field=post_status` → expect `publish` (or whatever the plugin's submitted state is — `submitted` per the v1.1.0 status allowlist)
6. Verify the application is linked: `wp post meta get <application_id> _wcb_job_id` → expect `<job-id>`. `wp post meta get <application_id> _wcb_candidate_id` → expect sarah's user ID. `wp post meta get <application_id> _wcb_status` → expect `submitted`
7. As `employer.figma` (or whichever employer owns the job), navigate to the employer dashboard → expect the new application to appear in the applicant list with correct candidate name + status
8. tail debug.log diff for this journey's window → expect ZERO new fatal / warning / `wp_register_ability` notice lines

## Teardown

```bash
# Delete the smoke application created during this run
wp post delete <application_id> --force
```

## Notes

- The `_wcb_status` allowlist is `submitted`, `reviewing`, `shortlisted`, `rejected` — older code used `applied`/`hired`; if step 6 returns one of those, this journey predates a fix and should be regenerated.
- Resume picker is REST-driven (`/wcb/v1/candidates/<id>/resumes`). If empty, seed a resume via the seeder script first.
- Re-applying to the same job: behavior is documented per plugin contract — either updates existing application or shows "already applied". Record which on first walk.
