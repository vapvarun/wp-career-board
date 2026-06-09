# Agent Smoke Runbook — WP Career Board

**Audience:** a browser-capable agent (Claude Sonnet or equivalent) with Playwright MCP + WP-CLI Bash access, OR a human QA person with the same access. Both should be able to execute every step of this runbook.

## How to read this runbook

Each C and E step describes a **customer contract**: what the feature promises, why it matters, the surfaces it touches, and what "working" looks like in customer terms. It does NOT prescribe the exact Playwright calls, selectors, REST paths, or DB queries. Read the relevant plugin code, pick the right mechanism, and verify the contract. This freedom is the point: the verifier is expected to notice bugs we did not pre-imagine.

D (regression guards) stays specific — those are repros of past incidents; the exact fixture IS the contract.

Infrastructure sections (preconditions, output contract, debug-log protocol, fixture cleanup, failure protocol) stay specific because they are the stable machinery the walk rides on.

## Global preconditions

- Working directory: `/Users/varundubey/Local Sites/jobboard/app/public`
- Site URL: `http://jobboard.local`
- WP-CLI: `wp --path="$WP_PATH" <cmd>` where `WP_PATH=/Users/varundubey/Local Sites/jobboard/app/public`
- Admin auto-login: `?autologin=1` on any front-end URL
- Per-user auto-login: `?autologin=<user_login>`
- Playwright: one Chromium session throughout; restart with `browser_close` + `browser_navigate` if it dies.
- Plugin version constant: `WCB_VERSION` (defined in `wp-career-board.php`)
- Pair plugin: `wp-career-board-pro` (constant `WCBP_VERSION`) — must be lockstep with free
- REST namespace: `wcb/v1`
- Free namespace: `WCB\` — Pro namespace: `WCB\Pro\`

## Output contract

At the end of the walk, write exactly one JSON file to
`wp-content/plugins/wp-career-board/docs/qa/.last-smoke-pass.json` (combo or default mode)
or `.last-smoke-pass-free.json` (free-only mode):

```json
{
  "mode": "free|combo",
  "release_version": "<from WCB_VERSION>",
  "pro_version": "<from WCBP_VERSION when combo>",
  "ran_at": "<ISO 8601 UTC>",
  "sections": {
    "A_fresh_install":     { "pass": N, "fail": N, "skipped": N },
    "B_upgrade":           { "pass": N, "fail": N, "skipped": N },
    "C_core_flows":        { "pass": N, "fail": N, "skipped": N },
    "D_regression_guards": { "pass": N, "fail": N, "skipped": N },
    "E_extensions":        { "pass": N, "fail": N, "skipped": N },
    "F_cross_browser":     { "pass": N, "fail": N, "skipped": N }
  },
  "failures": [
    { "id": "...", "origin": "from|for", "triage_note": "...", "expected": "...", "actual": "...", "url": "...", "screenshot": "..." }
  ],
  "debug_log_issues": [
    { "section": "...", "level": "fatal|warning|notice|deprecated", "line": "...", "file": "..." }
  ],
  "manual_required": []
}
```

Emit a Basecamp draft per failure using the plugin's tracker (project id `46502739` — fill in once a project exists). Origin `from` (our code) blocks the release; origin `for` (theme/other-plugin/legacy) is logged but does not block.

## Fixture cleanup (before every walk)

Delete any leftover test data from prior runs. WP-CLI eval here is permitted because this is infrastructure, not a feature check.

```bash
wp --path="$WP_PATH" eval '
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->prefix}posts WHERE post_type IN (\"wcb_job\",\"wcb_application\",\"wcb_resume\",\"wcb_company\",\"wcb_board\") AND (post_title LIKE \"E2E %\" OR post_title LIKE \"Smoke %\")" );
wp_cache_flush();
echo "fixtures cleaned\n";
'
```

> Verified CPT slugs (from code, 2026-05-09): `wcb_job`, `wcb_application`, `wcb_resume` (labelled "Resumes"; this is the candidate-profile CPT), `wcb_company`. Pro reuses these — no Pro-owned CPTs.

## Debug log protocol

Enable `WP_DEBUG` + `WP_DEBUG_LOG` + `WP_DEBUG_DISPLAY=false` before Section A. Baseline `wp-content/debug.log` byte count. After every section, diff new lines into `debug_log_issues[]` classified by level. Any new fatal or warning is a failure unless explicitly whitelisted.

```bash
BASELINE_SIZE=$(wc -c < "$WP_PATH/wp-content/debug.log" 2>/dev/null || echo 0)
# after each section:
tail -c +$((BASELINE_SIZE + 1)) "$WP_PATH/wp-content/debug.log" 2>/dev/null | grep -vE "^\s*$|^\[cli\]"
```

At walk end, archive the diff window to `docs/qa/.debug-log-<release_version>-<ran_at>.txt`.

---

## A — Fresh install

### A.activation.first-request
**What to verify:** after a clean activation, the plugin's primary front-end route (job board archive) responds 200 on the very first request — without the user having to hit Settings > Permalinks.
**Why it matters:** rewrite-flush-on-activation regressions break first impressions and SEO crawl.
**Acceptance:** the primary front-end route returns HTTP 200; rewrite rules contain `wcb_job` (or whatever CPT permalink rule the plugin registers).

### A.db.tables-and-version
**What to verify:** all expected tables exist; `wcb_db_version` option matches `WCB_VERSION`. Free owns 3 tables (`wcb_notifications_log`, `wcb_job_views`, `wcb_gdpr_log`) created in `core/class-install.php`. With Pro active, 9 additional Pro tables exist (`wcb_credit_ledger`, `wcb_field_groups`, `wcb_field_definitions`, `wcb_field_values`, `wcb_job_boards`, `wcb_job_alerts`, `wcb_application_stages`, `wcb_ai_vectors`, `wcb_notifications`) created in `core/class-pro-install.php`; `wcbp_db_version` (option key, not `wcb_pro_db_version`) matches `WCBP_VERSION`. Total expected table count (combo): 12.

```bash
wp --path="$WP_PATH" db query "SHOW TABLES LIKE '%wcb%'" --skip-column-names
# Expected: 12 rows (3 free + 9 pro)
wp --path="$WP_PATH" option get wcb_db_version
wp --path="$WP_PATH" option get wcbp_db_version
```

### A.pro.pairs-cleanly
**What to verify:** activating `wp-career-board-pro` on top of `wp-career-board` does not fatal; Pro-only tables are created; `WCB_VERSION` and `WCBP_VERSION` print the same string (lockstep). Deactivating Free → Pro shows the "requires WP Career Board" admin notice and does not fatal.

---

## B — Upgrade from previous version

### B.migration.silent
**What to verify:** upgrading from the prior stable version to this build completes with no debug.log entries during the activation HTTP request; pre-existing jobs / applications / candidates / settings still render and behave; denormalized counters (application count per job, saved-job count per candidate) stay in sync.

---

## C — Core customer flows

Persona ladder: **Anonymous → Candidate (member) → Employer → Admin**. Exercise both desktop 1280px and mobile 390px where the UI differs.

Each step is a contract, not a script. Verify the UI as a user would AND confirm the server-side effect (DB row, REST response, queued side effect) to rule out a "looks right, didn't actually save" bug.

### C.anon.home
**What to verify:** the job-board landing renders for a logged-out visitor, shows real jobs (or a clean empty-state if none), and offers a clear entry (login / register / "Post a job" CTA).

### C.anon.search
**What to verify:** searching a known-present keyword returns matching jobs (share ≥ 1 meaningful token with the query); gibberish returns a clean empty state, not a fatal. Filters by location / category / type narrow the result set as expected.
**Why it matters:** search is how candidates find jobs; a broken search drives ticket volume.

### C.anon.single-job
**What to verify:** a job page renders title, company, location, description, application-deadline, salary, custom fields. As of v1.1.0 the company block also surfaces tagline, industry, company size, and HQ location (meta keys `_wcb_tagline`, `_wcb_industry`, `_wcb_company_size`, `_wcb_hq_location` — present in the `/wcb/v1/companies` response shape). The Apply CTA is auth-gated: anonymous click cleanly redirects to login rather than 403-ing silently.

### C.anon.company
**What to verify:** a public company / employer profile renders without leaking private fields (email, draft jobs, internal notes); shows the employer's published jobs.

### C.candidate.register
**What to verify:** a new visitor can register as a candidate via the plugin's signup surface; required fields are validated client + server side; the new account lands on a sensible landing page (dashboard or onboarding).

### C.candidate.apply
**What to verify:** a logged-in candidate can apply to a job (resume + cover letter + custom fields). The application is persisted (visible in the candidate's "My Applications" AND the employer's pipeline), the employer is notified, the candidate sees an acknowledgement (not a silent submit). Re-applying to the same job either updates the existing application or shows a clear "already applied" state per contract.

### C.candidate.profile
**What to verify:** a candidate can edit name / bio / skills / social links / avatar / resume; changes save, reload cleanly, and reflect on their public profile.

### C.candidate.save-job
**What to verify:** save (bookmark) a job → it appears in "Saved jobs"; unsave → removed; counts reflect the change.

### C.candidate.mobile
**What to verify:** every primary candidate flow (browse → single-job → apply) is usable at 390px: touch targets reachable, no horizontal overflow, no critical surface clipped.

### C.employer.post-job
**What to verify:** a logged-in employer can compose a new job (title, description, location, type, salary, application form, custom fields) and submit. The job is persisted, appears in the public listing (or in moderation queue if moderated), and on the employer's dashboard.

### C.employer.edit-job
**What to verify:** an employer can edit / pause / close their own jobs; changes propagate to public listing + search index. Closed jobs are clearly marked, accept no new applications.

### C.employer.applications
**What to verify:** the employer sees applications for their jobs, can open an application detail (resume + answers + attachments), and can change status (review / shortlist / reject / hire). Pipeline drag-to-stage (Pro) persists across reload.

### C.mod.queue-and-actions
**What to verify:** a `wcb_board_moderator` user reaches the WP admin (WooCommerce lockdown lets them through), sees the Career Board → Jobs queue, and can approve or reject pending jobs from both the row actions and the bulk-Approve dropdown. The Trash bulk action and the Edit/Trash/Restore row actions stay hidden for moderators. Every other Career Board admin page (Settings, Companies, Applications, etc.) returns "Sorry, you are not allowed to access this page." Resolve-flag IS shipped in 1.2.0: moderators see a Flagged view in the Jobs queue and clear user-reported jobs via `POST /wcb/v1/jobs/{id}/resolve-flag` (dismiss/unpublish) — covered by the `report-a-job` journey. A standalone mark-as-spam action and per-board scoping are not in scope — board-level scoping ships as a `wcb_moderate_jobs_ability_check` filter for extensions.

### C.admin.pages
**What to verify:** every plugin admin page renders without PHP Notice/Warning/Fatal; every tab loads its content; every AJAX action returns the expected JSON shape.

### C.admin.crud
**What to verify:** the plugin's top-level admin entities (job categories, job types, locations, fields, settings) can be created, edited, reordered, and deleted. Settings edits MERGE with existing values (editing one key does not drop others — this is a hard contract).

### C.admin.user-management
**What to verify:** admin can change a user's role (candidate ↔ employer ↔ both); capability is honoured immediately (the user gains/loses access to the matching dashboards on their next request). Banning an employer prevents new job posts; banning a candidate prevents new applications.

### C.notifications
**What to verify:** every notification-triggering event (new application, job approved, job-alert match, application-status change) reaches the correct recipient in the UI. Preferences (email-vs-instant) are respected. Unread count is accurate. Mark-all-read works.

### C.cron
**What to verify:** every expected cron event (`wcb_*`) is scheduled after activation; none are orphaned after deactivation; events actually fire when triggered (`wp cron event run <hook>`).

---

## D — Known-regression guards

Each row is a repro of a past bug that caused customer pain. D rows stay specific — the fixture IS the contract.

| ID | Bug | Fixture + assertion |
|----|-----|---------------------|
| D.wcb-closed-status | Jobs manually closed by an employer (status `closed`) were not registered as a custom WP post status, causing them to vanish from the employer's dashboard. Basecamp 9872024322. | Create a job as employer, call `PATCH /wcb/v1/jobs/{id}` with `{"status":"closed"}`, reload employer dashboard — job must appear with status label "Closed" not disappear. |
| D.apply-email-scraped | The job REST response (`/wcb/v1/jobs`) was including `apply_email` in the JSON, allowing scrapers to harvest recruiter inboxes. Basecamp referenced as F-1 in role-data-baseline. | As anonymous: `GET /wcb/v1/jobs/{id}` — assert response JSON contains NO `apply_email` field (any non-empty value is a fail). |
| D.resume-required-default | Fresh installs (option `apply_resume_required` absent) were silently defaulting to NOT require a resume, letting applications land with no attachment. Basecamp 9818132111. | On a fresh install (no `apply_resume_required` option saved), attempt to `POST /wcb/v1/jobs/{id}/apply` without a resume file — assert HTTP 400 with code `wcb_resume_required`. |
| D.company-tagline-missing | Single-job pages and the company-archive block were not surfacing company tagline, industry, size, or HQ location because those meta keys were absent from `prepare_item_for_response_array()`. Basecamp 9871740742. | `GET /wcb/v1/companies/{id}` — assert response body contains non-empty keys `tagline`, `industry`, `size_label`, `hq`. |
| D.location-dropdown-scope | The employer HQ location dropdown on the company-edit form was showing all WP locations (global tax) instead of only the employer's own HQ + Remote + Other, leaking other companies' locations. Basecamp 9866553120. | As employer: navigate to company profile edit page — assert the location dropdown contains only "Remote", "Other", and HQ-specific options, NOT terms belonging to other companies. |
| D.ability-slug-format | Ability slugs used bare `wcb_post_jobs` format instead of the WP 6.9 `wcb/post-jobs` namespace/slug format, causing `wp_get_ability()` to reject every registration silently. Basecamp implicit in T3.5. | Load `/?autologin=employer_alice`, assert that `POST /wcb/v1/jobs` returns HTTP 201 (not 403) and debug.log shows ZERO `wp_get_ability` or `wp_is_ability_granted` notices. |
| D.vector-column-mariadb-11-7 | Pro's `wcb_ai_vectors` schema used unbacktickged `vector` column name — MariaDB 11.7+ and MySQL 9+ added `VECTOR` as a reserved data type, so dbDelta failed silently and the table was never created on those server versions. Every Pro AI feature would have errored with "table doesn't exist". Fixed in commit `fa3a337` (backtick column) + `f7ea313` / `910cdf2` (verify-before-bump version gate so a silent dbDelta failure no longer masks itself). | On a fresh wp-env with MariaDB 11.7+ or MySQL 9+, run `wp plugin deactivate ... && wp option delete wcbp_db_version && wp plugin activate wp-career-board-pro`. Then `wp db tables \| grep -c "wp_wcb_ai_vectors"` MUST return `1` AND `wp option get wcbp_db_version` MUST equal the file constant (proving create_tables succeeded). All 13 plugin-owned tables (3 Free + 10 Pro counting wcb_credit_gateway_log from the SDK) must exist. |
| D.pwa-icon-404 | Pro's PWA module emitted a manifest with a hardcoded `icon-192.png` path that was never shipped, producing a 404 on every page load and a manifest-icon warning visible to anyone with DevTools open. Fixed in commit `936c04a` — switched to the WordPress Site Icon (Settings → General → Site Icon) at 192px and 512px, omitting the `icons` key entirely if no Site Icon is configured (manifest spec accepts that). | On a wp-env with no Site Icon configured + Pro PWA module active: `curl http://site/wcb-manifest.json \| jq '.icons // empty'` must return EMPTY. Loading any frontend page must NOT log `icon-192.png` 404 in the network tab. |
| D.lucide-hydration-mismatch | Lucide JS swapped `<i data-lucide>` placeholders to `<svg>` after Interactivity's hydrator captured the `<i>` into its vnode tree, producing 6+ console errors per page (`Expected a DOM node of type "i" but found "svg"`) on every block that used `data-wp-interactive`. Fixed in commits `2c2cfd9` / `add54c5` — built `WCB\Core\Icon::svg()` server-side helper and replaced 40 `<i data-lucide>` sites across 13 Interactivity-bound block render templates with inline SVG. | Open DevTools, navigate to /jobs/, /jobs/<single>/, /employer-dashboard/. Console must show ZERO errors matching the pattern `Expected a DOM node of type "i" but found`. Visual icons must still render (no broken-image squares). |
| D.test-email-bridge | Test email endpoint used ReflectionClass to bypass `is_enabled()`; the abstraction was fragile and the response omitted whether `wp_mail()` actually succeeded. Fixed in 1.2.0 (commit `e5b7020`): `AdminEndpoint` now calls `AbstractEmail::test_send()`, which routes through the shared `dispatch()` helper. Basecamp 9895205013. | As admin: `POST /wcb/v1/admin/emails/test` with a valid `email_id` and `to` address. Assert: HTTP 200, response body has `sent` (bool), `to` (string), `logged` (int). Query `wp_wcb_notifications_log WHERE status IN ('sent_test','failed_test')` — assert a row exists with `is_test: true` in the `payload` column. Assert NO row with `status = 'sent'` was written (test must not pollute production metrics). |
| D.meta-filter-default-allow | The `metaFilter` block attribute and `?meta_<key>=<value>` REST param required every `_wcb_*` key to be listed explicitly via `wcb_jobs_allowed_meta_filters`, preventing integrators from using custom WCB meta without a code change. Fixed in 1.2.0 (commit `e5b7020`): any `_wcb_*` namespaced key is auto-allowed. Basecamp 9891012864. | Create a job with a custom `_wcb_partner_id` meta value. As anonymous: `GET /wcb/v1/jobs?meta__wcb_partner_id=<value>`. Assert the response includes only jobs with that meta value (non-empty `items[]`). Repeat the query WITHOUT a `wcb_jobs_allowed_meta_filters` hook registered — result must be identical. |
| D.setup-wizard-centering | Setup wizard page (`?page=wcb-setup-wizard`) had no max-width / centering rule so it stretched edge-to-edge on wide viewports. Fixed in 1.2.0 (commit `e5b7020`): `.wcb-wizard-wrap` is `margin-left/right: auto` and gets a 12px side-margin below 960px. Basecamp 9890815047. | As admin, navigate to `wp-admin/admin.php?page=wcb-setup-wizard` at 1440px viewport. Assert the wizard content is horizontally centered (left and right margins visible). At 768px viewport assert the side-margin collapses to 12px with no horizontal scroll. |
| D.company-cards-alignment | Company archive cards with short taglines had meta chips that floated to different y-positions compared to adjacent cards with long taglines, because the card grid lacked explicit row sizing. Fixed in 1.2.0 (commit `e5b7020`): `.wcb-ca-card-link` has `grid-template-rows: auto 1fr auto`, chips row has `align-self: start`. Basecamp 9890919239. | Navigate to the company archive. Visually verify that the tag/chip row aligns at the same vertical position across cards in the same row when cards have taglines of different lengths. Check at 1440px and 390px. |
| D.active-filter-spacing | The active-filter chip row (`.wcb-active-filters.wcb-shown`) had no bottom margin, so chips ran directly into the job cards below with no visual separation. Fixed in 1.2.0 (commit `e5b7020`): `margin-bottom: var(--wcb-space-md)` added. Basecamp 9890885030. | Apply any filter on the job listings block. Assert the `.wcb-active-filters` strip has visible bottom spacing before the first job card (computed `margin-bottom` matches `--wcb-space-md`, typically 16px or 1rem). |
| D.public-chevron-lucide | Filter-panel expand/collapse chevrons on job-listings and company-archive blocks were hand-rolled inline SVGs that caused Interactivity API hydration mismatches. Fixed in 1.2.0 (commit `e5b7020`): both render templates replaced with `<i data-lucide="chevron-down">`. Basecamp 9891577445. | Open DevTools, navigate to /jobs/ and the company archive. Console must show ZERO hydration errors. Chevron icons must render correctly (visible, correct orientation) at both 1440px and 390px. |

