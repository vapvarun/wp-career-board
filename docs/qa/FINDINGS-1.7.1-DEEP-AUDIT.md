# WP Career Board 1.7.1 — Deep QA Findings Log

**Env:** Free+Pro 1.7.1 · `http://jobboard.local` · BuddyPress active · branch `1.7.1`
**Rule:** Collect only. Re-replicate at end, then file **one card per root-cause class** into Basecamp Bugs `9691964821`.

## Status legend
- `CANDIDATE` — suspected defect, needs re-repro before filing
- `CONFIRMED` — code+browser verified
- `BY_DESIGN` / `ENV` — not a bug
- `PASS` — flow OK

---

## F1 Apply to job — PASS
Guest + candidate apply, 409 dedupe, resume required, apply_email REST leak OK, email/URL routing OK.

## F2 Post a job — PASS with CONFIRMED orphan-link defect
- Form loads for employer.figma, no credit nag, free posting OK, multi-step submit OK.
- ENV: `auto_publish_jobs=true` on this site → success copy "Job posted successfully" (walkthrough default is pending). Not a bug.
- Board picker: Smoke Board (default), Main Board.

### CANDIDATE → likely CONFIRMED: C1 Orphan job when user company meta empty at post time
**Symptom:** Browser-posted job `167` (`QA Smoke Senior PHP Developer`) has empty `_wcb_company_id` and does **not** appear in employer My Jobs (`GET /employers/me/jobs` returns company-scoped jobs only). REST post with user meta set correctly links job `168`.
**Root cause class:** `JobsEndpoint` create stamps company only from `get_user_meta(_wcb_company_id)` (`class-jobs-endpoint.php:819-826`). No resolve-from-owned-company + no orphan backfill on job create. `backfill_orphan_jobs` only runs on company create/update (`class-employers-endpoint.php:437-440,592-627`). Once user meta is later set, company-scoped `get_my_jobs` hides still-unlinked jobs (`get_jobs` meta_query on `_wcb_company_id`).
**Coverage (one card):** post-a-job create path + My Jobs list + applications company scope + single-job company panel when meta empty.
**Repro:** delete user meta `_wcb_company_id` while leaving owned `wcb_company`; post job; set/backfill user meta; observe job missing from My Jobs and `_wcb_company_id` still empty on job.

## F3 Browse / search / filter
- Find Jobs shows cards including smoke job; search present.
- Filters selector miss in first pass — check page composition vs missing block (may be ENV).

## F4 Employer dashboard
- Dashboard loads with company, apps count, credits warning (0 balance).
- My Jobs shows company-linked jobs only — job 167 missing (see C1).

## F5 / Pro flows
(pending parallel browser agent + continued checks)


## F3 Browse/search — PASS (filters ENV)
- Archive + keyword search OK; smoke job visible.
- No `wcb-job-filters` block on Find Jobs page composition → ENV/page setup, not product code defect (unless wizard should inject it — check later).

## F4 Employer dashboard / status — PASS (+ C1 impact)
- Dashboard, My Jobs (company-scoped), Applications counts OK when company meta set.
- Status change REST `POST /applications/{id}/status` → 200 (`submitted`→`reviewing`) as employer.figma.
- C1 causes newly posted orphan jobs to vanish from My Jobs.

## F5 Admin Free — PASS
- Settings, Jobs, Applications, Companies, Candidates menus load.
- Emails live under Settings `?tab=emails` (templates + activity log) — wrong legacy URLs 403 (not a bug).
- Candidate dashboard for sarah.chen OK (apps, resumes, alerts, notifications).

## Pro REST smoke
- Credits balance 200 `{balance:0,ledger:[]}`.
- Alerts 200 (sarah has 1 alert).
- Notifications 200 (status-change + apply events).
- Resumes list 200.
- Kanban GET 200; stage move PUT 200.
- Pipeline write gate fixed to job-owner + `wcb/view-applications` (prior 1.5.1 BUG-3 appears fixed).

