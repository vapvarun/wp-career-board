# WP Career Board (Free) - Documentation Audit

## Re-audit 2026-10-13 (1.8.1)

Scope: the Free plugin, branch 1.8.1. Every finding of the July audit (written against 1.7.0) was re-checked against the 1.8.1 source. Pro-owned findings are listed for completeness and left to the Pro audit. Sources used: `wp eval` route dump (`rest_get_server()->get_routes()` with Pro skipped), a scan of every `do_action` / `apply_filters` call, `SettingsSchema::fields()`, `add_shortcode` and `block.json`, every `new WP_Error( 'wcb_...' )`, the WP-CLI command classes, `Roles` and `Abilities`.

### Status of the original findings

| ID | Finding | Status | Evidence |
|---|---|---|---|
| A1 | Registration page 404 (`/register/` vs `/employer-registration/`) | Fixed in code | `admin/class-pages.php:90` sets slug `employer-registration`; the wizard and Settings both create pages from `Pages::definitions()` |
| A2 | "Missing pages" banner printed a raw slug | Fixed in code | `admin/class-admin-settings.php:591` maps each key to its page title |
| A3 | Guest to account auto-link had no implementation | Fixed in code, docs corrected in this pass | `modules/applications/class-applications-module.php:37-43,65-90` claims guest applications after the email is proven (`wcb_email_verified`, `after_password_reset`, or an admin/WP-CLI created account) and fires `wcb_guest_applications_claimed`. The customer docs still said "when the account is created"; corrected in `for-candidates/09-guest-apply.md`, both FAQ pages, the overview and `troubleshooting/02-jobs-and-applications.md` (also removed the stale "up to 200" limit: the claim now pages through all matches) |
| A4 | Employer Overview "Recent Applications" empty without a company | Fixed in code | `blocks/employer-dashboard/view.js:731-733` falls back to `/employers/me/applications` |
| A5 | Member block was REST-only | Fixed in code | `modules/jobs/class-jobs-module.php:88,109` (listing query and single job 404), `modules/candidates/class-candidates-module.php:312,401`, plus the REST paths `jobs-endpoint.php:229,491` |
| B1a | Field Builder overview inverted | Out of scope (Pro doc) | Pro audit |
| B1b | Pipeline stage doc wrong UI | Out of scope (Pro doc) | Pro audit |
| B1c | Browse page named three ways | Fixed in docs | All Free docs say **Find Jobs** / `/find-jobs/`; `01-settings.md` explains why |
| B1d | Free candidate docs implied a Pro archive | Fixed in docs | **Find Candidates** is marked Pro in `for-candidates/06`, `10`, the Pages table and the tutorial |
| B1e | Employer tutorial oversold welcome email | Fixed in docs | `tutorials/02-employer-end-to-end.md:14` and `troubleshooting/03-emails-and-accounts.md:16-20` match `class-email-welcome.php:93` |
| B2a | "What's New" frozen at 1.4.6 | Fixed in docs | Added a 1.8.1 section to `getting-started/07-whats-new.md`; `readme.txt` has blocks for 1.5.0 to 1.8.1 and Stable tag 1.8.1 |
| B2b | 1.7.0 wave undocumented (app, deletion, report/block) | Fixed in docs | Mobile App tab and Privacy tab in `01-settings.md`; REST sections in `03-rest-api.md`; member hooks in the hooks reference; deletion in `04-gdpr.md`, `for-candidates/11`. Push is Pro/app and belongs to the Pro audit |
| B2c | Analytics tab, translations, idempotent CSV import missing; stale 1.4.3 stamps | Fixed in docs (Free part) | Analytics is a Pro tab, listed in `01-settings.md`; `tutorials/07-multi-language-board.md`; CSV import is Pro (`pro-features/07-migration.md`). No `1.4.x` stamps remain in `docs/website` |
| B3a | Jargon on first screens | Fixed in docs | Last two instances (`CPT`, `Interactivity API`) rewritten in `getting-started/01` and `02` |
| B3b | Missing screenshots | Still open | See "Remaining gaps" |
| B3c | Duplicate `04-` prefix; missing FAQ entries | Fixed in docs | `getting-started/` runs 01 to 07 once each; `faq/01-site-owners.md` answers the missing-pages, registration URL and wizard-or-settings questions |
| C1 | Stale counts ("41 routes", "108 hooks") | Fixed in docs | No count claims remain in `docs/website`; `03-rest-api.md` now states 56 routes (80 route and method pairs); `ARCHITECTURE.md` corrected |
| C2a | Cookbook email recipe fatal (abstract methods, `wcb_candidate_registered` arity) | Fixed in docs | Recipe now defines the four abstract methods, adds `boot()`, and hooks `wcb_candidate_registered` with 1 argument (`class-abstract-email.php:49-119`; `class-candidates-endpoint.php:171`) |
| C2b | Cookbook apply-form recipe silently dropped values | Fixed in docs | Uses `wcb_application_form_fields_groups` with the list-of-groups schema |
| C2c | Phantom REST rows (`GET /employers`, POST toggles) | Fixed in docs | `03-rest-api.md` states there is no `GET /employers` list and the toggles are `POST /jobs\|companies/{id}/bookmark` |
| C2d | Phantom hook `wcb_theme_primary_color`; SDK-dispatched `wcb_featured_upgrade_*` | Fixed in docs | No occurrence in `docs/website` or `HOOKS.md`; the real filter is `wcb_theme_accent_primary` |
| C2e | Pro shortcode phantoms, Pro hook signatures, Pro `SHORTCODES.md` claims | Out of scope (Pro doc) | Pro audit |
| C3a | Pro REST undocumented | Out of scope (Pro doc) | Pro audit |
| C3b | 1.7.0 Free routes missing | Fixed in docs | All 56 Free routes are in `03-rest-api.md` |
| C3c | High-demand Free hooks undocumented | Fixed in docs | All 183 Free `wcb_*` hooks are in `02-hooks-reference.md` (two, `wcb_candidate_resume_url` and `wcb_job_pipeline_url`, were added in this pass) |
| C3d | Pro hooks 44% | Out of scope (Pro doc) | Pro audit |
| C3e | No template-override documentation | Fixed in docs | `developer-guide/06-template-overrides.md` |
| C4a | Mobile-API doc is a future-tense plan | Partly fixed | The shipped contract is in `03-rest-api.md` (auth, `app-config`, account, deletion, members) and the generated `docs/api/openapi.json`. `docs/plans/app/WCB-1.7.0-MOBILE-API.md` is a planning record and stays as history. Push request bodies are Pro |
| C4b | Hooks reference name-only, no args | Fixed in docs | Every hook row with arguments lists them, and every stated argument list matches the call site (0 mismatches in a scripted check) |
| C4c | Free to Pro money seam undocumented | Still open | Pro's `hold_on` / `deduct_on` / `refund_on` wiring is in the Pro plugin. The Free side (`wcb_job_payment`, events `create`, `resubmit`, `republish`, `board_change`, `feature`) is documented in the hooks reference and `HOOKS.md` |
| C4d | Missing high-frequency recipes | Fixed in docs | `05-extension-cookbook.md` now has jobs query, status change, wizard step, email template override, plus three new ones (resume link, default deadline, term order) |

