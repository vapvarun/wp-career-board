---
id: company-handover-drops-old-employer
priority: high
personas: employer, admin
requires: seed:jobs
last_verified: 2026-10-23
needs: cli
bug_ref: 10401941306
---

# A company handed to another owner drops the old employer's link

**Why this journey exists:** an employer's `_wcb_company_id` link counts as owning that company (it is how employers reach a company an admin created for them), and ownership opens applicants, private resume files and company editing. When the company's author changed, or a co-owner was taken off `_wcb_company_owner`, the old link stayed, so the previous employer kept reading the company's applicants. Owner decision: clear stale links when the owner changes.

## Steps

1. As admin, create a published company C (author admin) and a job J under it; a candidate applies to J. Link employer E1 to C: `wp user meta update <E1> _wcb_company_id <C>`.
2. As E1, `GET /wcb/v1/jobs/<J>/applications` → 200; E1's dashboard lists C.
3. Save C without changing its author (`wp post update <C> --post_title="C renamed"`) → E1 still gets 200 (the link survives an ordinary save).
4. Hand C to employer E2: `wp post update <C> --post_author=<E2>` → E1's `_wcb_company_id` is gone; `GET .../applications` as E1 → 403; as E2 → 200.
5. Co-owner case: `wp post meta add <C> _wcb_company_owner <E1>` and re-link E1 → E1 gets 200; `wp post meta delete <C> _wcb_company_owner <E1>` → the link is gone and E1 gets 403.
6. Delete a user who authors C with "Attribute all content to" E2 (Users > Delete) → linked employers other than E2 lose the link; E2 owns C.
6a. **Old employer loses company access on handover (card:10401941306).** Change company C's author from `mateo.ruiz` to `ahmed.karim` → `GET /wcb/v1/jobs/<J>/applications` answers 403 for `mateo.ruiz` and 200 for `ahmed.karim`, at 1440 and 390 in the employer dashboard.
7. Regression test: `wp eval-file wp-content/plugins/wp-career-board/tests/test-employer-companies.php` → all pass.

## Teardown

Delete C, J and the application; remove the test users or their `_wcb_company_id` meta.