### CANDIDATE C2: New applications never land on Kanban (no default stage)
**Symptom:** Apps without `_wcb_stage_id` (normal after Free apply) do not appear in any Kanban column. Job 151 had apps but Applied column total=0 until we manually `PUT .../stage`. After move, Shortlisted shows the card.
**Root cause:** Kanban queries exact `_wcb_stage_id` (`class-pipeline-endpoint.php:251`). No listener on `wcb_application_submitted` assigns the board's first stage. `PipelineModule` only syncs terminal stages on `wcbp_application_stage_changed`.
**Coverage (one card):** apply → employer kanban empty; pipeline board view; ATS first-column expectation.
**Repro:** apply to job as candidate → employer opens kanban for that job → Applied column empty despite applications list showing the app.
**Re-verified 2026-07-27:** DB shows 10 of 14 live applications have no `_wcb_stage_id`.

## Contract-audit wave (2026-07-27, post 1.7.1 bump) — CONFIRMED C3–C5

Source: `wp-contract-audit` pair scan (0 errors, 31 warnings triaged; company meta-box fields, admin.js `activate`, RTL `.wcb-wizard-step`, candidate-dashboard `_wcb_location` legacy fallback all verified FALSE POSITIVES).

### CONFIRMED C3: Application custom-field answers are collected, saved, and never shown to anyone
- No feeder: `wcb_application_form_fields_groups` has zero `add_filter` in Free or Pro. Pro FieldsModule hooks only `wcb_job/company/candidate/resume_form_fields` (`class-fields-module.php:31-34`) — Field Builder cannot put questions on the apply form; only site code can.
- Write side works: job-single renders questions + POSTs `custom_fields[<key>]` (`blocks/job-single/view.js:296-300`), endpoint persists `_wcb_application_field_<key>` + `_wcb_application_custom_fields` (`class-applications-endpoint.php:358-362`).
- Read side absent: zero `get_post_meta` of either key in both plugins. `prepare_for_candidate/employer/admin` (`class-applications-endpoint.php:994/1037/1055`) omit them; admin detail widgets (applicant-card/cover-letter/resume-preview/status-timeline) read only candidate/guest/job meta; pipeline kanban and emails read nothing either.
- Extra: endpoint reads `$_POST['custom_fields']` directly (`:328`) — JSON REST clients' answers are silently dropped (multipart only).
- **Replicated live 2026-07-27:** temp mu-plugin fed one question → question rendered on job 167 apply form → applied as test candidate with answer "Two weeks" → meta saved → admin `GET /wcb/v1/applications/{id}` envelope contains no custom-field data. Fixtures cleaned up after.

### CONFIRMED C4: Resume Map block can never show a pin (dead feature) + false empty-state copy + dead toolbar
- No writer: `_wcb_lat/_wcb_lng` written only by `MapsModule::geocode_job()` via `wcb_job_created`/`wcb_job_imported` (`class-maps-module.php:38,42,98-99`); no resume lifecycle hook exists in either plugin. Live DB: 7 published resumes, 0 with `_wcb_lat`.
- Wrong keys: pin title/location read `_wcb_candidate_title` + post-meta `_wcb_location` (`blocks/resume-map/render.php:48-49`) — WPJM-importer-only keys; native resumes use `_wcb_resume_headline`/`_wcb_resume_location`.
- False copy: empty state (`render.php:246`) promises auto-geocoding on save + a Resumes-admin backfill — neither exists.
- Dead toolbar: search toolbar renders outside the empty-pins guard (unlike job-map) → user can search and get "No candidates found in that area. Try a wider search radius." over a guaranteed-empty dataset (`view.js:315,379-385`). SSR-verified on live page 2026-07-27. Toolbar also uses raw `⌖` glyph instead of Lucide (job-map uses `Icon::svg`).
- Surfaced via inserter, `[wcbp_resume_map]`, fullwidth templates; docs instruct owners to place it (`docs/website/pro-blocks/01-blocks-reference.md:45,270`).

