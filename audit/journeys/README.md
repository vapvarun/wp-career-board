# Customer journeys — wp-career-board

Journeys are commit-time regression sentinels: every customer-visible bug fix authors (or updates) a journey that, if executed, would have caught the bug. Journeys verify **specific contracts**, not whole UIs.

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
- `priority` — `critical` | `high` | `medium` (critical journeys block CI on failure)
- `personas` — comma-separated user logins to walk the journey as
- `requires` — comma-separated prerequisites (`pro:credits`, `seed:jobs`, `mu:autologin`)
- `last_verified` — ISO date when the journey was last manually walked
- `bug_ref` — Basecamp / GitHub issue ID this journey guards (if any)

Steps are imperative, one action per step, with an explicit assertion.

```markdown
1. As `candidate.carol`, navigate to `/jobs/<published-job>/` → expect HTTP 200
2. Click "Apply" → expect modal to open with resume picker
3. POST `/wcb/v1/jobs/<id>/apply` with body `{resume_id:<id>}` → expect 201, response `{application_id: <int>}`
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

Running a journey: `bin/run-journeys.sh` orchestrates Playwright MCP + WP-CLI to execute it deterministically. Output lands in `audit/journey-runs/<journey-id>-<timestamp>.json`.

The executor is forgiving on selectors (treat them as suggestions); strict on contracts (REST status, DB row, computed style).

## Critical journeys (must be green to release)

The smoke skill (`wp-career-board-smoke`) reads `audit/journeys/*/` for journeys with `priority: critical` and walks them as part of Section C/E. If any critical journey fails, the release gate blocks.

## Differences vs the smoke runbook

| | Runbook (sections A–F) | Journeys |
|---|---|---|
| Scope | Whole-plugin per release | Per-bug-fix sentinel |
| Cadence | Pre-release, 25-min walk | Pre-commit + on-demand |
| Granularity | "verify the contract for this feature" | "verify this exact symptom doesn't return" |
| Authoring | One author per minor version | Co-located with the bug-fix PR |
| Failure mode | Blocks tag | Blocks commit (via pre-push hook) |

Both are needed. Runbook catches "we missed a category"; journeys catch "this exact regression came back."

## Maintenance

- Every journey gets re-walked at least once per minor release. Update `last_verified`.
- A journey whose `bug_ref` has been fixed AND green for 2 minor releases can graduate into the runbook (Section C/E) and be removed from `audit/journeys/`.
- Stale journeys (`last_verified` >90 days) are flagged by `bin/run-journeys.sh --audit-stale`.
