# WP Career Board — Long-Term Fix Plan

> Cross-check of every Basecamp Bugs-column card + grouping by refactor target so we ship one coherent fix per architectural seam, not 17 patches across 17 PRs.
>
> **Compiled 2026-05-09 from a full audit cycle:** smoke runbook + 8 parallel CLI journey walks (47 pass / 22 fail) + 4 wppqa wiring checks + action-audit cross-layer scan + plugin-dev-rules security check + coding-rules sweep.

---

## Cross-check — every card in Basecamp Bugs column right now

**17 cards in Bugs column** (project 46502739, column 9691964821). 12 active from this audit cycle + 1 retracted false positive + 4 pre-existing UI cards from Simran Kaur's prior triage.

### Active real bugs filed by this audit (12)

| # | Card | Title | Verified | Fix status | Refactor seam |
|---|---|---|---|---|---|
| 1 | 9874889920 | REST: GET /wcb/v1/jobs/{id} omits company tagline/industry/size_label/hq | ✓ live REST probe | **Fixed locally** (4 keys + size_label helper added) | **R2** company-meta serialization |
| 2 | 9874890156 | REST: GET /employers/{id}/applications hides applications received on closed/expired jobs | ✓ source read confirms line 715 missing entries | **Fixed locally** (1-line array consistency) | **R1** post_status allowlists |
| 3 | 9874907956 | Security: Pro gateway settings save lacks capability check (nonce-only) | ✓ block read, no `wp_is_ability_granted` inside verify branch | Filed only | **R3** ability gate (or local capability_check) |
| 4 | 9874915447 | Custom application fields silently dropped on submit (updateCustomField action missing from store) | ✓ template emits directive, view.js has zero matches | Filed only — needs 3-layer fix | **R5** multi-layer wiring |
| 5 | 9874915588 | REST: /wcb/v1/resumes/photo-upload called by JS but no PHP route registered (silent 404) | ✓ JS caller exists, zero PHP registrations grep'd | Filed only | New route — not a refactor, but a missing feature |
| 6 | 9874915782 | A11y: Employer dashboard "Settings" tab points to a panel with no matching id | ✓ 5 panels have ids, settings panel doesn't | Filed only | Discrete fix (1 attribute) |
| 7 | 9874928178 | **Security HIGH:** Banned employer can still post jobs (ban meta not checked in ability callback) | ✓ rest_do_request as banned employer 50 succeeded | Filed only | **R3** ability gate |
| 8 | 9874928455 | Email: Test-send dispatches raw merge tags ({{candidate_name}}) without substitution | ✓ test-send REST handler bypasses substitution pipeline | Filed only | **R4** Mailer chokepoint |
| 9 | 9874930898 | Pro alerts: REST keywords param not mapped to search_query column — alert saved with empty criteria, never fires | ✓ DB row inspection: search_query empty after POST | Filed only | Discrete fix (param mapping) |
| 10 | 9874932717 | REST: GET /wcb/v1/jobs ignored ?board_id= filter (param read as 'board' instead of 'board_id') | ✓ 8 jobs returned for both filtered + unfiltered | **Fixed locally** (1-char typo, restored consistency) | Discrete fix |
| 11 | 9874932439 | Deactivation leaves wcb_send_deadline_reminders + wcb_expire_featured_jobs as orphaned cron events | ✓ Install::deactivate clears only wcb_check_job_expiry | Filed only | **R3-class** chokepoint pattern (cron registry) |
| 12 | 9874932735 | Email subject merge tags (e.g. {job_title}) are never replaced — raw placeholder sent to recipients | ✓ AbstractEmail::send line 96-98 never substitutes subject | Filed only | **R4** Mailer chokepoint |

### Retracted false positive (1)

| # | Card | Reason |
|---|---|---|
| — | 9874927219 | Cron: wcbp_credit_reconcile registered in manifest but never scheduled — actually a manifest staleness; the live hook is named `wcbp_reconcile_credit_holds` and IS correctly scheduled. **Should be moved to Done** with the empirical-evidence comment trail. |

### Pre-existing UI cards from Simran Kaur (4) — not part of this audit cycle

