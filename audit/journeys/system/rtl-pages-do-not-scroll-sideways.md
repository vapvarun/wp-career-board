---
id: rtl-pages-do-not-scroll-sideways
priority: high
personas: varundubey
requires: mu:autologin
last_verified: 2026-11-16
needs: browser
bug_ref: 1.8.4-smoke
---

# On a right-to-left site no Career Board page scrolls sideways

**Why this journey exists:** the off-screen honeypot used `left: -9999px`. In LTR that hides it; in RTL the box sits past the right edge and made the single job page and Post a Job scroll about 10,000px sideways (scrollWidth 10389 at a 390px viewport). Found by the 1.8.4 smoke re-walk, not by any earlier check.

## Steps

1. Switch the site language to an RTL locale: `wp language core install ar && wp site switch-language ar`.
2. Open a published job page, Post a Job, the Find Jobs page, a company page and both dashboards at 390 and 1440.
3. On each page read `document.documentElement.scrollWidth` and compare it with `innerWidth` → equal.
4. On the job page and Post a Job the honeypot field exists, is outside the viewport, has `tabindex="-1"` and is never reached by pressing Tab through the whole form.
5. Pro: repeat on the resume form, the resume archive and the candidate map.
6. `bash bin/coding-rules-check.sh --full` → Rule 17 reports no physical off-screen offsets.
7. Restore: `wp site switch-language en_US`. tail debug.log diff → expect ZERO new lines.

## Notes

- `-rtl.css` twins are generated (`npm run rtl`); this journey is the guard for the source rule, which must use `inset-inline-start`.
- A region-less locale (`ar` with no default country) also flags every job with "No location" in the Jobs list because the country is unset. That is a separate follow-up, not part of this journey.
