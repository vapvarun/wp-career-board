---
id: walkthrough-moderation-and-reporting
priority: high
personas: sarah.chen, morgan_moderator
requires: mu:autologin, seed:jobs
last_verified: 2026-06-29
needs: cli
---

# Walkthrough: Job Moderation & Reporting — report a job, review the Flagged queue, resolve, and clear the pending-review queue

> **Set these two first.** Every command and URL below uses them, so the
> walkthrough runs on any machine rather than the one it was written on:
>
> ```bash
> WCB_PATH="$(cd "$(git rev-parse --show-toplevel)/../../.." && pwd)"
> WCB_SITE="$(wp --path="$WCB_PATH" option get home)"
> ```
>
> `bin/qa-fixtures.sh` derives the same root the same way, so the two agree.

**Why this journey exists:** This is the end-to-end walkthrough of the standalone Job Moderation & Reporting feature. It traces the full happy path a real user takes — a logged-in candidate flags a live listing from the job-single page, a Job Moderator reviews the admin Flagged queue, resolves the flag (dismiss to keep it live, or unpublish to send it back to the employer), and separately clears the pending-review approve/reject queue — so the whole functionality is browser-coverable in one pass.

## Steps

1. Pick a published job to report: `wp post list --post_type=wcb_job --post_status=publish --field=ID --posts_per_page=1` → capture as `<job-id>`.
2. As `sarah.chen`, navigate to `<job-id>`'s single page `$WCB_SITE/?p=<job-id>&autologin=sarah.chen` → expect HTTP 200 and the report control `button.wcb-job-report__trigger` ("Report this job", with `flag` icon) visible at the bottom of the `.wcb-job-report` block (renders because the viewer is logged in and is not the job owner — `$wcb_can_report` in render.php:247).
3. Click `button.wcb-job-report__trigger` (`actions.toggleReport`) → expect the form `.wcb-job-report__form` to unhide and the reason `select#wcb-report-reason.wcb-report-reason` to show options scam / spam / expired / inaccurate / offensive (from `ModerationModule::report_reasons()`, render.php:823).
4. Select reason `spam` in `#wcb-report-reason` (`actions.updateReportReason`), then click the primary "Submit report" button (`.wcb-job-report__actions .wcb-btn--primary`, `actions.submitReport`) → expect the form to hide and `.wcb-job-report__done` ("Thanks - this job has been reported for review.") to show. Under the hood this POSTs `wcb/v1/jobs/<job-id>/report` body `{"reason":"spam"}` (view.js:391).
5. Verify the flag persisted: `wp post meta get <job-id> _wcb_flag_status` → expect `open`; `wp post meta get <job-id> _wcb_flag_count` → expect `1`.
6. Confirm per-user dedupe — re-POST `/wp-json/wcb/v1/jobs/<job-id>/report` body `{"reason":"spam"}` as `sarah.chen` (with `X-WP-Nonce`) → expect HTTP 200 with `already_reported: true` and `count: 1` (report_job() short-circuits on a repeat reporter, class-moderation-module.php:337).
7. As `morgan_moderator`, navigate to the admin Flagged queue `$WCB_SITE/wp-admin/admin.php?page=wcb-jobs&wcb_flag=open&autologin=morgan_moderator` → expect HTTP 200, the "Flagged" view link is `.current`, and the row for `<job-id>` shows a `span.wcb-badge--danger` "1 report" in the Flags column (column_flags(), class-admin-jobs.php:530) with the top reason ("Spam or advertisement") as its `title`.
8. Hover the `<job-id>` row and confirm the resolve row actions render for the moderator: `a` "Dismiss flag" (`?wcb_resolve_flag=dismiss&job=<job-id>` + nonce) and `a.submitdelete` "Unpublish" (`?wcb_resolve_flag=unpublish&job=<job-id>` + nonce) — gated on `wcb/moderate-jobs` (class-admin-jobs.php:410). Click "Dismiss flag" → expect a redirect back to `…&wcb_flag=open` and `<job-id>` no longer in the Flagged list.
9. Verify dismiss cleared the flag but kept the job live: `wp post meta get <job-id> _wcb_flag_status` → expect `resolved`; `wp post get <job-id> --field=post_status` → expect `publish` (dismiss path in `resolve_job_flags()`, class-moderation-module.php:425 — only `unpublish` changes status).
10. Exercise the unpublish path via REST: re-flag with `wp post meta update <job-id> _wcb_flag_status open` then POST `/wp-json/wcb/v1/jobs/<job-id>/resolve-flag` body `{"action":"unpublish"}` as `morgan_moderator` → expect HTTP 200; `wp post get <job-id> --field=post_status` → expect `pending` (unpublish sends the job back to the employer for revision).
11. Walk the pending-review queue: as `morgan_moderator`, navigate to `$WCB_SITE/wp-admin/admin.php?page=wcb-jobs&post_status=pending&autologin=morgan_moderator` → expect HTTP 200 and the `<job-id>` row now exposes the `button.wcb-approve-job[data-job-id="<job-id>"]` "Approve" and `button.wcb-reject-job` "Reject" row actions (rendered only for `pending` posts, class-admin-jobs.php:394).
12. Approve the job over REST (the action the Approve button drives): POST `/wp-json/wcb/v1/jobs/<job-id>/approve` as `morgan_moderator` → expect HTTP 200 with `{"id":<job-id>,"status":"publish"}`; `wp post get <job-id> --field=post_status` → expect `publish`.
13. Confirm the report endpoint stays moderator-gated end-to-end: as `sarah.chen` (no `wcb/moderate-jobs`), POST `/wp-json/wcb/v1/jobs/<job-id>/resolve-flag` body `{"action":"dismiss"}` → expect HTTP 403 (`moderate_permissions_check`, class-moderation-module.php:171).
14. tail debug.log diff → expect ZERO new fatal/warning lines.

