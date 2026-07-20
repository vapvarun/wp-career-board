---
id: seeker-apply-to-a-job
priority: critical
personas: anonymous, sarah.chen
requires: mu:autologin, seed:jobs
last_verified: 2026-07-08
covers: job-single block, POST /wcb/v1/jobs/{id}/apply, GET /wcb/v1/jobs/{id}, guest apply panel, apply routing (email / external URL)
bug_ref: 9818132111
---

# Apply to a job — as a guest and as a logged-in candidate, plus email/external-URL routing

**Why this journey exists:** applying is the plugin's #1 money/conversion path. This consolidates the apply half
of `customer/walkthrough-find-jobs-and-apply` (steps 5-13), `customer/apply-to-job`,
`security/anonymous-can-apply-cleanly`, and `customer/job-apply-routing-email-or-url` into one human-runnable
pass: guest apply, candidate apply, the dedupe guard, and the two off-site routing variants (Apply Email row and
external Apply URL). It guards Basecamp 9818132111 (resume-required default) and the F-1 rule that public job
REST never leaks `apply_email`.

## Steps

1. As `anonymous`, open any published job at `/jobs/<slug>/` → expect HTTP 200, hero `h1.wcb-job-title` inside the
   `.wcb-job-single` wrapper. Find a candidate slug via
   `wp post list --post_type=wcb_job --post_status=publish --field=post_name --posts_per_page=1`
   (`blocks/job-single/render.php:406-416`).
2. Assert the public job REST does not leak the recruiter inbox: `GET /wp-json/wcb/v1/jobs/<id>` → expect HTTP
   200 and the response body must NOT contain a non-empty `apply_email` (F-1). Repeat against the list
   `GET /wp-json/wcb/v1/jobs?per_page=5` → no `jobs[]` entry may expose `apply_email`.
3. Click "Apply Now" `button.wcb-apply-trigger` (`data-wp-on--click="actions.openPanel"`) → expect the slide-in
   panel `.wcb-apply-panel[role="dialog"]` to open (`.wcb-open`), showing guest fields `#wcb-guest-name` +
   `#wcb-guest-email` because the visitor is not logged in
   (`blocks/job-single/render.php:406-413,882-908`).
4. Fill `#wcb-guest-name` + `#wcb-guest-email`, attach a PDF via `#wcb-resume-file` (`.wcb-apply-resume-file`;
   required when `apply_resume_required` default-true — `render.php:243`), then click "Submit Application"
   `button[data-wp-on--click="actions.submitApplication"]` → the browser POSTs multipart `FormData`
   (`guest_name`, `guest_email`, `cover_letter`, `resume_file`) with header `X-WP-Nonce` to
   `/wp-json/wcb/v1/jobs/<id>/apply` (`blocks/job-single/view.js:264-303`).
5. Assert the guest apply REST contract directly: `POST /wp-json/wcb/v1/jobs/<id>/apply` (multipart) with
   `guest_name=Guest Tester`, `guest_email=guest.smoke@example.com`, `cover_letter=Walkthrough smoke` (+ a
   `resume_file` if resume is required) → expect HTTP 200 and JSON body `{ "id": <int>, "job_id": <id>,
   "status": "submitted" }` (`api/endpoints/class-applications-endpoint.php:368-374`). The route allows guests:
   `POST /wcb/v1/jobs/(?P<id>\d+)/apply`, `permission_callback` returns true for `! is_user_logged_in()`
   (`:39-47,117-124`).
6. Back in the browser, after the guest submit resolves → expect the panel to close and the applied badge
   `.wcb-applied-badge` ("Application Submitted") to show via `state.submitted`
   (`blocks/job-single/render.php:414-416`; `view.js:311-312`).
7. Verify the guest application persisted: `wp post get <application_id> --field=post_type` = `wcb_application`;
   `wp post meta get <application_id> _wcb_candidate_id` = `0` (guest, no user account);
   `wp post meta get <application_id> _wcb_status` = `submitted`.