> D rows are sourced from the last 30 git commits (2026-05-15 audit) and updated after every customer-visible fix. After 2 clean releases, a D row graduates into C/E.

---

## E — Pro extensions / addons

Combo-mode only. Each contract covers the customer-visible promise, not the implementation. Implementation status flagged below — modules marked **stub** at v0.1.0 should be reported `skipped` in `sections.E_extensions` until they materialise.

### E.pro.resume — `complete` (v1.1.0: 2 module files + 4 API endpoint files, 18 hooks; REST routes: `GET/POST /resumes`, `GET /resumes/{id}`, `GET /resumes/{id}/pdf`, `GET/POST /candidates/{id}/resumes`; admin: `edit.php?post_type=wcb_resume` via Pro submenu)
**What to verify:** a candidate can upload a resume (PDF / DOC / DOCX); the file is stored, parseable preview rendered, and downloadable from the employer's view. Bad MIME types are rejected with a clear error. The public resume archive (`GET /wcb/v1/resumes`) returns published resumes; the `?autologin=candidate_carol` path sees the candidate's own draft resumes via `GET /wcb/v1/candidates/{id}/resumes`.

### E.pro.notifications-bell — `partial` (v1.1.0: 1 file, 5 hooks; REST: `GET /notifications`, `PUT /notifications/{id}/read`, `POST /notifications/read-all`; uses `wcb_notifications` table)
**What to verify:** the bell icon shows the unread count; clicking opens a panel with recent notifications; mark-all-read clears the count and persists across reload.

