# WP Career Board — Role / Permission Matrix

**Generated**: 2026-04-29
**Source**: [`audit/manifest.json`](manifest.json) (capabilities, REST permissions)

Permissions are checked through the **Abilities API** (`wp_register_ability` /
`wp_is_authorized`) — not `current_user_can( 'manage_options' )`.

Legend: **C**reate · **R**ead · **U**pdate · **D**elete · **—** no access.

---

## The role ladder — who QA logs in as (wp-card-qa §1.5)

Walk top-down on the surface a card names. Stop at the first row that disagrees
with the tables below; that disagreement IS the finding.

| # | Rung | Persona (`docs/qa/qa-config.json` → `personas`) | Why it is its own row |
|---|---|---|---|
| 1 | Reporter's role | whichever the card names | The claim is about this view. Everything else is context. |
| 2 | Anonymous | — (logged out) | Login gates, public surfaces, and what leaks before auth. |
| 3 | Member — owner | `employer` = `employer.figma` · `candidate` = `sarah.chen` | The authenticated happy path, on their OWN content. |
| 4 | Member — **not** owner | `employer_other` = `employer.stripe` · `candidate_other` = `marcus.williams` | The other member's content. Where privacy and permission bugs actually live. |
| 5 | Elevated | `moderator` = `morgan_moderator` (`wcb_board_moderator`) | Only for surfaces the moderation ability touches. |
| 6 | Admin | `admin` = `varundubey` | **Last, never first.** An administrator bypasses nearly every gate below, so a permission card confirmed only as admin is not confirmed. |

Rows 3 and 4 are **two different logins, not one**. With a single account the
owner can always see their own item, so "works for me" is guaranteed and
meaningless — which is why `bin/qa-fixtures.sh` fails if either second member is
missing rather than letting a session start on a ladder that cannot detect that
class of bug.

`subscriber` = `siobhan` is the seventh persona: a logged-in user holding no
`wcb_*` role. Use it to check that a surface gates on the ability rather than on
`is_user_logged_in()` — a distinction that hid three unenforced abilities until
1.7.1.

---

## Custom plugin roles

| Role slug | Source | Granted abilities |
|---|---|---|
| `wcb_employer` | `core/class-roles.php:add_employer_role` | `wcb_post_jobs`, `wcb_manage_company`, `wcb_view_applications`, `wcb_access_employer_dashboard` |
| `wcb_candidate` | `core/class-roles.php:add_candidate_role` | `wcb_apply_jobs`, `wcb_manage_resume`, `wcb_bookmark_jobs`, `wcb_access_candidate_dashboard` |
| `wcb_board_moderator` | `core/class-roles.php:add_moderator_role` | `wcb_moderate_jobs` |

> **Note on `wcb_admin` and `wcb_user`:** These role slugs were referenced in the onboarding spec, but the codebase itself does **not** define them. The plugin uses the WordPress core `administrator` role (granted every `wcb_*` ability through `Roles::add_admin_caps`) for admin functions, and any logged-in user (including the default `subscriber` role) is treated as a generic public/"user" surface for read-only access. The roles enumerated below are the canonical ones the plugin actually creates.

---

## Feature × Role matrix

