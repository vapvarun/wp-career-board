---
id: admin-settings-save-merge
priority: critical
personas: varundubey
requires: mu:autologin
last_verified: 2026-05-09
needs: cli
---

# Admin changes one setting key; other keys retain their original values (settings merge contract)

**Why this journey exists:** the settings save must MERGE with existing values, not replace the whole option. A save that writes only the submitted tab's keys and zeros out all other tabs is a data-loss bug that is invisible to the admin until a different tab's feature silently breaks. This is documented as a hard contract in the smoke runbook (C.admin.crud).

## Steps

1. As `varundubey` (admin), navigate to `/wp-admin/admin.php?page=wcb-settings&autologin=1` → expect HTTP 200, settings page renders
2. Read the current full settings object: `wp option get wcb_settings` → capture as `<original-settings>` (JSON blob)
3. Identify at least two distinct settings keys from different logical groups (e.g. a "Listings" key and a "Notifications" key from `wcb_settings`) — note their current values
4. Navigate to one settings tab (e.g. the Listings tab) and change exactly ONE value (e.g. toggle "resume_archive_enabled") → save the tab → expect HTTP 200 or 302 (no PHP error)
5. Read the updated settings: `wp option get wcb_settings` → capture as `<updated-settings>`
6. Assert the changed key updated: verify the key toggled in step 4 has the NEW value in `<updated-settings>`
7. Assert all other keys are unchanged: compare `<original-settings>` and `<updated-settings>` — every key NOT changed in step 4 must have identical values. In particular: keys from a DIFFERENT tab (e.g. notifications settings) must not be zero/empty/missing in `<updated-settings>`
8. Restore: `wp option update wcb_settings '<original-settings>'` (paste the captured value) → expect the option is restored
9. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

```bash
# Restore original settings (run even if earlier steps failed)
wp option update wcb_settings '<original-settings-captured-in-step-2>'
```

## Notes

- This is a `critical` priority journey because a settings-replace-on-save bug would silently disable notifications or other features across all installations simultaneously.
- The settings option key is `wcb_settings` (group: `wcb_settings_group`) per the manifest.
- The "merge" contract means the REST settings endpoint (or admin form POST) must do a `wp_parse_args($new, $existing)` style merge, not a direct `update_option($new)` replace.
