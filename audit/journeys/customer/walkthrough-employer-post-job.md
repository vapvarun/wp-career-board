---
id: walkthrough-employer-post-job
priority: critical
personas: employer.figma, varundubey
requires: mu:autologin, seed:jobs
last_verified: 2026-06-29
---

# Walkthrough: Employer Post a Job — open the multi-step form, fill it, submit, land in moderation, go live in Find Jobs

**Why this journey exists:** This is the end-to-end walkthrough of the employer "Post a Job" flow. It
traces the full happy path a real employer takes — open the 4-step form (`wcb/job-form` block),
fill Basics → Details → Categories → Preview, submit to `POST /wcb/v1/jobs`, see the
"submitted for review" confirmation (default moderation), then have an admin publish it so it
appears in Find Jobs. The whole post-a-job functionality is browser-coverable in one pass.

## Steps

1. As `employer.figma`, navigate to `http://jobboard.local/post-a-job/?autologin=employer.figma` → expect HTTP 200, the wrapper `.wcb-job-form-wrap` with `data-wp-interactive="wcb-job-form"`, and the step indicator `nav.wcb-steps` showing step `1 Basics` active (`.wcb-step--active`). (NOT the `.wcb-job-form-gate` "Please sign in as an employer" notice — that renders only when logged-out or lacking the `wcb/post-jobs` ability.)

2. **Step 1 (Basics)** — type into `#wcb-job-title` (`data-wcb-field="title"`) e.g. `Senior PHP Developer`, and set the description source `#wcb-job-desc` (`data-wcb-field="description"`, the hidden `textarea.wcb-editor-source` inside `.wcb-editor` — set value + dispatch an `input` event) to a non-empty body → expect both `state.title`/`state.description` populated and no `#wcb-form-validation-error` text.

3. Click the primary "Next: Details" button (`.wcb-btn--primary[data-wp-on--click="actions.nextStep"]`) → expect step 2 panel to show (`.wcb-form-step--show` on the Details step driven by `state.isStep2`) and the indicator to mark step 1 `.wcb-step--done`. (Sanity: clearing the title and clicking Next instead surfaces `#wcb-form-validation-error` "Job title is required before you can continue." — `nextStep` guards title+description on step 1.)

4. **Step 2 (Details)** — set `#wcb-salary-min` to `60000`, `#wcb-salary-max` to `90000`, leave `#wcb-currency` at its default (admin `salary_currency`, USD), tick the "Remote-friendly position" checkbox (`actions.toggleRemote`). Note `#wcb-deadline` is `readonly` (admin-controlled, auto-filled `+jobs_expire_days`, default 30). Click "Next: Categories" → expect step 3 panel shown (`state.isStep3`).

5. **Step 3 (Categories)** — choose `#wcb-category`, `#wcb-job-type`, `#wcb-location` (or pick `__custom__` to reveal `#wcb-location-custom`), `#wcb-experience` from the seeded taxonomy terms, and type `React, TypeScript, Node.js` into `#wcb-tags` → expect each select's `state.*Slug` set. Click "Preview Job" → expect step 4 panel shown (`state.isStep4`).

6. **Step 4 (Preview)** — expect the preview card `.wcb-preview-card` to mirror the entered data: `.wcb-preview-card__title` = the job title, `.wcb-cbadge--remote` visible (remote ticked), and `.wcb-preview-meta-item` salary string rendered from `state.salaryDisplay` (e.g. `USD 60,000 – 90,000/yr`).

7. Click the submit button `.wcb-btn--primary[data-wp-on--click="actions.submitJob"]` (label "Post Job" via `state.submitLabel`) → expect a single `POST http://jobboard.local/wp-json/wcb/v1/jobs` request carrying header `X-WP-Nonce` and JSON body `{ title, description, salary_min:"60000", salary_max:"90000", salary_currency:"USD", salary_type:"yearly", remote:true, deadline, categories:[…], job_types:[…], locations:[…], experience:[…], tags:["react","typescript","node-js"], board_id, custom_fields:{}, hp:"" }`.

8. Expect HTTP `201` with body `{ id, status:"pending", permalink }` (default: `auto_publish_jobs` is OFF, so `create_item()` stores `post_status = pending`). The success panel `.wcb-form-success--show` appears and `.wcb-form-success__title` shows the pending copy "Job submitted for review. You'll be notified once it's approved." (the published copy + "View your job listing →" link are hidden while `state.jobPending` is true).

9. Confirm "Post another job" reset works — click `.wcb-form-success__reset` (`actions.resetForm`) → expect the form to return to step 1 (`state.step === 1`) with cleared fields (title/description/salary empty). (The form also auto-resets after 8s.)

