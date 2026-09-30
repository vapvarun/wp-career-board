---
id: walkthrough-admin-settings-tabs
priority: critical
personas: varundubey
requires: mu:autologin
last_verified: 2026-09-27
covers: admin/admin-settings-general-tab-save, admin/admin-settings-jobs-tab-save, admin/admin-settings-antispam-tab-save, admin/admin-settings-save-merge, admin/admin-settings-tabs-render
---

# Walkthrough: Settings Tabs - render every grouped tab, save Jobs / Sign-ups / Antispam, and prove the save-merge contract

> **Set these two first.** Every command and URL below uses them, so the
> walkthrough runs on any machine rather than the one it was written on:
>
> ```bash
> WCB_PATH="$(cd "$(git rev-parse --show-toplevel)/../../.." && pwd)"
> WCB_SITE="$(wp --path="$WCB_PATH" option get home)"
> ```
>
> `bin/qa-fixtures.sh` derives the same root the same way, so the two agree.

**Why this journey exists:** `wcb_settings` is one serialised option shared by every tab. Since 1.8.0 every tab's form is schema-driven (`SettingsSchema::form_fields()` emits a hidden `wcb_settings[_wcb_form]=1` marker) and `AdminSettings::sanitize()` overlays **only the keys present in that submission** onto the existing option - no more per-tab field-key guessing. A regression that writes every key on every save would silently zero out the other tabs (data loss invisible until a different feature breaks). This walkthrough renders every tab across every sidebar group, saves three tabs, and asserts the cross-tab merge holds. This is the human-runnable form of the four `admin-settings-*` sentinels.

## Steps

