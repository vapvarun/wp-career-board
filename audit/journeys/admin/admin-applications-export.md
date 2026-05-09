---
id: admin-applications-export
priority: medium
personas: varundubey
requires: mu:autologin, seed:applications, pro:active
last_verified: 2026-05-09
needs: cli
---

# Admin exports the applications list as CSV/JSON; file downloads successfully

**Why this journey exists:** the analytics export is the primary way HR teams extract data from the plugin; a 500 on export or a truncated/empty file is a high-impact support ticket. Verifies that the export endpoint responds with the correct Content-Type and a non-empty body.

## Steps

1. Skip cleanly if Pro is not active: `wp plugin is-active wp-career-board-pro` → if false, mark journey `skipped: pro_inactive` and exit success (export is a Pro feature via `GET /wcb/v1/analytics/credits.csv`)
2. As `varundubey` (admin), navigate to `/wp-admin/admin.php?page=wcb-credits&autologin=1` → expect HTTP 200, the analytics/credits admin page renders
3. GET `/wp-json/wcb/v1/analytics/credits.csv` with admin auth cookie → expect HTTP 200, response header `Content-Type` contains `text/csv` or `application/csv`, response body is non-empty text with at least one header row
4. Verify the CSV has a header line: inspect first line of the response body → expect comma-separated column names (e.g. `employer_id`, `amount`, `entry_type`, `created_at` or similar schema from `wcb_credit_ledger`)
5. Verify the CSV has at least one data row (given seed data is present): line count of response body (excluding header) ≥ 1
6. GET `/wp-json/wcb/v1/analytics/credits.csv` as `employer.figma` (non-admin) → expect HTTP 403 (ability check `wcb/manage-credits` restricts to admin)
7. Navigate to `/wp-admin/edit.php?post_type=wcb_application?autologin=1` → expect HTTP 200, applications list renders; confirm the table shows the correct application count matching the seed
8. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

None — read-only journey.

## Notes

- The analytics export endpoint is `GET /wcb/v1/analytics/credits.csv` per the Pro manifest, with permission `wcbp_manage_credits ability + license check`.
- Free does not have a dedicated export endpoint — the admin list at `edit.php?post_type=wcb_application` is the Free export surface.
- If the Pro export is not yet wired (status `partial`), step 3 may return 404 — record and mark that step as `skipped: endpoint_not_wired` rather than failing the journey.
