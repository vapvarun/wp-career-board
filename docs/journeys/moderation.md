---
feature: module — Report a job → moderator/admin resolve (dismiss / unpublish)
roles: candidate, employer, subscriber, moderator, admin
surface: frontend report control (job-single) + REST (/jobs/{id}/report, /resolve-flag) + admin Jobs "Flagged" view
last_walked: 2026-06-26
---

# Moderation (report → resolve) — full browser walkthrough

**What it is:** Any logged-in user can report a published job for a reason; reports are deduped per user and stored as `_wcb_flag_*` postmeta. A moderator/admin resolves the flags by dismissing them (job stays live) or unpublishing (job → pending for the employer to revise).
**Where it lives:** "Report this job" control on `/jobs/<slug>/` (job-single block); resolution in **Career Board → Jobs → Flagged** view. Endpoints in `ModerationModule`.

## As any logged-in user (candidate / employer / subscriber)
1. `?autologin=wcb_demo_candidate` → open a published job → click **Report this job** → a reason select appears: Scam, Spam, Expired/filled, Inaccurate, Offensive.
2. Pick a reason, submit → `POST /wcb/v1/jobs/{id}/report {reason}` → expect a confirmation; postmeta `_wcb_flag_count` increments, `_wcb_flag_status='open'`, and `wcb_job_reported` fires.
3. Report the **same** job again → response returns `already_reported: true` with an unchanged count (one flag per user — deduped via `_wcb_flag_reporters`).
4. The job stays **live** for everyone until a moderator acts — reporting never auto-hides content.

## As anonymous
1. Logged out → the report control prompts sign-in; a forged `POST /jobs/{id}/report` resolves to user 0 → **401** (`report_permissions_check` requires login).

## As moderator / admin
1. `?autologin=1` → `wp-admin/admin.php?page=wp-career-board` → Jobs → the **Flagged** view lists jobs with open flags; the **Flags** column shows the count.
2. **Dismiss** (row or bulk) → `resolve_job_flags($id,'dismiss')` clears `_wcb_flag_*`, sets `_wcb_flag_status='resolved'`, job stays `publish`; fires `wcb_job_flag_resolved`.
3. **Unpublish** → same clear, but job → `pending` so the employer must revise/resubmit. The admin row + REST (`POST /jobs/{id}/resolve-flag {action}`) share the one `resolve_job_flags()` helper.
4. A `wcb_board_moderator` (no admin) holding `wcb/moderate-jobs` can reach the same Flagged actions; report stays open to any authenticated user.

## Themes & states
- Frontend control: Reign / BuddyX / BuddyX dark at 1440px + 390px — the reason select + confirmation stay readable in dark mode.
- Empty: no flags → Flagged view shows an empty state, not an error.

## Contracts guarded
- Dedup: one report per user per job (`_wcb_flag_reporters`) — repeat reports are idempotent.
- Single resolver: REST and admin row actions both call `resolve_job_flags()` so dismiss/unpublish behave identically.
- Permission split: report = any logged-in user; approve/reject/resolve = `wcb/moderate-jobs` (moderator + admin).
- a11y: report control + reason select have focus rings; admin row actions reachable by keyboard.
