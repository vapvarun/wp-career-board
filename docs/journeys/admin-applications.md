---
feature: admin Applications list + application-detail meta boxes
roles: admin
surface: admin page (WP_List_Table) + post-edit meta boxes + REST (/applications/{id}/status)
last_walked: 2026-06-26
---

# Applications admin — full browser walkthrough

**What it is:** The admin applications queue — a custom `WP_List_Table` with status tabs, search, inline status changer, CSV export, and bulk status/trash; plus the per-application detail screen (overview + status meta boxes).
**Where it lives:** `wp-admin/admin.php?page=wcb-applications` (Career Board → Applications). "View" opens the native `wcb_application` edit screen where the detail meta boxes render.

## As admin — the list
1. `?autologin=1` → `wp-admin/admin.php?page=wcb-applications` → expect the `.wcb-admin` shell with a `clipboard-list` header icon and description.
2. Status tabs: **All**, **Submitted**, **Reviewing**, **Shortlisted**, **Rejected**, **Hired** — counts come from `_wcb_status` postmeta (raw `$wpdb` COUNT); zero-count non-active tabs hide.
3. Columns: Candidate, Job, **Status** (token badge), **Change Status** (inline select), Date. Status badge map: `info` (Submitted), `warn` (Reviewing), `success` (Shortlisted / Hired), `danger` (Rejected).
4. Search by job title OR candidate name/email/login (custom meta_query over `_wcb_job_id` + `_wcb_candidate_id`). A no-match search forces zero results, not all rows.
5. Guest applications render a **Guest** badge with the stored `_wcb_guest_name` / email and an **Email Guest** mailto row action; deleted candidate/job render `(deleted)`.
6. Change a row's inline **Change Status** select → fires `PUT /wcb/v1/applications/{id}/status` (REST, no reload); the Status badge updates.
7. Bulk actions: **Mark as Reviewing / Shortlisted / Rejected / Hired** (writes `_wcb_status`, fires `wcb_application_status_changed`), **Export to CSV** (streams a UTF-8-BOM file with cover letter + resume URL), **Move to Trash**. All gated per-row on `edit_post`.

## As admin — the detail screen (meta boxes)
1. From a row click **View** → native `wcb_application` edit screen. The **Application overview** meta box stacks four partials: applicant card, cover letter, resume preview, status timeline.
2. **Resume**: shows filename + size with **Open** (new tab) when `_wcb_resume_attachment_id` (or the linked `wcb_resume`) resolves; otherwise "No resume uploaded with this application."
3. **Status timeline**: reverse-chronological history of status changes (labels: Submitted → Reviewing → Shortlisted → Rejected → Hired).
4. Side **Application status** meta box: the **Change status** select + a **Save** button with an aria-live feedback span (bound to the `/applications/{id}/status` REST URL), plus **Quick actions** buttons — Shortlist / Mark Hired / Reject (data-status) and an Email applicant action when an address exists.

## Themes & states
- Reign / BuddyX light / **BuddyX dark** at 1440px + 390px. Status badges, the timeline, and the changer feedback span stay readable in dark mode.
- Empty state: no applications → `wcb-empty-state` card with a `mail` icon.

## Contracts guarded
- REST↔JS: inline select + meta-box changer both POST to `/applications/{id}/status`; UI reflects the result (no silent failure).
- Status enum parity: list, badges, timeline, and changer all share the five-value `STATUSES` set (`submitted…hired`).
- CSV export is per-row `edit_post`-gated and BOM-prefixed (Excel encoding); bulk nonce `bulk-applications`.