### E.pro.notifications-pro — `partial` (v1.1.0: 7 files, 5 hooks; no standalone admin slug — settings live under the free plugin's Settings page)
**What to verify:** email preference settings (email-only / instant / digest) are respected on the next triggering event. A status change on an application triggers the correct notification type and the bell count increments.

### E.pro.fields — `partial` (v1.1.0: 3 files, 10 hooks; REST: CRUD on `/fields/groups`, `/fields/groups/{id}/fields`, `/fields/{id}`, `POST /fields/reorder`; admin slug: `wcbp-field-builder`; DB: `wcb_field_groups`, `wcb_field_definitions`, `wcb_field_values`)
**What to verify:** the field builder lets an admin create a custom field, attach it to a job CPT or candidate profile, and configure validation. The field renders on the matching form, persists on save, and the value is visible everywhere the entity renders.

### E.pro.ai — `partial` (v1.1.0: 5 files, 2 hooks; REST: `POST /ai/match`, `GET /ai/matches`, `GET /ai/ranked-applications/{job_id}`, `POST /ai/generate-description`; admin tab: `wcbp-ai-settings` → redirects to `wcb-settings&tab=ai-settings`; needs Ollama or OpenAI key configured; DB: `wcb_ai_vectors`)
**What to verify:** with a configured AI provider (Ollama / OpenAI), parsing a sample resume returns structured data within the configured timeout; the parsed fields populate a candidate-profile (`wcb_resume`) draft. With no provider configured, no fatal; the UI surfaces a graceful "AI unconfigured" notice. Block `ai-chat-search` renders.

