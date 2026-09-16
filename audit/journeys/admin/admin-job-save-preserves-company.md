---
id: admin-job-save-preserves-company
priority: high
personas: admin, employer.figma
requires: mu:autologin
last_verified: 2026-07-27
needs: cli
bug_ref: 10134657106
---

# Saving a job in wp-admin preserves its company link, and an explicit unlink is honoured

**Why this journey exists:** `AdminMetaBoxes::save_job_meta()` treated "no company id in the POST" as "delete the link", so an admin who opened an employer's job and pressed Update destroyed the `_wcb_company_id` that job-create had just stamped — and the job vanished from the employer's company-scoped My Jobs list. The metabox also only listed **published** companies, so a job linked to a pending/draft company rendered with "- Select a company -" pre-selected and lost its link on the next save. Both directions must hold: an untouched save preserves, a deliberate "- Select a company -" unlinks.

## Steps

1. As `employer.figma`, confirm one of their jobs has `_wcb_company_id` set (`wp post meta get <job-id> _wcb_company_id`)
2. As `admin`, open `wp-admin/post.php?post=<job-id>&action=edit` → the Job Details metabox Company select has the employer's company pre-selected (not "- Select a company -")
3. Press **Update** without touching the Company select → `wp post meta get <job-id> _wcb_company_id` is unchanged and `_wcb_company_name` still matches the company title
4. Repeat with a job whose linked company is **draft or pending** → the select still pre-selects it (the option list is published-only plus the currently-linked company) and Update preserves the link
5. Take a job with NO `_wcb_company_id` whose author owns a company → open it, press Update without touching the select → the link is adopted from the author (`_wcb_company_id` now set)
6. Now explicitly choose "- Select a company -" on a linked job and press Update → `_wcb_company_id` and `_wcb_company_name` are both deleted (the deliberate unlink is honoured, not re-adopted)
7. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

```bash
wp post meta update <job-id> _wcb_company_id <company-id>
wp post meta update <job-id> _wcb_company_name "<company-title>"
```

## Notes

- The "no companies exist yet" case still uses the free-text `wcb_company_name` input; that branch is detected by `isset( $_POST['wcb_company_name'] )`, which is only rendered when the select is absent.
- Step 6 is distinguishable from step 5 solely by whether the job currently has a `_wcb_company_id` — there is no third form field. Keep that ordering in mind before refactoring the save branch.
