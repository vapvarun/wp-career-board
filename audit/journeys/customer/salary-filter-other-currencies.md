---
id: salary-filter-other-currencies
priority: high
personas: anonymous, candidate
requires: seed:jobs
last_verified: 2026-10-23
needs: browser, cli
bug_ref: 10402937387, 10417744512
---

# Salary filter and sort with jobs in several currencies

**Why this journey exists:** the salary filter silently dropped every job paid in a currency other than the site's, and "Highest salary" compared raw numbers across currencies, so ₹1,500,000 outranked $100,000. There are no exchange rates, so the owner decided: the range applies to site-currency jobs, other-currency jobs are listed after them under a note, and the sort puts site-currency jobs first, then other currencies grouped by currency. Job alerts stay site-currency only.

## Steps

1. Site default currency USD. Publish three remote jobs titled `QA-RFT ...`: 1,500,000 INR yearly, 100,000 USD yearly, 80,000 GBP yearly.
2. `/find-jobs/?search=QA-RFT&sort=salary` at 1440 and 390 → USD job first; then a note "Paid in other currencies. These follow the USD jobs, highest first within each currency."; then GBP, then INR. The note is in the server HTML (view source) and spans the grid row.
3. `/find-jobs/?search=QA-RFT&salary_min=50000` → USD job, then the note "... Your salary range is in USD, so it does not apply to these jobs.", then the GBP and INR jobs. No job disappears silently.
4. `/find-jobs/?search=QA-RFT` (no salary filter, default sort) → no note.
5. From step 4, pick **Highest salary** in the sort control → the note appears in the right place without a reload; pick the default again → it goes.
6. With 20+ jobs and Load more, a page that starts with other-currency jobs after a page of USD jobs shows the note only once, before the first other-currency job.
7. `GET /wcb/v1/jobs?search=QA-RFT&salary_min=50000` → the same order; each job carries `salary_currency`.
8. Job alerts: `JobSearch::salary_matches( <GBP job>, 50000, 0 )` → false; `( <USD job>, 50000, 0 )` → true.
9. Job form: clear the currency field and save → the job is stored in the site currency, not USD on a non-USD site.
10. `wp eval-file wp-content/plugins/wp-career-board/tests/test-job-search.php` → all pass.

## Teardown

Delete the three `QA-RFT` jobs.