1. As `varundubey`, snapshot the full option before any change: `BEFORE=$(wp option get wcb_settings --format=json --path=$WCB_PATH)` → note `notification_email` (an Emails key) and `jobs_archive_page` (a Pages key) for the merge assertions.
2. As `varundubey`, navigate to `$WCB_SITE/wp-admin/admin.php?page=wcb-settings&autologin=varundubey` → expect HTTP 200, the settings shell `.wcb-settings-wrap` with sidebar `.wcb-settings-sidebar` grouped into four `nav.wcb-settings-nav-group` blocks (**Jobs & Applications**, **Candidates & Employers**, **Emails & App**, **Site**), each with a `.wcb-settings-nav-group__label` and its tabs as `a.wcb-settings-nav-item[data-section]`. Free tab slugs: `listings`, `applications`, `industries`, `import` (Jobs & Applications); `signups`, `privacy` (Candidates & Employers); `emails`, `mobile-app` (Emails & App); `pages`, `antispam`, `integrations`, `advanced` (Site). The default section `#section-listings` is visible.
3. **Tabs render (all, every group).** Click each `a.wcb-settings-nav-item[data-section="<slug>"]` in turn and assert its `#section-<slug>` becomes visible without a PHP notice/blank panel: `listings` → "Jobs" card; `applications` → "Applications" card; `signups` → "Sign-ups" card with the Open Sign-Up status line and a link to Settings > General; `pages` → `wp_dropdown_pages()` selects; `emails` → "Sender" card (From Name / From Email / Admin Notification Email) followed by the per-template table (rendered by `do_action('wcb_settings_tab_emails')`, `admin/class-admin-settings.php:1107`); `mobile-app` → app branding, legal links, and its own "Save Changes" button; `import` → WPJM migration card(s); `antispam` → CAPTCHA Provider dropdown with four options; `advanced` → Content Width + Remove Data on Delete; `integrations` → integrations card. (All sections are emitted server-side in one page load; `assets/js/admin/settings-nav.js` only toggles visibility.)
4. **Jobs tab save.** On `#section-listings`, set the "Auto-Publish Jobs" toggle `input[name="wcb_settings[auto_publish_jobs]"]` to **off**, set `#wcb-jobs-per-page` (`name="wcb_settings[jobs_per_page]"`) to `12`, and `#wcb-jobs-expire-days` (`name="wcb_settings[jobs_expire_days]"`, labeled "Default listing length (days)") to `45`; click this section's "Save Changes" (`#section-listings form[action="options.php"] submit.wcb-btn--primary`) → expect the Settings-API redirect back with `settings-updated` and the "Settings saved." notice.
5. Verify the Jobs keys persisted: `wp option get wcb_settings --format=json` → expect `jobs_per_page: 12`, `jobs_expire_days: 45`, `auto_publish_jobs: false`. (`SettingsSchema::sanitize()` clamps `jobs_per_page` to 1-100 and `jobs_expire_days` to 1-365.)
6. **Merge assertion #1 (Jobs save must not clobber other tabs).** Re-read `wcb_settings` and compare with `$BEFORE` → expect `notification_email` and `jobs_archive_page` UNCHANGED. (`_wcb_form` overlay applies only the keys present in the Jobs form, leaving the `pages`/`emails` keys untouched - `admin/class-admin-settings.php:204-221`.)
7. **Emails tab (Sender card) save.** Click `a.wcb-settings-nav-item[data-section="emails"]`, set `#wcb-from-name` (`name="wcb_settings[from_name]"`) to `Careers Team`, `#wcb-from-email` (`name="wcb_settings[from_email]"`) to `careers@example.test`, and the required `#wcb-notification-email` (`name="wcb_settings[notification_email]"`) to `admin@example.test`; click "Save Changes" → expect `settings-updated` and the sender values persisting on reload.
8. **Merge assertion #2 (Emails save must not clobber Jobs).** Re-read `wcb_settings` → expect `jobs_per_page` still `12` and `jobs_expire_days` still `45` (the values from step 4 survive the Emails save).
9. **Antispam tab save.** Click `a.wcb-settings-nav-item[data-section="antispam"]` → expect the Anti-Spam form `form[action="options.php"]` (same Settings-API path as every other tab, with the `_wcb_form` marker) and `select#wcb-captcha-provider[name="wcb_settings[captcha_provider]"]` offering None (Honeypot only) / Cloudflare Turnstile / Google reCAPTCHA v3 (invisible, score) / Google reCAPTCHA v2 (invisible badge). Select "Cloudflare Turnstile", enter dummy keys `ts_site_key_smoke` / `ts_secret_key_smoke`, submit → expect the same `settings-updated` redirect and "Settings saved." notice as every other tab.
10. Switch the provider to "Google reCAPTCHA v3 (invisible, score)", enter `rc_site_key_smoke` / `rc_secret_key_smoke`, submit → verify `wp option get wcb_settings --format=json` shows `captcha_provider: recaptcha` AND `turnstile_site_key` still present (switching provider must not delete the other provider's stored keys - the survival contract this schema-driven rewrite exists to guarantee).
11. **Merge assertion #3 (Antispam save must not clobber earlier tabs).** Re-read `wcb_settings` → expect `jobs_per_page: 12`, `notification_email: admin@example.test`, and `jobs_archive_page` still equal to `$BEFORE` - the antispam save left every non-captcha key intact.
12. Navigate to a non-existent tab `$WCB_SITE/wp-admin/admin.php?page=wcb-settings&tab=zzz_nope&autologin=varundubey` → expect HTTP 200 and the page still renders (falls back to the default first section) with no PHP warning.
13. tail `wp-content/debug.log` diff over the whole run → expect ZERO new fatal/warning lines.

## Teardown

```bash
SITE='$WCB_PATH'
# Restore the whole option to the pre-walk snapshot (safest; run unconditionally).
wp option update wcb_settings "$BEFORE" --format=json --path="$SITE" 2>/dev/null || true
# If $BEFORE was lost, at minimum clear the smoke captcha keys and reset the provider:
wp option patch update wcb_settings captcha_provider none --path="$SITE" 2>/dev/null || true
wp option patch update wcb_settings turnstile_site_key ""   --path="$SITE" 2>/dev/null || true
wp option patch update wcb_settings turnstile_secret_key "" --path="$SITE" 2>/dev/null || true
wp option patch update wcb_settings recaptcha_site_key ""   --path="$SITE" 2>/dev/null || true
wp option patch update wcb_settings recaptcha_secret_key "" --path="$SITE" 2>/dev/null || true
wp option patch update wcb_settings recaptcha_v2_site_key ""   --path="$SITE" 2>/dev/null || true
wp option patch update wcb_settings recaptcha_v2_secret_key "" --path="$SITE" 2>/dev/null || true
```

## Notes
- Every tab form now shares one save mechanism: `method="post" action="options.php"` plus `SettingsSchema::form_fields()`'s hidden `_wcb_form` marker. `AdminSettings::sanitize()` overlays only the keys present in that submission, so every tab is a merge-safe write (`admin/class-admin-settings.php:190-232`). There is no separate `admin-post.php` save path for Anti-Spam anymore, and no `general` tab slug.
- The sidebar is grouped by task (`wcb_settings_tab_groups` filter, `admin/class-admin-settings.php:344-372`), not a flat tab list. A tab's `data-section` slug is unchanged by which group it renders under.
- What was once the "Notifications" tab (From Name, From Email, Admin Notification Email) is now the **Sender** card at the top of the **Emails** tab; sender fields still use the same `wcb_settings` keys (`from_name`, `from_email`, `notification_email`).
- Fake CAPTCHA keys are fine - no live Cloudflare/Google verification is expected; keys are stored as plain strings on the Free side.
- No 1.5.1-new surface here (the configurable email body lives on the Emails tab - see `emails.md`).
