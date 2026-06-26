---
id: employer-edit-company
priority: high
personas: employer.figma
requires: mu:autologin, seed:jobs
last_verified: 2026-05-09
needs: cli
bug_ref: 9871740742
---

# Employer updates company tagline, industry, size, and HQ; changes reflect on company page and linked job pages

**Why this journey exists:** guards Basecamp 9871740742 — company tagline, industry, size label, and HQ location were not surfacing on single-job pages because those meta keys were absent from `prepare_item_for_response_array()`. Every release must confirm all four fields round-trip from the company edit form to the public job listing.

## Steps

1. As `employer.figma`, navigate to `/?autologin=employer.figma` → expect HTTP 200, employer is logged in (user ID 50)
2. Find employer.figma's company: `wp post list --post_type=wcb_company --post_author=50 --field=ID --posts_per_page=1` → capture as `<company-id>`
3. Capture the company's current slug: `wp post get <company-id> --field=post_name` → capture as `<company-slug>`
4. PATCH `/wp-json/wcb/v1/employers/<employer-id>` with body `{"tagline": "Smoke Tagline 2026", "industry": "Technology", "size": "11-50", "hq": "San Francisco, CA"}` → expect HTTP 200. The employers endpoint owns company updates: it maps `industry`→`_wcb_industry`, `size`→`_wcb_company_size`, `hq`→`_wcb_hq_location` (+ tagline) onto the employer's company. There is no `/companies/<id>` update route.
5. Verify meta keys in DB: `wp post meta list <company-id>` → expect `_wcb_tagline` = `Smoke Tagline 2026`, `_wcb_industry` = `Technology`, `_wcb_company_size` = `11-50` (or the size slug), `_wcb_hq_location` = `San Francisco, CA`
6. There is no public single-company REST route (only `GET /wcb/v1/companies` list, `/companies/{id}/bookmark`, `/companies/{id}/trust`). Verify the company meta reaches the public API via a linked job: find a published job for this company (`wp post list --post_type=wcb_job --meta_key=_wcb_company_id --meta_value=<company-id> --post_status=publish --field=ID --posts_per_page=1`), then GET `/wp-json/wcb/v1/jobs/<job-id>` as anonymous → expect HTTP 200, response body contains non-empty `company_tagline`, `company_industry`, `company_size_label`, and `company_hq` keys (the D.company-tagline-missing guard per Basecamp 9871740742; the jobs endpoint prefixes company fields with `company_`)
7. Navigate to `/companies/<company-slug>/` → expect HTTP 200, page renders tagline "Smoke Tagline 2026" visibly
8. Find a job linked to this company: `wp post meta list $(wp post list --post_type=wcb_job --fields=ID --posts_per_page=1) | grep _wcb_company_id` → navigate to that job's public URL → expect HTTP 200, the company tagline "Smoke Tagline 2026" is visible in the company section on the job page
9. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

```bash
# Restore original company meta (use the values captured before step 4)
wp post meta update <company-id> _wcb_tagline ""
wp post meta update <company-id> _wcb_industry ""
```

## Notes

- The four meta keys to verify are exactly: `_wcb_tagline`, `_wcb_industry`, `_wcb_company_size`, `_wcb_hq_location` — per the D.company-tagline-missing guard row in the runbook.
- Company updates flow through `EmployersEndpoint` (`PUT|PATCH /wcb/v1/employers/<id>`), not a company-CPT route — verified 2026-06-26 against the registered routes (no public `/companies/<id>` GET/PUT exists; only `/companies` list, `/companies/{id}/bookmark`, `/companies/{id}/trust`).
- `size_label` in the REST response is the human-readable form of the size slug (e.g. "11-50 employees").
