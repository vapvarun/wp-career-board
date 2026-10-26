# Walk Remediation Plan — WP Career Board 1.5.1

Long-term fix plan derived from the full journey walk ([`WALK-FINDINGS-1.5.1.md`](WALK-FINDINGS-1.5.1.md)).
34 walkthroughs walked (Free + Pro, combo). Grouped by **theme → fix**, prioritized. Nothing was
fixed during the walk (per instruction); this is the plan to act on.

## Headline: the two money-paths work
- **Seeker apply** (S2) and **employer post-a-job** (P2) both pass end-to-end (UI + REST + DB). ✅
- Most admin surfaces (A1–A8) and all Pro admin tabs (PA1–PA6, incl. the new 1.5.1 Analytics) render cleanly with no console errors. ✅
- The issues below are mostly **discoverability / provisioning / consistency**, plus **QA-infra gaps** — not broken core transactions.

---

## Theme 1 — Single source of truth for entity ownership & state (highest-value)

The most impactful long-term fix. Several surfaces answer the same question ("does this employer
have a company?", "is this job still open?") in different ways and disagree.

| # | Issue (finding) | Sev | Root cause | Long-term fix |
|---|---|---|---|---|
| 1.1 | Employer dashboard **Overview shows 3 jobs but My Jobs/Applications say "set up company profile"** (P3/P4) | 🟡 | Overview resolves jobs via post-side company link; My Jobs/Applications gate on `state.companyId` (from `_wcb_company_id` user meta). The two paths disagree when the user-meta link is absent. | One `Employer::get_company_id($user)` resolver used by EVERY surface (Overview, My Jobs, Applications, logo, banner). No surface computes ownership independently. Add a self-heal: if a `wcb_company` exists with `_wcb_company_owner=$uid` but the user meta is missing, backfill it. |
| 1.2 | **Expired/past-deadline jobs shown as "open"** in archive, company Open Positions, employer Active Jobs (S6/P3) | 🔵 | Listings filter by `post_status=publish`; "Deadline Auto-Close" is opt-in and OFF by default, so a past-deadline job stays `publish` until the daily `wcb_check_job_expiry` cron (up to 24h lag) — and even then only if enabled. | Filter active-job queries by `deadline >= today` at query time (independent of the cron), OR surface an "Applications Closed" badge on past-deadline jobs regardless of the auto-close setting. The cron then only changes stored status, not visibility correctness. |

---

## Theme 2 — Provisioning & discoverability parity (Free vs Pro, employer vs candidate)

Free provisions pages + nav via the setup wizard; Pro (and the candidate side) don't — so real
features exist but users can't reach them.

| # | Issue | Sev | Root cause | Long-term fix |
|---|---|---|---|---|
| 2.1 | **Candidate registration undiscoverable** — the only signup entry is a page/nav labelled "Employer Registration"; the "Find a Job" card is hidden inside it; candidate-dashboard (anon) offers only "Sign In" (S3/P1) | 🟠 | Registration page + nav item named for employers only; no candidate-facing entry. | Rename the page/slug/nav to a neutral **"Join / Register"** (it already role-picks Find-a-Job vs Hire-Talent). Add a "Create an account" link on the dashboard Sign-In empty-state and on the login screen. |
| 2.2 | **Pro member pages not provisioned** — `job-map` has no page, no Find-Jobs map toggle, `wcb_resume` has `has_archive=false`; only `/find-candidates/` exists (PS3) | 🟠 | Pro activation/setup wizard doesn't create pages for its member-facing blocks; resume builder & alerts survive only because the candidate dashboard embeds them. | Extend the setup wizard (or a Pro activation step) to provision the Pro pages it ships blocks for — at minimum a **Job Map** page (the 1.5.1 "pins narrow to results" feature is currently unreachable by default) and a resume directory/archive. Add matching nav entries. |
| 2.3 | **Discovery blocks render empty** — `featured-jobs`, `recent-jobs`, `job-stats` output nothing on the frontend (bare placement) despite 12 jobs / 1 featured (S7) | 🟠 | render callback returns empty with block.json defaults (or requires editor-set attributes); no empty-state. | Make each block render from its defaults with data present; add explicit empty states; add a journey that places each on a page and asserts non-empty output. |

---

## Theme 3 — Registration bootstrap asymmetry

| # | Issue | Sev | Root cause | Long-term fix |
|---|---|---|---|---|
| 3.1 | Employer register bootstraps a `wcb_company`; **candidate register does NOT bootstrap a `wcb_resume`** (S3/P1) | 🔵 | `register_candidate()` creates only the WP user; the walkthrough/manifest note claims a resume transaction. | Decide intended behavior: either create an empty draft resume on register (so a new candidate can apply immediately) or update the docs to stop claiming it. Newly-registered candidates currently can't apply until they build a resume (apply requires one when `apply_resume_required` is on). |

---

## Theme 4 — Minor UX / cosmetic (low priority)

| # | Issue | Sev | Fix |
|---|---|---|---|
| 4.1 | Find Jobs count label switches format when filtered ("10 of 12 jobs" → "6 jobs") (S1) | 🔵 | Keep "N of M" format when filtered. |
| 4.2 | Pro Analytics "Top Jobs by Views" lists an **"(untitled)"** job (PA6) | 🔵 | Filter out views for deleted/untitled jobs, or join to a valid published title. |
| 4.3 | Minimal companies render empty card bodies (no meta) (S6) | 🔵 | Add card empty-state / placeholder for missing industry/size/description. |
| 4.4 | Employer "Set Up Company Profile" welcome banner persists after a company exists (P3) | 🔵 | Dismiss the onboarding banner once a company is present (same resolver as 1.1). |
| 4.5 | `job-bookmark` meta stored as scalar string, not array (S5) | 🔵 | Confirm bookmarking a 2nd job appends; if not, migrate to an array. |
| 4.6 | `GET /candidates/register` returns 404 (not 405) for method mismatch (S3) | 🔵 | Cosmetic/doc — WP default; update the journey expectation to 404. |

---

## Theme 5 — QA infrastructure (test tooling, not shipped product)

These blocked or complicated the walk itself. Fixing them makes 1.5.1 (and every release) actually testable.

| # | Issue | Sev | Fix |
|---|---|---|---|
| 5.1 | **Seeder omits `_wcb_company_id` user meta** → employer dashboard (My Jobs/Applications/logo) untestable for seeded employers; looked like a 🔴 product bug until root-caused (P3/P4) | 🟠 | In `bin/seed-qa-fixtures.php`, after creating each company, `update_user_meta($employer_id,'_wcb_company_id',$company_id)`. |
| 5.2 | Seeder role bug + persona-name mismatch (roles `employer`/`candidate` didn't exist; logins didn't match qa-config/walkthroughs) | 🟠 | **FIXED THIS SESSION** — seeder now uses `wcb_employer`/`wcb_candidate` and canonical logins (employer.figma/…, sarah.chen/…, siobhan). |
| 5.3 | `wp eval-file seed-qa-fixtures.php` fatals; `require`-in-`wp eval` works | 🟡 | Investigate the eval-file execution-context fatal so the documented run command works. |
| 5.4 | Seeder creates minimal companies (no industry/size) → trips completeness gates + empty cards | 🔵 | Seed complete company profiles so dashboard gates and cards are exercised. |
| 5.5 | Coverage gate greps a non-existent PHP test corpus, ignores the Markdown journeys' `covers:` frontmatter → 0% + can't credit new journeys (e.g. the new AI-cron journey) | 🔵 | Point `qa-coverage-check.php` at `audit/journeys/**/*.md` `covers:` fields (see the earlier coverage discussion). |
| 5.6 | Walkthrough docs over-claim in places (resume-on-register; `/credits/balance` route is docblock-only, real is `/employers/{id}/credits`; photo upload is on `resume-form-simple` not `resume-form`; dead `openCheckout`; GET 405) | 🔵 | Correct the affected walkthrough/catalog rows (catalog already partially corrected this session). |
| 5.7 | Auto-login mu-plugin no-ops when already logged in → persona switching needs explicit logout | 🔵 | **DOCUMENTED THIS SESSION** in both walkthrough READMEs. Optionally make the mu-plugin force-switch when `?autologin=` differs from the current user. |

---

## Suggested order of work

1. **5.1** (one-line seeder fix) — unblocks employer-dashboard testing immediately.
2. **1.1** (single company resolver + self-heal) — fixes the dashboard inconsistency AND hardens against 5.1-class data gaps for real users.
3. **2.1** (rename registration → Join, add candidate entry) — restores the seeker signup funnel.
4. **2.3** (discovery blocks empty) and **2.2** (provision Pro pages incl. Job Map) — reach the shipped features.
5. **1.2** (deadline-based visibility) and **3.1** (resume-on-register decision).
6. Sweep Theme 4 cosmetics + Theme 5 doc/coverage cleanups.

## Addendum — findings from the 5 completed journeys (second pass)

| # | Issue | Sev | Fix |
|---|---|---|---|
| A.1 | **Job custom-field values save to postmeta, not `wcb_field_values`** (PP4) — the field renders + saves (`walk_field` postmeta), but the dedicated `wcb_field_values` table stayed NULL. | 🟡 | Decide the storage system of record: if postmeta is intentional for jobs, the `wcb_field_values` table is dead weight for jobs (remove or document its real scope — resume/company fields). If the table is intended, jobs aren't writing to it. Consolidate to one. |
| A.2 | **Orphan application silently omitted** from candidate applications, not shown "Job Removed" (P5) | 🟡 | The candidate applications query drops apps whose job is missing (INNER-join / null-filter). Show orphaned apps with a "Job Removed" state instead of hiding them. **Verify with a trashed job (not a never-existent id) before fixing** — behavior may differ for `trash` status. |
| A.3 | Kanban header reads **"2 jobs jobs with applications"** — duplicated word (PP2) | 🔵 | Fix the string interpolation (double "jobs"). |
| A.4 | Kanban **stage-move REST 403** in fixtures (PP2) — seeded stages scoped to "smoke board"; job on another board | 🟡 | Confirm the permission/board-scope logic: an employer moving their own application's stage should succeed when the target stage belongs to that application's board. Re-test with board-aligned stages; if it 403s there too, it's a real permission bug. |
| A.5 | `GET /notifications/unread-count` → 404 (PS4) | 🔵 | Either the route is named differently or the count only rides the list response — confirm the bell's unread source. |

(A.1/A.4 fold into **Theme 1 single-source-of-truth**; A.2 into **Theme 1 data integrity**; A.3/A.5 into **Theme 4 cosmetic**.)

## Not reproduced as product bugs (walk artifacts, for the record)
- "Employer sees 0 applicants" (looked 🔴) → seeder gap 5.1; applicant counts are correct (2/1/0) once the company link exists.
- P5 orphan-handling, PP1/PP2/PP4 deep flows, PS4 PWA offline: deferred (need isolated setup); not walked to completion this pass.
