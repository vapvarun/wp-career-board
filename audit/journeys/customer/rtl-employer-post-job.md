---
id: rtl-employer-post-job
priority: high
personas: employer.figma
requires: mu:autologin, theme:any, npm:rtl-built
last_verified: 2026-09-28
bug_ref: 10345568037
---

# Post a job from the dashboard on a right-to-left site

**Why this journey exists:** on an RTL request each of our block styles must print its generated `-rtl.css` twin. Core marks block styles `rtl=replace` with a `.min` suffix when SCRIPT_DEBUG is off, which never matches our unminified files, so the job form kept its left-to-right stylesheet and the page scrolled 10,000px sideways.

## Steps

1. Run `npm run rtl` in Free and Pro so the twins exist, and force the request to RTL for the test only (a cookie-driven mu-plugin setting `$wp_locale->text_direction` and `wp_styles()->text_direction` to `rtl`; do not change the site language).
2. As `employer.figma` at 390px, open `/employer-dashboard/#post-job` → expect `document.documentElement.dir` to be `rtl` and `scrollWidth - clientWidth` to be `0`.
3. List the page's `link[rel=stylesheet]` under `wp-career-board*` → expect no `<link>` for a file whose `-rtl.css` twin exists on disk to point at the left-to-right file (for example `blocks/job-form/style.css` must be `style-rtl.css`, and `employer-dashboard/styles/views.css` must not print beside `views-rtl.css`).
4. Load the same page without the RTL cookie → expect `dir` empty, zero `-rtl` links, overflow `0` (LTR control unchanged).
5. Across the stylesheet requests of steps 2 to 4 → expect zero 4xx responses.
6. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

Remove the temporary mu-plugin. Nothing else changes.

## Notes

- Regression guard for 10345568037. The runtime opt-in is `core/class-rtl.php`; `tests/test-rtl-styles.php` covers the same rule at unit level, including the `.min` suffix case.
- Twins are gitignored build output (`grunt rtl`); a checkout without them serves the LTR files, which is correct but not mirrored.