### Defects found in this pass (docs that were confidently wrong)

| Where | Was | Is |
|---|---|---|
| Guest-apply, both FAQ pages, overview, troubleshooting | Applications link "when the account is created" and "up to 200" | Linked only after the email is proven; no 200 cap (`class-applications-module.php:37-43,65-90`) |
| `docs/SHORTCODES.md` | Attributes `placeholder`, `submitLabel` (search), `headline`, `subheadline`, `bgImage` (hero), `boardId`, `showLocation`, `showType`, `showCategory` (filters), `redirect`, `successMessage` (registration), `orderBy` (company archive), `boardId` (stats), `limit`, `boardId` (recent and featured) | None of these exist. Real attributes come from `block.json` and `render.php`; three shortcodes were missing from the table |
| `docs/SHORTCODES.md` | `[wcb_registration]` "creates a wcb_employer user"; stats include "hires"; employer dashboard needs `wcb_post_jobs` | Role picker for candidate or employer; stats are jobs, companies, candidates; the dashboard checks `wcb/manage-company` (`blocks/employer-dashboard/render.php:13`) |
| `docs/CLI.md` | Ability slugs `wcb_moderate_jobs` etc. as the required abilities; `run-expiry` "Respects Settings > Job Listings"; no migrate or scale commands | Abilities are `wcb/moderate-jobs`, `wcb/manage-settings`, `wcb/view-applications` (`cli/class-job-commands.php:152,212,275,334`); there is no Job Listings tab; 7 commands were missing |
| `docs/HOOKS.md` | Field types list stopped at `checkbox`; wizard route line numbers and routes stale | `radio`, `multiselect`, `repeater` are supported (`core/class-form-custom-fields.php:24,457`); wizard routes are `admin/class-setup-wizard.php:258-311` |
| `docs/ARCHITECTURE.md` | 7 REST endpoint files, `/companies` "CRUD", `/import` "CSV/JSON", `wp_is_authorized()`, 14 blocks | 13 endpoint files, `GET /companies` only, WP Job Manager import, `wp_is_ability_granted()`, 17 blocks |
| `readme.txt` FAQ | Migrate at "Career Board > Import" | **Career Board > Settings > Import** (there is no top-level Import menu) |
| `admin-guide/01-settings.md` | A garbled sentence under **Default listing length** ("Not a switch.") | Rewritten to say what happens at the deadline and when the button shows |
| `troubleshooting/02` | Button under "Settings > Jobs > When a job ends" | There is no such heading; the button sits beside **Default listing length (days)** |
| `for-candidates/02-finding-jobs.md` | Load more link `/jobs/page/2/` | `/find-jobs/page/2/` on the Find Jobs page; `/jobs/page/2/` only on the jobs archive |

