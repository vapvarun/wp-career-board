---
id: poster-edit-job-and-company
priority: high
personas: employer.figma, morgan_moderator
requires: mu:autologin, seed:jobs
last_verified: 2026-07-08
bug_ref: 9871740742
covers: blocks/job-form (edit mode), blocks/employer-dashboard, PATCH /wcb/v1/jobs/{id}, PATCH /wcb/v1/employers/{id}, GET /wcb/v1/jobs/{id}, GET /wcb/v1/employers/me/jobs, GET /wcb/v1/search, companies archive
---

# Walkthrough: Edit a Job & Company — edit an existing job, update the company profile, and resubmit a rejected job into moderation

**Why this journey exists:** Editing is a routine employer action across three surfaces that must all stay in sync. This walkthrough traces: (1) editing a job so the change propagates to the public listing, REST response, and search index; (2) editing the company profile so tagline/industry/size/HQ round-trip to the public job page (guards Basecamp 9871740742 — those four fields were missing from `prepare_item_for_response_array()`); and (3) resubmitting a rejected job so it reads "Rejected" (not "Draft") and re-publishing routes back to moderation (`pending`), never straight live (guards Basecamp 9976849052). Consolidates `customer/employer-edit-job`, `customer/employer-edit-company`, and `customer/employer-rejected-job-resubmit`.

## Steps

### A. Edit a job

1. As `employer.figma`, navigate to `http://jobboard.local/?autologin=employer.figma` → expect HTTP 200, logged in (user ID 50). Find a `wcb_job` owned by them: `wp post list --post_type=wcb_job --post_author=50 --post_status=publish --field=ID --posts_per_page=1` → capture `<job-id>`; read `wp post get <job-id> --field=post_title` → capture `<original-title>`.

2. Open the job in the form's edit mode — from the dashboard My Jobs list click the Edit control `.wcb-db-link-btn--edit[data-wp-bind--href="context.job.editUrl"]` (`blocks/employer-dashboard/render.php:576`; `editUrl` = `add_query_arg('edit', <job-id>, <job-form-page>)`, `api/endpoints/class-employers-endpoint.php:666`) → expect the `wcb/job-form` block to prefill the existing title/description/salary.

3. Change the title + salary and save → observe `PATCH http://jobboard.local/wp-json/wcb/v1/jobs/<job-id>` with body `{"title":"Smoke Edit - <original-title>","description":"Updated description","salary_max":99999}` → expect HTTP 200 with `id === <job-id>`. Only the owner may PATCH (cross-employer edit protection: `security/employer-cant-edit-other-job`).

4. Verify propagation across all three surfaces:
   - DB: `wp post get <job-id> --field=post_title` → `Smoke Edit - <original-title>`; `wp post meta get <job-id> _wcb_salary_max` → `99999`.
   - Public REST (anonymous): `GET http://jobboard.local/wp-json/wcb/v1/jobs/<job-id>` → `title` = `Smoke Edit - <original-title>`, `salary_max` = `99999` (not stale).
   - Search index: `GET http://jobboard.local/wp-json/wcb/v1/search?q=Smoke+Edit` → array includes an entry with `id === <job-id>`. (If cached, bump `wp option update wcb_jobs_cache_v $(($(wp option get wcb_jobs_cache_v) + 1))`.)

5. Restore the title: `PATCH /wcb/v1/jobs/<job-id>` `{"title":"<original-title>"}` → expect HTTP 200.

### B. Edit the company profile

6. Find employer.figma's company: `wp post list --post_type=wcb_company --post_author=50 --field=ID --posts_per_page=1` → `<company-id>`; capture its slug `wp post get <company-id> --field=post_name` → `<company-slug>`. Note there is NO `/companies/<id>` update route — company updates flow through the employers endpoint.

7. `PATCH http://jobboard.local/wp-json/wcb/v1/employers/<employer-id>` with body `{"tagline":"Smoke Tagline 2026","industry":"Technology","size":"11-50","hq":"San Francisco, CA"}` → expect HTTP 200. The endpoint maps `industry`→`_wcb_industry`, `size`→`_wcb_company_size`, `hq`→`_wcb_hq_location` (+ tagline) onto the owner's company.

