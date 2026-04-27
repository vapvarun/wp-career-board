# WP Career Board (Free) — 1.2.0 Plan

This plan captures features intentionally deferred from 1.1.0. They were
ranked lower than the BuddyPress-funnel and audit-fix work that shipped
in 1.1.0 — see `CHANGELOG.md` and the `1.1.0` git log for what already
shipped.

Scope is **Free plugin only**. Pro-side equivalents live in
`wp-career-board-pro/docs/PLAN-1.2.0.md`.

## Strategic frame

Free's job is to be **the default community-site job board** when bundled
with Reign / BuddyX. WPJM-customer attraction is a side benefit. Items
land in 1.2.0 only if they:

1. Make a brand-new community-site install feel feature-complete out of
   the box (no "buy Pro to get the basics" critique), or
2. Clear a real audit or competitive-parity gap that hurts adoption.

Anything else waits.

---

## In scope for 1.2.0 (Free)

### Application source / UTM tracking
**Why:** Recruiters and community owners want to know which channel
brought an applicant. Modern ATSes log this; it's table-stakes.
**What:** Capture `utm_source` / `utm_medium` / `utm_campaign` / `referer`
on `wcb_application_submitted`, store as
`_wcb_source_*` post-meta on the application. Surface in the rebuilt
admin Application detail screen (extend the ApplicantCard widget) and in
the bulk applicant CSV export.
**Files:** `api/endpoints/class-applications-endpoint.php`,
`modules/applications/widgets/class-applicant-card.php`,
`admin/class-admin-applications.php` (export columns).
**Effort:** S.

### WPJM-format CSV export of jobs
**Why:** Trust signal — "you can leave anytime." We already import from
WPJM; offering a symmetric export removes lock-in fear.
**What:** Bulk export action on the Jobs admin list-table that emits a
WPJM-shaped CSV (post_title, content, location, salary, type,
categories, tags). Mirror the column set used by WPJM's CSV importer.
**Files:** `admin/class-admin-jobs.php` or new
`admin/class-admin-jobs-exporter.php`.
**Effort:** S.

### REST envelope cleanup
**Why:** 1.1.0 keeps the legacy `X-WCB-Total` and `X-WCB-TotalPages`
response headers populated alongside the new `{items, total, pages,
has_more}` envelope, for one back-compat cycle. 1.2.0 drops the headers.
**What:** Remove `$response->header('X-WCB-Total', …)` and
`X-WCB-TotalPages` from every list endpoint helper. Update any docs.
**Files:** `api/endpoints/class-jobs-endpoint.php`,
`class-companies-endpoint.php`, `class-employers-endpoint.php`,
`class-applications-endpoint.php`, `class-candidates-endpoint.php`.
**Effort:** S. Coordinated with Pro — make sure all known consumers
(both plugins) read the body, not the header, before dropping.

### `before_*` / `after_*` action pairs on writes
**Why:** Skill §1.2 requires both. Today most write paths fire only
post-action (`wcb_job_created`, `wcb_application_submitted`); listeners
have no way to short-circuit or augment a write before it commits.
**What:** Add `wcb_before_job_create`, `wcb_before_application_submit`,
`wcb_before_application_status_change`, etc. The before-action receives
the candidate payload by reference (or a `$payload` array filter
sibling) so listeners can mutate or `WP_Error`-veto.
**Files:** `api/endpoints/class-jobs-endpoint.php`,
`class-applications-endpoint.php`, `modules/moderation/class-moderation-module.php`.
**Effort:** M. Document each new hook in `docs/HOOKS.md`.

### `created_at` / `updated_at` ISO 8601 on REST resources
**Why:** Skill §3.3 single-resource shape. Today endpoints return `date`
in MySQL format. ISO 8601 with timezone is the modern default.
**What:** Replace `'date' => $post->post_date` with
`'created_at' => mysql_to_rfc3339($post->post_date_gmt)` and add
`updated_at` = `mysql_to_rfc3339($post->post_modified_gmt)` to every
`prepare_*` method. Keep `date` populated one cycle for back-compat.
**Files:** all `api/endpoints/*.php` `prepare_*` methods.
**Effort:** S.

### Application deadline reminder — Pro-grade UI
**Why:** 1.1.0 ships the cron + email. 1.2.0 should let the admin
preview the email template, customize the days-out buckets (default
3 + 1, configurable), and view a reminder log per candidate.
**What:** Settings tab UI for buckets and template preview. Reuse the
EmailDeadlineReminder class. Read the per-(user,job,bucket) flag we
already write.
**Files:** `admin/class-admin-settings.php`,
`admin/class-email-settings.php`,
`modules/jobs/class-deadline-reminders.php`.
**Effort:** S.

