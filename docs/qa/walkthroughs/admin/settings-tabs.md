---
id: walkthrough-admin-settings-tabs
priority: critical
personas: varundubey
requires: mu:autologin
last_verified: 2026-07-08
covers: admin/admin-settings-general-tab-save, admin/admin-settings-jobs-tab-save, admin/admin-settings-antispam-tab-save, admin/admin-settings-save-merge, admin/admin-settings-tabs-render
---

# Walkthrough: Settings Tabs — render every tab, save Listings / Notifications / Antispam, and prove the save-merge contract

**Why this journey exists:** `wcb_settings` is one serialised option shared by every tab. Its sanitizer (`AdminSettings::sanitize()`) detects which tab was submitted by the presence of that tab's field keys, then overlays **only** that tab's keys onto the existing option. A regression that writes every key on every save would silently zero out the other tabs (data loss invisible until a different feature breaks). This walkthrough renders all tabs, saves three of them, and asserts the cross-tab merge holds. This is the human-runnable form of the four `admin-settings-*` sentinels.

## Steps

1. As `varundubey`, snapshot the full option before any change: `BEFORE=$(wp option get wcb_settings --format=json --path=/Users/varundubey/Local Sites/jobboard/app/public)` → note `notification_email` (a Notifications key) and `jobs_archive_page` (a Pages key) for the merge assertions.
2. As `varundubey`, navigate to `http://jobboard.local/wp-admin/admin.php?page=wcb-settings&autologin=varundubey` → expect HTTP 200, the settings shell `.wcb-settings-wrap` with sidebar `.wcb-settings-sidebar` listing `a.wcb-settings-nav-item[data-section]` for `listings`, `pages`, `notifications`, `emails`, `import`, `integrations`, plus the `antispam` nav item injected via the `wcb_settings_tabs` filter (`modules/antispam/class-anti-spam-module.php:75`). The default section `#section-listings` is visible.
3. **Tabs render (all).** Click each `a.wcb-settings-nav-item[data-section="<slug>"]` in turn and assert its `#section-<slug>` becomes visible without a PHP notice/blank panel: `listings` → "Job Listings" card; `pages` → `wp_dropdown_pages()` selects; `notifications` → From Name / From Email / Notification Email inputs; `emails` → brand settings + per-template table (rendered by `do_action('wcb_settings_tab_emails')`, `admin/class-admin-settings.php:1098`); `import` → WPJM migration card(s); `antispam` → CAPTCHA Provider dropdown; `integrations` → integrations card. (All sections are emitted server-side in one page load; `assets/js/admin/settings-nav.js` only toggles visibility.)
4. **Job Listings tab save.** On `#section-listings`, set the "Auto-Publish Jobs" toggle `input[name="wcb_settings[auto_publish_jobs]"]` to **off**, set `#wcb-jobs-per-page` (`name="wcb_settings[jobs_per_page]"`) to `12`, and `#wcb-jobs-expire-days` (`name="wcb_settings[jobs_expire_days]"`) to `45`; click this section's "Save Changes" (`#section-listings form[action="options.php"] submit.wcb-btn--primary`) → expect the Settings-API redirect back with `settings-updated` and the "Settings saved." notice.
5. Verify the Listings keys persisted: `wp option get wcb_settings --format=json` → expect `jobs_per_page: 12`, `jobs_expire_days: 45`, `auto_publish_jobs: false`. (Sanitizer clamps `jobs_per_page` to 1-100 and `jobs_expire_days` to `>= 1`, `admin/class-admin-settings.php:203-204`.)
6. **Merge assertion #1 (Listings save must not clobber other tabs).** Re-read `wcb_settings` and compare with `$BEFORE` → expect `notification_email` and `jobs_archive_page` UNCHANGED. (Sanitizer detects the `listings` tab via its field keys and overlays only `tab_fields['listings']`, leaving the `pages`/`notifications` keys untouched — `admin/class-admin-settings.php:194-244`.)
7. **Notifications tab save.** Click `a.wcb-settings-nav-item[data-section="notifications"]`, set `#wcb-from-name` (`name="wcb_settings[from_name]"`) to `Careers Team`, `#wcb-from-email` (`name="wcb_settings[from_email]"`) to `careers@jobboard.local`, and the required `#wcb-notification-email` (`name="wcb_settings[notification_email]"`) to `admin@jobboard.local`; click "Save Changes" → expect `settings-updated` and the sender values persisting on reload.
8. **Merge assertion #2 (Notifications save must not clobber Listings).** Re-read `wcb_settings` → expect `jobs_per_page` still `12` and `jobs_expire_days` still `45` (the values from step 4 survive the Notifications save).
9. **Antispam tab save (separate path).** Click `a.wcb-settings-nav-item[data-section="antispam"]` → expect the Anti-Spam form `form[action=".../admin-post.php"]` with hidden `action=wcb_save_antispam`, its nonce field, and `select#wcb-captcha-provider[name="captcha_provider"]` offering None / Cloudflare Turnstile / Google reCAPTCHA v3. Select "Cloudflare Turnstile", enter dummy keys `ts_site_key_smoke` / `ts_secret_key_smoke`, submit → expect a redirect back to `?tab=antispam` with the "Anti-Spam settings saved." notice. (This form posts to `admin-post.php?action=wcb_save_antispam` → `AntiSpamModule::save_settings()`, `modules/antispam/class-anti-spam-module.php:288`, which reads the whole `wcb_settings`, overlays only the captcha keys, and calls `update_option()` directly — NOT the Settings API.)
10. Switch the provider to "Google reCAPTCHA v3", enter `rc_site_key_smoke` / `rc_secret_key_smoke`, submit → verify `wp option get wcb_settings --format=json` shows `captcha_provider: recaptcha` AND `turnstile_site_key` still present (switching provider must not delete the other provider's stored keys — the survival contract).
11. **Merge assertion #3 (Antispam save must not clobber earlier tabs).** Re-read `wcb_settings` → expect `jobs_per_page: 12`, `notification_email: admin@jobboard.local`, and `jobs_archive_page` still equal to `$BEFORE` — the antispam admin-post save left every non-captcha key intact.
12. Navigate to a non-existent tab `http://jobboard.local/wp-admin/admin.php?page=wcb-settings&tab=zzz_nope&autologin=varundubey` → expect HTTP 200 and the page still renders (falls back to the default first section) with no PHP warning.
13. tail `wp-content/debug.log` diff over the whole run → expect ZERO new fatal/warning lines.

## Teardown

```bash
SITE='/Users/varundubey/Local Sites/jobboard/app/public'
# Restore the whole option to the pre-walk snapshot (safest; run unconditionally).
wp option update wcb_settings "$BEFORE" --format=json --path="$SITE" 2>/dev/null || true
# If $BEFORE was lost, at minimum clear the smoke captcha keys and reset the provider:
wp option patch update wcb_settings captcha_provider none --path="$SITE" 2>/dev/null || true
wp option patch update wcb_settings turnstile_site_key ""   --path="$SITE" 2>/dev/null || true
wp option patch update wcb_settings turnstile_secret_key "" --path="$SITE" 2>/dev/null || true
wp option patch update wcb_settings recaptcha_site_key ""   --path="$SITE" 2>/dev/null || true
wp option patch update wcb_settings recaptcha_secret_key "" --path="$SITE" 2>/dev/null || true
```

## Notes
- Tab fields the sanitizer knows about: `listings`, `pages`, `notifications` (`admin/class-admin-settings.php:194-198`). `emails` writes through its own handler into `wcb_settings['emails']` (see `emails.md`); `antispam` writes via `admin-post.php`. All three converge on the same `wcb_settings` option, so the merge contract spans mechanisms — that is exactly why assertions #1-#3 cover all three save paths.
- The catalog labels this area "General tab save" and "Jobs tab save"; in the shipped UI both map to the single **Job Listings** section fields (`jobs_per_page`, `jobs_expire_days`, `auto_publish_jobs`), with sender config living under **Notifications**. There is no separate `general` tab slug.
- Fake CAPTCHA keys are fine — no live Cloudflare/Google verification is expected; keys are stored as plain strings on the Free side.
- `wcb-antispam-saved` (not `settings-updated`) is the query param that drives the antispam success notice.
- No 1.5.1-new surface here (the configurable email body lives on the Emails tab — see `emails.md`).
