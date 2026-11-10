# WP Career Board (Free) — Common Use-Case Catalog

**Purpose:** the Pareto pre-release checklist. Instead of proving 100% touch-point
coverage, this catalogs the **80–90% of flows real users actually hit**, grouped by
the three actors (**Job Seeker**, **Job Poster / Employer**, **Admin**). Walk this
before every release. Pro adds its own delta catalog — see
[`wp-career-board-pro/docs/qa/COMMON_USE_CASES.md`](../../../wp-career-board-pro/docs/qa/COMMON_USE_CASES.md).
In a combo (Free+Pro) release, **walk this Free catalog first**, then the Pro delta.

- **Companion docs:** permissions → [`audit/ROLE_MATRIX.md`](../../audit/ROLE_MATRIX.md);
  per-flow contracts → [`AGENT_SMOKE_RUNBOOK.md`](AGENT_SMOKE_RUNBOOK.md);
  executable form → [`audit/journeys/`](../../audit/journeys/).
- **This catalog is the index; the journeys are the executable version of each row.**

## How to use it (every release)

1. **Refresh** (see below) so the catalog matches the version being shipped.
2. Walk **Tier 1** for all three actors — that is the ~80% no release ships without.
3. Walk **Tier 2** to reach ~90%.
4. Give **extra scrutiny** to rows flagged 🆕 (changed since last release — not yet
   release-hardened) and ⚠ (no journey yet — manual walk required).
5. Record pass/fail in the sign-off table at the bottom.

## Refresh procedure (keep it from drifting)

Run before each release; update rows that changed:

```bash
cd wp-content/plugins/wp-career-board
# touch-point inventory (denominator)
ls blocks | grep -v '\.'                                   # frontend blocks
grep -rhoE "add_shortcode\( *'[^']+" . '--include=*.php'   # shortcodes
# what changed this cycle (flag as 🆕)
git log --oneline "v$(git describe --tags --abbrev=0 2>/dev/null || echo 1.5.0)"..HEAD
```

Then re-map any new block/route/admin page to an actor + tier below, and add a
`🆕` flag to anything the `git log` shows as new/changed.

---

## 🔎 Job Seeker (candidate + anonymous) — highest traffic

### Tier 1 — core (walk every release)

| ✓ | Use case | Primary surface | Journey | Flag |
|---|---|---|---|---|
| ☐ | Browse the jobs archive (Find Jobs / `/jobs`) | `job-listings` + `job-search` + `job-filters` blocks | `customer/walkthrough-find-jobs-and-apply` | 🆕 1.8.1: Load More is a real `/page/N/` link; default 12 per page |
| ☐ | Keyword search jobs | `job-search` block | `customer/search-jobs-keyword` | 🆕 emits `wcb:results` w/ matched IDs |
| ☐ | Filter jobs (type / board / location chips) | `job-filters` / `job-listings` | `customer/walkthrough-job-search-filters`, `customer/job-listings-board-filter` | 🆕 facet order now filterable via `wcb_default_filter_order` (`blocks/job-listings/render.php:42`) 🆕 1.8.1: shared links tick filters and pills, `pushState` + Back, nested categories, category search, `wcb_order` |
| ☐ | Open a single job | `job-single` block | `customer/walkthrough-find-jobs-and-apply` | — |
| ☐ | **Apply to a job as guest** | `job-single` apply panel → `POST /jobs/{id}/apply` | `security/anonymous-can-apply-cleanly` | — money path 🆕 1.8.1: account email gets 409 `wcb_guest_has_account` + Sign in link; repeat reply identical |
| ☐ | **Apply to a job as logged-in candidate** | `job-single` apply panel | `customer/apply-to-job` | — money path 🆕 1.8.1: first application grants `wcb_candidate` |
| ☐ | Apply routing (email vs external URL jobs) | `job-single` | `customer/job-apply-routing-email-or-url` | — |
| ☐ | Register as a candidate | `employer-registration`/register REST | `customer/candidate-register` | — |
| ☐ | Candidate dashboard → view my applications | `candidate-dashboard` block | `customer/walkthrough-candidate-dashboard`, `customer/candidate-view-applications` | 🆕 1.8.1: tab router writes the hash for every tab, no wrong-tab flash, server-rendered welcome card, Request data export fixed |

