---
id: poster-post-a-job
priority: critical
personas: employer.figma, employer.stripe, employer.vercel, varundubey
requires: mu:autologin, seed:jobs
last_verified: 2026-07-08
bug_ref: 9976885975
covers: blocks/job-form (wcb/job-form), POST /wcb/v1/jobs, GET /wcb/v1/jobs/{id}, find-jobs archive, wp-admin edit.php?post_type=wcb_job, filter wcb_board_credit_cost, filter wcb_board_options_for_employer
---

# Walkthrough: Post a Job — open the multi-step form, clear the free-posting credits gate, pick a board you belong to, submit into moderation, go live

**Why this journey exists:** Post-a-Job is the plugin's #1 money path for the employer actor. This walkthrough traces the full happy path a real employer takes — open the 4-step `wcb/job-form` block, confirm posting is FREE by default (credits are opt-in, no nag), confirm the board picker only lists boards the employer belongs to, fill Basics → Details → Categories → Preview, submit to `POST /wcb/v1/jobs`, see the "submitted for review" confirmation (default moderation), then have an admin publish it so it appears in Find Jobs. Consolidates `customer/walkthrough-employer-post-job`, `customer/employer-post-job`, `system/credits-opt-in-free-posting`, and `customer/employer-boards-picker-respects-membership`.

## Steps

1. As `employer.figma`, navigate to `http://jobboard.local/post-a-job/?autologin=employer.figma` → expect HTTP 200, the wrapper `.wcb-job-form-wrap` with `data-wp-interactive="wcb-job-form"`, and the step indicator `nav.wcb-steps` showing step `1 Basics` active (`.wcb-step--active`). NOT the `.wcb-job-form-gate` "Please sign in as an employer" notice (renders only when logged-out or lacking `wcb/post-jobs`, `blocks/job-form/render.php:26-44`).

2. **Free-posting gate (credits are opt-in)** — expect NO "purchase more credits" / "requires N credits" nag and the Next / Post Job buttons enabled, even with a 0-credit balance. On the default Main Board the cost resolves to `0` from both surfaces: `apply_filters('wcb_board_credit_cost', 0, <main_board_id>)` returns `0`, and (Pro) the SDK `job_post` consumer cost callable returns `0` — gate and hold agree via `BoardSettings::get()` defaults. Guards Basecamp 9976885975 (default cost was wrongly 1, blocking free posting out of the box).

3. **Board picker respects membership** — if the site is multi-board (Pro), inspect `document.querySelectorAll('#wcb-board-id option')` → expect it to INCLUDE every board for a group the employer belongs to plus non-group admin boards ("Main Board"), and to EXCLUDE any group's board the employer is not a member/mod/admin of. The contract is the Free filter `wcb_board_options_for_employer( array $options, int $user_id )` (registered in `blocks/job-form/render.php`), implemented by Pro's `BpGroupBoards::restrict_boards_to_user_groups()`. On single-board Free the picker is hidden and the REST callback falls back to the default board.

4. **Step 1 (Basics)** — type into `#wcb-job-title` (`data-wcb-field="title"`, render.php:446) e.g. `Senior PHP Developer`, and set the description source `#wcb-job-desc` (the hidden `textarea.wcb-editor-source`, render.php:478 — set value + dispatch `input`) to a non-empty body → expect `state.title`/`state.description` populated and no `#wcb-form-validation-error` text. Click "Next: Details" `.wcb-btn--primary[data-wp-on--click="actions.nextStep"]` → expect step 2 panel shown and step 1 marked `.wcb-step--done`. (Sanity: clearing the title and clicking Next surfaces `#wcb-form-validation-error` "Job title is required…" — `nextStep` guards title+description, `blocks/job-form/view.js:375-393`.)

5. **Step 2 (Details)** — set `#wcb-salary-min` to `60000`, `#wcb-salary-max` to `90000`, leave `#wcb-currency` at its default (admin `salary_currency`, USD), tick "Remote-friendly position" (`actions.toggleRemote`). Note `#wcb-deadline` is `readonly` (admin-controlled, auto-filled `+jobs_expire_days`, default 30; render.php:626). Click "Next: Categories" → expect step 3 panel shown (`state.isStep3`).

6. **Step 3 (Categories)** — choose `#wcb-category`, `#wcb-job-type`, `#wcb-location` (or `__custom__` to reveal `#wcb-location-custom`), `#wcb-experience` from seeded taxonomy terms, and type `React, TypeScript, Node.js` into `#wcb-tags` (render.php:710-805) → expect each select's `state.*Slug` set. Click "Preview Job" → expect step 4 panel shown (`state.isStep4`).

7. **Step 4 (Preview)** — expect `.wcb-preview-card` to mirror the entered data: `.wcb-preview-card__title` = the job title, `.wcb-cbadge--remote` visible (remote ticked), and `.wcb-preview-meta-item` salary string from `state.salaryDisplay` (e.g. `USD 60,000 – 90,000/yr`).

