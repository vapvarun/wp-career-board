---
id: admin-candidates-page-renders
priority: high
personas: varundubey
requires: mu:autologin, seed:jobs
last_verified: 2026-05-09
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
8. Diff `debug.log` → expect ZERO new fatal/warning/notice lines

## Teardown

```bash
wp post delete $CAND_ID --force
```

## Notes

- The candidates list screen is `edit.php?post_type=wcb_resume`. The manifest entry records it as `wcb-candidates` but the actual URL uses the native CPT edit screen.
- If `resume_archive_enabled` is OFF, the "View" row action may not be present (no public URL). The journey only asserts "Edit" works.
- Search uses WordPress core WP_Query `s` param; no custom search endpoint is needed for this journey.
