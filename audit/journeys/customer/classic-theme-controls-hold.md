---
id: classic-theme-controls-hold
priority: high
personas: guest, ahmed.karim
requires: mu:autologin, seed:jobs, seed:companies, theme:astra, theme:kadence
last_verified: 2026-10-25
bug_ref: 10436072102, 10436093928, 10436185528, 10436196754, 10436217621, 10436294696, 10436356200, 10430753987, 10412882677, 10439524614
---

# Plugin controls keep their look on Astra and Kadence

**Why this journey exists:** Astra and Kadence style bare `button:hover`, `button:focus`, `input[type=search]`, `input[type=range]` and `.entry-content a` at (0,1,1) to (0,2,1). A plugin control styled with one class loses to them: tiles and stars fill with the theme's button colour, link-buttons are underlined, the salary slider becomes a text box. Reign and BuddyX hide all of this, so it only shows on a site owner's classic theme.

## Steps

1. As `ahmed.karim`, with Astra active, open `/employer-dashboard/` and hover then keyboard-focus the "Total Jobs" stat tile → expect computed `background-color` `rgb(255, 255, 255)` (the card surface) and dark text, never the theme blue `rgb(4, 92, 180)`. Repeat on Kadence: never `rgb(33, 83, 135)` (card:10436072102).
2. Applications → select an applicant → hover each star under "Rating and notes" → expect a transparent background; an off star stays an outline, an on star stays amber (card:10436356200).
3. My Jobs → expect `input.wcb-job-search` at 44px tall with the same corner radius as the status pills' row controls, on Astra and Kadence, at 1440 and 390; focus it → expect the plugin's blue ring, not the theme's grey border and drop shadow (card:10436196754).
4. On the same dashboard, `#wcb-note-draft` and, as a candidate, the resume builder's Professional Summary → expect `padding-top` of 12px, so text never touches the top border, on any theme (card:10436294696).
5. As `guest` on Astra, `/find-jobs/`, a single job and a company page → expect zero `a.wcb-btn`, `a.wcb-job-alert-card__btn` or `a.wcb-cp-link` with computed `text-decoration-line: underline` (card:10436093928).
6. On Kadence, `/find-jobs/` → expect the Salary sliders (`.wcb-salary-popover__slider`) at 0 padding, 0 border and under 20px tall, focused or not (card:10436185528).
7. With Pro, a page holding the Credit Balance block → hover and focus "Transaction History" → expect a transparent background and no theme shadow on Astra and Kadence (card:10436217621).
8. With Pro, `/job-map/` and a page holding the Resume Map block → expect the red Search button to compute `display: inline-flex` (or `flex`) with the magnifier's centre within 1px of the button's centre (card:10412882677).
9. As `ahmed.karim`, Post a Job → in the description editor add a bulleted list and a numbered list → expect `list-style-type` `disc` and `decimal` while editing, as on the public job page (card:10430753987).
9a. On Kadence, `/job-map/` → hover, then Tab to, the My location (crosshair) button → the icon stays dark (`--wcb-contrast`) on the light button at rest, hover, focus and press. Same on a page with the Resume Map block, where on Kadence the icon was white even at rest (card:10439524614).
9b. **No theme underlines on our chips and titles (card:10439870246, card:10439827925).** On Astra, Kadence and Reign, at 1440 and 390: job and company card titles, company names, skill chips, the share buttons, `Back to dashboard`, dashboard bookmark and application titles, and the My Jobs row menu (View, Edit, Delete) → computed `text-decoration-line: none`; title links underline on hover and keyboard focus only.
9c. **40px targets on a phone (card:10439870251, card:10439833707).** At 390 (or a coarse pointer): job page Report this job, Save job, the apply panel close, the similar-jobs alert button after applying; employer dashboard applicant chips, rating stars and the My Jobs status pills → each at least 40px tall (stars 40×40). On a 1440 fine pointer they keep their compact look. The status pills stay on one row and scroll sideways at 390 with no page overflow.
9d. **Apply Email reads like every other detail row (card:10439870327).** A job with a long apply email → the Job Details row shows label left, value right on one line with an ellipsis, and the full address in the link's `title`, at 1440 and 390.
9e. **Dashboard lists stay compact on a phone (card:10439832512, card:10439832520).** As a candidate with saved jobs and companies, at 390 → each saved row stays one horizontal row (about 70-90px). My Applications shows a Withdraw button only on a withdrawable application and a Remove button only when the job is gone; no row menu; each button's accessible name names the job.
10. tail debug.log diff → expect ZERO new fatal/warning lines.

## Teardown

None. Read-only.

## Notes

- Switch the theme per request with a cookie-driven mu-plugin that filters `stylesheet` and `template`, never the site's active theme. Install Astra and Kadence inactive.
- Force hover and focus with CDP `CSS.forcePseudoState` so the measurement does not depend on the pointer.
- Each fix outranks the theme rule by specificity, never `!important`: block-scoped `:is([class*="wp-block-wp-career-board"], [class*="wp-block-wcb-"])` prefixes, or `:is(:hover, :focus)` on the plugin's own class.