### Auto-location autocomplete on Post-a-Job
**Why:** Reduces friction on submission. WPJM Auto Location is a paid
add-on; making it core in Free is a competitive-parity win.
**What:** Replace the location text input on `blocks/job-form/render.php`
with a typeahead that hits the existing Pro `/wcb/v1/geocode` endpoint
when Pro is active and falls back to plain text otherwise. The Pro
geocode endpoint already exists; this commit just wires the Free UI.
**Files:** `blocks/job-form/render.php`, `blocks/job-form/view.js`.
**Effort:** S. Cross-cutting: needs Pro to expose the geocode endpoint
unauthenticated for this use (already does for logged-in users; check
permission_callback).

### `wcb_rest_prepare_{resource}` filters across endpoints
**Why:** Skill §3.7. Today only `wcb_job_response` exists. Other
resources lack a uniform extension point.
**What:** Add `wcb_rest_prepare_company`, `wcb_rest_prepare_employer`,
`wcb_rest_prepare_application`, `wcb_rest_prepare_candidate` filters at
the end of each `prepare_*` method.
**Files:** all `api/endpoints/*.php` `prepare_*` methods.
**Effort:** S. Document in `docs/HOOKS.md` with examples.

---

## Out of scope (Free) — not 1.2.0

These are real but the value-per-effort favours Pro:

- **Indeed Apply / LinkedIn Easy Apply.** Partner OAuth contracts and API
  access required; not unilateral.
- **Email drip integrations** (Mailchimp, Sendinblue, Mailpoet). Each is
  a discrete integration that benefits Pro buyers; not a Free feature.
- **Team multi-user employer accounts.** Schema-touching, deserves its
  own 2.0 release with migration coordination.
- **Job-board-specific theme.** Separate product, not plugin work.

---

### wp-plugin-development skill — REST contract gaps

Carried over from the 2026-04-28 skill audit. Each item is a real gap
against `~/.claude/skills/wp-plugin-development/SKILL.md`, not a false
positive.

- **`wcb_rest_prepare_*` filters on every prepared resource.** Skill
  §3.9 mandates `apply_filters( 'wcb_rest_prepare_<resource>', $data,
  $post, $request )` at the end of every `prepare_*()` method (jobs,
  applications, candidates, employers, companies, search). Currently
  zero sites. Touches ~6 prepare methods.
- **`GET /wcb/v1/settings/app-config` bootstrap endpoint.** Skill §3.8
  mandatory mobile-readiness. Returns non-sensitive startup config
  (`per_page`, `currency`, `moderation_mode`, `feature_toggles`,
  `is_pro_active`, `timezone`) behind the `wcb_rest_app_config` filter.
  Apps call this once on launch.
- **REST cache priming in list endpoints.** `update_meta_cache()` +
  `update_object_term_cache()` are wired in `blocks/job-listings/render.php`
  but missing in `class-jobs-endpoint.php` itself. Mirror the priming
  pattern across every list controller before the prepare loop.
- **Migrate `wp_get_object_terms()` → `get_the_terms()` in REST loops.**
  Skill §4.5. 7 sites in `class-jobs-endpoint.php`, 2 in
  `class-candidates-endpoint.php`. Each call is uncached; at per_page=12
  that's ~84 extra DB queries per page render.
- **`before_save` / `after_save` lifecycle hooks on every write op.**
  Skill §1.2. `do_action( 'wcb_before_save', $type, $data )` /
  `do_action( 'wcb_after_save', $type, $id, $data )` on jobs,
  applications, companies, candidates, resumes. Currently only Reign
  theme compat hooks are present.

### Drop the deprecated `date` / `items` aliases shipped in 1.1.0

1.1.0 added `created_at` / `updated_at` to applications + jobs single
responses while keeping the legacy `date` key as a one-cycle alias. Pro
`/resumes` likewise kept `items` alongside `resumes`. 1.2.0 removes both
aliases — bump app integrators a release in advance via the docs site.

## Definition of done for 1.2.0

- [ ] Every item above ships behind a feature flag where it could affect
      existing consumers (envelope cleanup, action signatures).
- [ ] `bin/ci-local.sh` green on every commit.
- [ ] Browser-verified at desktop + 390px on every UI change.
- [ ] `readme.txt` `Stable tag: 1.2.0` and `WCB_VERSION` constant bumped.
- [ ] `CHANGELOG.md` entry per item (one-line bullet).
- [ ] BC notes posted to docs site when X-WCB-Total headers go away.

## How to start work

```bash
git checkout 1.1.0
git checkout -b 1.2.0
# pick the smallest item, ship a commit, repeat.
```

Same release branch convention as 1.1.0 — version number IS the branch
name. CI workflow already triggers on `[0-9]+.[0-9]+.[0-9]+`.
