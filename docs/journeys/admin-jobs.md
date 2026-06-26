---
feature: admin Jobs list (CRUD + moderation flags)
roles: admin, moderator
surface: admin page (WP_List_Table) + REST (/jobs/{id}/approve|reject, /jobs/{id}/resolve-flag)
last_walked: 2026-06-26
---

# Jobs admin — full browser walkthrough

**What it is:** The admin job queue — a custom `WP_List_Table` with status tabs, a Flagged view, search, sortable columns, row actions, and bulk approve / dismiss-flags / trash.
**Where it lives:** `wp-admin/admin.php?page=wcb-jobs` (Career Board → Jobs). Edit/Add open the native `wcb_job` post screen (`post-new.php?post_type=wcb_job`).

## As admin
1. `?autologin=1` → `wp-admin/admin.php?page=wcb-jobs` → expect the `.wcb-admin` page shell: header with a `briefcase` Lucide icon, description, and an **Add New** button (gated on `wcb/post-jobs`).
2. Status tabs render above the table: **All**, **Published**, **Pending Review**, **Draft**, **Expired**, **Closed**, **Trash** — each with a live `(count)`; zero-count tabs (other than the active one) are hidden. The current tab carries `class="current"`.
3. Status column shows a token-scoped badge: `success` (Published), `warn` (Pending / Expired), `default` (Draft / Closed), `danger` (Trash). Confirm badges render with colour, not bare text.
4. Search "Search Jobs" by title → results narrow; sort by Title / Date via the sortable headers (Date defaults DESC).
5. Click a row title → opens the native job edit screen (admins only — the title is a plain `<strong>` for moderators with no `edit_post`).
6. **Pending** rows expose inline **Approve** / **Reject** row-action buttons (REST, no reload); approving a job fires `wcb_job_approved` → the approval email. **Published** rows expose **View** (new tab).
7. Bulk-select rows → **Approve** (any moderator) or **Move to Trash** (admin only) → submit → page redirects back to `wcb-jobs`. Trash requires both `wcb/manage-settings` and per-post `edit_post`.

## As admin — Flagged / moderation view
1. With a reported job present (see `moderation.md` for the report flow), a **Flagged** tab appears with the open-flag count; the **Flags** column shows a `danger` badge "N reports" with the top reason as a `title` tooltip.
2. Open `wcb-jobs&wcb_flag=open` → the list filters to jobs with `_wcb_flag_status = open` across all statuses.
3. Flagged rows expose two extra row actions for holders of `wcb/moderate-jobs`: **Dismiss flag** (clears the flag, keeps the job) and **Unpublish** (clears + unpublishes). Each carries a per-job nonce (`wcb_resolve_flag_{id}`) and routes through `ModerationModule::resolve_job_flags()`.
4. Bulk **Dismiss flags** is available to moderators — same resolve path. After resolving, redirect lands back on the Flagged view.

## As moderator
1. `?autologin=<wcb_board_moderator user>` → same page. The Job Moderator sees the queue and **Approve / Reject / Dismiss / Unpublish** but **no Edit, Trash, or Add New** — their contract is approve/reject, not deletion. Title is non-clickable.

## Themes & states
- Reign / BuddyX light / **BuddyX dark** at 1440px + 390px. Badges and the Flags tooltip stay readable in dark mode (no light-on-light).
- Empty state: no jobs → `wcb-empty-state` card with `briefcase` icon and an **Add New Job** CTA.

## Contracts guarded
- Capability split: Approve/Dismiss on `wcb/moderate-jobs`; Trash/Edit/Add on `wcb/manage-settings` — moderators never reach destructive actions.
- Single resolve implementation: admin row/bulk actions and REST both call `ModerationModule::resolve_job_flags()` (no drift).
- Lucide icons + `.wcb-badge--*` tokens render (no raw glyphs); nonce-guarded flag links (`wcb_resolve_flag_{id}`) and bulk nonce (`bulk-jobs`).
