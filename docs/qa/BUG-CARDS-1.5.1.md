# Verified Bug Cards — WP Career Board 1.5.1

Only issues **confirmed at BOTH code level (root cause at file:line) AND browser/live replication**.
Refuted / by-design / false-positive findings are NOT here (see [`VERIFY-TRACKER-1.5.1.md`](VERIFY-TRACKER-1.5.1.md)).
Ready to file (Basecamp project 46502739 → Bugs `9691964821`, or GitHub issues).

**Env for all:** WP Career Board (Free+Pro) `1.5.1`, `http://wp-career-board.local` (Local), Reign theme, BuddyPress inactive.

Verification summary: **13 findings verified → 7 confirmed bugs, 6 refuted/by-design.**

---

## BUG-1 (V1) · Candidate registration is undiscoverable — only entry is labelled "Employer Registration"
**Severity:** High (UX / conversion) · **Plugin:** Free · **Area:** onboarding

**Steps to reproduce**
1. As an anonymous visitor, open `/candidate-dashboard/` → the gate shows only a **"Sign In"** button, no Register link.
2. Look for a candidate sign-up entry — the only registration page is `/employer-registration/` (title "Employer Registration"); no `/candidate-registration/` page exists.
3. (The working candidate form is the "Find a Job" card *inside* `/employer-registration/`, which a job seeker would never open.)

**Expected:** a job seeker can find a way to create a candidate account from the front-end.
**Actual:** candidate signup is reachable only through an employer-labelled page; `POST /wcb/v1/candidates/register` works but nothing candidate-facing links to it.

**Root cause (file:line)**
- Setup wizard creates only `employer_registration_page` titled "Employer Registration" — `admin/class-setup-wizard.php:410-413`; slug map `admin/class-pages.php:48`. No candidate-registration page.
- Anonymous candidate-dashboard gate renders only `wp_login_url()` "Sign In" — `blocks/candidate-dashboard/render.php:18-28`.
- Route exists, unlinked — `api/endpoints/class-candidates-endpoint.php:90-115`.

**Suggested fix:** rename the page to a role-neutral "Join / Register" (+ back-compat slug alias), and add a "Create an account" link to the anonymous candidate-dashboard gate (mirror `blocks/employer-dashboard/render.php:31-40`). No new route needed.
**Fix verification:** anonymous candidate-dashboard shows a Register link → reaches the candidate signup form → new user has role `wcb_candidate`.

---

## BUG-2 (V3) · Employer dashboard: Overview shows jobs but My Jobs/Applications say "Set up your company profile"
**Severity:** High (functional) · **Plugin:** Free · **Area:** employer-dashboard

**Steps to reproduce**
1. Have an employer who owns a published `wcb_company` post (`post_author` + `_wcb_company_owner` = the user) with published jobs, but whose `_wcb_company_id` **user meta is unset** (e.g. company created via WP-CLI/import/admin, or `wp user meta delete <uid> _wcb_company_id`).
2. Load the employer dashboard → **Overview**: stat cards + "Active Jobs" correctly show the jobs.
3. Click **My Jobs** → "Set up your company profile first" (list hidden). Click **Applications** → empty. Overview also shows the "Set Up Company Profile" welcome banner.
   *(Reproduced live during the walk: seeded employer `employer.figma` had the company post but no user meta → Overview showed 3 jobs, My Jobs said "set up profile"; adding the user meta + full reload fixed all surfaces.)*

**Expected:** all dashboard surfaces agree on whether the employer has a company.
**Actual:** two divergent detection paths disagree, leaving the employer unable to manage jobs/applications despite Overview proving they exist.

**Root cause (file:line)**
- **Path A (Overview, author-based, ungated):** SSR counts by `post_author` — `blocks/employer-dashboard/render.php:94-116`; Active Jobs via `/employers/me/jobs` author fallback — `api/endpoints/class-employers-endpoint.php:622-635`.
- **Path B (My Jobs/Apps/banner, user-meta-gated):** `state.companyId` from `get_user_meta(...,'_wcb_company_id')` only — `render.php:53`; `noCompany = !companyId` — `render.php:151`; gates list/apps/banner — `view.js:157,541`, `render.php:426,551-554`.
- No self-heal backfills the user meta from an owned company post. Register sets it (`class-employers-endpoint.php:202`), so only divergent-link employers are hit.

**Suggested fix:** one canonical `Company::resolve_for_user($uid)` used by render.php + `get_my_jobs()` + the apps fetch: read user meta, else look up an owned `wcb_company`, and **backfill** the user meta on match (mirror `backfill_orphan_jobs`).
**Fix verification:** an employer with company-but-no-user-meta sees jobs consistently on Overview AND My Jobs on first load; the user meta gets backfilled.

