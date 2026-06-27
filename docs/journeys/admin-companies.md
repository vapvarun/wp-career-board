---
feature: admin Companies list (wcb_company + trust level)
roles: admin
surface: admin page (WP_List_Table) + REST (/companies/{id}/trust)
last_walked: 2026-06-26
---

# Companies admin — full browser walkthrough

**What it is:** The admin company directory — a custom `WP_List_Table` over `wcb_company` posts with status tabs, search, sortable columns, an inline **Trust Level** changer, row actions, and bulk trash.
**Where it lives:** `wp-admin/admin.php?page=wcb-companies` (Career Board → Companies). Edit/Add open the native `wcb_company` post screen.

## As admin
1. `?autologin=1` → `wp-admin/admin.php?page=wcb-companies` → expect the `.wcb-admin` shell: `building-2` header icon, description, and an **Add New** button (`post-new.php?post_type=wcb_company`).
2. Status tabs: **All**, **Published**, **Draft**, **Trash** — counts from `wp_count_posts`. Note companies are **not** moderated, so there is no Pending tab by design (unlike Jobs).
3. Columns: **Company Name**, **Employer**, **Website**, **Active Jobs**, **Trust Level** (inline select), **Status** (token badge), Date (sortable: Title, Date).
4. **Employer** column resolves the linked user via `_wcb_company_id` user-meta → links to the user-edit screen, or `—`. **Website** shows the parsed host linked out (new tab). **Active Jobs** counts published `wcb_job` posts with this `_wcb_company_id`.
5. **Status** badge: `success` (Published), `default` (Draft), `danger` (Trash). Confirm colour renders, not bare text.
6. **Trust Level** column is an inline `<select>` — `— None —`, **Verified**, **Trusted**, **Premium** — bound to `_wcb_trust_level`. Change it → fires `POST /wcb/v1/companies/{id}/trust` (REST, no reload, admin-only ability). The selection persists on reload.
7. Search by company name. Row actions: **Edit**, **View** (published only, new tab), **Trash** / **Restore** + **Delete Permanently** (trash view). Bulk: **Move to Trash** (per-row `edit_post`-gated, nonce `bulk-companies`).

## Themes & states
- Reign / BuddyX light / **BuddyX dark** at 1440px + 390px. Status badge and the trust select stay readable in dark mode.
- Empty state: no companies → `wcb-empty-state` card with a `building-2` icon, noting companies are created from the employer dashboard.

## Contracts guarded
- Trust changer round-trip: the inline select writes `_wcb_trust_level` via `/companies/{id}/trust`; the frontend job-single trust badge reads the same key (verified company → check icon).
- No-moderation contract: company list intentionally omits a Pending status (only Jobs moderate).
- `.wcb-badge--*` tokens + Lucide icons render; trust update gated on `wcb/manage-settings`.
