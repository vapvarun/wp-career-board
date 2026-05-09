---
id: admin-settings-general-tab-save
priority: critical
personas: varundubey
requires: mu:autologin
last_verified: 2026-05-09
needs: cli
---

# Admin saves one Job Listings setting; all other settings are preserved (merge contract)

**Why this journey exists:** The `wcb_settings` option is a single serialised array. The sanitize callback in `AdminSettings::sanitize()` uses a tab-detection + overlay strategy: it reads existing settings, identifies which tab was submitted, and overwrites only that tab's keys. This journey verifies that editing ONE listing setting does NOT zero-out the notifications or pages keys (the merge contract).

## Steps

1. As `varundubey`, snapshot the current `wcb_settings` option before any change:
   ```bash
   BEFORE=$(wp option get wcb_settings --format=json)
   echo "$BEFORE"
   ```
   Note the current value of `jobs_per_page` and `notification_email` (a non-listings key)
2. Navigate to `/wp-admin/admin.php?page=wcb-settings&tab=listings&autologin=1` → expect 200, "Job Listings" section is visible
3. Edit ONLY the `jobs_per_page` field: change its value from the current value to `15` (or a different number). Do NOT touch any other field. Submit the form ("Save Changes" button)
4. Expect redirect to `admin.php?page=wcb-settings&tab=listings&settings-updated=1` with a "Settings saved." notice
5. Verify persistence:
   ```bash
   wp option get wcb_settings --format=json | python3 -c "import json,sys; d=json.load(sys.stdin); print('jobs_per_page:', d.get('jobs_per_page'))"
   ```
   → output is `jobs_per_page: 15`
6. Verify merge contract — the notifications key is untouched:
   ```bash
   wp option get wcb_settings --format=json | python3 -c "
   import json, sys
   before = $BEFORE
   after  = json.load(sys.stdin)
   # notification_email should not have been zeroed out
   assert after.get('notification_email') == before.get('notification_email'), \
     f'MERGE FAIL: notification_email changed from {before.get(\"notification_email\")} to {after.get(\"notification_email\")}'
   # pages keys should not have been zeroed out
   for k in ['jobs_archive_page', 'employer_dashboard_page']:
     assert after.get(k) == before.get(k), f'MERGE FAIL: {k} changed'
   print('Merge contract: PASS')
   "
   ```
   → output is `Merge contract: PASS`
7. Restore the original `jobs_per_page` value:
   ```bash
   ORIG_JPP=$(echo "$BEFORE" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d.get('jobs_per_page', 10))")
   # Re-save via settings API form POST (or direct option patch for teardown)
   ```
8. Diff `debug.log` → expect ZERO new fatal/warning/notice lines

## Teardown

```bash
# Restore original jobs_per_page
ORIG_JPP=$(echo "$BEFORE" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d.get('jobs_per_page', 10))")
wp option patch update wcb_settings jobs_per_page "$ORIG_JPP" 2>/dev/null || true
```

## Notes

- The merge logic lives in `AdminSettings::sanitize()` (lines 169–223 in `admin/class-admin-settings.php`). The submitted-tab detection checks for the PRESENCE of a tab's field keys in `$_POST` — not a hidden `_tab` field. If a future refactor breaks this detection, ALL keys would be written on every save, clobbering the other tabs.
- The `antispam` tab uses a separate `admin-post.php` action (`wcb_save_antispam`) and writes to `wcb_settings` directly — it is not subject to the same merge contract tested here. Its own merge is tested in `admin-settings-antispam-tab-save.md`.
- Run with `WP_DEBUG=true` to catch any accidental `$_POST` sanitization warnings.
