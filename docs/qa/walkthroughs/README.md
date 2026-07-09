# WP Career Board (Free) — Walkthrough Journeys

Docs-level, QA-facing **end-to-end walkthroughs**, one per common use case, organized by
actor. This is the human-runnable companion to the Pareto catalog
([`../COMMON_USE_CASES.md`](../COMMON_USE_CASES.md)) — the catalog says *what* to check;
these walkthroughs are the *how*, step by step, grounded in real `file:line`.

- Pro adds its own set under [`../../../wp-career-board-pro/docs/qa/walkthroughs/`](../../../wp-career-board-pro/docs/qa/walkthroughs/).
- Regression sentinels (security/system contracts, per-bug journeys) still live in
  [`../../audit/journeys/`](../../audit/journeys/) — these walkthroughs consolidate the
  customer/admin *flows*; audit/journeys keeps the fine-grained guards.

## Index

### 🔎 Job Seeker (`seeker/`)
| Walkthrough | Covers |
|---|---|
| [browse-search-filter-jobs](seeker/browse-search-filter-jobs.md) | Find Jobs archive · keyword search · filter chips (1.5.1 `wcb:results`) |
| [apply-to-a-job](seeker/apply-to-a-job.md) | Apply as guest + candidate · email/URL routing (money path) |
| [register-candidate-account](seeker/register-candidate-account.md) | Candidate registration · account settings |
| [candidate-dashboard](seeker/candidate-dashboard.md) | Dashboard · view applications · edit profile |
| [bookmarks](seeker/bookmarks.md) | Bookmark a job · bookmark a company |
| [companies-directory](seeker/companies-directory.md) | Browse companies · view a company profile |
| [discovery-widgets](seeker/discovery-widgets.md) | Featured / recent / stats blocks · `[wcb_widget]` shortcode |

### 📝 Job Poster / Employer (`poster/`)
| Walkthrough | Covers |
|---|---|
| [register-employer](poster/register-employer.md) | Employer registration → dashboard (**new coverage**) |
| [post-a-job](poster/post-a-job.md) | Post a job · credits opt-in · board picker (money path) |
| [manage-applicants](poster/manage-applicants.md) | Applicants list · change status (1.5.1 reorder filter) |
| [edit-job-and-company](poster/edit-job-and-company.md) | Edit job · edit company · resubmit rejected |
| [orphan-handling](poster/orphan-handling.md) | Orphan job adopted · orphan application shows "removed" |

### ⚙️ Admin (`admin/`)
| Walkthrough | Covers |
|---|---|
| [setup-wizard](admin/setup-wizard.md) | First-run setup wizard |
| [settings-tabs](admin/settings-tabs.md) | General/Jobs/Antispam save · save-merge |
| [jobs-and-moderation](admin/jobs-and-moderation.md) | Jobs list · bulk actions · moderation queue |
| [emails](admin/emails.md) | Templates · merge tags · **1.5.1 configurable message body** |
| [applications-and-candidates](admin/applications-and-candidates.md) | Applications list + export · candidates list |
| [companies-and-employers](admin/companies-and-employers.md) | Companies + edit meta · employers + ban |
| [taxonomies-and-roles](admin/taxonomies-and-roles.md) | Taxonomy CRUD · user role change |
| [gdpr](admin/gdpr.md) | Personal-data export + erase |

## How to run

Each file is plain English — walk it manually in the browser (~2–5 min), or execute
deterministically via the runner:

```bash
cd wp-content/plugins/wp-career-board
composer journeys:list        # discover
composer journeys             # run priority=critical
bin/run-journeys.sh <path>    # run a single walkthrough
```

For a full pre-release admin+member sweep, the `/wp-plugin-smoke` skill walks these as
part of Sections C/E.

### Persona switching — auto-login gotcha (read before walking)

The dev auto-login mu-plugin (`?autologin=<login>`) **no-ops when a session already
exists** — its guard is `if ( is_user_logged_in() ) return;`. So when a walkthrough moves
between actors (e.g. admin → candidate → employer), you MUST log out first, or you stay
as the previous user and see the wrong UI (an admin/owner sees "View Applications", never
"Apply Now"). Log out between personas:

```
/wp-login.php?action=logout   → click the confirm link → then ?autologin=<next-login>
```

Canonical logins are seeded by `bin/seed-qa-fixtures.php` and match `docs/qa/qa-config.json`:
`employer.figma` / `employer.stripe` (wcb_employer), `sarah.chen` / `marcus.williams` /
`wcbp_p5_candidate` (wcb_candidate), `morgan_moderator` (wcb_board_moderator), `siobhan`
(subscriber), `varundubey` (admin). Re-seed with:
`wp eval 'require "wp-content/plugins/wp-career-board/bin/seed-qa-fixtures.php";'`.

## Refresh (every release)

1. Re-check the touch-point inventory (see [`../COMMON_USE_CASES.md`](../COMMON_USE_CASES.md) § Refresh).
2. For any use case new/changed this cycle, update the matching walkthrough and bump its
   `last_verified`.
3. Anything flagged 🆕 in the catalog gets a re-walk before tag.

Legend for frontmatter: `priority` critical|high|medium · `personas` logins walked ·
`requires` prerequisites · `covers` surfaces exercised · `last_verified` last manual walk.