### Measured coverage (Free)

| Surface | In code | Documented before | Documented after |
|---|---|---|---|
| REST routes | 56 routes, 80 route+method pairs | 56 / 56 (100%); `PATCH` and `POST` aliases of `PUT` rows not stated | 56 / 56 (100%), aliases stated; OpenAPI spec regenerated for 1.8.1 |
| Hooks (`wcb_*` fired by Free) | 183 (60 actions, 123 filters) | 181 / 183 (99%) | 183 / 183 (100%); 0 argument-count mismatches |
| Cron events | 11 | 5 / 11 (45%) | 11 / 11 (100%) |
| Settings keys (`SettingsSchema`) | 50 | 50 / 50 (100%) by key or label | 50 / 50 |
| Blocks | 17 | 17 / 17 | 17 / 17 |
| Shortcodes | 19 tags | `docs/website` 19 / 19; `docs/SHORTCODES.md` 16 / 19 with 9 wrong attribute rows | 19 / 19, attribute rows match `block.json` |
| WP-CLI subcommands | 16 | `docs/website` 16 / 16; `docs/CLI.md` 9 / 16 | 16 / 16 in both (CLI.md points to the full tables) |
| Abilities | 12 | 12 / 12 | 12 / 12 |
| Capabilities and roles | 13 caps, 3 roles | 13 / 13, 3 / 3 | 13 / 13, 3 / 3 |
| Error codes (`WP_Error` with a `wcb_` code) | 84 | 10 / 84 (12%) | 84 / 84 (100%) in the new `07-error-codes.md` |
| 1.8.1 additions (the 11 customer and developer items in the release notes) | 11 | 6 / 11 | 11 / 11 (see below) |

The 11 items: `JobDeadline::default_end_for_board()` and `JobDeadline::cap()` (HOOKS.md, cookbook); `wcb_candidate_resume_url` (hooks reference, HOOKS.md, cookbook); Order field on taxonomy terms (`admin-guide/19-job-terms.md`, HOOKS.md, cookbook); **No expiry** and **Apply URL / Apply Email** in the admin editor (`admin-guide/20-edit-a-job-in-admin.md`); employer-chosen deadline cap (`for-employers/02-post-a-job.md`, `01-settings.md`); guest-has-account sign-in (REST, FAQ, guest-apply); private profile 404 (REST); documents download as attachments (`04-gdpr.md`, `13-application-editor.md`); Load More pages (`02-finding-jobs.md`); 12 per page default (`01-settings.md`); also added: the View resume links (`13-application-editor.md`, `20-edit-a-job-in-admin.md`) and nested categories with the category search box (`02-finding-jobs.md`, `19-job-terms.md`).

