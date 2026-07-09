---
id: walkthrough-admin-jobs-and-moderation
priority: high
personas: varundubey, morgan_moderator
requires: mu:autologin, seed:jobs
last_verified: 2026-07-08
covers: admin/admin-jobs-page-renders, admin/admin-jobs-list-bulk-action, admin/moderation-approve-flagged-job, admin/moderator-redirected-to-queue, admin/walkthrough-moderation-and-reporting (admin queue portion)
---

# Walkthrough: Jobs List & Moderation — render the Jobs queue, run bulk actions, work the Flagged view, and confirm the moderator lands on the queue

**Why this journey exists:** The Jobs admin page (`admin.php?page=wcb-jobs`, a custom `WP_List_Table`) is the site owner's moderation cockpit: it must render every column, filter by status, expose bulk actions, surface flagged listings in a dedicated view, and let a moderator dismiss/unpublish flags or approve/reject pending jobs. A Job Moderator (who lacks `wcb/manage-settings`) must also be bounced silently to this queue on login. This is the human-runnable form of the four jobs/moderation sentinels.

## Steps

1. As `varundubey`, navigate to `http://jobboard.local/wp-admin/admin.php?page=wcb-jobs&autologin=varundubey` → expect HTTP 200 and the Jobs list table (`AdminJobs::render()`, `admin/class-admin-jobs.php:57`) with column headers **Title, Status, Flags, Company, Author, Date** (`get_columns()`, `admin/class-admin-jobs.php:104-113`).
2. Confirm the status views render: assert the view links (`get_views()`, `admin/class-admin-jobs.php:236-307`) include **All** (`.current` by default) and per-status links for Published/Pending Review/Draft/Expired/Closed/Trash that have a non-zero count. Click **Pending Review** (`?post_status=pending`) → expect only pending jobs listed.
3. Seed two published jobs for the bulk action: `JOB1=$(wp post create --post_type=wcb_job --post_title="Smoke Bulk 1" --post_status=publish --post_author=50 --porcelain --path=/Users/varundubey/Local Sites/jobboard/app/public)` and `JOB2=$(... "Smoke Bulk 2" ...)`. Reload `admin.php?page=wcb-jobs` → expect both rows visible under the Published view.
4. **Bulk approve.** Select `$JOB1` and `$JOB2`, choose "Approve" in the bulk-actions dropdown (`get_bulk_actions()` always offers `approve`; adds "Move to Trash" for admins, "Dismiss flags" for moderators — `admin/class-admin-jobs.php:135-149`), and Apply → expect the list to reload (`process_bulk_action()` runs on the `load-` hook, `admin/class-admin.php:90`) with no PHP notice. Verify `wp post get $JOB1 --field=post_status` → `publish`.
5. **Row actions on a pending job.** Create `PJOB=$(wp post create --post_type=wcb_job --post_title="Smoke Pending" --post_status=pending --post_author=50 --porcelain ...)`; navigate to `admin.php?page=wcb-jobs&post_status=pending` and hover its row → expect `button.wcb-approve-job[data-job-id="<PJOB>"]` "Approve" and `button.wcb-reject-job[data-job-id]` "Reject" row actions (rendered only for `pending`, `admin/class-admin-jobs.php:394-405`). No Edit/Trash for a moderator — those stay admin-only (`admin/class-admin-jobs.php:146-148`).
6. **Approve over REST** (the action the Approve button drives): as `varundubey`, `POST http://jobboard.local/wp-json/wcb/v1/jobs/<PJOB>/approve` → expect HTTP 200 `{"id":<PJOB>,"status":"publish"}`; `wp post get <PJOB> --field=post_status` → `publish`.
7. **Flagged view.** Flag a published job to make the view appear: `wp post meta update $JOB1 _wcb_flag_status open` and `wp post meta update $JOB1 _wcb_flag_count 1`. Navigate to `http://jobboard.local/wp-admin/admin.php?page=wcb-jobs&wcb_flag=open&autologin=varundubey` → expect HTTP 200, the "Flagged" view link `.current` (`admin/class-admin-jobs.php:296-304`), and the `$JOB1` row showing a `span.wcb-badge--danger` "1 report" in the Flags column (`column_flags()`, `admin/class-admin-jobs.php:530-541`).
8. Hover the `$JOB1` row → expect the moderator resolve actions: `a` "Dismiss flag" (`?page=wcb-jobs&wcb_resolve_flag=dismiss&job=<JOB1>` + nonce `wcb_resolve_flag_<JOB1>`) and `a.submitdelete` "Unpublish" (`?wcb_resolve_flag=unpublish&job=<JOB1>` + nonce) — rendered only when `_wcb_flag_status=open` AND the viewer holds `wcb/moderate-jobs` (`admin/class-admin-jobs.php:410-445`). Click "Dismiss flag" → expect a redirect back to `…&wcb_flag=open` (`process_flag_action()`, `admin/class-admin-jobs.php:677-704`).
9. Verify dismiss cleared the flag but kept the job live: `wp post meta get $JOB1 _wcb_flag_status` → `resolved`; `wp post get $JOB1 --field=post_status` → `publish` (dismiss reuses `ModerationModule::resolve_job_flags($id,'dismiss')`; only `unpublish` changes status to `pending`).
10. **Moderator redirect.** As `morgan_moderator` (role `wcb_board_moderator`: has `wcb/moderate-jobs`, not `wcb/manage-settings`), navigate to `http://jobboard.local/wp-admin/?autologin=morgan_moderator` → expect the final URL to be `…/wp-admin/admin.php?page=wcb-jobs` (bounced off the WP Dashboard by `Admin::redirect_moderator_to_queue()` on `admin_init`, `admin/class-admin.php:256-275`), HTTP 200, and NO "headers already sent" text anywhere on the page.
11. As `morgan_moderator`, navigate to `http://jobboard.local/wp-admin/admin.php?page=wp-career-board` → expect a silent redirect to `…page=wcb-jobs` (the Career Board dashboard is not theirs). Assert NO license/credit "needs setup" nag notice is present on the queue.
12. **Admin regression.** As `varundubey`, navigate to `http://jobboard.local/wp-admin/?autologin=varundubey` → expect to STAY on the WP Dashboard (admins are never bounced); navigate to `admin.php?page=wp-career-board` → expect the Career Board dashboard to render (title "WP Career Board").
13. tail `wp-content/debug.log` diff over the whole run → expect ZERO new fatal/warning lines (especially no "Cannot modify header information").

