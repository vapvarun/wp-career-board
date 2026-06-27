---
id: admin-companies-edit-meta
priority: high
personas: varundubey
requires: mu:autologin, seed:jobs
last_verified: 2026-05-09
needs: cli
bug_ref: 9871740742
---

# Admin edits company tagline, industry, size, and HQ; changes persist and render publicly

**Why this journey exists:** the admin path for editing company meta is distinct from the employer self-service path; this journey verifies the admin can update the four company meta keys (guards D.company-tagline-missing / Basecamp 9871740742) through the WP admin UI, and confirms they render on the public-facing company and job pages.

## Steps

1. As `varundubey` (admin), navigate to `/wp-admin/edit.php?post_type=wcb_company&autologin=1` → expect HTTP 200, companies list table renders with at least one row
2. Select employer.figma's company ID: `wp post list --post_type=wcb_company --post_author=50 --field=ID --posts_per_page=1` → capture as `<company-id>`; navigate to `/wp-admin/post.php?post=<company-id>&action=edit` → expect 200, company edit screen renders with meta boxes
3. Via WP-CLI (simulating the admin save), update all four meta keys:
   ```bash
   wp post meta update <company-id> _wcb_tagline "Admin Smoke Tagline"
   wp post meta update <company-id> _wcb_industry "Finance"
   wp post meta update <company-id> _wcb_company_size "51-200"
   wp post meta update <company-id> _wcb_hq_location "New York, NY"
   ```
   → each command should return success (no error output)
4. Verify all four meta keys persisted: `wp post meta list <company-id> --keys=_wcb_tagline,_wcb_industry,_wcb_company_size,_wcb_hq_location` → expect all four rows present with the values set in step 3
5. There is no public single-company REST route. Verify the saved meta reaches the public API via a linked job: find a published job for this company (`wp post list --post_type=wcb_job --meta_key=_wcb_company_id --meta_value=<company-id> --post_status=publish --field=ID --posts_per_page=1`), then GET `/wp-json/wcb/v1/jobs/<job-id>` as anonymous → expect HTTP 200, response body contains `company_tagline: "Admin Smoke Tagline"`, `company_industry: "Finance"`, `company_size_label` with "51-200" in it, and `company_hq: "New York, NY"` (the jobs endpoint prefixes company fields with `company_`)
6. Navigate to the company's public URL: `wp post get <company-id> --field=guid` → GET that URL → expect HTTP 200, tagline "Admin Smoke Tagline" is visible on the page
7. Find a published job linked to this company: `wp post list --post_type=wcb_job --meta_key=_wcb_company_id --meta_value=<company-id> --post_status=publish --field=ID --posts_per_page=1` → navigate to that job's single-job URL → expect company section shows "Admin Smoke Tagline"
8. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

```bash
wp post meta update <company-id> _wcb_tagline ""
wp post meta update <company-id> _wcb_industry ""
wp post meta update <company-id> _wcb_company_size ""
wp post meta update <company-id> _wcb_hq_location ""
```

## Notes

- This journey is the admin-side complement to `customer/employer-edit-company.md`. Both guard the same D.company-tagline-missing contract from opposite user roles.
- If the admin edit screen uses a Gutenberg meta-box rather than a classic meta box, the WP-CLI `post meta update` approach still writes the correct DB record.
