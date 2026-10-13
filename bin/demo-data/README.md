# Demo dataset (QA, screenshots, UX review)

`wp eval-file wp-content/plugins/wp-career-board/bin/seed-demo-data.php` seeds a realistic board:
24 invented companies with logos, ~130 jobs with full descriptions and believable pay,
42 candidates with CC0 stock photos and Pro resumes, ~120 applications across every status.
Add `teardown` to remove it. Everything is tagged `_wcb_demo`. Not shipped (bin/ is excluded from the zip).

All QA - smoke walks, journeys, card checks, screenshots - runs on this data. Content a test
creates gets the same care: a real-sounding title and company, never "Smoke Job 3",
"QA Test" or a random string. Uniqueness, when needed, comes from a realistic variation
(city, team, level).

## Files

- `dataset.json` - the data the seeder reads. Regenerate with
  `python3 src/build.py src dataset.json` after editing `src/*.py` (needs the Openverse
  `candidates.json` cache the build script reads; see `src/build.py`).
- `logos/*.webp` - generated marks for the invented companies (`python3 src/logos.py src logos`, macOS fonts).
- Photos are not committed. The seeder downloads each from Openverse, crops it to a headshot
  and stores the credit (creator, licence, source) as the attachment caption. All are CC0.

Companies and people are invented. Real brand names are never used in demo content.
