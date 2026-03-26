# Backend Options Audit — WP Career Board Free

> Date: 2026-03-26
> Auditor: Deep code trace (every option end-to-end)

---

## Critical Findings (3)

### 1. `_wcb_size` vs `_wcb_company_size` key mismatch
- **Severity:** HIGH — company size is blank until first update
- **File:** `api/endpoints/class-employers-endpoint.php`
- **Issue:** `create_item()` line 292 writes `_wcb_size`, `update_item()` line 359 writes `_wcb_company_size`, `prepare_company()` line 751 reads `_wcb_company_size`
- **Fix:** Change create_item meta_map to use `_wcb_company_size`

### 2. `wcb_apply_jobs` capability not enforced at REST layer
- **Severity:** HIGH — any logged-in user can apply regardless of role
- **File:** `api/endpoints/class-applications-endpoint.php:495`
- **Issue:** `submit_permissions_check()` returns `true` unconditionally
- **Fix:** Add `current_user_can('wcb_apply_jobs') || !is_user_logged_in()` check (allow guests + candidates, block employers)

### 3. WPJM importer writes location to postmeta instead of taxonomy
- **Severity:** HIGH — imported jobs invisible to location filters
- **File:** `import/class-wpjm-importer.php:170`
- **Issue:** Maps `_job_location` to `_wcb_location` postmeta, but location is taxonomy-based
- **Fix:** Use `wp_set_object_terms()` for `wcb_location` taxonomy instead of postmeta

---

## Not Cleaned Up on Uninstall (8)

| Option/Data | File |
|-------------|------|
| `wcb_email_settings` | Not in uninstall.php |
| `wcb_default_board_id` | Not in uninstall.php |
| `wcb_jobs_cache_v` | Not in uninstall.php |
| `wcb_setup_complete` | Not in uninstall.php |
| `wcb_sample_data_installed` | Not in uninstall.php (also DEAD — never read) |
| Admin role `wcb_*` capabilities | Never removed from administrator role |
| `wcb_email_settings` brand/template config | Full email settings persist |
| `employer_registration_page` key in wcb_settings | Dead key — wizard writes, nothing reads |

---

## Dead Writes (6 — data written but never read back)

| Meta/Option | Written At | Status |
|-------------|-----------|--------|
| `wcb_sample_data_installed` | setup-wizard.php:386 | Written, never read |
| `_wcb_rejection_reason` | moderation-module.php:189 | Written on reject, never displayed |
| `_wcb_status_log` | applications-endpoint.php:325 | Status history accumulated, inaccessible |
| `_wcb_resume_attachment_id` | applications-endpoint.php:257 | Upload attachment ID stored, never retrieved |
| `_wcb_preferred_currency` | jobs-endpoint.php:428 | Saved but never pre-populated |
| `employer_registration_page` in wcb_settings | setup-wizard.php:241 | Wizard stores, nothing reads |

---

## Import Bugs (orphaned meta on wrong CPT)

Written to `wcb_job` posts by importer but these are company-level fields with no reader on jobs:
- `_wcb_website`, `_wcb_tagline`, `_wcb_twitter`, `_wcb_company_video`, `_wcb_location` (postmeta)

---

## Unenforced Capabilities (4)

| Capability | Granted To | Actually Checked? |
|------------|-----------|-------------------|
| `wcb_apply_jobs` | candidate, admin | **NO** — submit_permissions_check returns true |
| `wcb_manage_resume` | candidate, admin | **NO** — only same-user check |
| `wcb_bookmark_jobs` | candidate, admin | **NO** — only is_user_logged_in() |
| `wcb_view_analytics` | admin | **NO** — registered but no gate |

---

## Write-Only DB Tables (3)

| Table | Writes | Reads | Purpose |
|-------|--------|-------|---------|
| `wcb_notifications_log` | AbstractEmail::send() | **None** | Email send history — no admin UI |
| `wcb_job_views` | JobsEndpoint::record_job_view() | **None** | View tracking — never surfaced |
| `wcb_gdpr_log` | GdprModule::log_action() | **None** | GDPR actions — never displayed |

---

## Settings Architecture Issues (2)

### Anti-Spam race condition
Anti-Spam tab saves via `admin-post.php` (read-modify-write on `wcb_settings`). Main tabs save via `options.php`. If both submit simultaneously, anti-spam keys get stripped by the main sanitizer.

### `jobs_per_page` not enforced in REST
Setting exists in UI but REST endpoint defaults to `per_page=20`, ignoring the stored value. Only the JS block reads it.

---

## Defaults Review (Fresh Install)

| Setting | Default | Safe? |
|---------|---------|-------|
| Job moderation | ON (pending) | Yes |
| Jobs per page | 10 | Yes |
| Job expiry | 30 days | Yes |
| Currency | USD | Yes |
| Page assignments | 0 (homepage fallback) | Poor UX but no crash |
| Email from | WordPress admin_email | Yes |
| Anti-Spam | Honeypot only | Yes |
| Default board | Auto-created on init | Minor race condition |

**No settings are strictly required for the plugin to function**, but without page configuration, all navigation targets default to homepage.
