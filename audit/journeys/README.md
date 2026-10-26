# Customer journeys — wp-career-board

Journeys are commit-time regression sentinels: every customer-visible bug fix authors (or updates) a journey that, if executed, would have caught the bug. Journeys verify **specific contracts**, not whole UIs.

## Three walkthrough layers (which one is for what)

The same flows are written down at three levels. They are not duplicates to merge: each has a different reader.

| Layer | Where | What it is for | What reads it |
|---|---|---|---|
| Regression sentinels | `audit/journeys/**` (this folder, including its `walkthrough-*.md` files) | Small contracts that guard one bug or one security/system rule, plus one consolidated `walkthrough-*` per area | `bin/run-journeys.sh` (lists, filters by frontmatter `priority`, prints the journey and writes a `manual_required` report), `bin/check-journey-routes.php` (every `/wcb/v1` path must resolve to a registered route), `bin/check-qa-portability.sh`. The release smoke is run with the global `/wp-plugin-smoke` skill |
| Human-runnable walkthroughs by actor | `docs/qa/walkthroughs/{seeker,poster,admin}/` | The step-by-step *how* behind each row of `docs/qa/COMMON_USE_CASES.md`; one per catalog use case | People and agents walking a release; `bin/check-qa-portability.sh` (no machine-pinned paths). No script parses them |
| Feature walkthroughs | `docs/journeys/` | One browser walkthrough per feature through every role (anonymous, candidate, employer, admin) | People and agents; no script reads them. Their `/wcb/v1` paths are NOT covered by the route gate |

Rule of thumb: a bug fix updates the sentinel here; a new customer flow updates the actor walkthrough; a new feature adds its feature walkthrough. The `walkthrough-*.md` files here deliberately overlap the other two layers because `bin/run-journeys.sh` only reads this folder.

## Where journeys live

```
audit/journeys/
├── customer/   ← end-user flows (apply to job, save job, edit profile)
├── admin/      ← admin-only flows (settings save, moderation actions, role changes)
├── security/   ← auth-gate verifications (anon can't apply, employer can't see other companies)
├── system/     ← cron, webhook, background-job verification
├── README.md   ← this file
└── .template.md ← copy this when authoring a new journey
```

## Journey schema

Every journey is a single Markdown file with frontmatter + numbered steps. The executor (`bin/run-journeys.sh`) parses the frontmatter to know:

- `id` — short kebab-case identifier (`apply-to-job`)
- `priority` — `critical` | `high` | `medium` (`bin/run-journeys.sh` selects by priority; the release gate is the smoke report, see below)
- `personas` — comma-separated user logins to walk the journey as
- `requires` — comma-separated prerequisites (`pro:credits`, `seed:jobs`, `mu:autologin`)
- `last_verified` — ISO date when the journey was last manually walked
- `bug_ref` — Basecamp / GitHub issue ID this journey guards (if any)

Steps are imperative, one action per step, with an explicit assertion.

```markdown
1. As `candidate.carol`, navigate to `/jobs/<published-job>/` → expect HTTP 200
2. Click "Apply" → expect modal to open with resume picker
3. POST `/wcb/v1/jobs/<id>/apply` with body `{resume_id:<id>}` → expect 200, response `{application_id: <int>}`
4. Reload `/dashboard/applications/` → expect new row matching `<application_id>`
5. tail debug.log diff → expect ZERO new fatal/warning lines
```

Every assertion is a contract. If the assertion fails, the journey fails.

## When to author a journey

| Trigger | Action |
|---|---|
| Customer-visible bug fix | Author or update a journey that would have caught it. Same PR. |
| New REST endpoint | Add a security journey verifying its permission_callback. |
| New admin action | Add an admin journey that performs the action and asserts the side-effect. |
| Pro feature ship | Add a Pro journey using `requires: pro:<module>`. |

## Reading vs running

Reading a journey: it's English. Anyone on the team can walk it manually in 2 minutes.

Running a journey: `bin/run-journeys.sh` selects and prints it (see below). It does not drive a browser itself: it prints each selected journey and writes `audit/journey-runs/<journey-id>-<timestamp>.json` (git-ignored, created on first run) with `status: manual_required`. Automated execution is the global `/wp-plugin-smoke` skill (Sonnet + Playwright MCP).

The executor is forgiving on selectors (treat them as suggestions); strict on contracts (REST status, DB row, computed style).

## Critical journeys (must be green to release)

List them with `grep -l 'priority: critical' -r audit/journeys`. The global `/wp-plugin-smoke` skill walks the plugin's critical flows as part of its release pass and writes `docs/qa/.last-smoke-pass.json`; `bin/build-release.sh` blocks packaging when that report is missing, for another version, stale, or lists failures. `composer journeys` itself only prints and records `manual_required`, so it cannot fail a build on its own.

## Differences vs the smoke runbook

| | Runbook (sections A–F) | Journeys |
|---|---|---|
| Scope | Whole-plugin per release | Per-bug-fix sentinel |
| Cadence | Pre-release, 25-min walk | On-demand and at release; `composer journeys:stale` flags old ones |
| Granularity | "verify the contract for this feature" | "verify this exact symptom doesn't return" |
| Authoring | One author per minor version | Co-located with the bug-fix PR |
| Failure mode | Blocks tag (smoke report gate in `bin/build-release.sh`) | Caught when the sentinel is walked; `composer journeys:routes` fails on a dead route reference |

Both are needed. Runbook catches "we missed a category"; journeys catch "this exact regression came back."

## Maintenance

- Every journey gets re-walked at least once per minor release. Update `last_verified`.
- A journey whose `bug_ref` has been fixed AND green for 2 minor releases can graduate into the runbook (Section C/E) and be removed from `audit/journeys/`.
- Stale journeys (`last_verified` >90 days) are flagged by `bin/run-journeys.sh --audit-stale`.