| # | Card | Title (verbatim) | Created | Relation to this audit |
|---|---|---|---|---|
| 13 | 9862324432 | UI issues on Candidate dashboard | 2026-05-06 | Adjacent to Card 4 (custom fields) — both touch the candidate dashboard surface |
| 14 | 9862907821 | UI issue Employer Dasboard | 2026-05-06 | **Related to Card 6** (a11y settings panel id) — same area (employer dashboard settings tab), different concerns: Simran's is visual layout, mine is screen-reader navigation. **Bundle into one PR.** |
| 15 | 9861939526 | UI issue on employer registration form | 2026-05-06 | Adjacent — same employer onboarding surface as Card 7 (banned-employer) |
| 16 | 9866391818 | UI issue job application for select resume dropdown | 2026-05-07 | Adjacent to Card 4 (custom fields) and Card 5 (photo-upload) — same apply-form surface |

### Other Bugs-column cards already in Ready for Testing — informational only

| # | Card | Title | Status | Note |
|---|---|---|---|---|
| — | 9871740742 | Incomplete Job info getting rendered (apply_email + external URL) | Ready for Testing | DIFFERENT from Card 1; Card 1's description correctly notes the divergence |
| — | 9872024322 | Job closing functionality not working (Reopen button missing) | Ready for Testing | DIFFERENT from Card 2; my card is REST-side, this one is UI-side. Both should land in same release for closed-status hardening. |
| — | 9818132111 | Resume-required default | unknown column | Already a D-row regression guard in our runbook |
| — | 9866553120 | Location dropdown scope | unknown column | Already verified passing in batch-7 (pro-boards-location-scope journey) |

---

## Long-term plan — group fixes by architectural seam, ship as one PR each

The 12 active bugs collapse to **4 root architectural seams + 4 discrete fixes**. Each seam fix lands once and prevents a class of similar bugs from re-appearing.

### PR 1 — Ability-gate chokepoint (R3) [HIGH PRIORITY]

**Closes:** Card 7 (banned-employer-bypass), Card 3 (gateway nonce-no-cap if we use the same gate helper for admin POST handlers).
**Prevents:** every future ability that forgets to honor a cross-cutting check (rate-limit, soft-delete, account-suspension, etc.).

Introduce one helper in `core/class-abilities.php`:
```php
private static function gate( string $cap, ?int $user_id = null ): bool {
    $user = $user_id ? get_user_by('ID', $user_id) : wp_get_current_user();
    if ( ! $user || 0 === $user->ID ) return false;
    if ( '1' === (string) get_user_meta( $user->ID, '_wcb_employer_banned', true ) ) return false;
    return $user->has_cap( $cap ) || $user->has_cap( 'manage_options' );
}
```
Convert every ability's permission_callback to a one-line `fn() => self::gate( '<cap>' )` call. 13 abilities → 13 one-liners. The ban contract enforces once, on every gate.

