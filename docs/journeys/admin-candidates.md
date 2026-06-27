---
feature: admin Candidates list (wcb_resume / candidate users)
roles: admin
surface: admin page (WP_List_Table over wcb_candidate users)
last_walked: 2026-06-26
---

# Candidates admin — full browser walkthrough

**What it is:** The admin candidate directory — a custom `WP_List_Table` over users holding the `wcb_candidate` role, surfacing profile visibility, application and bookmark counts. The candidate's `wcb_resume` and detail live on the native WP user-edit screen reached via the row link.
**Where it lives:** `wp-admin/admin.php?page=wcb-candidates` (Career Board → Candidates).

## As admin
1. `?autologin=1` → `wp-admin/admin.php?page=wcb-candidates` → expect the `.wcb-admin` shell: `users` header icon, description, and an **Add New** button (`user-new.php`).
2. A single **All (count)** view link sits above the table (count from a `wcb_candidate` `WP_User_Query`).
3. Columns: **Name** (display name + email, links to the user-edit screen), **Profile Visibility**, **Applications**, **Bookmarks**, **Registered** (sortable: Name, Registered; Registered defaults DESC).
4. **Profile Visibility** column renders a token badge from `_wcb_profile_visibility`: `success` when `public`, `default` otherwise (e.g. `private`). Confirm the badge has colour, not bare text.
5. **Applications** column counts the candidate's `wcb_application` posts (via `_wcb_candidate_id` meta) — a non-zero count links into the Applications admin page; zero shows a plain `0`.
6. **Bookmarks** column shows the count of `_wcb_bookmark` user-meta rows (saved jobs).
7. Search by name / email / login (`*term*` over `user_login`, `user_email`, `display_name`).
8. Row actions: **Edit** (native user-edit screen — where the candidate's resume/profile detail and `wcb_resume` association are managed) and **View** (author archive, new tab). Bulk action: **Delete**.

## Themes & states
- Reign / BuddyX light / **BuddyX dark** at 1440px + 390px. Visibility badge stays readable in dark mode (light text on dark tint).
- Empty state: no candidates → `wcb-empty-state` card with a `users` icon and the note that candidates self-register on the frontend.

## Contracts guarded
- Role scoping: the list queries `role__in = wcb_candidate` only — employers/subscribers never leak in.
- Count accuracy: Applications count uses `_wcb_candidate_id` (numeric meta); Bookmarks uses the multi-row `_wcb_bookmark` user-meta — both match the candidate dashboard surfaces.
- `.wcb-badge--*` tokens + Lucide icons render; admin-only page (`wcb_manage_settings`).
