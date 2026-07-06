# Plan — Configurable email body (Basecamp 10065162323 + 10065174994)

Branch: `1.5.1` (Free + Pro, lockstep). Verify via Mailpit `http://localhost:10045`.

## Customer expectation (both cards, read together)
1. **No email ever sends with a blank body** (Card 1 — currently all 3 Pro emails do).
2. **Every email notification exposes an admin-editable Subject + Message/Body** in plugin
   settings, for **all** emails (Free 9 + Pro 3), not just Alert Me (Card 2).
3. Chosen model: **Full replace** — the admin body is the entire inner body; the designed
   PHP templates are superseded by a merge-tag-driven default body.

## Root cause of Card 1 (confirmed)
`wp-career-board-pro/modules/notificationspro/class-notifications-pro-module.php:63`
registers the Pro template dir as `modules/notifications-pro/…` (hyphen). The dir was renamed
to `notificationspro/` in the 2026-06-09 release-integrity wave; this runtime path was missed
(Rule 7 scans autoload paths, not template dirs). `render_template()` finds no file →
returns header+footer only → blank body. Affects `job-alert`, `credit-topup`, `low-balance`.
Fix: one-line path correction (already applied locally, uncommitted).

## Target render model (AbstractEmail)
Body resolution order in `dispatch()`:
1. Saved admin body (`wcb_settings.emails[<id>].body`) if non-empty → `render_string()` with vars.
2. Else `get_default_body()` (per-email merge-tag string, ported from current template markup).
3. Else legacy file template via `render_template()` (kept ONLY as theme-override fallback).
Inner body is always wrapped by the existing branded header + footer partials.

## New per-email metadata (AbstractEmail — abstract, all 12 implement)
- `get_default_body(): string` — default HTML body with `{merge_tags}`.
- `get_merge_tags(): array<string,string>` — tag ⇒ human label, for the settings UI + preview.
- `get_body(): string` — saved body ?: default body (mirrors existing `get_subject()`).

## Merge-tag registry (from audit)
| Email id | Recipient | Merge tags |
|---|---|---|
| application-confirmation | candidate | job_title, dashboard_url |
| application-guest-confirmation | guest | guest_name, job_title, job_url |
| application-received | employer | job_title, candidate_name, dashboard_url |
| application-status-changed | candidate | job_title, new_status, dashboard_url |
| deadline-reminder | candidate | job_title, job_url, days_left, deadline_iso, company_name |
| job-approved | employer | job_title, job_url |
| job-expired | employer | job_title, repost_url |
| job-pending-review | admin | job_title, approve_url |
| job-rejected | employer | job_title, reason |
| job-alert (Pro) | candidate | **job_list** (pre-rendered `<ul>`), count |
| credit-topup (Pro) | employer | credits_added, new_balance, dashboard_url |
| low-balance (Pro) | employer | balance, topup_url |

**Loop problem:** `job-alert` currently loops `$job_items` (array) in PHP. A flat admin body
can't loop. Fix: `EmailJobAlert::handle()` pre-renders the `<ul>` into a scalar `{job_list}`
var; the admin body embeds `{job_list}`. `render_string()` already str_replaces raw values
(no escaping) so plugin-built HTML passes through; admin body is `wp_kses_post`-sanitized on save.

## Settings UI (class-email-settings.php)
Per email row: existing Subject + Enabled + Test, plus an **"Edit content"** expander revealing
a **Body textarea** and a clickable **merge-tag chip list** (inserts `{tag}`). Textarea (not
TinyMCE) — predictable email-safe HTML + simple chip insertion. Body defaults shown as
placeholder = `get_default_body()`. Responsive (stacks ≤640px), a11y (labels, keyboard chips),
dark-mode tokens. JS in existing `assets/js/admin/emails.js`; no inline script.

## Save / sanitize
`EmailSettings::save()` — add `'body' => wp_kses_post( $raw[$id]['body'] ?? '' )` beside subject.
Empty body → not stored → falls back to default. No destructive migration; no DB_VERSION bump
(read-with-fallback). Existing installs immediately gain content.

## Files
Free:
- `modules/notifications/class-abstract-email.php` — abstract methods + get_body() + dispatch() body resolution.
- `modules/notifications/emails/*.php` (9) — implement get_default_body() + get_merge_tags().
- `admin/class-email-settings.php` — body editor + merge-tag chips (both render paths).
- `assets/js/admin/emails.js` + `assets/css/admin/emails.css` — expander + chip insertion + styles.
- `api/endpoints/class-admin-endpoint.php` — extend $test_vars for new tags (job_list sample, etc.).
Pro:
- `modules/notificationspro/class-notifications-pro-module.php` — path fix (Card 1).
- `modules/notificationspro/emails/*.php` (3) — get_default_body() + get_merge_tags(); job-alert builds {job_list}.

## Verification (Mailpit, per email)
For each of 12: (a) default send → body non-empty + correct content; (b) admin custom body with
merge tags → substitutes correctly; (c) toggle off → no send. Card 1 repro: real job-alert with
job_ids renders the job list. Plus WPCS + PHPStan + coding-rules + journeys on both repos.

## QA artifacts
- New journey `admin/email-content-configurable`; update `customer/pro-alerts-email-trigger`.
- UX_AUDIT row for the new settings editor (per persona/viewport/theme).
- Changelog (WooCommerce-style) Free + Pro, lockstep version bump.
- Manifest: no new routes/hooks/tables; refresh timestamp.

## Sub-decisions (signed off)
1. **Legacy PHP templates:** KEEP as theme-override fallback. Default body authoritative;
   files remain last-resort so theme overrides (`theme/wp-career-board/emails/*.php`) keep working.
2. **Editor type:** plain textarea + clickable merge-tag chips (no TinyMCE).