### CONFIRMED C5: Job geocoding only covers REST-created jobs + `wcb_job_created` consumer fatals on non-REST firing
- `wcb_job_created` fires ONLY from REST create (`class-jobs-endpoint.php:871`); wp-admin job save (`class-admin-meta-boxes.php` — zero `do_action`) and the setup-wizard sample seeder never geocode. Live DB: 14 published jobs, only 1 (front-end-posted job 167) has `_wcb_lat`.
- Job-map empty-state copy (`blocks/job-map/render.php:102`) claims "new jobs get geocoded automatically when an admin saves them" — the admin path is exactly the one that never geocodes; the claimed backfill tool doesn't exist either.
- SSR/REST inconsistency: SSR pin location reads importer-only `_wcb_location_text` (`render.php:41`) while the REST refresh path uses taxonomy-derived `job.location` (`view.js:346` ← `class-jobs-endpoint.php:1507`) → pin city line appears only after a visitor runs a location search.
- Blocker for the natural fix: `FieldsModule::save_field_values( int, ?WP_REST_Request )` is hooked to `wcb_job_created` (`class-fields-module.php:35,343`) — firing the hook without a request object fatals (TypeError, replicated live 2026-07-27). Fix must relax this signature before admin-save can fire the hook.
- Minor: `MapsModule::enqueue_map_assets()` gates on `has_block( 'wp-career-board/job-map' )` (`class-maps-module.php:110`) — registered name is `wcb/job-map`; harmless today only because render.php enqueues directly.


## Pro UI smoke — PASS (where pages exist)
- Find Candidates, credit-balance, job-map (qa page), employer Board layout, status change, alerts/notifications REST OK.
- Dedicated `/resume-builder`, `/job-map`, `/job-alerts` slugs 404 on this site — **ENV** (Pro wizard page provisioning not fully run). Not filed; blocks work when placed on a page.

## Not filed (by design / ENV / already fixed)
- `auto_publish_jobs=true` on this site (moderation default OFF in product; site setting).
- Find Jobs missing filter block (page composition).
- Legacy `/wp-admin/.../wcb-emails` 403 (emails live under Settings → Emails).
- Prior 1.5.1 Kanban 403 write-gate — fixed in 1.7.1 (job-owner + view-applications).

## Filed to Basecamp Bugs (re-replicated)

| ID | Card | URL |
|---|---|---|
| C1 | Post-a-job orphans company link… | https://app.basecamp.com/5798509/buckets/46502739/card_tables/cards/10134657106 |
| C2 | New applications never appear on Kanban… | https://app.basecamp.com/5798509/buckets/46502739/card_tables/cards/10134657558 |
| C3 | Application custom-field answers saved but never shown to anyone… | https://app.basecamp.com/5798509/buckets/46502739/card_tables/cards/10134659689 |
| C4 | Resume Map block can never show a pin (no resume geocoding, wrong keys, misleading empty state)… | https://app.basecamp.com/5798509/buckets/46502739/card_tables/cards/10134662279 |
| C5 | Job geocoding only covers REST-created jobs + wcb_job_created consumer fatal… | https://app.basecamp.com/5798509/buckets/46502739/card_tables/cards/10134662318 |


## Pro subagent triage (follow-up)
Re-checked P1-01 / P2-02 / P3-02 from the parallel Pro walk — **not filed**:
- **P1-01 Buy more empty href:** by design — link is `wcb-hidden` / `display:none` when `wcb_credit_purchase_url` is empty (`employer-dashboard/render.php:561`); purchase path works on `/wcb-credit-test/`.
- **P2-02 Edit → BP profile:** false positive — WCB My Resumes Edit opens `#resume-builder`; BP “Edit Profile” is admin-bar chrome.
- **P3-02 job_id deep link:** works — `?job_id=151#applications` marks `.wcb-apps-job-item.wcb-active`.
- Remaining subagent notes (404 slugs, drag inconclusive, 403 during autologin) stay ENV/low/inconclusive.

