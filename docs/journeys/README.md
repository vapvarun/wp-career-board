# WP Career Board — Feature Journeys (browser walkthroughs)

One journey per feature. Each journey walks the **whole feature in the browser**,
through every role that touches it (Anonymous → Candidate → Employer → Admin).
This is the human/Playwright walkthrough layer — readable, role-organised, and
complete — distinct from `audit/journeys/**` (the small automated CI regression
sentinels) and `docs/qa/AGENT_SMOKE_RUNBOOK.md` (the release smoke).

## The rule

**Every feature has exactly one journey here. Add a new feature → add its journey
in the same PR.** A feature with no journey is not done. (The drift gate in
`audit/qa-coverage.json` flags manifest entries with no coverage.)

**Route gate:** every `/wcb/v1/...` path a journey references must resolve to a
route the plugin actually registers — enforced by `bin/check-journey-routes.php`
(`composer journeys:routes`), which also runs as a pre-flight in
`bin/run-journeys.sh` before any journey executes. A journey naming a
renamed/removed route fails the run, so journeys can't silently drift from the API.

## Per-feature journey template

```markdown
---
feature: <block / admin page / module name>
roles: anonymous, candidate, employer, admin   # only the ones that apply
surface: <frontend block | admin page | REST + UI>
last_walked: YYYY-MM-DD
---

# <Feature> — full browser walkthrough

**What it is:** <one line.>
**Where it lives:** <page URL(s) / admin slug / block name.>

## As anonymous
1. Navigate to `<url>` → expect <state>.
2. ...

## As candidate
1. `?autologin=wcb_demo_candidate` → `<url>` → ...

## As employer
1. `?autologin=wcb_demo_employer` → `<url>` → ...

## As admin
1. `?autologin=1` → `<admin url>` → ...

## Themes & states
- Reign / BuddyX / BuddyX dark at 1440px + 390px (only where UI differs).
- Empty state, loading, error, RTL where applicable.

## Contracts guarded
- <e.g. focus rings, dark-mode readability, dependency-gating, REST↔JS shape.>
```

## Feature index (one row = one journey)

### Free — frontend
| Feature | Journey file | Primary roles |
|---|---|---|
| Browse job listings | `browse-job-listings.md` | anon, all |
| Job search + hero | `job-search.md` | anon, all |
| Job filters sidebar | `job-filters.md` | anon, all |
| Job single + apply | `job-single-and-apply.md` | anon → candidate |
| Featured / recent / stats blocks | `jobs-showcase-blocks.md` | anon, all |
| Companies browse / single | `companies-browse.md` | anon, all |
| Candidate dashboard | `candidate-dashboard.md` | candidate |
| Candidate bookmarks / saved | `candidate-saved.md` | candidate |
| Employer dashboard | `employer-dashboard.md` | employer |
| Employer registration | `employer-registration.md` | logged-in → employer |
| Post a job (job-form) | `employer-post-job.md` | employer |
| Company profile edit | `employer-company-profile.md` | employer |
| Applications review | `employer-applications.md` | employer |

### Free — admin
| Feature | Journey file | Primary roles |
|---|---|---|
| Jobs admin (CRUD + flags) | `admin-jobs.md` | admin, moderator |
| Applications admin | `admin-applications.md` | admin |
| Candidates admin | `admin-candidates.md` | admin |
| Companies admin | `admin-companies.md` | admin |
| Employers admin (ban/unban) | `admin-employers.md` | admin |
| Settings | `admin-settings.md` | admin |
| Emails | `admin-emails.md` | admin |
| Setup wizard | `admin-setup-wizard.md` | admin |
| Import | `admin-import.md` | admin |
| Moderation (report → resolve) | `moderation.md` | any → moderator |
| Notifications | `notifications.md` | candidate, employer |
| GDPR export / erase | `gdpr.md` | admin, candidate |

### Pro
| Feature | Journey file | Primary roles |
|---|---|---|
| AI chat search | `pro-ai-chat-search.md` | candidate |
| AI auto-rank / match | `pro-ai-rank.md` | employer, admin |
| Credits balance + buy | `pro-credits.md` | employer |
| Credits ledger / mappings | `pro-credits-admin.md` | admin |
| Field builder | `pro-field-builder.md` | admin |
| Job boards | `pro-job-boards.md` | admin, employer |
| Job alerts | `pro-job-alerts.md` | candidate |
| Resume form + single | `pro-resume.md` | candidate |
| Open to work | `pro-open-to-work.md` | candidate |
| My applications | `pro-my-applications.md` | candidate |
| PWA | `pro-pwa.md` | all |
| Maps / location | `pro-maps.md` | all, admin |
| BuddyPress integration | `pro-buddypress.md` | admin, members |
| Analytics | `pro-analytics.md` | admin |

> Pro journeys live in `wp-career-board-pro/docs/journeys/` (same template).

## Personas (auto-login)
`?autologin=1` admin · `?autologin=wcb_demo_employer` · `?autologin=wcb_demo_candidate`.
Seed demo content via the Setup Wizard before walking.
