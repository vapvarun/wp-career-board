---
id: job-copy-link-http-fallback
priority: medium
personas: anonymous
requires: seed:jobs
last_verified: 2026-10-13
bug_ref: 10389676158
---

# Copy Link on a job page works on http sites and shows the link when copying is impossible

**Why this journey exists:** the job page Copy Link button called `navigator.clipboard.writeText()`, which does not exist on plain-http origins, and swallowed the error, so on an http site the button did nothing and said nothing. It now uses the shared `copyText()` from `@wcb/ui` (Clipboard API, then a hidden-textarea `execCommand` fallback) and shows the link selected in place when both fail.

## Steps

1. On an http site (Local sites are http: `$WCB_SITE` starts with `http://`), open any job page `/jobs/<slug>/` and click the share-row button with `aria-label="Copy link"` (`.wcb-share-btn`, `actions.copyLink`) → the icon swaps to the check mark for about 2 seconds (`state.linkCopied`), and pasting into the address bar yields the job permalink.
2. On https (or `localhost`) the same click works through the Clipboard API with the same visible result.
3. Failure path: in Playwright, override both methods before clicking (`navigator.clipboard.writeText = () => Promise.reject(); document.execCommand = () => false;`, or deny the permission) then click → no check mark; the readonly text field `input.wcb-copy-fallback` (aria-label "Copy this link") appears next to the button, holding the permalink and focused with its text selected, so Ctrl+C works. It stays hidden whenever copying succeeded.
4. Keyboard: Tab to the button, press Enter or Space → same results. The field is reachable by Tab when shown.
5. Browser console shows no uncaught errors in any of the above.

## Teardown

None - read-only journey.

## Notes

- Pro's Job Feed "Copy URL" button uses the same helper: see Pro journey `admin/pro-job-feed-copy-url-http`.
- Selectors from `blocks/job-single/render.php` (share row) and `blocks/job-single/view.js` (`copyLink`).