### E.pro.maps — `partial` (v1.1.0: 6 files, 4 hooks; REST: `GET /geocode`; block `job-map`)
**What to verify:** a single-job page renders the map for a geocoded location; the marker is at the correct coordinates; geocoding falls back gracefully when the provider is rate-limited.

### E.pro.boards — `partial` (v1.1.0: 2 files, 6 hooks; REST: `GET/DELETE /boards/{id}`, CRUD on `/boards/{id}/stages`; admin surface: "Boards" tab under `wcb-settings` — rendered by `AdminBoards::render()`; board CPT is `wcb_board` owned by Free)
**What to verify:** an admin can create a second job board with its own slug and category scope; a job assigned to that board appears on its public listing but not on the default board's listing.

### E.pro.credits — `partial` (v1.1.0: 1 file, 3 hooks; REST: `GET /credits/packages`, `GET /employers/{id}/credits`; admin tab: `wcbp-credits` → redirects to `wcb-settings&tab=credits`; DB: `wcb_credit_ledger`)
**What to verify:** the credit balance endpoint returns the employer's current balance (sum of signed ledger amounts); posting a job on a paid board decrements the balance; admin can grant credits and the audit row persists in `wcb_credit_ledger`. Reaching zero balance blocks new job posts with a clear "out of credits" notice.

