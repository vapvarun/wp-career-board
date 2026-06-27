---
feature: admin Employers list (ban / unban)
roles: admin
surface: admin page (WP_List_Table over wcb_employer users) + user-meta gate
last_walked: 2026-06-26
---

# Employers admin — full browser walkthrough

**What it is:** The admin employer directory — a custom `WP_List_Table` over users holding the `wcb_employer` role, with their company, website, active-job count, ban **Status** column, search, and Ban/Unban row + bulk actions.
**Where it lives:** `wp-admin/admin.php?page=wcb-employers` (Career Board → Employers).

## As admin
1. `?autologin=1` → `wp-admin/admin.php?page=wcb-employers` → expect the `.wcb-admin` shell: `briefcase-business` header icon, description, and an **Add New** button (`user-new.php`).
2. A single **All (count)** view link above the table (count from a `wcb_employer` `WP_User_Query`).
3. Columns: **Name** (display name + email, links to user-edit), **Company**, **Website**, **Active Jobs**, **Status**, **Registered** (sortable: Name, Registered; Registered DESC default).
4. **Company** resolves via `_wcb_company_id` user-meta → links to the company edit screen, or `—`. **Website** shows the parsed host of the company `_wcb_website`. **Active Jobs** counts the employer's published `wcb_job` posts and links into the Jobs admin filtered by author.
5. **Status** column renders a token badge: `success` **Active**, or `danger` **Banned** when `_wcb_employer_banned` user-meta is `1`. Confirm the badge has colour.
6. Search by name, email, login, **or company name** (company match resolves company post IDs → `_wcb_company_id` user-meta → included user IDs).

## As admin — Ban / Unban
1. On any employer row (not your own account), the row actions show **Ban** (with `wcb-row-action--danger` styling) or **Unban** depending on current state — a nonce-protected link (`bulk-employers`) that flips `_wcb_employer_banned`.
2. Click **Ban** → redirect back to the list; the row's Status badge flips to **Banned**. Banning writes `_wcb_employer_banned = 1` and fires `wcb_employer_banned`; `core/class-abilities.php` then strips every `wcb_*` ability from that user (the gate's write side).
3. Click **Unban** → deletes the meta, fires `wcb_employer_unbanned`, badge returns to **Active**.
4. Bulk-select rows → **Ban** / **Unban** bulk actions → same path. Both row and bulk paths gate on `wcb/manage-settings` and skip the current user (an admin can never ban themselves).

## Themes & states
- Reign / BuddyX light / **BuddyX dark** at 1440px + 390px. The Banned/Active badge and the danger-styled Ban link stay readable in dark mode.
- Empty state: no employers → `wcb-empty-state` card with a `briefcase-business` icon, noting employers self-register or are invited.

## Contracts guarded
- Ban write↔read: the list is the write side for `_wcb_employer_banned`; the abilities gate reads the same key — banning actually revokes capabilities (the 1.4.2 fix; the flag was previously read but never written).
- Self-ban guard: `$user_id === get_current_user_id()` is always skipped on both row and bulk paths.
- `.wcb-badge--*` tokens + Lucide icons render; ban/unban nonce `bulk-employers`, gated on `wcb/manage-settings`.
