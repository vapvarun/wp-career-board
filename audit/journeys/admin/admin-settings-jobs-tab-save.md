---
id: admin-settings-jobs-tab-save
priority: critical
personas: varundubey
requires: mu:autologin
last_verified: 2026-05-09
needs: cli
---

# Admin saves one Jobs tab setting; all other tabs' settings are preserved

**Why this journey exists:** Mirrors `admin-settings-general-tab-save.md` but targets the Listings tab fields that control job lifecycle (expire days, deadline auto-close, currency). Specifically guards that changing `jobs_expire_days` does NOT overwrite the pages or notifications keys — a real risk because the sanitize loop writes every detected-tab key at once.

## Steps

1. Snapshot before:
   ```bash
   BEFORE=$(wp option get wcb_settings --format=json)
   ORIG_EXPIRE=$(echo "$BEFORE" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d.get('jobs_expire_days', 30))")
   echo "Current jobs_expire_days: $ORIG_EXPIRE"
   ```
2. Navigate to `/wp-admin/admin.php?page=wcb-settings&tab=listings&autologin=1` → expect 200
3. Change ONLY `jobs_expire_days` to `45`. Leave all other fields at their current values. Submit ("Save Changes")
4. Expect redirect with `settings-updated=1` and "Settings saved." notice
5. Verify the targeted key persisted:
   ```bash
   wp option get wcb_settings --format=json | python3 -c "import json,sys; d=json.load(sys.stdin); print('jobs_expire_days:', d.get('jobs_expire_days'))"
   ```
   → `jobs_expire_days: 45`
6. Verify the `salary_currency` key was NOT reset to the default `USD` if it was previously set to something else:
   ```bash
   ORIG_CURR=$(echo "$BEFORE" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d.get('salary_currency','USD'))")
   AFTER_CURR=$(wp option get wcb_settings --format=json | python3 -c "import json,sys; d=json.load(sys.stdin); print(d.get('salary_currency','USD'))")
   [ "$ORIG_CURR" = "$AFTER_CURR" ] && echo "currency: PASS" || echo "currency: FAIL (was $ORIG_CURR, now $AFTER_CURR)"
   ```
   → `currency: PASS`
7. Verify cross-tab merge — pages settings untouched:
   ```bash
   wp option get wcb_settings --format=json | python3 -c "
   import json,sys
   before=$(echo $BEFORE | python3 -c 'import json,sys; d=json.load(sys.stdin); print(repr(d))')
   # simplified: just check jobs_archive_page didn't become 0 when it was set
   d = json.load(sys.stdin)
   print('jobs_archive_page after save:', d.get('jobs_archive_page'))
   "
   ```
   → the page ID should be unchanged from `$BEFORE`
8. Diff `debug.log` → expect ZERO new fatal/warning/notice lines

## Teardown

```bash
wp option patch update wcb_settings jobs_expire_days "$ORIG_EXPIRE" 2>/dev/null || true
```

## Notes

- The `listings` tab fields are: `auto_publish_jobs`, `jobs_per_page`, `jobs_expire_days`, `deadline_auto_close`, `allow_withdraw`, `salary_currency`, `apply_resume_required`, `apply_resume_max_mb`, `apply_featured_days`, `resume_archive_enabled`, `max_resumes`. Submitting ANY one of these triggers the `listings` tab overlay in the sanitizer.
- `allow_withdraw` and `apply_resume_required` default to ON (true). The sanitize function reads them as `!empty($input['field'])` — an unchecked checkbox sends no key in POST, so submitting the form with these unchecked INTENTIONALLY turns them OFF. Verify this is the desired behavior if those are changed during the test.
- Maximum boundary for `jobs_expire_days` is enforced at 1 min, no explicit max clamp in sanitize. Use a value in the range 1–365 to avoid unexpected clamps.
