---
id: walkthrough-admin-companies-and-employers
priority: high
personas: varundubey, employer.figma
requires: mu:autologin, seed:jobs
last_verified: 2026-07-08
covers: admin/admin-companies-page-renders, admin/admin-companies-edit-meta, admin/admin-employers-page-renders, admin/admin-employer-ban-cant-post
---

# Walkthrough: Companies & Employers — render both lists, edit company meta, and ban an employer so they can no longer post

**Why this journey exists:** The Companies list shows the site owner every employer profile; editing company meta (tagline/industry/size/HQ) must persist and reach the public API. The Employers list is where a site owner disciplines abuse — a ban must propagate to the REST permission layer on the very next request, not just set a DB flag. A ban that leaves the job-posting endpoint unaware lets banned employers keep posting. This is the human-runnable form of the four companies/employers sentinels.

## Steps

1. As `varundubey`, navigate to `http://jobboard.local/wp-admin/admin.php?page=wcb-companies&autologin=varundubey` → expect HTTP 200 and the Companies list table (`AdminCompanies::render()`, `admin/class-admin-companies.php:54`) with column headers **Company Name, Employer, Website, Active Jobs, Trust Level, Status, Date** (`get_columns()`, `admin/class-admin-companies.php:108-118`).
2. Confirm the views render WITHOUT a "Pending" tab — companies are not moderated by design (`admin/class-admin-companies.php:170-171`); expect at minimum All / Published / Draft.
3. **Edit company meta.** Pick employer.figma's company: `COMP=$(wp post list --post_type=wcb_company --post_author=50 --field=ID --posts_per_page=1 --path=/Users/varundubey/Local Sites/jobboard/app/public)`. Navigate to `http://jobboard.local/wp-admin/post.php?post=<COMP>&action=edit&autologin=varundubey` → expect HTTP 200 and the company edit screen with its meta boxes. Set Tagline, Industry, Size, and HQ, and Update (or, to simulate the save: `wp post meta update <COMP> _wcb_tagline "Admin Smoke Tagline"`, `_wcb_industry "Finance"`, `_wcb_company_size "51-200"`, `_wcb_hq_location "New York, NY"`).
4. Verify the four meta keys persisted: `wp post meta list <COMP> --keys=_wcb_tagline,_wcb_industry,_wcb_company_size,_wcb_hq_location` → expect all four rows with the set values.
5. Confirm the saved meta reaches the public API via a linked job (there is no single-company REST route): `JOB=$(wp post list --post_type=wcb_job --meta_key=_wcb_company_id --meta_value=<COMP> --post_status=publish --field=ID --posts_per_page=1 ...)`; `GET http://jobboard.local/wp-json/wcb/v1/jobs/<JOB>` as anonymous → expect HTTP 200 with `company_tagline: "Admin Smoke Tagline"`, `company_industry: "Finance"`, a `company_size_label` containing "51-200", and `company_hq: "New York, NY"` (the jobs endpoint prefixes company fields with `company_`).
6. **Employers list.** Navigate to `http://jobboard.local/wp-admin/admin.php?page=wcb-employers&autologin=varundubey` → expect HTTP 200 and the Employers list table (`AdminEmployers::render()`, `admin/class-admin-employers.php:54`) with column headers **Name, Company, Website, Active Jobs, Status, Registered** (`get_columns()`, `admin/class-admin-employers.php:107-116`). The Name column renders `<strong><a class="row-title">display_name</a></strong>` with the email below it (`column_name()`, `admin/class-admin-employers.php:421-427`).
7. Confirm employer.figma (user 50) is listed and record baseline capability: `wp eval 'echo user_can(50, "wcb_post_jobs") ? "yes" : "no";'` → expect `yes`.
8. **Ban an employer.** Hover employer.figma's row → expect a "Ban" row action (`a.wcb-row-action--danger`, `?page=wcb-employers&action=ban&user[]=50` + nonce `bulk-employers`, `admin/class-admin-employers.php:442-462`). Click "Ban" → expect a redirect back to the list and the Status column now showing a "Banned" badge (`column_status()`, `admin/class-admin-employers.php:200-204`). Under the hood `process_bulk_action()` writes `_wcb_employer_banned = 1` user-meta and fires `wcb_employer_banned` (`admin/class-admin-employers.php:156-184`).
9. Verify the ban revoked the capability immediately: `wp eval 'echo user_can(50, "wcb_post_jobs") ? "yes" : "no";'` → expect `no` (`core/class-abilities.php` reads `_wcb_employer_banned` and strips every WCB ability from the banned user).
10. **Banned employer cannot post.** As `employer.figma`, `POST http://jobboard.local/wp-json/wcb/v1/jobs` with body `{"title":"Should Not Post","description":"banned test"}` via `?autologin=employer.figma` → expect HTTP 403, and the error `code` is NOT `rest_no_route` (the route exists; the permission callback rejects it). Verify no job was created: `wp post list --post_type=wcb_job --post_author=50 --search="Should Not Post" --field=ID` → zero rows.
11. **Unban.** Hover the row → the toggle now reads "Unban" (`?action=unban&user[]=50` + nonce). Click it → expect the "Banned" badge to clear and `wp eval 'echo user_can(50, "wcb_post_jobs") ? "yes" : "no";'` → `yes` (fires `wcb_employer_unbanned`).
12. tail `wp-content/debug.log` diff over the whole run → expect ZERO new fatal/warning lines.

## Teardown

```bash
SITE='/Users/varundubey/Local Sites/jobboard/app/public'
# Ensure employer.figma is unbanned (run unconditionally).
wp user meta delete 50 _wcb_employer_banned --path="$SITE" 2>/dev/null || true
# Reset the company meta touched in step 3.
for K in _wcb_tagline _wcb_industry _wcb_company_size _wcb_hq_location; do
  wp post meta update "$COMP" "$K" "" --path="$SITE" 2>/dev/null || true
done
```

## Notes
- Both pages are CUSTOM admin pages (`admin.php?page=wcb-companies` / `?page=wcb-employers`, `WP_List_Table` subclasses), NOT native screens. The Employers page shipped a Ban/Unban feature (`get_bulk_actions()` + the row-action toggle, `admin/class-admin-employers.php:138-142,442-462`) — older atomic journeys that say "no ban action in v1.1.0" are stale; ban is present now.
- The ban contract spans two layers: the WRITE side is `AdminEmployers::process_bulk_action()` (sets `_wcb_employer_banned`); the READ/enforcement side is `core/class-abilities.php`, which strips abilities so the REST `create_item_permissions_check` on the Jobs endpoint denies the post. Both must be exercised — step 8 (write) and step 10 (enforcement).
- Companies have no `pending` status; do not assert a Pending tab. Trust Level renders a badge from `_wcb_trust_level` meta (may be empty).
- Company meta is edited on the native post-edit screen (`post.php?post=<id>&action=edit`); this is the admin complement to the employer self-service `customer/employer-edit-company` flow — both guard the same `company_*` API contract.
- No 1.5.1-new surface in this walkthrough.