### E.pro.alerts — `stub` (v1.1.0: 1 file, 4 hooks; REST: `GET/POST /alerts`, `PUT/DELETE /alerts/{id}`; cron `wcbp_dispatch_alerts`; no admin tab yet; DB: `wcb_job_alerts`)
**What to verify (when implemented):** a candidate creates a saved-search alert; a newly posted job matching the saved query triggers an email within the alert's cadence (daily / weekly). Until fully implemented: report `skipped` with the stub note. REST endpoint exists and should return 200.

### E.pro.analytics — `stub` (v1.1.0: 1 file, 0 hooks; REST: `GET /analytics/credits.csv` — export only; reads `wcb_job_views` table owned by Free; no admin dashboard page yet)
**What to verify (when implemented):** the analytics credits CSV exports without 500. The `wcb_job_views` table is populated by Free's `record_job_view()`. Until implemented: `skipped`.

### E.pro.feed — `stub` (v1.1.0: 1 file, 5 hooks; admin tab: `wcbp-job-feed` → redirects to `wcb-settings&tab=job-feed`; page may render empty)
**What to verify (when implemented):** the activity feed renders the latest events for the viewer. Pagination advances. No N+1. Until implemented: `skipped`.

### E.pro.migration — `stub` (v1.1.0: 2 files, **0 hooks** — module not wired into any action; no admin slug registered)
**What to verify (when implemented):** importing a sample CSV creates records, orphans flagged, re-running is idempotent. Until implemented: `skipped` with note "module has zero hook bindings — not boot-wired".