| Feature / Surface | administrator | wcb_employer | wcb_candidate | wcb_board_moderator | subscriber (default) | guest (anon) |
|---|---|---|---|---|---|---|
| Browse job listings (`/jobs` GET) | R | R | R | R | R | R |
| Search (`/search` GET) | R | R | R | R | R | R |
| Public company directory (`/companies` GET) | R | R | R | R | R | R |
| Read single job | R | R | R | R | R | R |
| Bookmark job (`POST /jobs/{id}/bookmark`) | C | — | C | — | — | — |
| Apply to job (`POST /jobs/{id}/apply`) | C | — | C | — | — | C* |
| Resume upload (`POST /candidates/resume-upload`) | C | — | C | — | — | — |
| Candidate dashboard (block) | R | — | R | — | — | — |
| Update own candidate profile | RU | — | RU | — | — | — |
| Withdraw own application | D | — | D | — | — | — |
| Post a job (`POST /jobs`) | C | C | — | — | — | — |
| Update own job (`PUT /jobs/{id}`) | CRUD | RU (own) | — | — | — | — |
| Delete own job (`DELETE /jobs/{id}`) | D | D (own) | — | — | — | — |
| Employer dashboard (block) | R | R | — | — | — | — |
| Edit own company (`PUT /employers/{id}`) | RU | RU (own) | — | — | — | — |
| Upload company logo (`POST /employers/{id}/logo`) | C | C (own) | — | — | — | — |
| View applications received (`/employers/{id}/applications`) | R | R (own) | — | — | — | — |
| Update application status (`PUT /applications/{id}/status`) | U | U (employer of job) | — | — | — | — |
| Approve / reject jobs (`POST /jobs/{id}/approve|reject`) | CRUD | — | — | CRUD | — | — |
| Update company trust (`POST /companies/{id}/trust`) | CRUD | — | — | — | — | — |
| Settings pages (`/wp-admin/admin.php?page=wcb-*`) | CRUD | — | — | — | — | — |
| Setup wizard (`/wizard/*`) | CRUD | — | — | — | — | — |
| Import (`/import/run`, `/import/status`) | CRUD | — | — | — | — | — |
| Dismiss admin banner (`POST /admin/dismiss-banner`) | C | — | — | — | — | — |
| WP-CLI commands (`wp wcb …`) | CRUD | — | — | — | — | — |
| App config (`GET /settings/app-config`) | R | R | R | R | R | R |
| Self-register as employer (`POST /employers/register`) | — | — | — | — | — | C |
| Self-register as candidate (`POST /candidates/register`) | — | — | — | — | — | C |

\* Guest "apply" is allowed only when the **Allow guest applications** setting is on; the endpoint then issues a magic-link receipt via `Email_App_Guest`.

---

## Ability → endpoint cross-reference

| Ability | Used by REST permission_callback | Used by render-time gate |
|---|---|---|
| `wcb_post_jobs` | `JobsEndpoint::create_item_permissions_check`, `update_item_permissions_check` | `blocks/job-form/render.php`, `blocks/job-form-simple/render.php` |
| `wcb_manage_company` | `EmployersEndpoint::update_item_permissions_check`, `upload_logo` | `blocks/employer-dashboard/render.php` |
| `wcb_view_applications` | `JobsEndpoint::view_applications_permissions_check`, `EmployersEndpoint::get_applications_permissions_check` | employer dashboard tab |
| `wcb_access_employer_dashboard` | — | `blocks/employer-dashboard/render.php` |
| `wcb_apply_jobs` | `ApplicationsEndpoint::submit_permissions_check` | `blocks/job-single/render.php` (apply button) |
| `wcb_manage_resume` | `ApplicationsEndpoint::upload_resume_file` (inline `is_user_logged_in`), `CandidatesEndpoint::update_item_permissions_check` | candidate dashboard |
| `wcb_bookmark_jobs` | `JobsEndpoint::toggle_bookmark` | `blocks/job-listings`, `blocks/job-single` |
| `wcb_access_candidate_dashboard` | — | `blocks/candidate-dashboard/render.php` |
| `wcb_moderate_jobs` | `ModerationModule::moderate_permissions_check` | filtered by `wcb_moderate_jobs_ability_check` |
| `wcb_manage_settings` | `admin/*` REST `admin_check`, `wizard_permission_check`, `manage_permissions_check` (companies trust) | All admin pages |
| `wcb_view_analytics` | (Pro only) | (Pro only) |

---

## Special grants

- **Administrator** receives every `wcb_*` capability via `Roles::add_admin_caps`,
  re-applied on every `init` (idempotent), so cap drift across upgrades is prevented.
- **Self-registration** (`/employers/register`, `/candidates/register`) creates the
  corresponding role automatically — these are the only two endpoints with
  `permission_callback => '__return_true'` that *write* state.
- **AntiSpam** gates all public write endpoints through the `wcb_pre_job_submit`
  and `wcb_pre_application_submit` filters — token failures short-circuit before
  the permission_callback would even run for unauth flows.