### Tier 2 — extended (walk to reach ~90%)

| ✓ | Use case | Primary surface | Journey | Flag |
|---|---|---|---|---|
| ☐ | Bookmark a job | `job-single`/`job-listings` → `POST /bookmark` | `customer/candidate-bookmark-job` | 🆕 1.8.1: button only with `wcb/bookmark-jobs`; failure shows "Could not save" |
| ☐ | Bookmark a company | `company-profile` → `POST /bookmark` | `customer/candidate-bookmark-company` | 🆕 1.8.1: same gate and failure text; Saved Companies logo/initial/industry; Saved Resumes avatar |
| ☐ | Copy a job's link (http site and failure fallback) | `job-single` share row | `customer/job-copy-link-http-fallback` | 🆕 1.8.1: new journey |
| ☐ | Edit candidate profile | `candidate-dashboard` | `customer/candidate-edit-profile` | — |
| ☐ | Update account settings | `candidate-dashboard` | `customer/account-settings-update` | 🆕 1.8.1: Delete my account form spacing |
| ☐ | Browse companies directory | `company-archive` block | `customer/walkthrough-companies-directory` | — |
| ☐ | View a company profile | `company-profile` block | ⚠ no dedicated journey | ⚠ |
| ☐ | Featured / recent / stats blocks render | `featured-jobs`, `recent-jobs`, `job-stats` | `customer/shortcode-renders-without-block-editor` (partial) | ⚠ partial |

---

## 📝 Job Poster / Employer

### Tier 1 — core

| ✓ | Use case | Primary surface | Journey | Flag |
|---|---|---|---|---|
| ☐ | Register as an employer | `employer-registration` block → `POST /employers/register` | ⚠ no dedicated journey | ⚠ |
| ☐ | **Post a job** | `job-form` block → jobs REST | `customer/walkthrough-employer-post-job`, `customer/employer-post-job` | — money path 🆕 1.8.1: deadline editable up to the board maximum (REST caps), site currency default |
| ☐ | Credits opt-in gate on free posting | `job-form` submit path | `system/credits-opt-in-free-posting` | — |
| ☐ | Employer dashboard renders | `employer-dashboard` block | `customer/walkthrough-employer-manage-applications` | 🆕 1.8.1: tab router + `data-wcb-tab-pending` guard |
| ☐ | View applicants for a job | `employer-dashboard` → `GET /employers/me/jobs` + applications | `customer/employer-applicants-list`, `customer/walkthrough-employer-manage-applications` | 🆕 1.8.1: zero-count status filter keeps chips, toggle and export; job switch resets filter |
| ☐ | Change an application status | `employer-dashboard` → `POST /applications/{id}/status` | `customer/employer-application-status-change` | — |
| ☐ | Edit a job | `job-form` (edit mode) | `customer/employer-edit-job` | — |
| ☐ | Edit company profile | `employer-dashboard` | `customer/employer-edit-company` | — |
| ☐ | Board picker respects membership | `job-form` board picker | `customer/employer-boards-picker-respects-membership` | — |

### Tier 2 — extended

| ✓ | Use case | Primary surface | Journey | Flag |
|---|---|---|---|---|
| ☐ | Resubmit a rejected job | `job-form` (edit) | `customer/employer-rejected-job-resubmit` | — |
| ☐ | Orphan job adopted on company create | `employer-dashboard` | `customer/orphan-job-adopted-on-company-create` | — |
| ☐ | Orphan application shows "job removed" | `employer-dashboard` | `customer/orphan-application-shows-job-removed` | — |

---

## ⚙️ Admin

### Tier 1 — core