---

## BUG-3 (V5b) · Employer cannot move applications across Kanban stages — 403 on every move
**Severity:** High (functional, Pro core feature) · **Plugin:** Pro · **Area:** application-kanban

**Steps to reproduce**
1. As a `wcb_employer` who owns a job with applications, open Employer Dashboard → Applications → **Board** view.
2. Move a card to another stage (or `POST /wcb/v1/applications/{id}/stage` with any `{stage_id}`).
3. → **403 `wcb_forbidden`** every time. The employer can *see* the pipeline but cannot change any stage. (Target stage's board is irrelevant — reproduced live as `employer.figma`.)

**Expected:** the job-owning employer can move their own applications across stages.
**Actual:** the write is gated on an ability employers don't have, so the pipeline is read-only to its intended user.

**Root cause (file:line)**
- `move_to_stage` write gate requires ability **`wcb/moderate-jobs`** — `api/endpoints/class-pipeline-endpoint.php:238-240` (`manage_permissions_check`) → 403 `wcb_forbidden` (`api/class-rest-controller.php:74,83`).
- `wcb_employer` role is granted `wcb_view_applications` but **not** `wcb_moderate_jobs` — `core/class-roles.php:43-54` (that cap is moderator/admin only, `:90-100`).
- (Separately, `move_to_stage` does no board/ownership validation on `stage_id` at all — `:78-102` — so out-of-board stages would be *accepted*, not 403'd.)

**Suggested fix:** align the write gate with the actor — allow the job-owning employer (mirror `ApplicationsEndpoint::update_permissions_check` at `class-applications-endpoint.php:835-852`: `wcb/view-applications` + job-owner check + admin bypass). Add `stage_id`-belongs-to-job's-board validation (400 on mismatch).
**Fix verification:** owning employer moves a card → 200, `_wcb_stage_id` persists across reload; out-of-board stage → 400; non-owner → 403.

---

## BUG-4 (V4a) · Job custom-field values: split storage (postmeta vs `wcb_field_values`) that doesn't stay in sync
**Severity:** Medium (data consistency) · **Plugin:** Free+Pro · **Area:** field-builder

**Steps to reproduce**
1. Pro active. Field Builder → create a Job field group + a text field (key `x`).
2. Front-end Post-a-Job (or `POST /wcb/v1/jobs` with `custom_fields:{x:"VAL"}`) → submit.
3. Check both stores: `SELECT * FROM wp_postmeta WHERE meta_key='x'` and `SELECT * FROM wp_wcb_field_values WHERE field_key='x'`.
   *(Live: run 1 → postmeta set, table NULL. run 2 → table set, postmeta empty, REST `custom_fields` read the table. The two stores did NOT both populate; which one wins was inconsistent.)*

**Expected:** one authoritative store; every read surface returns the saved value.
**Actual:** two write paths and two read sources (REST response reads the table `class-fields-module.php:307`; edit-form reads postmeta `class-form-custom-fields.php:635`) that can disagree — a surface reading the empty store shows no value.

**Root cause (file:line)**
- Free postmeta write — `core/class-form-custom-fields.php:484` (via `JobsEndpoint::save_job_custom_fields` `api/endpoints/class-jobs-endpoint.php:703,930-946`).
- Pro table write hooked to `wcb_job_created` — `wp-career-board-pro/modules/fields/class-fields-module.php:321-347` (+ `class-field-storage.php:36-71`), no allow-list.
- Divergent reads: response `:307`, edit-form `:635`.

**Suggested fix:** pick ONE source of truth for Pro-defined job fields (recommend the table); stop the other write for Pro keys; unify the read side; add an allow-list to the Pro write. **Repro caveat:** confirm with a Field-Builder-UI-created field (raw-DB fields in testing behaved inconsistently).
**Fix verification:** submit a Pro job field → the value appears in BOTH the job REST `custom_fields` and the edit-form pre-fill, and only the chosen store is written.

---

## BUG-5 (V6a) · Pro member pages not provisioned — Job Map (1.5.1) is unreachable by default
**Severity:** Medium (feature invisible) · **Plugin:** Pro · **Area:** provisioning

**Steps to reproduce**
1. Fresh site, activate Free+Pro, run the Pro setup wizard.
2. Only `/find-candidates/` (Find Resumes) + an AI Job Search page are created. There is **no** Job Map page, no map view on Find Jobs, and `wcb_resume` has no archive.
3. The 1.5.1 job-map feature ("pins narrow to results") is reachable only if the owner manually adds the `wcb/job-map` block to a page.

**Expected:** shipped Pro member features have a reachable page/entry after setup.
**Actual:** the job-map block ships but is never surfaced.

**Root cause (file:line)**
- Pro wizard provisions only resume-archive + AI-search pages — `wp-career-board-pro/admin/class-pro-setup-wizard.php:318-323`, `modules/resume/class-resume-module.php:2283-2330`.
- `wcb/job-map` registered but never embedded — `core/class-pro-plugin.php:229,472`; no Find-Jobs map toggle exists.
- `wcb_resume` `has_archive=false` — `wp-career-board/modules/candidates/class-candidates-module.php:109` (intentional).

**Suggested fix:** provision a Job Map page (block `wcb/job-map`) in `create_pro_pages_handler()`, and/or add a list/map toggle to the Find Jobs archive reusing the results query.
**Fix verification:** after Pro setup, a Job Map page exists and renders geocoded pins that narrow with the job filters.

---

## BUG-6 (V6b) · Pro Analytics "Top Jobs by Views" lists "(untitled)" (deleted-job view rows)
**Severity:** Low (data hygiene) · **Plugin:** Pro · **Area:** analytics

**Steps to reproduce**
1. View a job (logs a `wcb_job_views` row), then permanently delete that job.
2. Open Career Board Pro → Settings → Analytics → **Top Jobs by Views** → a row shows **"(untitled)"** with its view count. (Seen live in the walk.)

**Expected:** only current published jobs appear.
**Actual:** orphan view rows for deleted jobs surface as "(untitled)".

**Root cause (file:line)**
- `top_jobs_by_views()` query has no JOIN / `post_status` filter — `wp-career-board-pro/modules/analytics/class-analytics-module.php:189-195`; `get_the_title('')` → empty → template prints "(untitled)" — `admin/class-pro-admin.php:1385-1387`.
- View rows never cleaned on delete — `on_job_deleted()` `wp-career-board/modules/applications/class-application-lifecycle.php:53-78` doesn't touch `wcb_job_views`.

**Suggested fix:** `INNER JOIN {posts} p ON p.ID=job_id AND p.post_status='publish' AND p.post_type='wcb_job'` in the query (and/or skip empty titles); optionally purge `wcb_job_views` in `on_job_deleted()`.
**Fix verification:** delete a viewed job → it no longer appears in Top Jobs by Views.

---

## BUG-7 (V6c) · Kanban selector reads "2 jobs jobs with applications" (duplicated word)
**Severity:** Low (copy) · **Plugin:** Free · **Area:** employer-dashboard

**Steps to reproduce**
1. Employer dashboard → Applications with 2+ jobs having applications → the hint reads **"2 jobs jobs with applications"**. (Seen live.)

**Expected:** "2 jobs with applications".
**Actual:** "jobs" duplicated.

**Root cause (file:line)**
- `blocks/employer-dashboard/view.js:229` concatenates `jobSingular` (`' job'`) + pluralize `'s'` + `jobsWithApps` (`' jobs with applications'`), strings at `blocks/employer-dashboard/render.php:274-275`.

**Suggested fix:** change `jobsWithApps` (`render.php:274`) to `' with applications'`.
**Fix verification:** hint reads "2 jobs with applications" / "1 job with applications".

---

## Refuted / by-design (verified NOT bugs — no cards)
| ID | Why not a bug |
|---|---|
| V2 discovery blocks empty | Test error — used `wp:wcb/recent-jobs`; Free blocks are `wp-career-board/*`. Render callbacks correct. |
| V2b `wcb/resume-builder` do_blocks | Correct — Pro registers the block as `wcb/resume-builder`. |
| V4b register no resume | By-design; apply accepts file upload. Docs claiming a resume transaction are wrong (doc fix only). |
| V5a orphan application | Works — returns `jobRemoved:true` + "Job no longer available"; my walk grep missed the normalized id. |
| V5c bookmark scalar | By-design — non-unique meta rows; 2nd bookmark appends. |
| V6d unread-count 404 | By-design — count rides `GET /notifications` `unread_count`. |

## Cosmetic/doc nits (optional single "polish" card, not verified as bugs)
Find Jobs count-label format (S1), empty company cards (S6), GET /candidates/register 404-vs-405, stale `DESIGN-SPEC.md:207` (bookmark), register-resume doc claim. Expired-job-visible = BY-DESIGN (Deadline Auto-Close opt-in).

## QA-infra (not customer bugs — remediation plan Theme 5)
Seeder `_wcb_company_id` gap (masked BUG-2 in fixtures), seeder role/persona bug (fixed this session), seed `eval-file` fatal, minimal-company seed data.
