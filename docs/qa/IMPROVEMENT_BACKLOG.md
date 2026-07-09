# WP Career Board (Free) — Improvement Backlog

> Consolidated, deduplicated improvement plan created 2026-06-29 after the 1.5.1 release.
> Paired with Pro's `../../../wp-career-board-pro/docs/qa/IMPROVEMENT_BACKLOG.md` (lockstep product).
> Source signals reconciled: this repo's `REFACTOR_NEEDED.md` (R-list) + `LONG_TERM_PLAN.md`,
> code TODO scan (none real — only `bin/qa-stub-gen.php` boilerplate), Basecamp project 46502739
> Bugs column (1 suggestion card), and the gaps surfaced while grounding the 25 walkthrough
> journeys. **No duplicates** — each row cites its origin; closed refactors are not re-listed.

Priority: **P1** ship-soon (live-plugin correctness) · **P2** next · **P3** debt · **P4** nice-to-have.
Status: `open` | `in-progress` | `done`.

## Theme A — Functional gaps (shared; Pro-led but touches Free)

| ID | Pri | Status | Item | Source |
|----|-----|--------|------|--------|
| A4 | P2 | open | Reconcile notification events — confirm Free moderation report → Pro bell fires on real events (no `wcb_job_reported`); align naming/docs | `walkthrough-notifications.md` + `walkthrough-moderation-and-reporting.md` |

## Theme B — Lock in QA (shared with Pro)

| ID | Pri | Status | Item | Source |
|----|-----|--------|------|--------|
| B1 | P2 | open | Wire the 25 walkthrough journeys into `bin/run-journeys.sh` + qa-coverage; set priorities; confirm each parses + executes | this session |
| B2 | P2 | open | Browser-verify the critical paths (find-jobs-and-apply, employer-post-job) run green in Playwright | this session |

## Theme C — Code-quality debt (from REFACTOR_NEEDED.md)

| ID | Pri | Status | Item | Source |
|----|-----|--------|------|--------|
| C1 | P3 | open | Reconcile `REFACTOR_NEEDED.md` status — mark R1 (`owner_visible_statuses`), R2 (`CompanyMetaShape`), R3/R4/R5 CLOSED; keep R6–R11 | REFACTOR_NEEDED drift |
| C2 | P3 | open | R6 — convert the 2 REST `register_rest_route()` carve-outs (setup wizard + moderation) to Endpoint classes extending `REST_Controller` | REFACTOR_NEEDED R6 |
| C3 | P3 | open | R8 — resume MIME upload/display contract: upload accepts more types than the single/archive views can render; align the accept-list | REFACTOR_NEEDED R8 |
| C4 | P3 | open | R9 — POST creates return 200 not 201; add a base-controller `created()` helper and adopt across create endpoints (JS `status===201` branches dead-end today) | REFACTOR_NEEDED R9 |
| C5 | P3 | open | R10 — move 3 inline `<script>`/`<style>` blocks in admin PHP to enqueued assets | REFACTOR_NEEDED R10 |

> R7 (manifest staleness) and R11 (reserved-word policy) remain noted in `REFACTOR_NEEDED.md`;
> promote here when actioned.

## Theme D — i18n follow-ups (shared with Pro)

| ID | Pri | Status | Item | Source |
|----|-----|--------|------|--------|
| D1 | P2 | open | Block/JS `.json` ship strategy — Interactivity frontend won't translate from `.mo` alone; wire `bin/build-release.sh` to run `wp i18n make-json` into dist, or commit the `.json` | 1.5.1 i18n work |
| D2 | P4 | open | Add a `= 1.5.1 =` changelog block to `readme.txt` (lockstep with Pro) when the first 1.5.1 improvement ships | release hygiene |

## Theme F — Suggestions (Basecamp)

| ID | Pri | Status | Item | Source |
|----|-----|--------|------|--------|
| F1 | P4 | open | Tag autocomplete/suggestions on the post-a-job tags field | Basecamp card 10004811850 (already tracked there — do not re-file) |

> Pro-side items (A1 CSV idempotency, A2 analytics dashboard, A3 job-map pins, C6 Pro refactor
> backlog, E1 Pro getting-started doc) live in Pro's backlog.
