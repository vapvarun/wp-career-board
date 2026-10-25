# Cycle audit - WP Career Board 1.8.2

Audit of everything done between `v1.8.1` (2026-10-14) and branch `1.8.2` HEAD (Free `163532c`, Pro `d526981`), run 2026-10-25. It answers three questions: was every change justified by a card or a written decision, is every fix guarded against coming back, and did the cycle leave duplicate or dead code. Read-only audit; the fixes it proposes are listed under Plan.

Scope: 58 Free and 41 Pro commits, about 6,600 changed lines of code (docs, audit, languages and tests excluded), 157 Basecamp cards. Evidence files: the per-card ledger and both code sweeps are kept outside the repo at `~/work-artifacts/scratch/bc/audit-1.8.2/` (`card-ledger.md`, `ponytail-free.md`, `ponytail-pro.md`).

## 1. Is the work justified?

| Kind | Cards |
|---|---|
| Fix | 133 |
| Fix with an owner decision | 6 |
| Fix, re-fixed after a QA fail | 5 |
| By design (reasoned, no code change) | 6 |
| Not replicated (refuted with the attempt) | 3 |
| Refuted as written, real gap fixed | 1 |
| New cards found during the cycle | 3 |

- Every card's developer comment names a root cause, the fix and its commits. All 90 cited commits (52 Free, 38 Pro) are on branch `1.8.2` in the repo the comment names: no missing, amended or mislabelled SHA.
- Every by-design and refuted card states a concrete reason: a measurement, a network log, the attempt typed, or a published reference.
- Owner decisions are recorded on the card for 10401941306, 10402937387, 10407369087, 10422877832, 10437969521 and 10439859249 (pipeline sets Reviewing).
- Board at audit time: 156 Done, 1 Ready for Testing (10439937451), Bugs 1 (filed by this audit, below), Possible Bugs 0.

Commits with no card behind them (17) are housekeeping and are justified by their subject: translation readiness and POT checks, README refreshes, QA coverage and manifest counts, coding-standard alignment, hand-off docs. Two are not housekeeping, see gap G2.

## 2. Gaps