8. Click submit `.wcb-btn--primary[data-wp-on--click="actions.submitJob"]` (label "Post Job" via `state.submitLabel`) → expect a single `POST http://jobboard.local/wp-json/wcb/v1/jobs` carrying header `X-WP-Nonce` and JSON body `{ title, description, salary_min:"60000", salary_max:"90000", salary_currency:"USD", salary_type:"yearly", remote:true, deadline, categories:[…], job_types:[…], locations:[…], experience:[…], tags:["react","typescript","node-js"], board_id, custom_fields:{}, hp:"" }` (assembled `blocks/job-form/view.js:403-477`).

9. Expect HTTP `201` with body `{ id, status:"pending", permalink }` — default `auto_publish_jobs` is OFF (`admin/class-admin-settings.php:8`), so `create_item()` stores `post_status = pending` (`api/endpoints/class-jobs-endpoint.php:544-545`, filterable via `wcb_job_default_status`). The success panel `.wcb-form-success--show` appears and `.wcb-form-success__title` shows "Job submitted for review. You'll be notified once it's approved." (published copy + "View your job listing →" link stay hidden while `state.jobPending` is true, view.js:489-492).

10. Confirm the free-posting credit outcome: with cost 0 the employer's credit balance is UNCHANGED (no hold, no deduction). (Only when a board is monetized — `_wcb_board_settings.credit_cost` set to e.g. 3 — do the gate, the SDK hold, and the ledger all read 3; see `system/credit-decrement`.)

11. As `varundubey` (admin), navigate to `http://jobboard.local/wp-admin/edit.php?post_type=wcb_job&post_status=pending&autologin=varundubey` → expect HTTP 200 and the new job listed under Pending review. Approve it (Quick Edit → Status: Published, or bulk "Approve") → expect the row to move to Published and the `wcb_job_created`/publish transition to fire.

12. As anonymous, navigate to `http://jobboard.local/find-jobs/` → expect HTTP 200 and the now-published job title visible (the archive queries `post_status = publish` only — pending jobs never appear here). Confirm the D-guard: GET `http://jobboard.local/wp-json/wcb/v1/jobs/<id>` → response body contains non-empty `company_tagline`, `company_industry`, `company_size_label`, `company_hq`, and MUST NOT contain `apply_email` (Basecamp 9871740742 + `security/anonymous-job-no-email-leak`).

13. tail `wp-content/debug.log` diff over the whole run → expect ZERO new fatal/warning lines.

## Teardown

```bash
# Safe + re-runnable: trash only the test job(s) authored by employer.figma matching the title.
wp post list --post_type=wcb_job --post_status=any --field=ID \
  --author="$(wp user get employer.figma --field=ID)" --s='Senior PHP Developer' \
  | xargs -r -n1 wp post delete --force

# If a board was monetized in step 10's negative check, restore it to free:
# wp post meta delete <main_board_id> _wcb_board_settings

# Orphan one-off wcb_location term created via the "Other (enter manually)" path, if used:
# wp term delete wcb_location <term_id>
```

## Notes

- **Default moderation path is the norm.** `auto_publish_jobs` defaults OFF, so step 9 returns `status:"pending"` and steps 11 are required. If an admin enables auto-publish (or a Pro board is auto-moderated) step 9 returns `status:"publish"`, the success panel shows "Job posted successfully!" + the "View your job listing →" link, and step 11's admin approval is skipped.
- **Gate / ability:** `blocks/job-form/render.php:26-44` renders `.wcb-job-form-gate` unless logged-in AND `wp_is_ability_granted('wcb/post-jobs')`; `employer.figma` holds `wcb_post_jobs` (`core/class-roles.php:154`).
- **REST route + moderation:** `POST /wcb/v1/jobs` at `api/endpoints/class-jobs-endpoint.php:37-50`; perm `create_item_permissions_check` gates on `wcb/post-jobs` (:1143-1145); `create_item()` (:506) sets pending/publish (:544-545), inserts (:593), returns 201 with `status`/`permalink` (:707-709).
- **Credits are OPTIONAL Pro.** Both the front-door gate (`wcb_board_credit_cost` filter) and the Pro SDK consumer cost callable resolve through `BoardSettings::get()` which merges defaults, so a board with no saved settings reads cost 0 from both — free and consistent. `requires: pro:credits` only for step 10's monetized negative check.
- **Board membership** uses `employer.stripe` / `employer.vercel` for the multi-board mirror check (step 3) — each must be creator/member of at least one group and NOT a member of another; a `manage_options` admin sees EVERY board (intentional bypass).
- **Seed needs:** `employer.figma` with `wcb_post_jobs`; `wcb_category`/`wcb_job_type`/`wcb_location`/`wcb_experience` terms seeded (sample-data seeder, `admin/class-setup-wizard.php`).
