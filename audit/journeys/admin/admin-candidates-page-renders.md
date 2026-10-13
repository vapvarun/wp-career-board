---
id: admin-candidates-page-renders
priority: high
personas: varundubey
requires: mu:autologin, seed:jobs
last_verified: 2026-10-13
bug_ref: 10395457283
needs: cli
---

# Admin views and searches the Candidates (Resumes) list table

**Why this journey exists:** Guards that the wcb-candidates screen renders, that the search box filters by name and returns matches without a PHP error, and that clicking into a candidate edit screen works.

## Steps

1. As `varundubey`, navigate to `/wp-admin/edit.php?post_type=wcb_resume&autologin=1` → expect 200, list table renders with no PHP error; columns include at minimum Title and Date
2. Create a fixture candidate:
   ```bash
   CAND_ID=$(wp post create --post_type=wcb_resume --post_title='Smoke Candidate AlphaTest' \
     --post_status=publish --post_author=1 --porcelain)
   echo "CAND_ID=$CAND_ID"
   ```
3. Reload the list view; verify "Smoke Candidate AlphaTest" appears in the table
4. Use the search box — navigate to `edit.php?post_type=wcb_resume&s=AlphaTest` → expect exactly 1 result containing "Smoke Candidate AlphaTest"
5. Search for a string that does NOT match: `edit.php?post_type=wcb_resume&s=ZZZZNotFound9999` → expect 0 results, "No candidates found" (or equivalent empty-state message) is shown
6. Click "Edit" row action on "Smoke Candidate AlphaTest" → expect `post.php?action=edit&post=<CAND_ID>` loads with candidate title pre-populated
7. Verify the CPT count via WP-CLI:
   ```bash
   wp post list --post_type=wcb_resume --post_status=publish --format=count
   ```
   → count is ≥ 1
8. **Applicants and resume authors are Candidates (1.8.1, Basecamp 10395457283).** The plugin Candidates screen is `$WCB_SITE/wp-admin/admin.php?page=wcb-candidates` (the `edit.php?post_type=wcb_resume` URL above is the resume CPT). Take a user with no `wcb_candidate` role (`UID=$(wp user create smoke_applicant smoke_applicant@example.test --role=subscriber --porcelain)`), then create an application authored by them: `wp post create --post_type=wcb_application --post_status=submitted --post_title='Smoke applicant app' --post_author=$UID --porcelain` → `wp user get $UID --field=roles` now contains `wcb_candidate` (hook `save_post_wcb_application` -> `CandidatesModule::grant_role()`), and the user appears on `admin.php?page=wcb-candidates`.
9. Same for a resume: create a `wcb_resume` post authored by another subscriber → that user gains `wcb_candidate` (hook `save_post_wcb_resume`).
10. Negative - administrators are left alone: an administrator who applies to a job (test application) does NOT gain `wcb_candidate` and does NOT show on the Candidates screen (`grant_role()` skips users with `manage_options`).
11. Search box: `#wcb-candidate-search-input` has placeholder "Name or email…" and one screen-reader label "Search Candidates"; search `smoke_applicant` → exactly that row.
12. The one-off backfill for applicants that existed before 1.8.1 is walked in `system/candidate-role-granted-and-backfilled`.
13. Diff `debug.log` → expect ZERO new fatal/warning/notice lines

## Teardown

```bash
wp post delete $CAND_ID --force
wp post delete $(wp post list --post_type=wcb_application,wcb_resume --author=$UID --format=ids) --force 2>/dev/null
wp user delete $UID --yes
```

## Notes

- Two screens: `edit.php?post_type=wcb_resume` is the resume CPT list (steps 1-7); the Candidates list table (users with the `wcb_candidate` role, `AdminCandidates`) is `admin.php?page=wcb-candidates` (steps 8-11).
- If `resume_archive_enabled` is OFF, the "View" row action may not be present (no public URL). The journey only asserts "Edit" works.
- Search uses WordPress core WP_Query `s` param; no custom search endpoint is needed for this journey.
