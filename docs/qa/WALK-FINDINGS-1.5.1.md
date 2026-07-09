# Walk Findings — WP Career Board (Free + Pro) 1.5.1

Full sequential browser walk of every docs-level walkthrough, combo mode (Free + Pro active),
on `http://wp-career-board.local`. **Issues logged, not fixed** — team reviews all at the end.

- **Env:** Local (vapvarun box), both plugins on branch `1.5.1`, BuddyPress inactive.
- **Personas** (seeded, correct roles): `varundubey` (admin), `employer.figma`/`employer.stripe`,
  `sarah.chen`/`marcus.williams`/`wcbp_p5_candidate`, `morgan_moderator`, `siobhan`.
- **Method per journey:** navigate key steps → DOM snapshot + console-error check → screenshot when a
  visual issue is suspected → record PASS / ISSUE with severity.
- **Severity:** 🔴 blocker (breaks the flow) · 🟠 functional (wrong result/data) · 🟡 UX/visual · 🔵 note.

Legend: ✅ pass · ⚠️ issue(s) found · ⬜ not walked yet.

---

## Free — Job Seeker

| # | Walkthrough | Status | Issues |
|---|---|---|---|
| S1 | browse-search-filter-jobs | ✅ | 🔵 count-label format inconsistency (see detail) |
| S2 | apply-to-a-job | ✅ | Verified earlier: guest/candidate apply → `app #94 submitted`, DB-confirmed. |
| S3 | register-candidate-account | ⚠️ | 🟠 no candidate signup UI · 🟠 no resume created on register · 🔵 GET=404 not 405 |
| S4 | candidate-dashboard | ✅ | account-update UI present (closes S3 deferral) |
| S5 | bookmarks | ✅ | 🔵 job-bookmark meta stored as scalar string not array (verify multi-bookmark append) |
| S6 | companies-directory | ⚠️ | 🟠 expired job shown as "Open Position" + in archive · 🔵 empty company cards |
| S7 | discovery-widgets | ⚠️ | 🟠 featured-jobs/recent-jobs/job-stats blocks render EMPTY on frontend |

## Free — Job Poster

| # | Walkthrough | Status | Issues |
|---|---|---|---|
| P1 | register-employer | ✅ | + refines S3: candidate reg IS on this page but mislabeled |
| P2 | post-a-job | ✅ | Verified earlier: 4-step wizard → `job #95 pending`, DB-confirmed. |
| P3 | manage-applicants | ⚠️ | 🟠 seeder gap: missing `_wcb_company_id` user-meta breaks dash · 🟡 dashboard company-detection inconsistent across surfaces · 🟠 expired job "Active" |
| P4 | edit-job-and-company | ✅ | Edit → `/post-a-job/?edit=102` pre-fills title/desc/board (once P3 seeder gap worked around) |
| P5 | orphan-handling | ◑ | 🟡 An application pointing to a **non-existent** job is silently **omitted** from the candidate applications REST (not shown as "Job Removed"). Inconclusive for the real contract — used a never-existent id, not a *trashed* job (`get_post` handles those differently); needs a trashed-job test. "Orphan job adopted on company create" not tested. No crash (200). |

## Free — Admin

| # | Walkthrough | Status | Issues |
|---|---|---|---|
| A1 | setup-wizard | ✅ | renders; correctly detects "Setup already completed" (idempotent) + Re-run option |
| A2 | settings-tabs | ✅ | renders all GENERAL+PRO tabs; **explains S6/P3**: "Deadline Auto-Close" is OFF by default (opt-in) → expired-job-visible is by-design, downgrade to 🔵 |
| A3 | jobs-and-moderation | ✅ | Jobs list + status filters (All 14/Pub 12/Pending 1/Draft 1) + Flags col; money-path job shows Pending; correct authors. Flagged view conditional (no flags now). |
| A4 | emails (1.5.1) | ✅ | Brand settings + 12 templates w/ editable Subject + Message body (1.5.1) + Enabled + Send test; Email Activity Log shows real SENT sends |
| A5 | applications-and-candidates | ✅ | Applications list All(11) + Export + Export Personal Data; Candidates All(7); no console errors. Admin sees all apps (not seeder-gated). |
| A6 | companies-and-employers | ✅ | Companies All(5) + Employers All(3) render; per-row Ban + bulk Ban action present; no console errors |
| A7 | taxonomies-and-roles | ✅ | Job Categories taxonomy renders (Name/Desc/Slug/Count + Add New); role change via core Users; no console errors |
| A8 | gdpr | ✅ | plugin registers WP privacy exporter + eraser ("WP Career Board"); surfaces in core Tools; "Export Personal Data" link present on Applications |

