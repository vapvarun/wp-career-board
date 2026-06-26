---
feature: module — GDPR export / erase candidate data + audit log
roles: admin, candidate
surface: WP core Privacy tools (Tools → Export/Erase Personal Data) + wcb_gdpr_log
last_walked: 2026-06-26
---

# GDPR export / erase — full browser walkthrough

**What it is:** Career Board plugs into WordPress's built-in privacy workflow. The admin exports or erases a user's Career Board data (applications, resume, bookmarks) through Tools → Export/Erase Personal Data; every completed action is written to the `wcb_gdpr_log` audit table with a SHA-256-hashed IP.
**Where it lives:** `wp-admin/export-personal-data.php` and `wp-admin/erase-personal-data.php`. Registered by `GdprModule` via `wp_privacy_personal_data_exporters` / `_erasers`.

## As admin — export
1. `?autologin=1` → `wp-admin/export-personal-data.php` → enter the candidate's email → **Send Request** → confirm the request.
2. Run the export → WordPress calls `export_user_data($email, $page)` repeatedly (page-based, 100/page) until `done` → the ZIP includes a **Job Applications** group with Job title, Status, and Submitted date per application.
3. On the final page the action is logged once to `wcb_gdpr_log` (`action='export'`) — no double-logging across pages.

## As admin — erase
1. `wp-admin/erase-personal-data.php` → enter the candidate's email → **Send Request** → confirm → run the eraser.
2. `erase_user_data()` deletes `wcb_application` posts in batches of 100 (destructive read always takes page 1; WP re-invokes until `done`). On the final batch it also clears profile meta `_wcb_resume_data` and all `_wcb_bookmark` rows.
3. The screen reports items removed; the action is logged once (`action='erase'`). Re-running on a now-empty user returns 0 removed, done immediately (unknown email also returns done with nothing removed).

## As candidate
1. The candidate's data only leaves via these admin-driven core privacy requests (with the standard email confirmation step) — there is no self-serve export/erase endpoint in Free.

## Themes & states
- Core WP admin screens — verify at 1440px + 390px (WP-native responsive).
- Large dataset: thousands of applications complete across multiple batches without timing out (the reason for paging).
- Audit privacy: `wcb_gdpr_log.ip_hash` stores `hash('sha256', $ip)` — never the plaintext IP.

## Contracts guarded
- Paging contract: exporter pages by `$page`; eraser always reads page 1 (destructive) and reports `done` on a short batch — no missed or repeated rows.
- Single log write per completed action (export/erase), only on the final batch.
- Erase clears applications + `_wcb_resume_data` + `_wcb_bookmark` together — no orphaned candidate data left behind.
- IP is hashed, not stored in plaintext.