| ✓ | Use case | Admin page | Journey | Flag |
|---|---|---|---|---|
| ☐ | Setup wizard (first run) | Setup Wizard | `admin/admin-setup-wizard-end-to-end`, `admin/walkthrough-admin-setup-and-settings` | 🆕 1.8.1: step bodies render on symlinked and Windows installs |
| ☐ | Settings → Jobs tab save (also proves the save merges, no clobber of other tabs) | Career Board → Settings | `admin/admin-settings-jobs-tab-save` | 🆕 1.8.1: "When a job ends" text folded into Default listing length; Jobs Per Page default 12 |
| ☐ | Settings → Antispam tab save | Career Board → Settings | `admin/admin-settings-antispam-tab-save` | — |
| ☐ | Jobs list + bulk actions | Career Board → Jobs | `admin/admin-jobs-list-bulk-action`, `admin/admin-jobs-page-renders` | 🆕 1.8.1: shared search box with placeholder; status counts refresh after inline approve/reject |
| ☐ | Moderation queue → approve/dismiss flagged job | Career Board → Jobs (Flagged) | `admin/moderation-approve-flagged-job`, `admin/moderator-redirected-to-queue` | 🆕 1.8.1: flag resolve redirects on `load-{page}` (no headers-already-sent) |
| ☐ | **Emails: template merge tags + message body** | Career Board → Emails | `admin/admin-emails-template-merge-tags`, `admin/walkthrough-moderation-and-reporting` | 🆕 configurable email message body + defaults |

### Tier 2 — extended

