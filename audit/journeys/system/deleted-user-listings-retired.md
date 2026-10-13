---
id: deleted-user-listings-retired
priority: high
personas: varundubey
requires: mu:autologin
last_verified: 2026-10-13
needs: cli
bug_ref: 10379561608
---

# Deleting a user without reassigning closes their jobs and drafts their companies; reassigning moves them; the upgrade repairs earlier orphans

**Why this journey exists:** WordPress does not delete `wcb_job` or `wcb_company` posts with their author, so a deleted employer left live jobs and a public company with no owner. Since 1.8.1 `delete_user` retires the listings unless the admin chose "Attribute all content to", and DB 1.3.8 repairs listings of users deleted earlier.

## Steps

1. Fixture: `E1=$(wp user create smoke_emp1 smoke_emp1@example.test --role=wcb_employer --porcelain)`; as that author create a published company and three jobs: `C1=$(wp post create --post_type=wcb_company --post_status=publish --post_title='Smoke Gone Co' --post_author=$E1 --porcelain)`, `J1` publish, `J2` pending, `J3` draft (all `--post_type=wcb_job --post_author=$E1`), plus `J4` in `trash`.
2. Delete WITHOUT reassigning: `wp user delete $E1 --yes` → `wp post get $J1 --field=post_status` = `wcb_closed`; `J2` = `wcb_closed`; `J3` = `wcb_closed`; `C1` = `draft` (recoverable). `J4` stays `trash`. The applications on those jobs are untouched (`wp post list --post_type=wcb_application --meta_key=_wcb_job_id --meta_value=$J1 --format=count` unchanged).
3. Public effect: anonymous `GET /wp-json/wcb/v1/jobs` no longer lists `J1`; `/companies/<slug>/` for `C1` is not served as a published company (404 or draft); the RSS feed and the sitemap no longer include `J1`.
4. Reassign path: `E2=$(wp user create smoke_emp2 smoke_emp2@example.test --role=wcb_employer --porcelain)` with a published company and job, then `wp user delete $E2 --reassign=1 --yes` → the job stays `publish`, the company stays `publish`, and `wp post get <job> --field=post_author` = `1`. `retire_owned_listings()` returns early when `$reassign` is set.
5. Upgrade repair: recreate the orphan state in SQL - `wp db query "UPDATE wp_posts SET post_author=999999, post_status='publish' WHERE ID IN ($J1,$C1)"` (author id that does not exist), `wp option update wcb_db_version 1.3.7`, then `wp eval 'echo 1;'` to trigger `Install::maybe_migrate()` → `J1` = `wcb_closed` and `C1` = `draft` again (the upgrade selects `DISTINCT post_author` of job/company posts with no matching user and runs the same retire routine).
6. Scale: give one soon-to-be-deleted employer 250 published jobs (`wp post generate` or a loop, `--post_type=wcb_job --post_author=$E3 --post_status=publish`), delete without reassign → ALL 250 end up `wcb_closed` (the retire loop works in batches of 100 and re-queries until a batch is short), none left `publish`; the request finishes without a timeout.
7. tail debug.log diff → expect ZERO new fatal/warning lines.

## Teardown

```bash
wp post delete $(wp post list --post_type=wcb_job,wcb_company --post_status=any --search='Smoke' --format=ids) --force 2>/dev/null
wp user delete $(wp user list --search='smoke_emp*' --field=ID) --yes 2>/dev/null
wp option update wcb_db_version 1.3.8
```

## Notes

- Statuses retired: jobs in `publish`, `pending`, `draft`, `future`, `wcb_expired` become `wcb_closed`; companies in `publish`, `pending`, `future` become `draft`. Trashed and already-closed rows are left alone.
- Hook is `delete_user` (priority 10, two args), registered in `EmployersModule::boot()`.