### Remaining gaps

- Screenshots (none captured in this pass). Needed: Order field on a term screen; the **Job Details** box with **No expiry** ticked; **View resume** on the Applications list; the employer job form deadline field; the guest sign-in prompt; the CSV export buttons (`for-employers/09`) and the guest apply flow (`for-candidates/09`). The Pro flows named in B3b (job alerts, credit spend) belong to the Pro audit.
- Free to Pro money seam (C4c): the Pro half of the hold, deduct and refund wiring cannot be documented from the Free repo.
- Push notifications and the app license gate: Pro and app repo.
- Per-endpoint response examples: `docs/api/openapi.json` carries parameters and schemas for all 80 operations, but `03-rest-api.md` still shows an example body only for the error envelope, the list envelope and `app-password`. Adding example bodies for the 10 most used routes is the next step.
- `docs/ARCHITECTURE.md` is still a 1.1.0 snapshot apart from the corrected endpoint list and counts; a full rewrite belongs with the next `/codebase-architect` run.
- Customer pages for member report and block exist only inside `16-content-filtering.md`; the feature is shown in the app, so a dedicated page waits for the app screens.

---

## Original audit (July 2026, written against 1.7.0) - kept for history

### WP Career Board — Documentation Audit (Free + Pro)

> Two-lens audit: **customer-friendliness** (can a non-technical owner/user succeed?) and
> **developer coverage** (can a dev/integrator do what they try to, correctly, from docs alone?).
> Every finding is code-verified. Branch: 1.7.0. Audit date: 2026-07-17.
> Legend — 🔴 P1 (breaks/misleads), 🟡 P2 (stale/incomplete), ⚪ P3 (polish).

---

## A. Real PRODUCT BUGS surfaced by the docs audit (engineering, not docs)

These are code defects the audit exposed while checking doc accuracy. Fix in code, not just docs.

1. 🔴 **Registration page 404** — setup wizard creates the page as `/register/` (no `post_name`), but every doc/UI path sends users to `/employer-registration/`. Broken on the single most important onboarding action. Fix: `admin/class-setup-wizard.php:473` add `post_name => 'employer-registration'`, OR correct all docs to `/register/`.
2. 🔴 **"Missing pages" banner prints a raw slug** — label map omits the `employer_registration_page` key, so the Settings banner renders the raw token. `admin/class-admin-settings.php:730-736`.
3. 🔴 **Guest→account auto-link claim has no implementation** — `for-candidates/09-guest-apply.md` promises guest applications link to a later account by email; there is no `user_register` claim routine; guest apps are `post_author=0` and My Applications queries by author. Decide: build the routine, or delete the promise + add honest troubleshooting.
4. 🟡 **Employer Overview empty-widget** — stat shows N but "Recent Applications" is empty when no company profile exists (`employer-dashboard/view.js:666` gates the fetch on `companyId`). (Also tracked in QA.)
5. 🟡 **Member-block is REST-only** — SSR frontend (`blocks/job-listings/render.php`, single job permalink) does not filter blocked authors; only the REST endpoint does (`core/class-blocks.php`, `jobs-endpoint.php:312,644`). Any future "blocking" doc must not claim it hides web content.

---

## B. CUSTOMER lens

### B1. Docs describe a product that no longer ships (accuracy) 🔴
- **Field Builder overview inverted** — describes "per-board, pick a board first"; real UI is **global fields** (board 0) + per-group "Hide on boards". Confirmed stale by the plugin's own 1.4.6 changelog. `pro docs/website/field-builder/01-overview.md`.
- **Pipeline-stage doc wrong UI** — `pro .../application-pipeline/02-configure-stages.md` describes a board "edit screen" + Terminal/Terminal-outcome fields; real UI is the inline **"Manage" panel** (label + color + reorder + delete) on the Boards list row.
- **Browse page named 3 ways** — docs say "Jobs" / `/jobs/`; real page is **"Find Jobs" / `/find-jobs/`** (`admin/class-pages.php:46`). Files: `for-candidates/02-finding-jobs.md`, `08-salary-filter.md`, `09-guest-apply.md`.
- **Free candidate docs imply a Pro-only archive** — `for-candidates/10-troubleshooting.md`, `11-profile-and-account.md` say the profile appears in a candidate archive; that's **Find Resumes (Pro)**. Gate behind Pro.
- **Employer tutorial oversells welcome/verification/magic-link email** the plugin doesn't send. `tutorials/02-employer-end-to-end.md:36,39` vs correct statements elsewhere.

