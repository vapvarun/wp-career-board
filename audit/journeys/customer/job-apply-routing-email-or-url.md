---
id: job-apply-routing-email-or-url
priority: critical
personas: wcbp_p5_candidate
requires: mu:autologin, seed:jobs
last_verified: 2026-05-12
bug_ref: Basecamp 9871740742
---

# Apply Email is listed and external Apply URL redirects, on the single job page

**Why this journey exists:** A candidate looking at a job posting expects to see the Apply Email when the employer set one, and clicking Apply Now must redirect to the external Apply URL when the employer set one. Pre-1.1.1 the meta keys were saved but the single-job template rendered neither, so candidates either could not contact the employer or were trapped in the internal apply panel for jobs that wanted off-site checkout.

## Steps

1. Seed two jobs:
   - Job A has `_wcb_apply_email = careers@payflow.test`, no apply URL.
   - Job B has `_wcb_apply_url = https://payflow.test/careers/apply`, no apply email.
2. As `wcbp_p5_candidate`, navigate to `/jobs/<job-a-slug>/?autologin=wcbp_p5_candidate` → expect HTTP 200.
3. Inspect the Job Details sidebar → expect a row labelled **Apply Email** with text `careers@payflow.test` and an `href` starting with `mailto:careers@payflow.test?subject=`.
4. Confirm the hero CTA renders the panel-opening **Apply Now** button (not an external link) → `document.querySelector('.wcb-apply-trigger')` must exist and `document.querySelector('.wcb-apply-external')` must be absent.
5. Navigate to `/jobs/<job-b-slug>/?autologin=wcbp_p5_candidate` → expect HTTP 200.
6. Inspect the Job Details sidebar → expect a row labelled **Apply Via** with the host `payflow.test ↗`.
7. Confirm both Apply CTAs (hero + sidebar) are external links with `target="_blank"` and `rel="noopener noreferrer nofollow"` pointing at `https://payflow.test/careers/apply` → `document.querySelectorAll('.wcb-apply-external').length === 2`.
8. Confirm the slide-in apply panel is NOT rendered on Job B → `document.querySelector('.wcb-apply-panel') === null`.
9. tail debug.log diff → expect ZERO new fatal/warning lines.

## Teardown

```bash
# Remove seeded meta — leaves jobs in place.
wp post meta delete <job-a-id> _wcb_apply_email
wp post meta delete <job-b-id> _wcb_apply_url
```

## Notes

The render-time URL gate accepts http/https only — strict URL validation lives in `Jobs_Endpoint` at save-time. The journey deliberately uses a `.test` TLD to ensure the relaxed render gate keeps surfacing valid-looking dev URLs.
