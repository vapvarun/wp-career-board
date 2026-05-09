---
id: admin-settings-antispam-tab-save
priority: high
personas: varundubey
requires: mu:autologin
last_verified: 2026-05-09
needs: cli
---

# Admin cycles CAPTCHA provider on Antispam tab; each switch persists; prior keys survive

**Why this journey exists:** The Antispam tab uses a SEPARATE save mechanism (`admin-post.php?action=wcb_save_antispam`) that directly calls `update_option('wcb_settings', ...)`. This journey verifies: (a) each provider switch persists correctly, (b) switching away from Turnstile does NOT delete the Turnstile site/secret keys from the option — they must survive for when the admin switches back, and (c) the `wcb_settings_tab_antispam` action fires cleanly.

## Steps

1. Snapshot before:
   ```bash
   BEFORE=$(wp option get wcb_settings --format=json)
   ORIG_PROVIDER=$(echo "$BEFORE" | python3 -c "import json,sys; d=json.load(sys.stdin); c=d.get('captcha_provider','none'); print(c)")
   echo "Current captcha_provider: $ORIG_PROVIDER"
   ```
2. Navigate to `/wp-admin/admin.php?page=wcb-settings&tab=antispam&autologin=1` → expect 200, Anti-Spam section renders with CAPTCHA Provider dropdown; three options: "None (Honeypot only)", "Cloudflare Turnstile", "Google reCAPTCHA v3"
3. Switch provider to `turnstile`: select "Cloudflare Turnstile" in the dropdown, enter fake keys `ts_site_key_smoke` and `ts_secret_key_smoke`, submit the form
4. Expect redirect back to `?tab=antispam&wcb-antispam-saved=1`; verify the success notice "Anti-Spam settings saved." is visible
5. Verify persistence in `wcb_settings`:
   ```bash
   wp option get wcb_settings --format=json | python3 -c "
   import json,sys
   d=json.load(sys.stdin)
   print('provider:', d.get('captcha_provider'))
   print('ts_site_key:', d.get('turnstile_site_key','')[:10])
   "
   ```
   → `provider: turnstile`, `ts_site_key: ts_site_ke` (first 10 chars)
6. Switch provider to `recaptcha`: select "Google reCAPTCHA v3", enter `rc_site_key_smoke` and `rc_secret_key_smoke`, submit
7. Verify persistence:
   ```bash
   wp option get wcb_settings --format=json | python3 -c "
   import json,sys
   d=json.load(sys.stdin)
   print('provider:', d.get('captcha_provider'))
   print('rc_site_key:', d.get('recaptcha_site_key','')[:10])
   # CRITICAL: Turnstile keys must NOT have been deleted
   ts = d.get('turnstile_site_key','')
   print('turnstile_site_key survived:', 'YES' if ts else 'NO - REGRESSION')
   "
   ```
   → `provider: recaptcha`, `turnstile_site_key survived: YES`
8. Switch provider back to `none`: submit with "None" selected
9. Verify:
   ```bash
   wp option get wcb_settings --format=json | python3 -c "
   import json,sys; d=json.load(sys.stdin)
   print('provider:', d.get('captcha_provider','none'))
   print('ts key survived:', 'YES' if d.get('turnstile_site_key') else 'NO')
   print('rc key survived:', 'YES' if d.get('recaptcha_site_key') else 'NO')
   "
   ```
   → `provider: none`, both key values survived
10. Diff `debug.log` → expect ZERO new fatal/warning/notice lines

## Teardown

```bash
# Restore original captcha_provider
wp option patch update wcb_settings captcha_provider "$ORIG_PROVIDER" 2>/dev/null || true
# Clear smoke keys
wp option patch update wcb_settings turnstile_site_key "" 2>/dev/null || true
wp option patch update wcb_settings turnstile_secret_key "" 2>/dev/null || true
wp option patch update wcb_settings recaptcha_site_key "" 2>/dev/null || true
wp option patch update wcb_settings recaptcha_secret_key "" 2>/dev/null || true
```

## Notes

- The Antispam save path is `admin-post.php?action=wcb_save_antispam` → `AntiSpamModule::save_settings()`. It reads the full existing `wcb_settings` array, overlays only the captcha keys, and calls `update_option('wcb_settings', ...)` directly — NOT through the Settings API. This is intentional (the form doesn't use `options.php`) but means the merge guarantee is manually implemented in `save_settings()`.
- Fake API keys are fine for this journey — no live Cloudflare or Google verification is expected. The keys are stored as plain text strings; no encryption is applied on the Free side for captcha keys.
- `wcb-antispam-saved` query param is checked inside `render_settings_tab()` to show the success notice (not `settings-updated`).