### B2. Staleness & undocumented 1.7.0 🟡
- **Both "What's New" frozen at 1.4.6** (`getting-started/06-whats-new.md`, `pro .../getting-started/04-whats-new.md`); `readme.txt` has **no `= 1.7.0 =` changelog block** and Stable tag still `1.6.0`.
- **Entire 1.7.0 wave undocumented for customers**: mobile app + Application-Passwords enablement, native push (`pwa` doc still calls push "future"), in-app account deletion, member report/block, the app license gate.
- Missing even at 1.6.0: **Analytics dashboard tab** (also absent from settings index), translations, idempotent CSV import. Version stamps stuck at `1.4.3` across integration/field-builder docs.

### B3. Clarity & structure (friendliness) ⚪
- **Jargon on first screens** — `CPT`, `taxonomy`, `Interactivity API`, `jQuery`, raw filter names in getting-started/intro; dev REST examples inside `for-candidates/08-salary-filter.md`.
- **Missing screenshots** on multi-step flows (job alerts, credit spend, CSV export, guest apply).
- **Structure** — duplicate `04-` prefix in getting-started; no FAQ for the 3 questions a fresh installer asks ("Missing-pages banner?", "registration URL?", "wizard or Settings?").

---

## C. DEVELOPER lens

### C1. Measured coverage
| Surface | In code | Documented | Coverage |
|---|---|---|---|
| Free REST | 46 | ~41 | header says "41" (stale) |
| Pro REST | ~34 | 0 | **no Pro REST reference** |
| Free hooks | 133 | 103 | ~77% |
| Pro hooks | 55 | 24 | ~44% |
| WP-CLI | 5 groups | 5 | ✅ |
| Free shortcodes | 19 | 19 | ✅ |
| Pro shortcodes | 16 | 16 (4 defects) | ❌ |

Stale headers: hooks docs claim "108 unique" (real 133 Free / 183 combined); REST doc claims "41 routes".

### C2. A dev following the docs hits a fatal / 404 🔴
- **Cookbook email recipe fatals twice** — `05-extension-cookbook.md:170-225` omits required abstract methods `get_default_body()` (`class-abstract-email.php:62`) + `get_merge_tags()` (`:70`), AND registers `wcb_candidate_registered` with 2 args when it fires 1 (`class-candidates-endpoint.php:172,272`). Also fix the class's stale docblock (`:18-21`).
- **Cookbook apply-form recipe silently drops values** — `05-extension-cookbook.md:22-34` uses assoc schema + hand-rendered `<input>`; endpoint reads list-of-groups `['key']`/`['type']` from `$_POST['custom_fields']` (`class-applications-endpoint.php:333-360`). Use canonical schema from `admin-guide/12-custom-fields.md`.
- **Phantom REST**: `GET /employers` is actually `POST` create (`class-employers-endpoint.php:77`); `/candidates/{id}/{bookmarks,saved-companies,saved-resumes}` are GET-only but documented "POST toggle" → 405. Toggles are `POST /jobs|companies/{id}/bookmark`.
- **Phantom hook** `wcb_theme_primary_color` (`02-hooks-reference.md:201`, zero occurrences). **`wcb_featured_upgrade_*`** are SDK-dispatched, not fired by Pro — relabel.
- **Phantom shortcode** `[wcbp_board_switcher]` (no block dir); **missing** `[wcbp_resume_form]`. Pro `SHORTCODES.md`.
- **Wrong signatures**: `wcbp_send_alert_email` fires 2 args `($alert,$job_ids)` not documented 3; `wcbp_credits_topped_up` 3rd arg is `$note` not `$source`.
- **False claims** in Pro `SHORTCODES.md`: the "these Pro blocks have no shortcode" section (`:157-172`) — all 8 ARE shortcodes; the "requires valid Pro license" gating claim (`:10-11`) — no license check on `init` (contradicts license-gates-updates-only). Also `ai-features/05:283` "no shortcode wrapper" but `[wcbp_ai_chat_search]` is registered.