## Pro — Job Seeker

| # | Walkthrough | Status | Issues |
|---|---|---|---|
| PS1 | resume-builder | ✅ | reachable via candidate-dashboard "My Resumes" (+ New Resume / Upload CV / 1-of-2 Pro limit / Edit/Delete). No standalone page (by design). |
| PS2 | job-alerts | ✅ | reachable via candidate-dashboard "Job Alerts" tab (1 seeded alert). No standalone page. |
| PS3 | job-map (1.5.1) | ⚠️ | 🟠 no page/entry provisioned — job-map block has no page, no map toggle on Find Jobs, CPT has no archive. 1.5.1 "pins narrow to results" not reachable by default. |
| PS4 | notifications-and-pwa | ◑→✅(notif) | Notifications work: `wcb_notifications` table (5 rows/5 unread), `GET /notifications` → 200, bell in dashboard chrome. 🔵 `/notifications/unread-count` route 404 (count likely in the list response). PWA not enabled by default (no `wcbp_pwa*` option) → service-worker/offline not driven; deferred. |

## Pro — Job Poster

| # | Walkthrough | Status | Issues |
|---|---|---|---|
| PP1 | buy-credits | ✅ | Balance panel shows credit count; **buy-credits link gated on purchase URL confirmed** — setting `wcbp_credit_purchase_url` made "Buy Credits ↗" appear in nav, linking to the exact configured URL. Full gateway checkout (external) not driven. |
| PP2 | application-kanban | ◑→✅ | Pipeline renders: List/Board toggle, 5 stage columns (Submitted/Reviewing/Shortlisted/Hired/Rejected) w/ counts + applicant cards. Stage-move REST `POST /applications/{id}/stage {stage_id}` returned 403 in fixtures (seeded stages scoped to "smoke board"; job on a different board) — needs board-aligned setup to confirm persist; not conclusively a bug. 🔵 "2 jobs jobs with applications" duplicated word. |
| PP3 | find-candidates | ✅ | `/find-candidates/` renders: search + skills/experience filters + candidate cards (skills/location/View Resume/bookmark). Shows 4 complete-profile sample resumes (minimal smoke resumes don't surface). |
| PP4 | custom-fields | ✅ | Field-builder field renders on job-form (Categories step: "Walk Group" → field) + **saves** (posted job → value persisted). 🟡 value saved to **postmeta** (`walk_field`), NOT the `wcb_field_values` table (stayed NULL) — that table appears unused for job fields (dual-storage / possible dead table). |

## Pro — Admin

| # | Walkthrough | Status | Issues |
|---|---|---|---|
| PA1 | license-and-setup | ✅ | License Status + Key + Activate render |
| PA2 | ai-settings | ✅ | AI-Powered Features + provider comboboxes (Claude/OpenAI/Ollama) + key setup |
| PA3 | boards | ✅ | Boards table (Main Board, Smoke Board) + Add/Edit/Delete |
| PA4 | credits-admin | ✅ | Credit Settings + Mappings + Detected Providers + Direct Gateways (Stripe/PayPal) |
| PA5 | field-builder | ✅ | Field Builder renders (+ Add Group) |
| PA6 | analytics-csv-feed (1.5.1) | ✅ | 🆕 Analytics renders get_stats (5 views/0.9/11/12) 🔵 one "(untitled)" job in Top Views · CSV Import card + Sample · Job Feed toggle. No console errors on any tab. |

---

## Detailed findings

### S2 — apply-to-a-job ✅
- Archive → single job → Apply Now → Pro resume-picker panel → submit → "✓ Application Submitted".
- DB: `app #94 status=submitted job=85 candidate=2`. UI + REST + DB all green.

### P2 — post-a-job ✅
- 4-step wizard, credits opt-in message, board picker → Post Job → "✓ Job submitted for review".
- DB: `job #95 status=pending author=6` (correct moderation default). UI + create contract + DB green.

### S1 — browse-search-filter-jobs ✅ (as anonymous)
- Archive renders "10 of 12 jobs", search box, 4 filter dropdowns, sidebar facets, grid.
- Keyword "engineer" → narrows to **6 jobs** (URL `?wcb_search=engineer`), correct engineer-only set.
- Sidebar "Full-time" checkbox → narrows to **3 jobs**, "Clear all" button + active filter appear.
- Console: clean (only a harmless 403 on the `wp-login.php?action=logout` resource — logout flow, not the archive).
- 🔵 **Note (cosmetic):** count label switches format when filtered — unfiltered shows "10 of 12 jobs" but filtered shows just "6 jobs" / "3 jobs" (drops the "of N total"). Minor inconsistency; a filtered "6 of 12" would read better. Not a bug.

### S3 — register-candidate-account ⚠️
- ✅ **REST works when enabled:** with `users_can_register=1`, `POST /wcb/v1/candidates/register` → 200, returns `{user_id, dashboard_url}`, creates user with role `wcb_candidate`; duplicate email → 409. Correctly gated: with the WP setting off it returns 403 `wcb_registration_disabled` (expected, not a bug).
- 🟠 **No candidate self-registration UI anywhere.** No `candidate-registration` page (employers have `/employer-registration/` + a nav link); the candidate-dashboard shown to an anonymous visitor offers only "Please sign in… / Sign In" — no Register option. The `/candidates/register` endpoint is unreachable from the plugin front-end. Job seekers (the #1 actor) cannot create an account to apply unless WP's core wp-login registration is enabled/linked. **Asymmetry: employer signup is first-class, candidate signup is REST-only.**
- 🟠 **Register does NOT create a `wcb_resume`.** The walkthrough (and manifest note) claim register is a "WP user + wcb_resume transaction," but no resume post is created for the new user (checked any status). Either the doc overstates or resume-on-register regressed — team to confirm intended behavior. Impact: a freshly-registered candidate has no resume to apply with until they build one.
- 🔵 GET `/candidates/register` → **404** (`rest_no_route`), walkthrough says "expect 405". WP returns 404 for a method-mismatch on a POST-only route; minor journey inaccuracy.
- Account-settings-update half (name/email/password without logout) deferred to S4 (lives in the candidate dashboard; no standalone `/account` page).

### S4 — candidate-dashboard ✅
- Overview renders accurate counts (2 Applications, 0 Shortlisted, 0 Saved, 1 Resume, 1 Alert); left nav My Activity/My Saves/Account; Recent Applications with status badges. No console errors.
- Account Settings tab: Display Name + Email + Save changes + Change Password (current/new/confirm) form renders correctly — this is the account-update surface deferred from S3. Not submitted (to avoid mutating state) but present and well-formed.

### S5 — bookmarks ✅ (job side)
- Job single "Save Job" button toggles to "Saved" on click; persists to `sarah.chen` user meta `_wcb_bookmark`. No console errors.
- 🔵 **Note:** `_wcb_bookmark` stored as a scalar string (`'20'`), not an array — worth confirming that bookmarking a 2nd job appends rather than overwrites (couldn't confirm multi-bookmark in this pass).
- 🔵 Company-bookmark side not independently verified — a direct `POST /wcb/v1/bookmark {type:company}` returned 404 (my route guess was wrong; the real path differs). Job bookmark is the proven half; company bookmark should be re-checked via the company-profile Save control.

### S6 — companies-directory ⚠️
- ✅ Archive: "5 companies found", search, sort, Industry + Company Size filters, cards (logo/desc/industry/size/location/open-count/View Profile/bookmark), grid+list toggle.
- ✅ Single profile (`/companies/smoke-co-alpha/`): hero + Save, About, Open Positions, Similar Companies sidebar, Recent Jobs, "Get Job Alerts" CTA → `/candidate-dashboard/?tab=alerts`.
- 🟠 **Expired job listed as active.** "Smoke Job 5 - EXPIRED" appears under the company's **Open Positions** AND in the Find Jobs archive. Either expired/past-deadline jobs aren't filtered from active listings, or closure is cron-based and the job-expiry cron hadn't run on this fresh seed. Needs confirming: is "open positions" filtered by deadline (should hide immediately) or only after `wcb_check_job_expiry` runs? A job literally titled EXPIRED sitting in Open Positions is a bad look either way. (Cross-ref system journey `job-expiry-cron-closes-old-jobs`.)
- 🔵 The two seeded smoke companies render empty card bodies (no description/industry/size) — seeder minimalism, but the card has no placeholder/empty-state for missing meta.

### S7 — discovery-widgets ⚠️
- 🟠 **featured-jobs / recent-jobs / job-stats blocks output nothing on the frontend.** Placed all three on a real published page (`/walk-discovery-test/`, since no seeded page uses them) and viewed in-browser: the content area was **completely empty** (only the page title + theme sidebar rendered). CLI `do_blocks` also returned empty for each. With 12 jobs (1 featured) seeded, at minimum recent-jobs and job-stats should render data. Caveat: inserted as bare blocks (block.json defaults) — worth double-checking whether they only render with editor-set attributes, but a dynamic block should honor its defaults. **These three "marketing/discovery" blocks appear non-functional as standalone placements.**
- 🔵 `[wcb_widget]` shortcode renders blank with no attributes — expected per its design (it's the `WidgetRegistry` dispatcher needing a widget id, not a discovery widget); not a bug, but the naming invites confusion.

### P1 — register-employer ✅ (+ refines S3)
- ✅ `/employer-registration/` is actually a **dual role-picker** ("Find a Job" / "Hire Talent"). Hire Talent → "Create an Employer Account" form (First/Last, Work Email, Company Name, Website, Industry, Company Size, HQ) — renders fully.
- ✅ REST `/employers/register`: reg off → 403 `wcb_registration_disabled`; reg on → 200 `{user_id, company_id, dashboard_url}`, user role `wcb_employer`, **company auto-created** (#112 "Walk Co").
- 🟠 **S3 refinement (important):** candidate self-registration DOES exist — it's the "Find a Job" card on this same page — but the page (title + slug `employer-registration`) and the ONLY nav link both say **"Employer Registration."** A job seeker will never click "Employer Registration" to sign up, so candidate signup is effectively undiscoverable. Fix is labeling/IA, not a missing feature: rename to a neutral "Register / Join" and/or add a candidate-facing entry (the dashboard "Sign In" screen should also offer "Register").
- 🔵 **Asymmetry confirmed:** employer register bootstraps a `wcb_company`; candidate register does NOT bootstrap a `wcb_resume` (S3). Decide if that's intended.

### P3 — manage-applicants ⚠️ (money-path impacting)
- 🟠 **CONFIRMED ROOT CAUSE — seeder gap (`_wcb_company_id` user-meta), NOT a product bug for real users.** Symptom: seeded employer Figma (user #15, company #97, jobs #102=2 apps / #104=1 app) initially saw "Set up your company profile first," empty My Jobs, "Applications 0," "No Applicants." Root cause: `blocks/employer-dashboard/render.php:53` seeds `state.companyId` from `get_user_meta($uid,'_wcb_company_id')`, and `state.noCompany = !companyId` (view.js:1307) gates My Jobs/Applications, which fetch `/employers/{companyId}/...`. The **register form sets `_wcb_company_id`** (verified: fresh registration set it to 114); the **QA seeder never sets it** (only post-side `post_author` + `_wcb_company_owner`). Setting `employer.figma`'s `_wcb_company_id=97` + a **full reload** → My Jobs lists all 3 jobs with correct applicant counts **2 / 1 / 0 (matching DB)**, nav "Applications 3." So the applicant money-path WORKS for real (registered) employers; only seeded fixtures were broken. **Fix: `bin/seed-qa-fixtures.php` must `update_user_meta($employer,'_wcb_company_id',$company_id)`.** (Note: my mid-walk "disproven" reading was a false alarm — an `#overview`→`#jobs` hash change doesn't re-run render.php, so the stale state persisted; a real reload confirmed the fix.)
- 🟡 **Minor product inconsistency:** the Overview stat cards + "Active Jobs" listed the 3 jobs even *before* `_wcb_company_id` was set (they resolve jobs via the post-side link), while My Jobs/Applications gate on `companyId`. So a company-link-less employer sees a contradictory dashboard (Overview "3 jobs" vs My Jobs "set up company profile"). Edge-case for real users, but company ownership should have one source of truth across surfaces.
- 🟡 "Set Up Company Profile" welcome/onboarding banner still shows even though the employer already owns a company (#97) with 3 live jobs — the banner isn't dismissed when a company exists.
- 🟠 (reinforces S6) "Smoke Job 5 - EXPIRED" appears under **Active Jobs** with a green "live" dot on the employer dashboard too — expired/past-deadline jobs are treated as live until the daily cron runs.

<!-- New findings appended below as each journey is walked. -->
