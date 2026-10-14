---
id: employer-post-job
priority: critical
personas: employer.figma
requires: mu:autologin
last_verified: 2026-10-13
needs: cli
bug_ref: 9871740742, 10394970998
---

# Employer posts a new job

**Why this journey exists:** post-a-job is the plugin's #1 employer-side action. Every release must verify it round-trips and the new job appears on the public board with correct company metadata (tagline, industry, size, HQ). Guards Basecamp 9871740742 (company tagline / industry / size / HQ were missing from the single-job render).

## Steps

1. As `employer.figma`, navigate to `/?autologin=employer.figma` → expect HTTP 200, employer is logged in
2. Navigate to the employer's "Post a Job" surface (front-end composer or admin `wp-admin/post-new.php?post_type=wcb_job`) → expect 200, form renders
3. POST `/wp-json/wcb/v1/jobs` with body `{"title": "Platform Engineer, Billing - Gdansk", "company_id": <figma's company id>, "description": "Join our payments team and help build reliable billing tools.", "deadline": "<+30 days>", "salary_min": 80000, "salary_max": 110000, "salary_currency": "USD", "remote": true, "type": "full-time"}` → expect HTTP 201, response contains job ID
4. Verify the job persists: `wp post get <job-id> --field=post_status` → expect `publish` (or `pending` if plugin is in moderation mode — record which)
5. Verify meta keys are in v1.1.0 shape: `wp post meta list <job-id>` → expect `_wcb_company_id`, `_wcb_deadline`, `_wcb_salary_min`, `_wcb_salary_max`, `_wcb_salary_currency`, `_wcb_remote`, `_wcb_company_name` all populated. NO `_wcb_job_company` (the v0.1.0 key) should be present
6. As anonymous, GET `/jobs/<job-slug>/` → expect HTTP 200, page renders title + description + company name + tagline + industry + size + HQ (all four company-meta fields must be visible per Basecamp 9871740742 contract)
7. As anonymous, GET `/wp-json/wcb/v1/jobs/<id>` → expect response body contains `company_tagline`, `company_industry`, `company_size_label`, `company_hq` keys with non-empty values (the jobs endpoint prefixes company fields with `company_`). Response MUST NOT contain `apply_email` (per security journey `anonymous-job-no-email-leak`)
8. Post the job from the employer dashboard (Post a Job tab) → the My Jobs badge and the Overview "jobs" figure go up by one immediately, without opening My Jobs or reloading; with credits on, the Credits badge shows the balance after any charge.
9. Click **Post another job** → the title AND the rich description editor are empty (the previous job's text is gone), and the Company Profile description on the same dashboard is unchanged.
10. On the job page, the hero and the "About the company" card show the company's logo when the company has one (initials otherwise).
11. **Deadline is editable up to the board maximum (1.8.1, Basecamp 10394970998).** In the employer dashboard Post a Job tab (`#post-job`) and in the quick form `[wcb_job_form_simple]`, `#wcb-deadline` (`#wcb-simple-deadline`) is a normal date input: NO `readonly`, `min` = tomorrow, `max` = the date `JobDeadline::default_end_for_board( <board> )` returns (today + Settings > Jobs > Default listing length, or the board's own length), help text "Pick any date up to <date>, the longest this board allows.". The old hint "Auto-filled from the job-board policy. Contact your site admin to extend the listing window." is gone.
12. Pick a date 10 days out and submit → `wp post meta get <job-id> _wcb_deadline` = that exact date (the employer's shorter choice is kept).
13. REST cap: as `employer.figma`, `POST /wp-json/wcb/v1/jobs` with `"deadline": "<today + 400 days>"` → 201 and `_wcb_deadline` = the board maximum, NOT +400 days. Then `PATCH /wp-json/wcb/v1/jobs/<id>` with the same far date → still clamped (`JobDeadline::cap()`: the larger of the job's existing deadline and the board default; an admin-set longer deadline is kept, not shortened).
14. Negative - moderators are not capped: `POST /wp-json/wcb/v1/jobs` as `varundubey` (holds `wcb/moderate-jobs`) with a +400 day deadline → stored as sent.
15. Pro boards: with a board whose `expiry_days` is 7 (filter `wcb_job_default_expiry_days`), the form's `max` is today + 7 and the REST clamp uses 7. Skip when Pro is inactive.
16. Republish: move an `wcb_expired` or `wcb_closed` job whose deadline has passed back to `publish` (employer Reopen, or `wp post update <id> --post_status=publish`) → `_wcb_deadline` is reset to `JobDeadline::default_end_for_board( <job board> )` (`JobsExpiry`), never left in the past.
17. tail debug.log diff → expect ZERO new fatal / warning lines

## Teardown

```bash
wp post delete <job-id> --force
```

## Variant — employer whose `_wcb_company_id` user meta is unset

Re-run steps 3-7 after `wp user meta delete <employer-id> _wcb_company_id`, leaving their `wcb_company` post published. Step 5 must still show `_wcb_company_id` and `_wcb_company_name` populated: job-create resolves the owner's company through `CompanyMetaShape::resolve_company_id()`, not a raw user-meta read. The full failure chain (the job only disappears once the dashboard self-heals the user meta) is covered by `customer/job-links-company-when-user-meta-empty.md`.

## Notes

- If Pro `credits` module is active, expect a `wcb_credit_ledger` row debited by 1 (or whatever the package amount is) — this is also covered by `system/credit-decrement.md`.
- Moderation mode: if `wcb_moderation_enabled` option is true, step 4 expects `pending`. Re-run as admin and approve to continue.
