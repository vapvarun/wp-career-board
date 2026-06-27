---
id: employer-cant-see-other-applications
priority: critical
personas: employer-a, employer-b
requires: mu:autologin, seed:applications
last_verified: 2026-06-27
needs: cli
---

# Employer cannot read another employer's applications list

**Why this journey exists:** the applications endpoint scoped to an employer (`GET /wcb/v1/employers/<company-id>/applications`) must enforce ownership — an employer must receive 403 when requesting a company they do not own. A missing ownership check leaks candidate personal data and is a GDPR/privacy violation.

> **Route semantics (verified 2026-06-27):** `<id>` is the **`wcb_company` post id** (the employer dashboard passes `state.companyId`), NOT a user id. `get_applications_permissions_check()` grants access when `get_current_user_id() === company.post_author` AND the user holds `wcb/view-applications`, OR the user holds `wcb/manage-settings` (admin). A non-`wcb_company` id returns 404.

## Steps

1. Resolve two employers' companies: employer A's company (`wp post list --post_type=wcb_company --post_author=<empA-uid> --field=ID --posts_per_page=1` → `<companyA>`) and employer B's company (`<companyB>`). Confirm applications exist for jobs linked to `<companyA>` (`_wcb_company_id = <companyA>`).
2. As **employer B**, navigate to `/?autologin=<empB>` → expect HTTP 200, logged in.
3. Attempt to read employer A's applications: GET `/wp-json/wcb/v1/employers/<companyA>/applications` → expect HTTP 403, response body has an error `code` (NOT 200, NOT an empty array that hides the auth failure).
4. The HTTP status must be 403 — not 200 with `[]` masking the permission failure.
5. As **employer A**, GET `/wp-json/wcb/v1/employers/<companyA>/applications` → expect HTTP 200 (own company's applications are accessible).
6. Symmetric: as employer A, GET `/wp-json/wcb/v1/employers/<companyB>/applications` → expect HTTP 403.
7. As `varundubey` (admin), GET `/wp-json/wcb/v1/employers/<companyA>/applications` → expect HTTP 200 (admin holds `wcb/manage-settings`, so the ownership check is bypassed).
8. tail debug.log diff → expect ZERO new fatal/warning lines.

## Teardown

None — read-only journey.

## Notes

- The endpoint is `GET /wcb/v1/employers/(?P<id>\\d+)/applications`, permission `get_applications_permissions_check`, handler `get_applications` (404s when `<id>` is not a `wcb_company`).
- Passing a **user** id (instead of the company post id) returns 403/404 — a common mistake when writing this journey. Always resolve the company id first.