| # | Gap | Why it matters | Action |
|---|---|---|---|
| G1 | **Job Form (Simple) never sends a typed "Other" location.** `blocks/job-form-simple/view.js:290` sends `locations: ['__custom__']` and no `location_custom`; the full form does (`blocks/job-form/view.js:764`). Replayed as an employer: with Require a job location on, the job is refused (400 `wcb_location_required`); with it off, the location is silently lost. A QA agent noted it on 10412882677, but no card was filed. | A real functional bug on a customer form. | Filed as **10440004221** (Bugs). Fix in 1.8.2. |
| G2 | **Two money changes have no card:** Pro `2ca7530` (Credits tab confirms a purchase the webhook already credited) and `da6f089` (Credits SDK 1.10.3, Stripe and PayPal webhooks verify again). They are guarded by the credit-purchase journey (`c5c0c39`), but QA was never asked to verify them. | Money paths are the highest-risk surface. | One verification card in Ready for Testing citing both commits and the journey. |
| G3 | **32 QA passes record something not run**, e.g. a real Stripe sandbox purchase (10439881291), a real Mapbox token (10407369087, 10439828930), a real German `.mo` (10437969521), a real AI provider call (10404086478), and kanban drag plus candidate view (10439859249). | Each is the side effect that usually breaks. | Cover them in the pre-release `/wp-plugin-smoke` run, not by reopening cards. |
| G4 | **19 fixes are guarded only by a runbook row**, not a `card:<id>` journey line (10409512100, 10407360943, 10421607707, 10403057955, 10403056516, 10403985914, 10407369087, 10437714777, 10417744413, 10417744155, 10417743899, 10417744280, 10421607738, 10403606771, 10401941213, 10417744512, 10417743747, 10401941306, 10417125333). | A runbook row is read by people; a journey line is replayed by the release walk. | Add the journey line to the journey the hand-off already names for each. |
| G5 | **Two by-design answers offer the owner a choice that was never answered:** 10418355276 (keep the 400 or clamp) and 10439833492 (panel links stay text-size on a mouse). 10421191546 ("remove the control") is a developer decision. | Policy calls belong to the owner. | Owner answer, recorded on each card. |
| G6 | **`audit/manifest.json` was last refreshed for 1.8.0** (2026-09-30). This cycle added methods, filters and state keys. | The manifest is the first thing every next change reads. | `/wp-plugin-onboard --refresh` at release, as the release checklist requires. |
| G7 | The Credits SDK 1.11.0 bundle card (10439881291) has no developer comment; the SDK merge commit lives upstream (wbcom-credits-sdk PR #33, tag `v1.11.0`). | Traceability only. | A one-line comment linking the upstream tag. |

QA verdicts and developer comments come from the same account; the QA passes were run as separate sessions under the "whoever wrote a fix does not verify it" rule.

## 3. Duplicate and dead code (ponytail sweep)

Every finding below was checked with a grep across both repos. No `data-wp-*` directive points at a missing getter in either plugin. The equal-height panel rule added in `163532c` is lean.

### Introduced or touched this cycle (fix in 1.8.2)

| Tag | Finding | Fix |
|---|---|---|
| dup | Pro `ResumeAccess::render_gate()` echoes its own gate markup and CSS (`.wcb-resume-access-gate` in resume-archive and resume-single `style.css`) while Free ships `templates/parts/dashboard-gate.php`, which Pro's own application-kanban already uses. | Build a `$wcb_gate` array and require Free's template; delete both CSS copies. |
| dup | Post a Job preview hides and shows chips with its own `wcb-cbadge--show` class and display rules, beside the shared `.wcb-cbadge.wcb-shown`. The six chip modifiers in `wcb-ui.css` that paint the same neutral colour can share one rule. | Use `data-wp-class--wcb-shown`; drop the preview display rules; fold the modifiers. |
| dup / delete | Two theme-link-defence underline blocks in `frontend-components.css` (lines ~1221 and ~1245); the `.wcb-chip` arm matches no markup in Free or Pro. | Merge into one block; drop `.wcb-chip`. |
| shrink | `.wcb-cb-history-toggle` (Pro credit-balance) hand-builds an outline button. | `wcb-btn wcb-btn--secondary` plus a full-width, space-between modifier. |
| shrink | Pro `admin.css` keeps grid track lists on `.wcbp-fields-header` / `.wcbp-field-row` that the subgrid rule always overrides. | Delete the dead declarations. |
| shrink | Pro pipeline Reviewing branch re-resolves the board through job meta although `$stage['board_id']` is in hand. | Use the stage's board. |
| delete | State and i18n nothing reads: `customFieldGroups` (2 Free dashboards, 3 Pro resume forms), credit-balance `nativeOk`, `customEnabled`, `billingIncomplete`, `confirming`, `canceled`; kanban `columnHeader`; candidate dashboard `confirmErase*`; a stale `termLabels` comment; a duplicate `.wcb-jm-radius-select:focus-visible`. | Delete. |
| fix | Reduced-motion `transition: none` lost specificity to this cycle's rescoped dashboard search inputs. | Scope the override the same way. |

### Pre-existing, structural (plan for 1.8.3, not at release time)

| Tag | Finding | Size |
|---|---|---|
| dup | Pro job-map and resume-map toolbars re-implement `.wcb-btn--primary/--secondary` and copy each other's toolbar CSS. | about -210 |
| delete | Pro email templates under `modules/notificationspro/templates/emails/` and `add_template_dir()` are unreachable: Free's `render_body()` calls `render_template()` only when a theme override exists, and the override wins first. Decide the `wcb_email_template_dirs` filter's future in the same change (it is a documented hook). | about -97 |
| dup | Pro `resume-search-hero` restates Free's `job-search-hero` rules on the same markup. | about -80 |
| dup | Block registration loop and `enqueue_block_style_array()` identical in Free and Pro. | about -40 |
| dup | Free admin list tables repeat filter-chip markup, hidden-input loops and `is_trash_view()` 3 times each; move into the `ListEmptyState` trait. | about -47 |
| dup | Free `enqueue_admin_editor_assets` re-implements `Plugin::enqueue_editor_assets`. | about -30 |
| dup | Job Form and Job Form (Simple) duplicate the dashboard URL, page heading, default-board lookup, salary check and `isGroupOnBoard`. Fixing G1 is the moment to start sharing these. | about -15 |
| dup | The CV mime list (pdf/doc/docx) is written out in 5 places across Free and Pro. | Free constant |
| other | `wcb_registered_emails` loop ×4, five identical `*Invalid` getters in employer-registration, `wcb_title_required_post_types` filter with no consumer, JS copy of `AlertsModule::TERM_FILTERS`, registration invalid-border rules that lose the cascade. | about -40 |

Net possible: about -755 lines (Free -180, Pro -575); about -120 of that is this cycle's own code.

## 4. Plan

1. **1.8.2, before release:** fix G1 (10440004221) and the "introduced this cycle" table as one cleanup commit per plugin, each browser-checked at 1440 and 390, with journey lines. Then G2 card, G4 journey lines, G7 comment, and the owner answers for G5.
2. **Release gate:** `/wp-plugin-smoke` covering the G3 not-run list, then the G6 manifest refresh.
3. **1.8.3:** the structural table, one seam per commit (map toolbar, email templates, search hero, registration helper, list-table trait, editor assets, form sharing, CV mimes).