### E.pro.pipeline — `stub` (v1.1.0: 1 file, 1 hook; REST: `PUT /applications/{id}/stage`, `GET /jobs/{id}/kanban`; block `application-kanban`; uses `wcb_application_stages` table)
**What to verify (when implemented):** employer creates a custom stage via boards admin, moves an application between stages via Kanban, persistence across reload. Until fully wired: `skipped`.

### E.pro.pwa — `stub` (v1.1.0: 1 file, 4 hooks; no service worker registered yet)
**What to verify (when implemented):** service worker registered, offline shell renders, install prompt available. Until implemented: `skipped`.

> **Pro implementation status snapshot (v1.2.0, 2026-05-15 audit):** resume is `complete` (REST + admin + UI all wired, 18 hooks); fields, ai, maps, boards, credits, notifications-bell, notifications-pro are `partial` (REST + some admin, limited UI); alerts, analytics, feed, migration, pipeline, pwa are `stub`. Walk the complete + 7 partial modules live; stub modules are `skipped` with the note above.

---

## F — Cross-browser, RTL, accessibility

### F.chromium
Already covered by sections A–E during the Sonnet walk.

### F.firefox-desktop and F.safari-ios
Chromium-only MCP cannot walk these. Populate `manual_required[]` with the critical flows a human must spot-check (file uploads on iOS, browser-native date pickers on Firefox, iOS scroll quirks in long lists).

