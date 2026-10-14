---
id: employer-several-companies
priority: high
personas: employer.vercel
requires: mu:autologin, seed:jobs
last_verified: 2026-10-13
bug_ref: 10400521949
---

# Employer runs several companies from the frontend: add, edit, make main, remove

**Why this journey exists:** a second company used to be invisible to the employer and unmanageable without wp-admin. Members have no wp-admin, so the whole lifecycle must work from the dashboard's Companies tab (owner decision, 1.8.1).

## Steps

1. As `employer.vercel` (Maya Okafor), open `/employer-dashboard/?autologin=employer.vercel#companies` → expect the Companies tab lists Northpeak Cloud (Main company badge) and Marketlane Commerce, each row with one "Edit profile" button and a "..." menu (View, Make main, Remove). At 390px the phone menu button reads "Companies".
2. Click "Add company", name it "Fernbrook Studio", save → expect a third row. `GET /wp-json/wcb/v1/employers/me/companies` → expect 3 items, Northpeak Cloud still `isMain: true` (adding a company never switches the main one).
3. Open Post a Job → expect a company picker listing all three, Northpeak Cloud preselected; posting "Brand Designer" with Fernbrook Studio saves `_wcb_company_id` = Fernbrook's ID.
4. On Fernbrook's row, open "..." → "Make main" (`POST /wp-json/wcb/v1/employers/<fernbrook-id>/main`) → expect the Main company badge moves, the sidebar reads "Employer · Fernbrook Studio" and "Public Page" opens Fernbrook's page.
5. "Edit profile" on Marketlane → `?company=<id>#company` → change the tagline to "Marketplaces that sell themselves" → save → expect the public company page shows it.
6. "..." → "Remove" on Fernbrook (`DELETE /wp-json/wcb/v1/employers/<fernbrook-id>`) → expect the row is gone, the main company passes back to another company, and "Brand Designer" is closed (see `company-removed-closes-jobs`).
7. As admin, wp-admin → Companies → expect both remaining companies list Maya under Employers.
8. tail debug.log diff → expect ZERO new fatal/warning lines.

## Teardown

```bash
wp post delete --force $(wp post list --post_type=wcb_job --title="Brand Designer" --format=ids --post_status=any)
wp post delete --force $(wp post list --post_type=wcb_company --title="Fernbrook Studio" --format=ids --post_status=any)
wp eval 'update_user_meta( get_user_by( "login", "employer.vercel" )->ID, "_wcb_company_id", 56521 );'
wp post meta update 56522 _wcb_tagline ""
```

## Notes

- Only the company's creator (or an admin) may remove it; a co-owner sees no Remove item.
- Covered by `tests/test-employer-companies.php` (13 checks) at the REST layer; this journey is the member's path.
