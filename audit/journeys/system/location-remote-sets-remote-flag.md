---
id: location-remote-sets-remote-flag
priority: high
personas: varundubey, employer.figma
requires: seed:jobs
last_verified: 2026-10-13
needs: cli
bug_ref: 10355292713
---

# A job located Remote is always stored as remote, and the 1.8.1 upgrade repairs existing jobs

**Why this journey exists:** the Remote LOCATION term and the `_wcb_remote` flag were two separate things: a job could be located Remote but saved as on-site, so the job page, the Remote-only filter, search, JobPosting schema, RSS and alerts disagreed. `Locations` now sets the flag when the term is set and refuses to store `0` while the term is Remote.

## Steps

1. Term sets the flag: `JOB=$(wp post create --post_type=wcb_job --post_status=publish --post_title='Remote Support Engineer - Europe' --meta_input='{"_wcb_qa_smoke":"1"}' --porcelain)`; `wp post meta update $JOB _wcb_remote 0`; `wp post term set $JOB wcb_location remote` → `wp post meta get $JOB _wcb_remote` = `1` (hook `set_object_terms` -> `Locations::flag_remote_on_terms`).
2. Writing 0 is refused while located Remote: `wp post meta update $JOB _wcb_remote 0` → `wp post meta get $JOB _wcb_remote` is still `1` (filters `update_post_metadata` and `add_post_metadata` -> `Locations::keep_remote_flag`). A form or the admin editor that saves an unticked Remote checkbox AFTER the terms cannot undo it.
3. Not remote stays not remote: a second job located in a non-Remote term keeps `_wcb_remote` = `0` and writing `0` or `1` there behaves normally. Moving the first job to a city term and then writing `0` is allowed (`wp post term set $JOB wcb_location <city-slug>` then `wp post meta update $JOB _wcb_remote 0` -> `0`).
4. Every reader agrees for the Remote job: REST `GET /wp-json/wcb/v1/jobs/$JOB` has `remote: true`; `GET /wp-json/wcb/v1/jobs?remote=1` includes it; the job page shows the Remote badge; the Find Jobs "Remote only" toggle lists it; the JobPosting JSON-LD on the job page contains `"jobLocationType":"TELECOMMUTE"`.
5. Upgrade backfill: put a legacy row back into the old state with SQL (the filter would block the API): `wp db query "UPDATE wp_postmeta SET meta_value='0' WHERE post_id=$JOB AND meta_key='_wcb_remote'"`, `wp option update wcb_db_version 1.3.7`, then `wp eval 'echo 1;'` (init runs the upgrade; the repair is queued on `init` priority 25 so the taxonomy exists) → `_wcb_remote` = `1` again and `wcb_db_version` = `1.3.8`. Direct call also works: `wp eval '\WCB\Core\Locations::backfill_remote_flags();'`.
6. Admin editor: open `post.php?post=$JOB&action=edit`, untick Remote, Update → the box is ticked again after save while the Remote location is still set.
7. tail debug.log diff → expect ZERO new fatal/warning lines.

## Teardown

```bash
wp post delete $JOB --force
wp option update wcb_db_version 1.3.8
```

## Notes

- `backfill_remote_flags()` loads every `wcb_job` with the Remote term (`posts_per_page` -1, `fields=ids`) in one query: fine for the thousands, revisit if a site has tens of thousands of Remote jobs.
- Removing the Remote term from a job does not clear the flag by itself; the editor or form must write `0` afterwards (step 3).
