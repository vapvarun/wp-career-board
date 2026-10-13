---
id: search-featured-first
priority: medium
personas: anonymous
requires: seed:jobs
last_verified: 2026-10-13
---

# Featured jobs sort first, and jobs with no featured row still appear

**Why this journey exists:** jobs with no `_wcb_featured` row sorted below older unfeatured jobs (fixed in 1.8.1, commit 3675e3b). The sort uses `COALESCE( meta_value = '1', 0 ) DESC`.

## Steps

1. Pick two published jobs: mark one featured (`wp post meta update <id-a> _wcb_featured 1`) and delete the meta from the other (`wp post meta delete <id-b> _wcb_featured`).
2. Anonymous `GET /wp-json/wcb/v1/jobs?per_page=50` → expect both jobs present; every `featured: true` item comes before every non-featured item.
3. Same with a keyword both titles share (`?search=Engineer`) → expect the same order rule with relevance second.
4. Open `/find-jobs/` → expect the featured job's card first with the amber Featured badge, and job B listed.
5. `wp post list --post_type=wcb_job --post_status=publish --format=count` equals the total the Find Jobs header reports (no job lost to the join).
6. tail debug.log diff → expect ZERO new fatal/warning lines.

## Teardown

```bash
wp post meta update <id-a> _wcb_featured <original>
```
