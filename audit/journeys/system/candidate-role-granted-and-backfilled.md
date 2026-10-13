---
id: candidate-role-granted-and-backfilled
priority: high
personas: varundubey
requires: mu:autologin
last_verified: 2026-10-13
needs: cli
bug_ref: 10395457283
---

# Anyone who applies or has a resume gets the candidate role, and existing applicants are backfilled by the 1.3.8 upgrade

**Why this journey exists:** the Candidates admin screen and its counts query the `wcb_candidate` role, but a member who simply applied (the default: any signed-in member may apply) never had it, so the screen was empty or short. Since 1.8.1 the role follows the first application or resume, and DB 1.3.8 schedules a background backfill for people who applied earlier.

## Steps

1. Live grant, application: `UID=$(wp user create jonas.keller jonas.keller@example.test --role=subscriber --porcelain)`; `wp post create --post_type=wcb_application --post_status=submitted --post_title='Application - Backend Developer, Payments' --meta_input='{"_wcb_qa_smoke":"1"}' --post_author=$UID --porcelain`; `wp user get $UID --field=roles` → contains `wcb_candidate`. The user is listed at `$WCB_SITE/wp-admin/admin.php?page=wcb-candidates`.
2. Live grant, resume: a second subscriber (`lena.fischer`), `wp post create --post_type=wcb_resume --post_status=publish --post_title='Backend Developer, 6 years in payments' --meta_input='{"_wcb_qa_smoke":"1"}' --post_author=<uid2>` → `wcb_candidate` granted (hook `save_post_wcb_resume`).
3. Negative - administrators are skipped: `wp post create --post_type=wcb_application --post_status=submitted --post_author=1` → `wp user get 1 --field=roles` does NOT gain `wcb_candidate` (`grant_role()` ignores users with `manage_options`), and admin does not appear on the Candidates screen. Guest applications (`post_author` 0) grant nothing.
4. Backfill setup: remove the role from both smoke users (`wp user remove-role $UID wcb_candidate`), then simulate an upgrade: `wp option update wcb_db_version 1.3.7` and run any request (`wp eval 'echo 1;'` loads WordPress; `Install::maybe_migrate()` runs at init priority 5) → `wp option get wcb_db_version` = `1.3.8` and `wp cron event list --fields=hook,args | grep wcb_backfill_candidate_role` shows one event with args `[0]`.
5. Run it: `wp cron event run wcb_backfill_candidate_role` → both smoke users have `wcb_candidate` again; the administrator still does not. Running it a second time changes nothing and logs nothing.
6. Batching at scale: seed 450 users each with one application (`wp user generate --count=450 --role=subscriber`, then one `wcb_application` per user), strip the role from all, reset `wcb_db_version` to 1.3.7 and trigger the upgrade → each run handles at most 200 distinct authors and, when it handled 200, schedules the next run with the last author ID as its argument (`wp cron event list --fields=hook,args` shows `[<last-id>]`); after three runs all 450 have the role and no further event is queued.
7. The Candidates screen at 450+ rows paginates (`LIMIT`/`OFFSET`, page links at the bottom, total count from `COUNT(*)`) and search by name or email still works.
8. tail debug.log diff → expect ZERO new fatal/warning lines.

## Teardown

```bash
wp post delete $(wp post list --post_type=wcb_application,wcb_resume --post_status=any --format=ids) --force 2>/dev/null
wp user delete $(wp user list --login__in=jonas.keller,lena.fischer --field=ID) --yes
wp user delete $(wp user list --role=subscriber --field=ID --search='*generated*') --yes 2>/dev/null
wp option update wcb_db_version 1.3.8
```

## Notes

- Hook is `CandidatesModule::BACKFILL_HOOK` = `wcb_backfill_candidate_role`; the backfill walks `post_author` ascending (`post_author > %d ... LIMIT 200`) so skipped users never stall it.
- The teardown deletes the 450 generated users by role search; adjust the filter to however you named them. Do not run the batching step on a site with real subscribers.