### F.rtl
**What to verify:** on an RTL locale (`wp option update WPLANG ar` or browser-locale toggle), primary templates render right-to-left without overflow; icons mirror where appropriate; brand glyphs stay untransformed.

### F.a11y
**What to verify:** primary interactive surfaces have visible focus rings; tab order is logical; icon-only buttons have `aria-label`; composers, voting controls, moderation actions have screen-reader-critical labels; the apply form is fully keyboard-operable.

---

## G — Post-release monitoring (first 24h after tag)

Runs on the production host. Watch for new debug.log entries, orphaned cron events, support tickets reporting breakage, and activity-signal drops (zero-applications-in-X-hours where the prior baseline was non-zero).

---

## Failure protocol

1. Screenshot on every failure: `browser_take_screenshot({ filename: "fail-<id>.png" })`.
2. **Triage: from vs for our plugin.**
   - `from` = our code is at fault.
   - `for` = failure surfaces while our plugin runs but root cause is elsewhere (theme / other plugin / browser limit / legacy data / hosting).
3. Record in `failures[]` with `{ id, origin, triage_note, expected, actual, url, screenshot }`.
4. Never halt. Collect all failures in one pass.
5. Emit a Basecamp draft per failure with the origin line populated.

Triage is Sonnet's job; fix-or-document is the calling session's job.

## Step ID format

`<Section>.<persona>.<feature>` e.g. `C.candidate.apply`. D rows: `D.<descriptor>`. E rows: `E.pro.<module>`.