| ✓ | Use case | Admin page | Journey | Flag |
|---|---|---|---|---|
| ☐ | Applications list renders + export | Career Board → Applications | `admin/admin-applications-page-renders`, `admin/admin-applications-export` | 🆕 1.8.1: View resume row action |
| ☐ | Candidates list renders | Career Board → Candidates | `admin/admin-candidates-page-renders` | 🆕 1.8.1: applicants and resume authors get `wcb_candidate`; backfill (DB 1.3.8) |
| ☐ | Companies list + edit meta | Career Board → Companies | `admin/admin-companies-page-renders`, `admin/admin-companies-edit-meta` | 🆕 1.8.1: bulk trash redirect on `load-{page}` |
| ☐ | Employers list + ban (banned can't post) | Career Board → Employers | `admin/admin-employers-page-renders`, `admin/admin-employer-ban-cant-post` | — |
| ☐ | Taxonomy CRUD (categories / types / locations / experience / tags) | Career Board → taxonomy submenus | `admin/admin-categories-and-types-crud` | 🆕 1.8.1: Order field on every term screen, nesting, seniority default |
| ☐ | Edit a job in wp-admin (Apply URL/Email, currency, salary, deadline, No expiry) | Job Details metabox | `admin/admin-job-editor-fields` | 🆕 1.8.1: new journey |
| ☐ | Delete an employer (jobs close, companies draft, reassign moves them) | Users → Delete | `system/deleted-user-listings-retired` | 🆕 1.8.1: new journey |
| ☐ | Change a user's role | Users | `admin/admin-user-role-change` | — |
| ☐ | GDPR export / erase | Tools → Export/Erase Personal Data | `admin/walkthrough-gdpr-export-erase` | 🆕 1.8.1: candidate Request data export button works |

---

## 🔒 Cross-cutting — always check (every release)

These are not per-actor flows but must be green on every ship:

| ✓ | Guarantee | Journey |
|---|---|---|
| ☐ | Subscriber can't reach the employer dashboard | `security/subscriber-cant-access-employer-dashboard` |
| ☐ | Employer can't see another employer's applications | `security/employer-cant-see-other-applications` |
| ☐ | Employer can't edit another employer's job | `security/employer-cant-edit-other-job` |
| ☐ | Candidate can't edit another candidate's resume | `security/candidate-cant-edit-other-resume` |
| ☐ | Shortcode `[wcb_widget]` renders without block editor | `customer/shortcode-renders-without-block-editor` |
| ☐ | Job-expiry cron closes old jobs | `system/job-expiry-cron-closes-old-jobs` |
| ☐ | Cron events scheduled on activate / removed on deactivate | `system/cron-events-scheduled-on-activate`, `system/cron-events-removed-on-deactivate` |
| ☐ | Candidate role granted on apply/resume; upgrade backfill | `system/candidate-role-granted-and-backfilled` |
| ☐ | A job located Remote is always `_wcb_remote` = 1 | `system/location-remote-sets-remote-flag` |
| ☐ | Job description shortcodes only for trusted authors, no recursion | `security/job-description-shortcodes-trusted-authors-only` |
| ☐ | Client IP uses the rightmost forwarded hop | `security/client-ip-uses-rightmost-forwarded-hop` |
| ☐ | `meta__wcb_*` REST filter ignores private keys | `security/rest-meta-filter-ignores-private-keys` |
| ☐ | Public company job list hides applicant counts | `security/public-company-jobs-hide-applicant-counts` |
| ☐ | Private candidate profile answers 404 | `security/private-candidate-profile-answers-404` |
| ☐ | Private candidate files download as attachments; confirmation link signs in only the registering browser | `security/candidate-files-are-private`, `customer/signup-email-verification` |
| ☐ | No new fatals/warnings in debug.log across the walk | (every journey step 13) |

---

## 🆕 New / changed in 1.8.1 — give extra scrutiny

Fixes from the 1.8.1 branch (commits `v1.8.0..HEAD`); not yet through a release cycle. Each bullet names the journey that walks it and the D-row in [`AGENT_SMOKE_RUNBOOK.md`](AGENT_SMOKE_RUNBOOK.md).

- **Critical batch** - setup wizard bodies on symlinked/Windows installs, candidate Request data export, flag-resolve and companies bulk-trash redirects, `wcb_candidate` role (+ DB 1.3.8 backfill), deleted-user listings, guest apply with an account email, employer zero-count status filter.
- **Job fields** - one sanitizer path for the admin editor, REST and forms; site-currency default everywhere; employer-editable deadline capped at the board maximum (`JobDeadline::cap`); `Locations` keeps `_wcb_remote` true for Remote jobs.
- **Find Jobs** - URL filters seed the checkboxes and pills, address bar follows filters, Back restores, Load More is a real `/page/N/` link, 12 per page by default, ordered/nested taxonomy lists (`Taxonomy::options`), category search box.
- **Dashboards** - `tabRouter()` writes the hash for every tab, Notifications deep link, `data-wcb-tab-pending` first-paint guard, server-rendered welcome card, Saved Companies/Resumes avatars and industry.
- **Shared UI** - `@wcb/ui` `copyText()` (job Copy Link, Pro Job Feed Copy URL), Save buttons gated on `wcb/bookmark-jobs` with a visible failure, admin `SearchBox` trait, Jobs status counts refresh.
- **Security (Free)** - shortcodes in job descriptions only for `unfiltered_html` authors, rightmost X-Forwarded-For hop, browser-bound verification link, REST-public-only `meta__wcb_*`, no applicant counts on the public company job list, private profile 404, documents download as attachments, repeat guest apply answered like the first.
- **Security (Pro)** - AI budget counts only allowed calls per user, guest alert sign-ups use the shared IP limiter, unsubscribe answers once, resume PDF real-type check. Needs Free 1.8.1 (`rate_limit_reached()`).

---

## 🆕 Previous cycle - changed in 1.5.1 — give extra scrutiny

Not yet through a release cycle; regressions most likely here.

- **Configurable email message body** (Free + Pro) — Emails admin + all transactional emails render the custom body with defaults.
- **`wcb:results` event** — job search/filter fetches emit matched job IDs; anything downstream (job map, counts) must react.
- **`wcb_default_filter_order` filter** — the order of filter facets (type/experience/category/tags/location/board/salary) on the job-listings block is now overridable (`blocks/job-listings/render.php:42`). Despite the commit message ("job dashboard reorder filters"), this is the **seeker browse/filter** surface, not the employer dashboard.
- **AI translations (de/fr/es/nl/ko) + `@wbcom/i18n-ai` pipeline** — textdomain now loads on `init`; verify strings translate and no early-load notices.

---

## Sign-off (fill per release)

| Version | Date | Job Seeker | Job Poster | Admin | Cross-cutting | Walked by |
|---|---|---|---|---|---|---|
| 1.8.1 | | ☐ | ☐ | ☐ | ☐ | |
| 1.5.1 | | ☐ | ☐ | ☐ | ☐ | |

Legend: 🆕 changed this cycle · ⚠ no journey yet (manual walk) · — journey exists & current.
