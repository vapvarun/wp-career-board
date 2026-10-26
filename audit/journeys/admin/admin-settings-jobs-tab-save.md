---
id: admin-settings-jobs-tab-save
priority: critical
personas: varundubey
requires: mu:autologin
last_verified: 2026-10-26
bug_ref: 10359893313, 10395593540
needs: cli
---

# Admin saves one Jobs tab setting; all other tabs' settings are preserved

**Why this journey exists:** `wcb_settings` is one serialised option shared by every tab. The Jobs tab form posts to `options.php` with the hidden `_wcb_form` marker, and `AdminSettings::sanitize()` then merges only the posted keys over the stored option. This journey guards that merge contract (saving one tab must never wipe another tab's keys) and the Jobs tab copy and defaults. It replaces the former `admin-settings-save-merge` and `admin-settings-general-tab-save` journeys.

## Steps

1. Snapshot before:
   ```bash
   BEFORE=$(wp option get wcb_settings --format=json)
   ORIG_EXPIRE=$(echo "$BEFORE" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d.get('jobs_expire_days', 30))")
   echo "$BEFORE" > /tmp/wcb_settings_before.json
   echo "Current jobs_expire_days: $ORIG_EXPIRE"
   ```
2. Navigate to `/wp-admin/admin.php?page=wcb-settings&tab=listings&autologin=1` → expect 200, the Jobs section (`#section-listings`) renders
3. Change ONLY `jobs_expire_days` (`#wcb-jobs-expire-days`) to `45`. Leave all other fields at their current values. Submit ("Save changes")
4. Expect redirect with `settings-updated=true` and the "Settings saved." notice
5. Verify the targeted key persisted:
   ```bash
   wp option get wcb_settings --format=json | python3 -c "import json,sys; d=json.load(sys.stdin); print('jobs_expire_days:', d.get('jobs_expire_days'))"
   ```
   → `jobs_expire_days: 45`
6. Verify the merge contract: every key that is NOT on the Jobs tab is unchanged. Keys from the Pages, Emails, Applications and Antispam tabs (for example `jobs_archive_page`, `notification_email`, `apply_resume_max_mb`, `captcha_provider`) must keep their original values and must not be zero, empty or missing:
   ```bash
   wp option get wcb_settings --format=json | python3 -c "
   import json,sys
   before=json.load(open('/tmp/wcb_settings_before.json')); after=json.load(sys.stdin)
   jobs_tab={'auto_publish_jobs','report_auto_hide_threshold','jobs_expire_days','jobs_per_page','jobs_default_sort','salary_currency','apply_featured_days','job_schema_enabled','require_job_location','default_country','social_tags_enabled'}
   bad=[k for k in before if k not in jobs_tab and after.get(k)!=before[k]]
   print('Merge contract: PASS' if not bad else 'MERGE FAIL: '+', '.join(bad))
   "
   ```
   → `Merge contract: PASS`
7. Verify `salary_currency` was NOT reset to the `USD` default when it was previously set to something else (it is on the Jobs tab, so it must round-trip from the form value):
   ```bash
   ORIG_CURR=$(python3 -c "import json; print(json.load(open('/tmp/wcb_settings_before.json')).get('salary_currency','USD'))")
   AFTER_CURR=$(wp option get wcb_settings --format=json | python3 -c "import json,sys; print(json.load(sys.stdin).get('salary_currency','USD'))")
   [ "$ORIG_CURR" = "$AFTER_CURR" ] && echo "currency: PASS" || echo "currency: FAIL (was $ORIG_CURR, now $AFTER_CURR)"
   ```
   → `currency: PASS`
8. **Jobs tab copy and default (1.8.1).** On `$WCB_SITE/wp-admin/admin.php?page=wcb-settings` > Jobs (`#section-listings`): there is NO separate setting row labelled "When a job ends"; its explanatory text ("At its deadline a job stops taking applications...") now sits inside the **Default listing length** row (`#wcb-jobs-expire-days`), as plain description, so it no longer looks like a control. On a fresh install `#wcb-jobs-per-page` defaults to 12 (`wp option get wcb_settings --format=json | jq .jobs_per_page` is 12 or absent, and the field shows 12), and its description says a multiple of 12 fills the 2, 3 and 4 column grids. An existing site keeps whatever value it saved.
9. Diff `debug.log` → expect ZERO new fatal/warning/notice lines

## Teardown

```bash
wp option patch update wcb_settings jobs_expire_days "$ORIG_EXPIRE" 2>/dev/null || true
```

## Notes

- Merge logic: `AdminSettings::sanitize()` in `admin/class-admin-settings.php`. When `_wcb_form` is present it starts from the stored option and overwrites only the posted keys that exist in `SettingsSchema::fields()` (`admin/class-settings-schema.php`). If a future refactor drops the marker or the start-from-stored step, every save would replace the whole option and clobber the other tabs.
- The Jobs tab (`tab=listings`) posts: `auto_publish_jobs`, `report_auto_hide_threshold`, `jobs_expire_days`, `jobs_per_page`, `jobs_default_sort`, `salary_currency`, `apply_featured_days`, `job_schema_enabled`, `require_job_location`, `default_country`, `social_tags_enabled`. `deadline_auto_close` has no toggle there; it is on for sites installed on 1.8.0+ and switched on from the Jobs tab for older sites.
- `allow_withdraw` and `apply_resume_required` live on the Applications tab (a separate form); an unchecked checkbox there is sent as `0` by the hidden `_wcb_form` companion fields, so unchecking intentionally turns them off.
- `jobs_expire_days` is clamped to a minimum of 1 by the schema (the form allows 1-365); `jobs_per_page` is clamped to 1-100.
- The Antispam tab also posts to `options.php` (since 1.8.0) and is covered by `admin-settings-antispam-tab-save.md`.
- `critical` priority: a replace-on-save bug would silently disable notifications or other features on every installation.
