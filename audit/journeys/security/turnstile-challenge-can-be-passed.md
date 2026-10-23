---
id: turnstile-challenge-can-be-passed
priority: high
personas: anonymous, candidate, employer
requires: seed:jobs
last_verified: 2026-10-23
needs: browser
bug_ref: 10417125333
---

# A visitor Cloudflare challenges can pass Turnstile and submit

**Why this journey exists:** the Turnstile shim rendered the widget with `size: 'invisible'`, which Turnstile does not have. The render threw, no token ever arrived, and any visitor Cloudflare chose to challenge was stuck on "Submitting..." on the apply panel, the job form and employer registration. The widget now renders with `appearance: 'interaction-only'` inside a panel that opens only when Cloudflare asks for a click, above the apply panel.

## Steps

1. Settings > Anti-Spam: provider Turnstile, site key `3x00000000000000000000FF` (always asks for a click), secret `1x0000000000000000000000000000000AA` (always passes).
2. As a candidate at 1440, open a job, Apply, pick a resume, Submit Application → the panel "Please confirm you are human to continue." opens above the apply panel with the Cloudflare checkbox. Tick it → the panel closes and "Application submitted" shows; a `wcb_application` row exists for that job.
3. As a second candidate at 390, repeat to the panel → it sits above the apply panel, inside the viewport (no horizontal scroll), and its Cancel button is clickable. Cancel → the panel closes, "CAPTCHA verification failed. Please try again." shows, Submit Application is enabled again, no application row.
4. As an employer, Post a Job through all four steps → Post Job opens the panel; tick it → "Job submitted for review."; a pending `wcb_job` exists.
5. Logged out, Employer Registration > Hire Talent, fill the form, Create Account → the panel opens; tick it → "Account created!"; the `wcb_employer` user exists.
6. Site key `1x00000000000000000000AA` (passes silently): repeat step 2 → the panel never shows and the application is created.
7. Browser console on every step → no `TurnstileError` and no page errors.

## Teardown

Restore the Anti-Spam settings and delete the application, job and user created above.
