---
id: find-jobs-multi-category
priority: medium
personas: anonymous
requires: seed:jobs
last_verified: 2026-10-13
bug_ref: 10398731580
---

# Find Jobs filters by several categories, and a parent includes its subcategories

**Why this journey exists:** 1.8.1 made the category filter multi-select (Free). Ticking a parent ticks its children; results are the union.

## Steps

1. Open `/find-jobs/` → in the Category filter, type "Eng" → expect the list narrows, each child shows its parent's name, and a no-match query shows an empty message.
2. Tick two categories (e.g. Engineering and Marketing) → expect the URL carries both slugs and every result belongs to one of them.
3. `GET /wp-json/wcb/v1/jobs?category=engineering,marketing` → expect the same IDs as the page.
4. Tick a parent with children → expect every child ticked; untick one child → expect the parent shows a partial state and that child's jobs drop out.
5. Reload the page with the URL from step 2 → expect the same ticks restored.
6. tail debug.log diff → expect ZERO new fatal/warning lines.

## Notes

- Posting a job in several categories (up to 3) is Pro: see Pro journey `job-categories-up-to-three`.