8. Re-POST the SAME guest email to the SAME job within 24h → expect HTTP 409 `wcb_already_applied` ("You have
   already applied to this job recently.") — the guest dedupe guard
   (`api/endpoints/class-applications-endpoint.php:147-175`).
9. Switch personas: navigate to `/jobs/<slug>/?autologin=sarah.chen` → expect HTTP 200, logged-in candidate; the
   apply panel shows the cover-letter editor `#wcb-cover-letter` and NO guest name/email fields
   (`blocks/job-single/render.php:882`). In Free a resume file-upload zone is shown; if Pro's resume CPT is
   active a resume `<select>#wcb-resume-select` appears instead.
10. Submit as the candidate — `POST /wp-json/wcb/v1/jobs/<id>/apply` with `X-WP-Nonce` (cover_letter +
    resume_file or resume_id) → expect HTTP 200, `{ id, job_id, status: "submitted" }`; verify persistence:
    `wp post meta get <id> _wcb_candidate_id` = sarah's user ID, `_wcb_status` = `submitted`
    (`class-applications-endpoint.php:245-319`).
11. As `sarah.chen` re-open the same job → expect "Apply Now" replaced by the `.wcb-applied-badge` because the
    already-applied check finds her `wcb_application` (`blocks/job-single/render.php:164-187,414-416`).
12. **Routing variant — Apply Email (Basecamp 9871740742).** Seed a job with
    `_wcb_apply_email = careers@payflow.test` (no apply URL) and open it as `sarah.chen` → expect a Job Details
    sidebar row labelled **Apply Email** with `careers@payflow.test` and an `href` starting
    `mailto:careers@payflow.test?subject=`. The hero CTA is still the panel-opening `.wcb-apply-trigger`
    (`document.querySelector('.wcb-apply-external')` is absent).
13. **Routing variant — external Apply URL.** Seed a second job with `_wcb_apply_url = https://payflow.test/careers/apply`
    (no apply email) and open it → expect a sidebar row **Apply Via** with host `payflow.test ↗`; BOTH Apply CTAs
    (hero + sidebar) are external links `.wcb-apply-external` with `target="_blank"` and
    `rel="noopener noreferrer nofollow"` (`document.querySelectorAll('.wcb-apply-external').length === 2`), and
    the slide-in apply panel is NOT rendered (`document.querySelector('.wcb-apply-panel') === null`).
14. tail debug.log diff for this journey's window → expect ZERO new fatal/warning lines (no
    `wp_register_ability` / Interactivity hydration notices, no warnings on the guest path).

## Teardown (safe to re-run)

```bash
# Remove the guest + candidate smoke applications and the routing meta seeded above.
GUEST_APP=$(wp post list --post_type=wcb_application --meta_key=_wcb_guest_email \
  --meta_value=guest.smoke@example.com --field=ID --posts_per_page=1)
[ -n "$GUEST_APP" ] && wp post delete "$GUEST_APP" --force

SARAH_ID=$(wp user get sarah.chen --field=ID)
CAND_APP=$(wp post list --post_type=wcb_application --meta_key=_wcb_candidate_id \
  --meta_value="$SARAH_ID" --field=ID --posts_per_page=1 --orderby=ID --order=DESC)
[ -n "$CAND_APP" ] && wp post delete "$CAND_APP" --force

# Routing meta (replace <job-a-id>/<job-b-id> with the seeded job IDs).
wp post meta delete <job-a-id> _wcb_apply_email
wp post meta delete <job-b-id> _wcb_apply_url
```

## Notes

- Apply route + body: `POST /wcb/v1/jobs/(?P<id>\d+)/apply`, `WP_REST_Server::CREATABLE`, guest-permitted
  (`class-applications-endpoint.php:39-47,117-124`). Body is multipart `FormData`: `cover_letter`,
  `guest_name`+`guest_email` (guests only), `resume_id` (Pro saved resume) and/or `resume_file` upload, plus
  `custom_fields[<key>]` (`blocks/job-single/view.js:264-303`). Success: `{ id, job_id, status: "submitted" }`.
- Resume requirement: `apply_resume_required` defaults true; when on, a guest browser apply must attach a file or
  the client blocks submit (`view.js:255-258`) and the server returns 400 `wcb_resume_required`
  (`class-applications-endpoint.php:310-317`). Toggle the setting off (or attach a PDF) to complete steps 4/10.
- `_wcb_status` allowlist is `submitted, reviewing, shortlisted, rejected`. A legacy `applied`/`hired` value in
  step 7/10 means the journey predates a fix — regenerate rather than editing the assertion.
- Employers/job-owners do NOT see Apply Now — they get "View Applications" instead
  (`render.php:139-154,388-394`). This walkthrough is seeker-side only.
- The render-time URL gate (step 13) accepts http/https only; strict URL validation lives in `JobsEndpoint` at
  save-time. The `.test` TLD is deliberate so the relaxed render gate keeps surfacing valid-looking dev URLs.
</content>
</invoke>
