---
id: apply-to-job
priority: critical
personas: sarah.chen
requires: mu:autologin, seed:jobs, seed:resumes
last_verified: 2026-10-13
needs: cli
bug_ref: 9818132111, 10359847545, 10355318941
---

# Candidate applies to a published job

**Why this journey exists:** the apply flow is the plugin's #1 customer-visible action. Every release must verify it round-trips end-to-end. Specifically guards Basecamp 9818132111 (resume-required default was silently false on fresh installs) and the broader "apply succeeds + persists + employer sees it" contract.

## Steps

1. As `sarah.chen`, navigate to `/jobs/?autologin=sarah.chen` → expect HTTP 200, candidate is logged in
2. Open any published `wcb_job` post (find a candidate via `wp post list --post_type=wcb_job --post_status=publish --field=ID --posts_per_page=1`) → expect 200, page renders title + description + Apply CTA
3. Click "Apply" CTA → expect modal/form with resume picker, cover-letter field
4. POST `/wp-json/wcb/v1/jobs/<id>/apply` with body `{"resume_id": <sarah's resume id>, "cover_letter": "I would love to bring my payments experience to this team."}` → expect HTTP 200, response contains `application_id` (integer >0)
5. Verify the application persists: `wp post get <application_id> --field=post_status` → expect `publish` (or whatever the plugin's submitted state is — `submitted` per the v1.1.0 status allowlist)
6. Verify the application is linked: `wp post meta get <application_id> _wcb_job_id` → expect `<job-id>`. `wp post meta get <application_id> _wcb_candidate_id` → expect sarah's user ID. `wp post meta get <application_id> _wcb_status` → expect `submitted`
6b. **Cross-plugin contract (Pro active only).** If `wp plugin is-active wp-career-board-pro` is false, mark this step `skipped: pro_inactive`. Otherwise assert Pro's `wcb_application_submitted` listener placed the application in the hiring pipeline:
   ```bash
   BOARD_ID=$(wp eval "echo \WCB\Pro\Modules\Pipeline\StageRepository::board_for_job( <job-id> );")
   FIRST_ID=$(wp eval "echo \WCB\Pro\Modules\Pipeline\StageRepository::first_stage_id( $BOARD_ID );")
   wp post meta get <application_id> _wcb_stage_id
   ```
   → expect `BOARD_ID` > 0, `FIRST_ID` > 0, and `_wcb_stage_id` exactly equal to `FIRST_ID`. An EMPTY `_wcb_stage_id` means Pro's listener did not run and the application is invisible on the employer's Kanban (Basecamp 10134657558).
7. As `employer.figma` (or whichever employer owns the job), navigate to the employer dashboard → expect the new application to appear in the applicant list with correct candidate name + status
8. **Guest with an account's email (1.8.1, Basecamp 10359847545).** As anonymous on `/jobs/<slug>/`, open Apply, fill the guest fields (`.wcb-apply-guest-fields`) with any name and `sarah.chen`'s email (`wp user get sarah.chen --field=user_email`), submit → `POST /wp-json/wcb/v1/jobs/<id>/apply` (`guest_name`, `guest_email`) = HTTP 409, code `wcb_guest_has_account`, message "This email already has an account. Sign in to apply.". The panel shows that text in `p.wcb-apply-error` AND a primary link `a.wcb-apply-login` "Sign in to apply" whose href is `wp_login_url( <job permalink> )`. No application row is created: `wp post list --post_type=wcb_application --meta_key=_wcb_guest_email --meta_value=<email> --format=count` = 0.
9. Retry with an unused email in the same panel → the sign-in link hides again (`state.errorNeedsLogin` resets on submit) and the application succeeds with 200.
10. **Repeat guest apply returns the first reply (1.8.1, Basecamp 10355318941).** Guest applies twice within 24 hours with the same unused email → both calls return HTTP 200 with the same body `{"id": <int>, "job_id": <job>, "status": "submitted"}` and the SAME `id`; `wp post list --post_type=wcb_application --meta_key=_wcb_guest_email --meta_value=<email> --format=count` = 1; Mailpit shows ONE confirmation email. The old 409 `wcb_already_applied` ("You have already applied to this job recently.") must NOT come back - it told anyone whether an email had applied.
11. Negative: a different guest email applying to the same job still creates its own row (the dedupe key is job + email, not job alone).
12. tail debug.log diff for this journey's window → expect ZERO new fatal / warning / `wp_register_ability` notice lines

## Teardown

```bash
# Delete the smoke application created during this run
wp post delete <application_id> --force
```

## Notes

- The `_wcb_status` allowlist is `submitted`, `reviewing`, `shortlisted`, `rejected` — older code used `applied`/`hired`; if step 6 returns one of those, this journey predates a fix and should be regenerated.
- Resume picker is REST-driven (`/wcb/v1/candidates/<id>/resumes`). If empty, seed a resume via the seeder script first.
- Re-applying to the same job: behavior is documented per plugin contract — either updates existing application or shows "already applied". Record which on first walk.
- **Free → Pro contract:** Free fires `do_action( 'wcb_application_submitted', $app_id, $job_id, $candidate_id )` from `api/endpoints/class-applications-endpoint.php`. With Pro active, `PipelineModule::assign_initial_stage()` consumes it at **priority 5** and stamps `_wcb_stage_id`. The priority matters: Pro's notification bell, BuddyPress integration and AI scorer all consume the same action at 10-20 and read the stage, so a change to Free's firing point (or to the arg order) breaks the Kanban silently. Step 6b is the sentinel. This is the downward counterpart to Pro's `audit/journeys/system/free-job-moderation-reaches-pro-bell.md`.
- The stamp is written with `add_post_meta( ..., $unique = true )`, so two concurrent submits for the same application produce exactly one meta row. If step 6b ever reports two `_wcb_stage_id` rows, the write was changed to `update_post_meta()`.
- Guest calls count toward the 10-per-hour per-IP limit (`wcb_guest_apply_`); run `wp transient delete --all` between repeated runs.
- The Re-applying bullet above is now settled for guests: signed-in repeats are unchanged, guest repeats answer like the first application.