8. Verify meta in DB: `wp post meta list <company-id>` → `_wcb_tagline`=`Smoke Tagline 2026`, `_wcb_industry`=`Technology`, `_wcb_company_size`=`11-50` (or slug), `_wcb_hq_location`=`San Francisco, CA`.

9. Verify the four fields reach the public API via a linked job (there is no public single-company REST route): find a published job for this company (`wp post list --post_type=wcb_job --meta_key=_wcb_company_id --meta_value=<company-id> --post_status=publish --field=ID --posts_per_page=1`), then anonymous `GET http://jobboard.local/wp-json/wcb/v1/jobs/<job-id>` → expect non-empty `company_tagline`, `company_industry`, `company_size_label`, `company_hq` (Basecamp 9871740742 guard; the jobs endpoint prefixes company fields with `company_`).

10. Navigate to `http://jobboard.local/companies/<company-slug>/` → expect HTTP 200 and the tagline "Smoke Tagline 2026" visibly rendered; open the linked job's public URL → the same tagline is visible in the company section.

### C. Resubmit a rejected job

11. As `morgan_moderator`, reject a published job: `POST http://jobboard.local/wp-json/wcb/v1/jobs/<id>/reject` `{"reason":"Missing salary range"}` → expect HTTP 200; the job becomes `draft` and `_wcb_rejection_reason` is set.

12. As `employer.figma` (the owner), `GET http://jobboard.local/wp-json/wcb/v1/employers/me/jobs` → the rejected job reports `status:"rejected"`, `statusLabel:"Rejected"`, `rejected:true` (NOT `draft`) — driven by `EmployersEndpoint::is_rejected_job()` (`class-employers-endpoint.php:653/756`). On the dashboard My Jobs tab it renders with a "Rejected" badge under the Rejected filter (not the Draft pill) and shows a **Resubmit** action `.wcb-db-link-btn--publish[data-wp-class--wcb-hidden="!context.job.isRejected"]` (`blocks/employer-dashboard/render.php:579`).

13. Click Resubmit → `POST http://jobboard.local/wp-json/wcb/v1/jobs/<id>` `{"status":"publish"}` (`actions.reopenJob`, render.php:579) → expect HTTP 200; the server OVERRIDES to `pending` and clears `_wcb_rejection_reason`. Verify `wp post get <id> --field=post_status` → expect `pending` (NOT `publish` — moderation was not bypassed).

14. tail `wp-content/debug.log` diff over the whole run → expect ZERO new fatal/warning lines.

## Teardown

```bash
# Restore the edited job's title (in case step 5 was not reached).
wp post update <job-id> --post_title="<original-title>"

# Restore the company meta touched in section B.
wp post meta update <company-id> _wcb_tagline ""
wp post meta update <company-id> _wcb_industry ""
wp post meta update <company-id> _wcb_company_size ""
wp post meta update <company-id> _wcb_hq_location ""

# The job rejected/resubmitted in section C is left at 'pending' — re-publish it for other walks:
wp post update <id> --post_status=publish
wp post meta delete <id> _wcb_rejection_reason
```

## Notes

- **Company updates go through `EmployersEndpoint` (`PUT|PATCH /wcb/v1/employers/<id>`), not a company-CPT route** — verified against registered routes: no public `/companies/<id>` GET/PUT exists; only `/companies` list, `/companies/{id}/bookmark`, `/companies/{id}/trust`. `size_label` in the REST response is the human-readable form of the size slug (e.g. "11-50 employees").
- **Rejected != Draft.** `is_rejected_job()` (draft + `_wcb_rejection_reason`) is the single source the My-Jobs builders use for the flag/label. The republish→pending override lives in `JobsEndpoint::update_item()`; the dashboard `reopenJob` optimistic update mirrors it (rejected → "Pending", else → "Published").
- **Four company meta keys to verify are exactly:** `_wcb_tagline`, `_wcb_industry`, `_wcb_company_size`, `_wcb_hq_location` (the D.company-tagline-missing guard row in the runbook).
- **Not 1.5.1-new** — all three sub-flows are pre-existing and covered by regression sentinels under `audit/journeys/customer/`. This file is the human-runnable consolidation.
- Search index may lag behind an edit if cached; allow one cache-busting request (step 4).