### C3. Whole surfaces undocumented 🔴
- **Pro REST API** — 0 of ~34 routes (resumes, alerts, notifications, AI, pipeline/kanban, boards, fields, credits, geocode, push).
- **1.7.0 Free routes** — `DELETE /me`, `GET|DELETE /me/deletion`, `/users/{id}/report`, `/users/{id}/block`, `/me/blocked` absent from `03-rest-api.md`.
- **High-demand Free hooks** — `wcb_member_suspended`/`_unsuspended`/`_reported`/`_blocked` (the 1.7.0 advertised hooks, zero built-in listeners = clean extension points), `wcb_account_deletion_*`, `wcb_before/after_card_footer`, `wcb_job_listings_filters_top/bottom`, `wcb_company_sidebar_*`, `wcb_wizard_steps`/`_required_pages`, `wcb_email_template_dirs`.
- **Pro hooks (44%)** — `wcbp_application_stage_changed`, fields CRUD events, AI-output filters, 8 resume-shaping filters, `wcbp_open_to_work_*` / `wcbp_my_applications_table_*` (in CLAUDE.md changelog, not HOOKS.md), `wcb_job_imported`.
- **No general template-override mechanism** (structural) — front-end is blocks (`render.php`); only emails are theme-overridable (`{theme}/wp-career-board/emails/{id}.php` + `wcb_email_template_dirs`). Document the reality: copy the block `render.php`.

### C4. Structural / usability 🟡
- **Mobile-API doc is a plan in future tense** though everything shipped — no per-endpoint response schemas, no `Authorization: Basic` example, no push request body; app-config JSON is a subset of the real response.
- **Hooks reference is name-only** for ~100 hooks and tells devs to grep for signatures; only ~8 hooks have a runnable example. `HOOKS.md` (the good doc) covers ~25.
- **Free→Pro money seam undocumented** — Pro's Credits SDK wires `hold_on=wcb_job_created`, `deduct_on=wcb_job_approved`, `refund_on=wcb_job_rejected` (`wp-career-board-pro.php:85-87`). Overriding job approval moves money; no doc says so. Notification-bell seam similarly undocumented for third parties.
- **Missing high-frequency recipes**: filter jobs query (`wcb_job_listings_query_args`), react to `wcb_application_status_changed`, extend the admin setup wizard (`wcb_wizard_steps`), email-template theme override.

---

## D. Prioritized action plan

### Do first (breakage / trust)
1. File & fix the 5 product bugs in §A (registration 404, raw-slug banner, guest auto-link claim decision, employer Overview widget, SSR member-block).
2. Fix the 3 broken cookbook recipes (§C2) — 2 fatal, 1 silent-fail.
3. Fix phantom/wrong REST rows + phantom/missing/false shortcode entries (§C2).

### Do next (staleness / discoverability)
4. Add `= 1.7.0 =` readme changelog + rewrite both "What's New" (1.5.0→1.7.0).
5. Document the 1.7.0 customer surfaces (mobile app + enablement, push, account deletion, block/report) or mark app-only.
6. Correct drifted docs: Field Builder global model, pipeline Manage panel, Find Jobs naming, Pro-gate candidate archive, welcome-email claim.

### Do after (coverage / completeness)
7. Create a Pro REST reference; add the 5 undocumented 1.7.0 Free routes; add per-endpoint request/response examples + error-code table; convert the mobile-API doc to a shipped reference.
8. Document the high-demand hooks (§C3), fix signatures, kill phantoms, add the Free→Pro seam table, correct stale counts, and put args in the hook tables.
9. Add the missing cookbook recipes (§C4) and state the template-override reality.

### Polish
10. De-jargon the first two customer screens; add missing screenshots + the 3 FAQ entries; fix the duplicate `04-` prefix.
