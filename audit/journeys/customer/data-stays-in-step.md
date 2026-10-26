---
id: data-stays-in-step
priority: high
personas: guest, mateo.ruiz, adrian.kowalski, varundubey
requires: mu:autologin, seed:jobs, seed:companies, pro:optional
last_verified: 2026-10-24
bug_ref: 10427560929, 10427558911, 10427556196, 10427543204, 10418357457, 10417521506, 10417521278, 10422461443, 10422290745
---

# Every copy of a fact agrees with its source

**Why this journey exists:** a batch of cards where one surface kept its own copy of a fact and drifted: jobs kept a company's old name, a company's draft URL went into search-engine data, Load more skipped the featured job page 1 had shown, an alert dropped its experience level, the credits CSV read the ledger by hand and showed IDs, two dropdowns ordered the same terms differently, and the credits warning disagreed with the employer's Credits tab.

## Steps

1. As `mateo.ruiz`, rename company Copperleaf Analytics (Employer dashboard > Profile, or wp-admin) to `QA-RFT Renamed Co` → `/find-jobs/?search=QA-RFT Renamed` lists all its jobs with the new name on each card, the job page and Recent/Featured jobs; rename it back. A site upgraded from 1.8.1 with jobs on a stale name gets them fixed by the DB 1.4.2 step (card:10427543204).
2. Set a company with a published job to Draft → as guest, the job page's JSON-LD `hiringOrganization` has no `url` and the page has no link containing `p=<company id>`; the company name shows as plain text. Publish it again (card:10418357457).
3. A company with more than 10 jobs, one featured on page 2 by date → company page lists the featured job first and Load more adds the rest, with no repeat and none missing (card:10427558911).
4. With Pro, as `adrian.kowalski`, Job Alerts block → pick Experience = Senior (also a Tag) → Subscribe → the list shows "Senior · Daily"; candidate dashboard Alerts shows a "Senior" pill; `wp_wcb_job_alerts.filters` has `"experience":"senior"` (card:10427556196).
5. With Pro, as admin, Analytics > Export credits CSV → one row per ledger entry (count matches `Credits::query_ledger` total), columns ID, Employer, Employer email, Employer ID, Amount, Type, Job, Job ID, Note, Date; a note starting `=` is prefixed with `'`; negative amounts are plain numbers (card:10427560929).
6. Experience Levels "Associate Level" and "International Programs" with no Order → the job form and Job Filters list Associate first; seeded Entry > Junior > Mid > Senior > Lead stay in seniority order (card:10417521506).
7. Job Locations with Order Zulu = 1, Alpha = 9 → the Location dropdown on Post a Job (both form blocks), as admin and as an employer, lists Zulu before Alpha, the same as Job Filters (card:10417521278).
8. With Pro and a live Stripe gateway, turn off both Payment methods toggles → Settings > Credits shows "Employers can't buy credits yet."; the job form shows no buy-credits link; the employer Credits tab says credits can't be bought; `POST /wbcom-credits/v1/wp-career-board/checkout/stripe` returns 403 `checkout_disabled`. Toggles on but no packs and Custom amount off → the same warning. Restore (card:10422290745).
9. `core/class-plugin.php` docblock above `print_container_width_css_var()` names Settings > Advanced > Content Width and the default 0 = theme width (card:10422461443).
10. Steps 1, 4 and 8 at 390 → no horizontal scroll.
11. tail debug.log diff → expect ZERO new fatal/warning lines.

## Teardown

Rename and republish any company touched, delete QA-RFT terms and alerts, and delete `wcbp_payment_methods` / restore `wcbp_credit_pricing` if they were unset before.
