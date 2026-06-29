---
id: walkthrough-find-jobs-and-apply
priority: critical
personas: anonymous, sarah.chen
requires: mu:autologin, seed:jobs
last_verified: 2026-06-29
---

# Walkthrough: Find Jobs & Apply — browse the archive, open a job, and apply as guest + candidate

**Why this journey exists:** This is the end-to-end walkthrough of the job-seeker side of WP Career Board.
It traces the full happy path a real user takes — landing on the Find Jobs archive, filtering, opening a
single job, and submitting an application both as an anonymous guest and as a logged-in candidate — so the
whole "find a job and apply" functionality is browser-coverable in one pass. This is the plugin's #1
money/conversion path.

## Steps

1. As `anonymous`, navigate to `/find-jobs/` → expect HTTP 200 and the listings wrapper `.wcb-job-listings`
   rendering at least one job card `article.wcb-job-card`. The slug `find-jobs` is the canonical
   `jobs_archive_page` (`admin/class-pages.php:46`); the setup wizard creates that page composed of the
   heading + job-search + job-filters + job-listings blocks (`admin/class-setup-wizard.php:422-424`). The CPT
   archive `/jobs/` renders the same `.wcb-job-listings` block and is an equivalent entry point.
2. Type `engineer` (or any seeded term) into the search field `#wcb-search-input` (`data-wp-on--input="actions.updateQuery"`,
   form `.wcb-search-form` submits via `actions.search`) and submit → expect the card grid `.wcb-jobs-container`
   to re-render filtered results, count label updates (`blocks/job-search/render.php:37-55`,
   `blocks/job-listings/render.php:698-702`).
3. In the filter sidebar `.wcb-filter-panel`, tick a Job type checkbox `.wcb-filter-panel__option input`
   (`data-wp-on--change="actions.toggleTypeChip"`) → expect an active-filter chip `.wcb-active-chip` to appear
   and the result set to narrow (`blocks/job-listings/render.php:502-516,685-695`).
4. Click a job card title link `a.wcb-card-title-link` (or its "View Job" button `a.wcb-cbtn--ghost`,
   `data-wp-bind--href="context.job.permalink"`) → expect navigation to the single job at `/jobs/<slug>/`,
   HTTP 200, hero `h1.wcb-job-title` + `.wcb-job-single` wrapper (`blocks/job-listings/render.php:723,776`;
   CPT rewrite slug `jobs`, `modules/jobs/class-jobs-module.php:210-214`).
5. On the single job (still `anonymous`), click "Apply Now" `button.wcb-apply-trigger`
   (`data-wp-on--click="actions.openPanel"`) → expect the slide-in panel `.wcb-apply-panel[role="dialog"]` to
   open (`.wcb-open`), showing guest fields `#wcb-guest-name` + `#wcb-guest-email` because the visitor is not
   logged in (`blocks/job-single/render.php:406-413,882-908`).
6. Fill `#wcb-guest-name` and `#wcb-guest-email`, attach a PDF via the upload input `#wcb-resume-file`
   (`.wcb-apply-resume-file`; required when `apply_resume_required` default-true), then click "Submit Application"
   `button[data-wp-on--click="actions.submitApplication"]` → the browser POSTs multipart `FormData`
   (`guest_name`, `guest_email`, `cover_letter`, `resume_file`) with header `X-WP-Nonce` to
   `/wp-json/wcb/v1/jobs/<id>/apply` (`blocks/job-single/view.js:264-303`).
7. Assert the guest apply REST contract directly: `POST /wp-json/wcb/v1/jobs/<id>/apply` (multipart) with
   `guest_name=Guest Tester`, `guest_email=guest.smoke@example.com`, `cover_letter=Walkthrough smoke` (+ a
   `resume_file` if resume is required) → expect HTTP 200 and JSON body `{ "id": <int>, "job_id": <id>,
   "status": "submitted" }` (`api/endpoints/class-applications-endpoint.php:368-374`).
8. Back in the browser, after the guest submit resolves → expect the panel to close and the applied badge
   `.wcb-applied-badge` ("Application Submitted") to show via `state.submitted`
   (`blocks/job-single/render.php:414-416`; `view.js:311-312`).
9. Re-POST the SAME guest email to the SAME job within 24h → expect HTTP 409 `wcb_already_applied`
   ("You have already applied to this job recently.") — the guest dedupe guard
   (`api/endpoints/class-applications-endpoint.php:147-175`).
