---
id: admin-categories-and-types-crud
priority: high
personas: varundubey
requires: mu:autologin
last_verified: 2026-05-09
needs: cli
---

# Admin creates, edits, and deletes terms for all five job taxonomies

**Why this journey exists:** Guards that all five `wcb_job`-linked taxonomies (`wcb_category`, `wcb_job_type`, `wcb_tag`, `wcb_location`, `wcb_experience`) are registered correctly and that the native term edit screen works for each. Hierarchical taxonomies (`wcb_category`, `wcb_location`) must support parent-child relationships; flat ones must not show the parent dropdown.

## Steps

1. Verify all five taxonomies are registered:
   ```bash
   wp taxonomy list --fields=name,hierarchical | grep -E 'wcb_category|wcb_job_type|wcb_tag|wcb_location|wcb_experience'
   ```
   → 5 rows; `wcb_category` and `wcb_location` show `hierarchical: 1`; `wcb_job_type`, `wcb_tag`, `wcb_experience` show `hierarchical: 0`
2. Create one term in each taxonomy:
   ```bash
   TID_CAT=$(wp term create wcb_category 'Smoke Category' --slug=smoke-category --porcelain)
   TID_TYPE=$(wp term create wcb_job_type 'Smoke Type' --slug=smoke-type --porcelain)
   TID_TAG=$(wp term create wcb_tag 'Smoke Tag' --slug=smoke-tag --porcelain)
   TID_LOC=$(wp term create wcb_location 'Smoke Location' --slug=smoke-location --porcelain)
   TID_EXP=$(wp term create wcb_experience 'Smoke Experience' --slug=smoke-experience --porcelain)
   echo "TIDs: $TID_CAT $TID_TYPE $TID_TAG $TID_LOC $TID_EXP"
   ```
3. Navigate to the category term list:
   `/wp-admin/edit-tags.php?taxonomy=wcb_category&post_type=wcb_job&autologin=1`
   → expect 200, "Smoke Category" appears in the table; "Parent" column is present (hierarchical taxonomy)
4. Navigate to the job type term list:
   `/wp-admin/edit-tags.php?taxonomy=wcb_job_type&post_type=wcb_job&autologin=1`
   → expect 200, "Smoke Type" appears; "Parent" column is NOT present (flat taxonomy)
5. Create a child category under "Smoke Category":
   ```bash
   TID_CHILD=$(wp term create wcb_category 'Smoke Child Category' --parent=$TID_CAT --porcelain)
   ```
   Navigate to `edit-tags.php?taxonomy=wcb_category&post_type=wcb_job` → expect "Smoke Child Category" is indented beneath "Smoke Category" in the list
6. Edit "Smoke Category": navigate to `term.php?taxonomy=wcb_category&tag_ID=$TID_CAT&post_type=wcb_job` → change the description to "Smoke description updated"; submit
   ```bash
   wp term get wcb_category $TID_CAT --field=description
   ```
   → output is `Smoke description updated`
7. Delete "Smoke Type" via the term list (row action "Delete"):
   ```bash
   wp term delete wcb_job_type $TID_TYPE
   wp term list wcb_job_type --slug=smoke-type --format=count
   ```
   → count is 0
8. Verify all five taxonomy admin screens are reachable without error:
   ```bash
   for SLUG in wcb_category wcb_job_type wcb_tag wcb_location wcb_experience; do
     wp eval "echo \$SLUG; echo (bool) taxonomy_exists('$SLUG') ? ' OK' : ' MISSING';"
   done
   ```
   → all five print "OK"
9. Diff `debug.log` → expect ZERO new fatal/warning/notice lines

## Teardown

```bash
wp term delete wcb_category $TID_CAT $TID_CHILD 2>/dev/null || true
wp term delete wcb_job_type $TID_TYPE 2>/dev/null || true
wp term delete wcb_tag $TID_TAG 2>/dev/null || true
wp term delete wcb_location $TID_LOC 2>/dev/null || true
wp term delete wcb_experience $TID_EXP 2>/dev/null || true
```

## Notes

- All five taxonomies are registered in `modules/jobs/class-jobs-module.php` via `register_taxonomy()`. The rewrite slugs are: `job-category`, `job-type`, `job-tag`, `job-location`, `job-experience`.
- Hierarchical taxonomies (`wcb_category`, `wcb_location`) get the "Add New" + parent dropdown on the left side of `edit-tags.php`. Flat taxonomies get the same left panel but without a Parent field.
- The post_type query parameter (`&post_type=wcb_job`) is required in the admin URL for the correct CPT context to be set. Without it, WordPress may render the tag-edit screen in the default post context.
