---
id: job-meta-rejects-script-values
priority: critical
personas: employer.figma
requires: mu:autologin
last_verified: 2026-09-27
bug_ref: 10344034246
---

# Job fields reject script values; the job page never runs them

**Why this journey exists:** an employer sent `salary_currency=</script><script>…` on `PATCH /jobs/{id}`, it was stored raw, and the JobPosting JSON-LD printed it inside `<script type="application/ld+json">`, running for every visitor including admins (Basecamp 10344034246). Every job-meta key now has a registered sanitizer (all writers) and the create/update routes validate input (400 naming the field).

## Steps

1. As `employer.figma`, POST `/wp-json/wcb/v1/jobs` with `{"title":"Probe","salary_currency":"</script><script>document.title='XSS'</script>"}` → expect HTTP 400 `rest_invalid_param`, `params.salary_currency` present
2. Same for `salary_type:"weekly"`, `salary_min:"abc"`, `deadline:"next week"`, `board_id:999999` → each HTTP 400
3. Create a valid job, then PATCH it with the step-1 currency → expect HTTP 400 and the stored `_wcb_salary_currency` unchanged
4. `wp eval "update_post_meta(<id>,'_wcb_salary_currency','</script><script>x'); echo get_post_meta(<id>,'_wcb_salary_currency',true);"` → prints the site default currency (the sanitizer covers importers and the admin editor too)
5. Plant a raw value directly in `wp_postmeta` for a published job, load the job page as a guest → the JSON-LD shows `</script>`; the page title is unchanged
6. Logged out, GET `/wp-json/wp/v2/wcb_job/<id>?_fields=meta` → no `_wcb_apply_email`; GET `/wp-json/wcb/v1/jobs?saved_by=<candidate_id>` → 0 jobs
7. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

```bash
wp post delete <id> --force
```

## Notes

- Sanitizers: `WCB\Modules\Jobs\JobsMeta` (`sanitize_date`, `sanitize_amount`, `sanitize_currency`, `sanitize_board_id`, …). Route validation: `JobsEndpoint::get_write_params()`.
- JSON-LD is encoded with `JSON_HEX_TAG | JSON_HEX_AMP` as defence in depth for values stored before 1.8.0.