10. Now switch personas: navigate to `/jobs/<slug>/?autologin=sarah.chen` → expect HTTP 200, logged-in candidate;
    the apply panel shows the cover-letter editor `#wcb-cover-letter` and NO guest name/email fields
    (`blocks/job-single/render.php:882`). If Pro/resume CPT is active a resume `<select>#wcb-resume-select`
    appears; in Free a file upload zone is shown instead.
11. Submit as the candidate — `POST /wp-json/wcb/v1/jobs/<id>/apply` with `X-WP-Nonce` (cover_letter +
    resume_file or resume_id) → expect HTTP 200, `{ id, job_id, status: "submitted" }`; verify persistence:
    `wp post get <id> --field=post_type` = `wcb_application`, `wp post meta get <id> _wcb_candidate_id` =
    sarah's user ID, `_wcb_status` = `submitted` (`class-applications-endpoint.php:245-319`).
12. As `sarah.chen` re-open the same job → expect "Apply Now" replaced by the `.wcb-applied-badge`
    ("Application Submitted") because the already-applied check finds her `wcb_application`
    (`blocks/job-single/render.php:164-187,414-416`).
13. tail debug.log diff for this journey's window → expect ZERO new fatal/warning lines (no
    `wp_register_ability` / Interactivity hydration notices).

## Teardown

```bash
# Remove the guest + candidate smoke applications created during this run.
# Guest: matched by guest email meta. Candidate: matched by sarah's user id + job.
GUEST_APP=$(wp post list --post_type=wcb_application --meta_key=_wcb_guest_email \
  --meta_value=guest.smoke@example.com --field=ID --posts_per_page=1)
[ -n "$GUEST_APP" ] && wp post delete "$GUEST_APP" --force

SARAH_ID=$(wp user get sarah.chen --field=ID)
CAND_APP=$(wp post list --post_type=wcb_application --meta_key=_wcb_candidate_id \
  --meta_value="$SARAH_ID" --field=ID --posts_per_page=1 --orderby=ID --order=DESC)
[ -n "$CAND_APP" ] && wp post delete "$CAND_APP" --force
```

## Notes

- Entry page slug: `find-jobs` is the canonical `jobs_archive_page` slug — `admin/class-pages.php:46`
  (`CANONICAL_SLUGS`). The setup wizard creates that page (title "Find Jobs") with the heading + job-search +
  job-filters + job-listings blocks — `admin/class-setup-wizard.php:422-424`. The `wcb_job` CPT also exposes a
  native archive at `/jobs/` (`has_archive => 'jobs'`, `modules/jobs/class-jobs-module.php:206-214`); both
  render the same `.wcb-job-listings` block.
- Apply route + body: `POST /wcb/v1/jobs/(?P<id>\d+)/apply`, `WP_REST_Server::CREATABLE`,
  `permission_callback` allows guests — `api/endpoints/class-applications-endpoint.php:39-47,117-124`.
  Body is multipart `FormData`: `cover_letter`, `guest_name`+`guest_email` (guests only), `resume_id`
  (Pro saved resume) and/or `resume_file` upload, plus `custom_fields[<key>]` — `blocks/job-single/view.js:264-303`.
  Success response: `{ id, job_id, status: "submitted" }` (`class-applications-endpoint.php:368-374`).
- Selectors grounded in: archive cards `.wcb-job-card` / `.wcb-card-title-link` / `a.wcb-cbtn--ghost`
  (`blocks/job-listings/render.php:714,723,776`); search `#wcb-search-input` (`blocks/job-search/render.php:44`);
  apply trigger `.wcb-apply-trigger`, panel `.wcb-apply-panel`, guest fields `#wcb-guest-name`/`#wcb-guest-email`,
  upload `#wcb-resume-file`, cover `#wcb-cover-letter`, submit `actions.submitApplication`, applied badge
  `.wcb-applied-badge` (`blocks/job-single/render.php:408,852-1113`).
- Resume requirement: `apply_resume_required` defaults true (`render.php:243`); when on, a guest browser apply
  must attach a file or the client blocks submit (`view.js:255-258`) and the server returns 400
  `wcb_resume_required` (`class-applications-endpoint.php:310-317`). Set the setting off (or attach a PDF) to
  complete steps 6/11 in the browser.
- Employers/job-owners do NOT see Apply Now — they get "View Applications" instead
  (`render.php:139-154,388-394`). This walkthrough only covers the seeker side.
- seed:jobs required — at least one published `wcb_job` must exist for the archive + single steps.
