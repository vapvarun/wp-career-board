---
id: block-theme-pages-readable
priority: high
personas: guest, sarah.chen
requires: mu:autologin, seed:jobs, seed:companies, theme:twentytwentyfive
last_verified: 2026-09-28
bug_ref: 10344651601, 10344675818
---

# Browse and open jobs on a block theme (Twenty Twenty-Five)

**Why this journey exists:** on a block theme the plugin's own rules must not hide the theme's Query Loop titles, show a message the store has hidden, or paint text the theme's background swallows; Reign and BuddyX mask all three.

## Steps

1. As `guest`, with Twenty Twenty-Five active, open `/jobs/` → expect HTTP 200 and every `.wp-block-post-title` inside `.wp-block-post-template` to have a computed `display` other than `none` (a job name on every entry); the page-level `.wp-block-post-title` (outside the loop) stays hidden.
2. Open `/companies/` → expect the same: every entry shows its company name.
3. Open `/find-jobs/` and `/find-companies/` → expect `.wcb-page-heading`, `.wcb-results-count` and `.wcb-filter-remote` text to be at least 4.5:1 against the page background, in the theme's default style and in its dark style (`--wp--preset--color--base` and `--contrast` swapped).
4. As `sarah.chen`, open any single job → expect `.wcb-job-report__done` to be `hidden` with computed `display: none`, and no "reported for review" text visible.
5. As `guest` and as `sarah.chen`, on `/find-jobs/`, `/find-companies/`, `/find-candidates/`, a single job and a company page → expect zero elements inside `.wp-block-wp-career-board-*` or `.wp-block-wcb-*` that carry the `hidden` attribute yet compute to a `display` other than `none`.
6. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

None. Read-only.

## Notes

- Switch the theme per request (a cookie-driven mu-plugin filtering `pre_option_template` and `pre_option_stylesheet`) rather than changing the site's active theme.
- Regression guard for 10344651601 (archive titles, dark contrast) and 10344675818 (the `hidden` attribute losing to a class that sets `display`). The `hidden` rule lives in `assets/css/frontend-components.css`; the canvas text colour is `--wcb-canvas-fg` in `assets/css/frontend.css`.
- The blank band above content on TT25 is the theme's own 70px margin and 70px padding, not a defect.