## Teardown

```bash
SITE='/Users/varundubey/Local Sites/jobboard/app/public'
# Clear any flag meta left on the seeded job and delete the smoke jobs.
for K in _wcb_flag_status _wcb_flag_count _wcb_flag_reasons _wcb_flag_reporters; do
  wp post meta delete "$JOB1" "$K" --path="$SITE" 2>/dev/null || true
done
wp post delete "$JOB1" "$JOB2" "$PJOB" --force --path="$SITE" 2>/dev/null || true
```

## Notes
- The Jobs page is the CUSTOM admin page `admin.php?page=wcb-jobs` rendered by `AdminJobs` (a `WP_List_Table` subclass), NOT the native `edit.php?post_type=wcb_job` CPT screen. Older atomic journeys reference the CPT URL; the shipped menu registers `wcb-jobs` (`admin/class-admin.php:82-89`).
- `wcb_expired` / `wcb_closed` are registered custom post statuses; closed/expired jobs remain visible in the admin views but drop out of the public `/wcb/v1/jobs` listing.
- Admin "Dismiss flag" and the REST `POST /jobs/{id}/resolve-flag` share `ModerationModule::resolve_job_flags()`. Flag postmeta keys: `_wcb_flag_status` (open→resolved), `_wcb_flag_count`, `_wcb_flag_reporters` (per-user dedupe), `_wcb_flag_reasons`.
- Moderator notice gating lives in Pro (`ProAdmin::maybe_show_license_notice()` / `ProSetupWizard::maybe_show_setup_notice()` early-return without `wcb/manage-settings`); Free is keyless and shows no license notice regardless.
- No 1.5.1-new surface in this walkthrough.