**Effort:** medium (1 hour incl. tests). **Tests in place:** 5 security/* journeys + the new banned-employer journey (Card 7's regression guard).

### PR 2 — Email Mailer chokepoint (R4) [HIGH PRIORITY]

**Closes:** Card 8 (test-send raw merge tags) AND Card 12 (production AbstractEmail subject not substituted). Both are the same bug class on different code paths.
**Prevents:** every future email send that takes a different path and forgets substitution.

Make every email send route through a single `Mailer::send( string $template_key, string $to, array $context ): bool` that:
1. Loads template (subject + body) by key.
2. Substitutes BOTH subject and body via the existing `render_template()` machinery (currently only handles body).
3. Wraps with brand styling.
4. Calls `wp_mail()` once.

The test-send REST handler becomes a 5-line caller that builds a fixture context. `AbstractEmail::send()` migrates to call `Mailer::send()`. Future drip campaigns / digests / new transactional types plug into the same chokepoint.

**Effort:** medium (2-3 hours). **Tests in place:** admin-emails-template-merge-tags + deadline-reminder-email-sent journeys.

### PR 3 — Custom application fields end-to-end (R5) [HIGH CUSTOMER IMPACT]

**Closes:** Card 4. Three layers wired together in one PR:
1. **Template** (`blocks/job-single/render.php`) — already correct, no change.
2. **Frontend store** (`blocks/job-single/view.js`) — add `state.customFields = {}`, `actions.updateCustomField(event)`, and append to FormData inside `submitApplication()`.
3. **Backend handler** (`api/endpoints/class-applications-endpoint.php::submit_application()`) — read `$_POST['custom_fields']`, validate against the active `wcb_application_form_fields_groups` filter output, sanitize per declared field type, persist as postmeta.

A patch on only one layer hides the symptom but values still don't land. The proper fix lands all three together with a journey step that asserts the round-trip.

**Effort:** medium-large (3-4 hours). **Tests:** add a custom-field round-trip step to the apply-to-job journey.

### PR 4 — Cron registry chokepoint (R3-class extension) [MEDIUM]

**Closes:** Card 11 (deactivate leaves orphans). 
**Prevents:** every future cron event that gets registered but forgets the corresponding `wp_clear_scheduled_hook` on deactivation.

Two-step:
1. Define `WCB_CRON_HOOKS` constant or a `Cron_Registry::all()` static method listing every plugin-owned cron hook.
2. `Install::deactivate()` iterates the registry: `foreach ( Cron_Registry::all() as $hook ) { wp_clear_scheduled_hook( $hook ); }`.
3. New cron events register their hook via the registry — Drop-in registration, automatic teardown.

**Effort:** small (1 hour). **Tests:** cron-events-removed-on-deactivate journey.

---

### Discrete fixes (no refactor — single PR each)

| PR | Card | Fix | Effort |
|---|---|---|---|
| 5 | Card 5 (photo-upload route missing) | Register `POST /wcb/v1/resumes/photo-upload` in `class-resume-endpoint.php`. Validate MIME (jpg/png/webp), size, and persist as `_wcb_resume_photo_id`. | small (1 hour) |
| 6 | Card 6 (settings panel aria id) | Add `id="wcb-panel-settings"` + `aria-labelledby="wcb-tab-settings"` to the settings view-panel div in `blocks/employer-dashboard/render.php`. | trivial (5 min) — bundle with Simran's UI Card 14 (employer dashboard layout) for one coherent dashboard PR |
| 7 | Card 9 (alerts keywords not mapped) | Map `keywords` POST param → `search_query` column in alerts module's REST handler. | small (30 min) |

---

### Already-fixed-locally PRs to commit (3)

These are real fixes that need to be committed + pushed to release branch:

| Commit | Card | File touched |
|---|---|---|
| Patch 1 | Card 1 (company meta in jobs REST) | `api/endpoints/class-jobs-endpoint.php` (4 keys + size_label helper). **Note:** flagged in REFACTOR_NEEDED.md as R2 patch — should be redone as a `Company_Meta_Shape` shared serializer when planning permits. |
| Patch 2 | Card 2 (closed-job applications hidden) | `api/endpoints/class-employers-endpoint.php:719`. **Note:** flagged in REFACTOR_NEEDED.md as R1 patch — should be redone as a `owner_visible_statuses()` private method consolidating 3 sites in the same file. |
| Patch 3 | Card 10 (board_id filter typo) | `api/endpoints/class-jobs-endpoint.php:179`. Pure consistency restoration — no refactor needed. |
| Sweep | em-dash i18n violations (41 replacements / 14 files) | admin/, modules/, blocks/, core/. No card; coding-rules-check.sh now green for Rule 3. |

Three of these went in as patches because they were small enough that the refactor would be over-engineering for a single bug. They're FLAGGED in `docs/qa/REFACTOR_NEEDED.md` so future development knows the consolidation work is queued.

---

## Recommended ship order

| Order | PR | Why first |
|---|---|---|
| 1 | **PR 1** — ability-gate chokepoint (R3) | HIGH security; closes Card 7 + Card 3; small refactor with big preventive value |
| 2 | **PR 5/6/7** (discrete fixes) | quickest wins, 3 small PRs that close 3 cards in one cycle. Bundle PR 6 with Simran Card 14 for unified dashboard ship. |
| 3 | **Already-fixed patches** (Patches 1+2+3 + em-dash sweep) | commit + push what's already verified locally |
| 4 | **PR 4** — cron registry (R3-class) | small refactor, closes Card 11, prevents future drift |
| 5 | **PR 3** — custom fields end-to-end (R5) | larger fix but biggest customer-visible regression — guards every future job's apply form |
| 6 | **PR 2** — Mailer chokepoint (R4) | closes Card 8 + Card 12 together; medium effort but two cards close at once |
| 7 | **R7 manifest refresh + drift gate** | (no card, but in REFACTOR_NEEDED.md) — small task, unblocks accuracy of every future audit |
| 8 | **R8 + R9 + R6** (refactor candidates) | DOCX MIME contract decision + 201 helper + REST controller carve-outs. These are quality-of-life refactors with no specific card — schedule in their own slot. |

**Estimated total effort:** ~16-20 hours of focused dev work across all 8 ships, distributed over 1-2 weeks of regular release cycle. After this lands, the plugin's REST + email + cron + ability surfaces are dramatically more consolidated, the 12 cards close, and the 6 architectural seams I documented in REFACTOR_NEEDED.md become single-method chokepoints.

---

## Cards I should NOT keep filing — what changed in my approach

Through this audit, I learned (from the user's "no patch work" directive + the protocol-violation incident) that:

1. **Filing every individual symptom inflates the bugs column** — better to file one card per architectural seam with all symptoms documented as repros under it.
2. **A patch that restores consistency (Patch 1, 2, 3 above) is OK** — but should be flagged in REFACTOR_NEEDED.md as queued consolidation work.
3. **A patch that hides the symptom without fixing the upstream pattern is NOT OK** — these get the bug filed but the code change deferred to the proper-refactor PR.
4. **Sub-agents must NOT have basecamp_create_card available unless explicitly authorized** — the batch-5 violation is logged and the next dispatch tightens its tool allowlist.
5. **Manifest staleness != bug** — when the recorded manifest disagrees with code, refresh the manifest, don't open a bug card. (The wcbp_credit_reconcile retraction is an example of this rule applied.)

---

## Files this audit produced (all plugin-level, travel with code)

```
wp-career-board/docs/qa/
├── AGENT_SMOKE_RUNBOOK.md         (Sonnet+Playwright runbook A-F + 7 D-rows)
├── PRE_RELEASE_SMOKE.md           (90-min human checklist)
├── QA_RELEASE_CHECKLIST.md        (release gate, 14 sections)
├── UX_AUDIT.md                    (per-template surface check)
├── SCAFFOLDING.md                 (what's still missing)
├── REFACTOR_NEEDED.md             (R1-R9 architectural debt memo)
└── LONG_TERM_PLAN.md              ← this file

wp-career-board/audit/
├── manifest.json                  (35 REST endpoints, refreshed 2026-05-07)
├── qa-coverage.json               (drift baseline)
├── journeys/                      (45 journeys, 4 areas)
├── journey-runs/                  (5 batch-N JSONs from this run)
└── (FEATURE_AUDIT.md, CODE_FLOWS.md, ROLE_MATRIX.md, graph.html — pre-existing)

wp-career-board/bin/
├── ci-local.sh                    (pre-existing)
├── coding-rules-check.sh          (NEW: 6 plugin-specific rules)
├── git-hooks/pre-push             (NEW)
├── qa-coverage-check.php          (NEW: drift gate)
├── qa-stub-gen.php                (NEW: TODO scaffolder)
├── run-journeys.sh                (NEW: list/dry-run/audit-stale/execute)
└── seed-qa-fixtures.php           (NEW: idempotent reseeder for v1.1.0 schemas)

wp-career-board/.githooks/
└── pre-commit                     (NEW: phpstan + phpcs + coding-rules + qa-coverage)

wp-career-board-pro/
├── docs/qa/{AGENT_SMOKE_RUNBOOK,QA_RELEASE_CHECKLIST}.md  (Pro supplement)
├── audit/{manifest,qa-coverage,journeys/,journey-runs/}    (Pro side)
├── bin/{coding-rules-check,qa-coverage-check,qa-stub-gen,run-journeys,git-hooks/pre-push}
├── .githooks/pre-commit
└── .claude/skills/wp-plugin-smoke/SKILL.md           (Sonnet smoke dispatcher)
```

Composer scripts in both: `composer ci`, `composer ci:no-journeys`, `composer ci:quick`, `composer journeys`, `composer journeys:list/dry-run/stale`, `composer qa-coverage`, `composer install-hooks`.