## Teardown

```bash
# Clear any flag meta left on the job and return it to a clean published state.
wp post meta delete <job-id> _wcb_flag_count
wp post meta delete <job-id> _wcb_flag_reasons
wp post meta delete <job-id> _wcb_flag_reporters
wp post meta delete <job-id> _wcb_flag_status
wp post update <job-id> --post_status=publish
```

## Notes

- Report control selectors grounded in `blocks/job-single/render.php:799-848` (`.wcb-job-report`, `button.wcb-job-report__trigger`, `select#wcb-report-reason.wcb-report-reason`, `.wcb-job-report__actions .wcb-btn--primary`, `.wcb-job-report__done`) and `blocks/job-single/view.js:365-415` (`toggleReport` / `updateReportReason` / `submitReport`). The control only renders when `$wcb_can_report` = logged-in AND not the job owner (render.php:247).
- REST routes grounded in `modules/moderation/class-moderation-module.php`: `POST /wcb/v1/jobs/{id}/report` (any logged-in user, reason ∈ scam|spam|expired|inaccurate|offensive — `report_reasons()` line 149), `POST /wcb/v1/jobs/{id}/resolve-flag` (`wcb/moderate-jobs`, action ∈ dismiss|unpublish), `POST /wcb/v1/jobs/{id}/approve` and `POST /wcb/v1/jobs/{id}/reject` (`wcb/moderate-jobs`). `report_job()` fires `wcb_job_reported`; `resolve_job_flags()` fires `wcb_job_flag_resolved`.
- Admin Flagged queue URL `admin.php?page=wcb-jobs&wcb_flag=open`; Flags column + danger badge in `admin/class-admin-jobs.php:530`, Dismiss/Unpublish row actions (per-job nonce `wcb_resolve_flag_<id>`, handled by `process_flag_action()` line 677) at 410-445, "Dismiss flags" bulk action at 140-142. Pending Approve/Reject row actions at 394-405. Admin dismiss and REST resolve share `ModerationModule::resolve_job_flags()`.
- Flag postmeta keys: `_wcb_flag_status` (open→resolved), `_wcb_flag_count`, `_wcb_flag_reporters` (dedupe by user id), `_wcb_flag_reasons`.
- `morgan_moderator` (role `wcb_board_moderator`, display "Job Moderator") is seeded by `bin/seed-qa-fixtures.php` and proves the moderation role reaches both queues without `wcb_manage_settings`. Browser-driven REST steps need the page's `wp_rest` nonce (job-single emits one via `wp_interactivity_state`, render.php:256); WP-CLI fallback: `wp post meta`/`wp post get`/`wp post update` for the assertions.