10. As `varundubey` (admin), navigate to `http://jobboard.local/wp-admin/edit.php?post_type=wcb_job&post_status=pending&autologin=varundubey` → expect HTTP 200 and the newly created job listed under Pending review.

11. Approve the job by publishing it (Quick Edit → Status: Published, or bulk "Approve") → expect the row to move to Published and the `wcb_job_created`/publish transition to fire.

12. As `employer.figma` (or anonymous), navigate to `http://jobboard.local/find-jobs/` → expect HTTP 200 and the now-published job title visible in the listings (the jobs archive queries `post_status = publish` only — pending jobs never appear here).

13. tail `wp-content/debug.log` diff over the whole run → expect ZERO new fatal/warning lines.

## Teardown
```bash
# Remove the test job(s) created by this walkthrough (title prefix "Senior PHP Developer").
# Safe + re-runnable: only trashes wcb_job posts authored by employer.figma matching the title.
wp post list --post_type=wcb_job --post_status=any --field=ID \
  --author="$(wp user get employer.figma --field=ID)" \
  --s='Senior PHP Developer' --path=/Users/varundubey/Local\ Sites/jobboard/app/public \
  | xargs -r -n1 wp post delete --force --path=/Users/varundubey/Local\ Sites/jobboard/app/public

# Orphan one-off wcb_location term created via the "Other (enter manually)" path, if used:
# wp term delete wcb_location <term_id> --path=...
```

## Notes
- **Page slug** `post-a-job` → `admin/class-pages.php:43` (`'post_job_page' => 'post-a-job'`, CANONICAL_SLUGS). Find Jobs archive slug `find-jobs` (same const). The page must embed the `wcb/job-form` block.
- **Gate / ability:** `blocks/job-form/render.php:26-44` — renders `.wcb-job-form-gate` unless logged-in AND `wp_is_ability_granted('wcb/post-jobs')`. `employer.figma` holds the `wcb_post_jobs` cap (`core/class-roles.php:154`).
- **Form steps + selectors:** `blocks/job-form/render.php` — step nav `nav.wcb-steps` (L327), step panels `.wcb-form-step` + `data-wp-class--wcb-form-step--show` (L414/515/700/849); fields `#wcb-job-title` (L446), `#wcb-job-desc` textarea (L478), `#wcb-salary-min/max` (L556/571), `#wcb-currency` (L530), remote checkbox (L607), readonly `#wcb-deadline` (L626), `#wcb-category`/`#wcb-job-type`/`#wcb-location`/`#wcb-location-custom`/`#wcb-experience` (L710-796), `#wcb-tags` (L805); validation banner `#wcb-form-validation-error` (L350); success panel `.wcb-form-success` + pending vs published copy (L385-408); honeypot `#wcb-hp` (L941).
- **Step actions + body shape:** `blocks/job-form/view.js` — `nextStep` title/description guard (L375-393), `submitJob` builds the body and POSTs (L403-477), reads `data.permalink`/`data.status` and sets `state.jobPending` when status `pending` (L489-492, getter L164-167), `resetForm` (L524-550).
- **REST route + moderation:** `api/endpoints/class-jobs-endpoint.php` — route `POST /wcb/v1/jobs` (L37-50, `WP_REST_Server::CREATABLE`), perm `create_item_permissions_check` gates on `wcb/post-jobs` (L1143-1145); `create_item()` (L506) sets `$status = auto_publish_jobs ? 'publish' : 'pending'` (L544-545, filterable via `wcb_job_default_status` — Pro per-board moderation override), inserts the post (L593), returns 201 via `prepare_item_for_response_array()` with `status`/`permalink` keys (L707-709, L1265+). `auto_publish_jobs` default OFF (`admin/class-admin-settings.php:8`) → walkthrough's pending/moderation path is the default. If an admin enables auto-publish (or a Pro board is auto-moderated), step 8 returns `status:"publish"`, the success panel shows "Job posted successfully!" + the "View your job listing →" link, and steps 10-11 (admin approve) are skipped.
- **Seed needs:** `employer.figma` must exist with the `wcb_post_jobs` cap; `wcb_category`/`wcb_job_type`/`wcb_location`/`wcb_experience` taxonomy terms should be seeded so step 5's selects are populatable (sample-data seeder in `admin/class-setup-wizard.php`). Multi-board (`showBoardPicker`) only appears on Pro multi-board sites; single-board Free hides the picker and the REST callback falls back to the default board.
