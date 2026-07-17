# WP Career Board — Documentation Audit (Free + Pro)

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
