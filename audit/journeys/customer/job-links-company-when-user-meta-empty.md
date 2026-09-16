---
id: job-links-company-when-user-meta-empty
priority: critical
personas: employer.figma
requires: mu:autologin
last_verified: 2026-07-27
needs: cli
bug_ref: 10134657106
---

# A job posted from the standalone page links to the company even when the reciprocal user meta is missing

**Why this journey exists:** employers own a `wcb_company` post AND carry a reciprocal `_wcb_company_id` user meta. Only registration, the wizard seeder and the 1.5.1 self-healer ever write that user meta — a company created by CSV/WP-CLI import, by an admin, or by a migration leaves it unset. `JobsEndpoint::create_item()` used to read that user meta raw, so the new job was saved with no `_wcb_company_id`.

The job still showed up at first, because `get_my_jobs()` falls through to an author-scoped query when the employer has no company. **The failure only lands after the employer opens their dashboard:** `blocks/employer-dashboard/render.php` calls `CompanyMetaShape::resolve_company_id()`, which self-heals the user meta, so `get_my_jobs()` switches to the company-scoped `meta_query` branch — and the unlinked job disappears permanently. Step 6's ordering is the whole point of this journey; verifying `/employers/me/jobs` *before* loading the dashboard passes even on the broken build.

## Steps

1. Put an employer into the bug's precondition: leave their `wcb_company` published and authored by them, but `wp user meta delete <employer-id> _wcb_company_id` → confirm `wp user meta get <employer-id> _wcb_company_id` is empty
2. As that employer, POST `/wp-json/wcb/v1/jobs` from the **standalone** Post-a-Job page (not the dashboard composer — the dashboard render self-heals the user meta before the form is even submitted) → expect HTTP 201
3. `wp post meta get <job-id> _wcb_company_id` → equals the employer's owned company id (was empty before the fix)
4. `wp post meta get <job-id> _wcb_company_name` → equals that company's post title
5. `wp user meta get <employer-id> _wcb_company_id` → the resolver has written the reciprocal link back
6. **In this order:** load the employer dashboard page, THEN GET `/wp-json/wcb/v1/employers/me/jobs` → the job is present in the list. On the broken build the dashboard load flips the endpoint to the company-scoped branch and the job is gone
7. As anonymous, GET `/wp-json/wcb/v1/jobs/<job-id>` → `company_tagline` / `company_industry` / `company_size_label` / `company_hq` are populated from the linked company, not empty strings
8. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

```bash
wp post delete <job-id> --force
wp user meta update <employer-id> _wcb_company_id <company-id>
```

## Notes

- Fix chokepoint: `\WCB\Core\CompanyMetaShape::resolve_company_id()` — it reads the user meta, validates a live `wcb_company`, falls back to a bounded owned-company lookup, and writes the user meta back. It PERFORMS A WRITE, so it may only be called in owner context (one current user per request), never inside a row loop.
- Existing installs get their already-orphaned jobs adopted by the 1.3.0 `Install::migrate_orphan_job_company_links()` upgrade step. Jobs whose author resolves to no company (imports, deleted employers, admin-authored) are intentionally left alone.
- Sibling coverage: `admin/admin-job-save-preserves-company.md` guards the wp-admin save path that used to strip the link this journey creates.
