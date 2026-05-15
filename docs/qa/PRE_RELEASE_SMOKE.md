# WP Career Board — Pre-Release Smoke Checklist

> **Run this before every tagged release. Every row must pass.**
> Any failure → file a Basecamp card in Bugs and **halt the release**.
> Target time: 90 minutes end-to-end.

**Matrix:** 3 personas × 3 browsers × 2 viewports × 2 theme modes (where applicable).

- Personas: Anonymous visitor, Member (subscriber/candidate), Employer, Admin
- Browsers: Chrome Desktop, Firefox Desktop, Safari iOS (sim or real)
- Viewports: 1440px desktop, 390px mobile
- Theme modes: Light, Dark

**Environment:**
- Clean Local site with `wp-career-board` **already on the previous stable version** for the upgrade test
- A second clean Local site for the fresh-install test
- Access to `wp-content/debug.log` and DevTools Network tab
- Mailpit / Mailhog open for email rows
- Reference combo install: `job-portal.local` (both `wp-career-board` + `wp-career-board-pro` active)

---

## A — Fresh install (10 min)

- [ ] Activate `wp-career-board` → no fatal, no PHP warning in `debug.log`
- [ ] Custom DB tables created: `wp db tables --all-tables | grep ^wp_wcb_`
- [ ] `wp option get wcb_db_version` equals `WCB_VERSION` value
- [ ] Front-end job-board route loads as the very first request after activation → HTTP 200 (regression guard against rewrite-flush 404)
- [ ] `/wp-json/wcb/v1/jobs` returns 200 to anonymous on a fresh install
- [ ] Deactivate → reactivate → no duplicate tables, no re-run migrations
- [ ] Activate `wp-career-board-pro` → no fatal, Pro-specific tables created (`wp_wcb_credit_ledger`, `wp_wcb_field_groups`, `wp_wcb_field_definitions`, `wp_wcb_field_values`, `wp_wcb_job_boards`, `wp_wcb_job_alerts`, `wp_wcb_application_stages`, `wp_wcb_ai_vectors`)

## B — Upgrade from previous release (5 min)

- [ ] Drop the new zip → update via WP → no fatal
- [ ] `wp option get wcb_db_version` updates to new constant
- [ ] Pre-existing data still renders correctly (jobs, applications, candidates, employer profiles, settings)
- [ ] No new warnings in debug.log

## C — Core user flows (25 min)

### C1 — Anonymous visitor
- [ ] Job board landing renders, no console errors
- [ ] Click through primary navigation → all public pages render
- [ ] Single-job page renders with title, company, location, description
- [ ] Search + filters return matching jobs (or a clean empty state)
- [ ] Public company / employer profile renders
- [ ] "Apply" CTA on a single-job page redirects to login (never silent 403)

### C2 — Member (candidate)
- [ ] Register or log in via WP Career Board's login surface
- [ ] **Primary creation flow:** apply to a job (upload resume / cover letter / answer fields) → application created, Network 2xx
- [ ] Application appears in the candidate's "My Applications" view AND in the employer's pipeline
- [ ] Edit candidate profile (name, bio, skills, links) → saves, persists across reloads
- [ ] Save-job / bookmark-job → reflected in "Saved jobs"
- [ ] Mobile 390px: every flow still usable, no horizontal overflow

### C3 — Employer
- [ ] **Primary creation flow:** post a new job (title, company, location, description, fields) → published, Network 2xx, appears on the public board
- [ ] Edit posted job → changes propagate to public listing + search index
- [ ] View applicants for a job → list renders, application detail opens, attachments download
- [ ] Pipeline / stage moves work (Pro) — drag application between stages, persists across reload
- [ ] Mobile 390px: post-a-job composer usable

### C4 — Admin
- [ ] Navigate to plugin admin pages → all render without PHP warnings
- [ ] Settings save flow works — change a setting, save, reload, persists
- [ ] List pages: filter, paginate, bulk actions on jobs / applications / candidates
- [ ] Moderation queue: approve / spam / trash a flagged job; silenced employers cannot post
- [ ] User management: change a user's role to candidate / employer; capability check honoured
- [ ] Emails admin → Send Test → response body shows `sent`/`to`/`logged`; log row has `sent_test` status (not `sent`)
- [ ] Setup wizard page is horizontally centered at 1440px and has side-margin at tablet width

## D — Known-regression guards (15 min)

See `docs/qa/AGENT_SMOKE_RUNBOOK.md` Section D for the full repro + assertion for each row. Quick human walk:

- [ ] `D.test-email-bridge`: Send test email from Emails admin → response `{sent, to, logged}`, log row has `status = sent_test`, NOT `sent`.
- [ ] `D.meta-filter-default-allow`: `GET /wcb/v1/jobs?meta__wcb_<any>=<val>` returns matching jobs without a `wcb_jobs_allowed_meta_filters` hook registered.
- [ ] `D.setup-wizard-centering`: Wizard page centered at 1440px; 12px side-margin at 768px; no horizontal scroll.
- [ ] `D.company-cards-alignment`: Company archive chip row aligns at the same y-position across cards with different tagline lengths.
- [ ] `D.active-filter-spacing`: Job listings active-filter chips have bottom margin before job cards.
- [ ] `D.public-chevron-lucide`: No hydration errors on /jobs/ and company archive; chevrons render correctly.
- [ ] `D.wcb-closed-status`: Close a job via `PATCH /wcb/v1/jobs/{id}` with `{"status":"closed"}` → job stays visible in employer dashboard with "Closed" label.
- [ ] `D.apply-email-scraped`: `GET /wcb/v1/jobs/{id}` as anonymous → response has NO `apply_email` field.
- [ ] `D.resume-required-default`: Fresh install, apply without resume → HTTP 400 `wcb_resume_required`.
- [ ] `D.company-tagline-missing`: `GET /wcb/v1/companies/{id}` → response has non-empty `tagline`, `industry`, `size_label`, `hq`.
- [ ] `D.location-dropdown-scope`: Employer company-edit location dropdown shows only HQ-specific + Remote + Other options.
- [ ] `D.ability-slug-format`: `POST /wcb/v1/jobs` as employer → HTTP 201, zero `wp_get_ability` notices in debug.log.
- [ ] `D.vector-column-mariadb-11-7`: On MariaDB 11.7+ fresh install → `wp_wcb_ai_vectors` table exists, `wcbp_db_version` matches constant.
- [ ] `D.pwa-icon-404`: No Site Icon configured + Pro PWA active → `wcb-manifest.json` has no `icons` key, no 404 in network tab.
- [ ] `D.lucide-hydration-mismatch`: No `Expected a DOM node of type "i" but found` errors on /jobs/, /jobs/<single>/, /employer-dashboard/.

## E — Pro extensions (if `wp-career-board-pro` active, 15 min)

> **v0.1.0 implementation status:** 5 of 14 Pro modules are `partial`; 6 are `stub`. Walk the partials live, mark stubs N/A until they ship. Re-evaluate the partition every minor release.

Partial / walkable today:
- [ ] **Resume** — upload (PDF/DOC/DOCX), parse, preview, employer download; bad MIME rejected
- [ ] **Notifications-bell + Notifications-pro** — unread count accurate; mark-all-read works; preferences honoured
- [ ] **Fields** — field builder: create a custom field, attach to a job CPT or `wcb_resume`, value persists on save (admin slug `wcbp-field-builder`)
- [ ] **AI** — with provider configured (Ollama/OpenAI), parse a sample resume; with none, graceful "AI unconfigured" notice (admin slug `wcbp-ai-settings`)
- [ ] **Maps** — single-job page renders map for a geocoded location; rate-limit fallback graceful
- [ ] **Boards** — create a second board, scope a job to it, public listing reflects scope (admin slug `wcbp-boards`)
- [ ] **Credits** — credit ledger shows balance; post-a-job decrements per package; zero-balance blocks new posts (admin slug `wcbp-credits`)

Stub at v0.1.0 — N/A pending implementation:
- [ ] ~~Alerts~~ — only cron `wcbp_send_alert_email` wired; admin not exposed
- [ ] ~~Analytics~~ — 1 file, 1 hook
- [ ] ~~Feed~~ — admin slug exists; page may render empty
- [ ] ~~Migration~~ — 2 files, **zero hook bindings** (admin slug `wcbp-migration`)
- [ ] ~~Pipeline~~ — block `application-kanban` exists; persistence not wired
- [ ] ~~PWA~~ — service worker not registered yet

Feature-toggle off → feature hidden from front-end; no dead buttons, no 404s.

## F — Cross-browser quick pass (10 min)

Run these 5 pages on **Chrome + Firefox + Safari iOS**:

1. Job board landing — `/jobs/` (or whatever the configured archive route is)
2. Single-job page — `/jobs/<slug>/`
3. Apply form — apply CTA on a single-job page
4. Employer dashboard — front-end "My Jobs / Applicants" view
5. Plugin admin — `wp-admin/admin.php?page=wcb`

Expectations: no JS errors, no layout breaks, interactive elements work.

## G — Post-release verification (first 24h)

- [ ] `wp-content/debug.log` clean of new warnings/notices/fatals
- [ ] `wp cron event list | grep wcb` — expected events scheduled, no orphans
- [ ] Zoho Desk / Slack #support — no "broke after update" tickets in first 24h
- [ ] Analytics / activity signal continues (no "zero events" sign of breakage)

---

## Failure protocol

1. **Stop.** Do not merge the release branch.
2. File a Basecamp card in **Bugs** with the failed row verbatim, environment, browser, user persona.
3. Fix + push to the release branch.
4. Re-walk the failed row AND the section that contains it.
5. Resume only after the failure is resolved.

## Version-specific additions

Append a section below for every release with the specific regression guards added that cycle. After 2 clean releases of a row → graduate it into the main flow.

### 1.2.0 — 2026-05-15

New checks added this cycle (beyond the D-row additions above):

- [ ] Company archive: chip row aligns across cards with different tagline lengths at 1440px and 390px.
- [ ] Job listings active-filter chips: bottom margin visible before job cards when at least one filter is active.
- [ ] Find Jobs and company archive filter chevrons: no hydration console errors; icons display.
- [ ] `metaFilter` block attribute: add `metaFilter="_wcb_partner_id:acme"` to the shortcode; assert filtered results without registering `wcb_jobs_allowed_meta_filters`.
- [ ] BuddyPress profile / group tabs: if `wcb_page_needs_frontend_assets` is wired, WCB blocks render without `.wcb-hidden` showing both states. (Basecamp 9895174032)
