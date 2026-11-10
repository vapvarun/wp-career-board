# Verification Tracker — WP Career Board 1.5.1

Every issue from the walk ([`WALK-FINDINGS-1.5.1.md`](WALK-FINDINGS-1.5.1.md)) must be **code-confirmed
AND browser-replicated** before it becomes a bug card. No blind cards.

- **Code** = agent read the source, confirmed root cause at file:line.
- **Browser** = reproduced live (or via a faithful CLI/REST proxy where UI isn't the surface).
- **Card** = written to [`BUG-CARDS-1.5.1.md`](BUG-CARDS-1.5.1.md) only when BOTH are ✅ CONFIRMED.

Verdicts: ✅ CONFIRMED · ❌ REFUTED (not a real bug) · ⚙️ BY-DESIGN · ⏳ pending

| ID | Issue | Origin | Code | Browser | Card | Notes |
|---|---|---|---|---|---|---|
| V1 | Candidate registration undiscoverable (labelled "Employer Registration") | S3/P1 | ✅ | ⏳ | — | wizard makes only `employer_registration_page` (setup-wizard.php:411); anon candidate-dash gate = Sign In only (candidate-dashboard/render.php:18-28); no candidate link to `/candidates/register`. Sev: UX/high. Walk already browser-repro'd. |
| V2 | Discovery blocks render empty (featured-jobs/recent-jobs/job-stats) | S7 | ❌ REFUTED | ⏳ | ✗ | **FALSE POSITIVE** — my walk temp-page used `wp:wcb/recent-jobs` (unregistered). Real namespace is `wp-career-board/*`; render.php callbacks are correct (recent-jobs=5, job-stats=counts, featured matches `_wcb_featured='1'`). Browser: place with correct name → should render. No card. |
| V2b | live `do_blocks('wp:wcb/resume-builder')` at render.php:894 | V2 agent | ❌ REFUTED | ❌ REFUTED | ✗ | **NOT a bug** — Pro registers the block as `"name":"wcb/resume-builder"` (blocks/resume-builder/block.json), so the call matches. Real story: Free blocks = `wp-career-board/*`, Pro blocks = `wcb/*` (naming inconsistency, not a defect). Resume flow worked in walk. No card. |
| V3 | Employer dashboard company-detection inconsistent (Overview vs My Jobs/Apps) | P3/P4 | ✅ | ✅(walk) | — | Path A Overview=author query (render.php:94-116, employers-endpoint:622-635, no gate); Path B My Jobs/Apps/banner gate on `noCompany` from `_wcb_company_id` user meta (render.php:53,151; view.js:157,541). No self-heal. Register sets meta (:202). Sev: High. **Repro proven in walk** (figma: company post owned, no user meta → Overview 3 jobs, My Jobs "set up profile"). Fix: single `Company::resolve_for_user()` + backfill. |
| V4a | Job custom-field values — **split storage, inconsistent** (postmeta vs `wcb_field_values`) | PP4 | ✅ | ✅ | ✓ | Code: two write paths (postmeta form-custom-fields:484; table fields-module:321-347) + divergent reads (REST response reads table :307, edit-form reads postmeta :635). **Live browser/CLI: NOT reliable dual-write** — run1 postmeta-only/table NULL, run2 table-only/postmeta empty (REST read table). Stores don't stay in sync → a surface reading the "wrong" store shows empty. Sev: Medium. Repro caveat: use Field-Builder-UI-created field for the authoritative case. → CARD |
| V4b | Candidate register creates no `wcb_resume` | S3 | ⚙️ BY-DESIGN | ✗ | ✗(doc) | Endpoint creates only user (:249-284); `wcb_candidate_registered` has 0 listeners. **Apply NOT blocked** — accepts file upload (`resume_required` :310-314). Docs claiming "user+resume transaction" are wrong. Doc fix, not a customer card. |
| V5a | Orphan application omitted, not shown "Job Removed" | P5 | ❌ REFUTED | ❌ REFUTED | ✗ | **FALSE POSITIVE (my detection error).** Live re-test: orphan app IS returned with `jobRemoved:true`, `jobTitle:"Job no longer available"`. My walk grepped the raw id `999999` which the response normalizes away. Feature works. No card. |
| V5b | Kanban stage-move 403 — **NOT board-scope; ABILITY mismatch** | PP2 | ✅ | ✅(walk) | — | `move_to_stage` needs `wcb/moderate-jobs` (pipeline-endpoint:238-240); `wcb_employer` only has `wcb_view_applications` (class-roles.php:43-54) → employer 403 on EVERY move; pipeline read-only for its target actor. Sev: **High**. Browser 403 seen in walk. Fix: mirror `ApplicationsEndpoint::update_permissions_check` (owner + view-applications). → CARD |
| V5c | Job-bookmark meta stored as scalar (append vs overwrite?) | S5 | ❌ BY-DESIGN | ✗ | ✗ | Intentional non-unique rows: `add_user_meta('_wcb_bookmark',id,false)` (jobs-endpoint:1014); reads `get_user_meta(...,false)`→array; 2nd bookmark appends. `'20'` was a `wp user meta get` read artifact. Stale doc DESIGN-SPEC.md:207 only. No card. |
| V6a | Pro member pages not provisioned; Job Map (1.5.1) unreachable | PS3 | ✅ | ✅(walk) | — | Pro wizard makes only resume_archive + AI-search pages (pro-setup-wizard.php:318-323, resume-module:2283-2330); job-map block registered but never embedded, no Find-Jobs toggle; wcb_resume has_archive=false (candidates-module:109, intentional). Sev: Medium. Walk confirmed no job-map page. → CARD |
| V6b | Analytics "Top Jobs by Views" shows "(untitled)" | PA6 | ✅ | ✅(walk) | — | `top_jobs_by_views()` no JOIN/status filter (analytics-module:189-195); `get_the_title('')`→"(untitled)" (pro-admin:1385); view rows never cleaned on delete. Sev: Low. Walk saw "(untitled)". → CARD |
| V6c | Kanban header "2 jobs jobs with applications" (dup word) | PP2 | ✅ | ✅(walk) | — | view.js:229 concat + strings render.php:274-275 → "job"+"s"+" jobs with applications". Sev: Low. Walk saw it. Fix: strings→" with applications". → CARD |
| V6d | GET /notifications/unread-count → 404 | PS4 | ⚙️ BY-DESIGN | ✗ | ✗ | No such route (only 4 notif routes); count rides `GET /notifications` `unread_count` field (bell-endpoint:166; clients read it). No card. |

## Excluded from card verification (already resolved / not product bugs)
- Seeder `_wcb_company_id` gap, seeder role/persona bug (role bug **fixed this session**), seed `eval-file` fatal, minimal-company seed data — **QA-infra**, tracked in the remediation plan Theme 5, not customer bug cards.
- Count-label format (S1), empty company cards (S6), GET /candidates/register 404-vs-405 (S3) — cosmetic/doc nits; roll into a single "polish" card if desired.
- Expired-job-visible (S6) — BY-DESIGN (Deadline Auto-Close opt-in, off by default).
