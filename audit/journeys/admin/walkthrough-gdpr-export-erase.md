---
id: walkthrough-gdpr-export-erase
priority: high
personas: varundubey
requires: mu:autologin
last_verified: 2026-09-27
---

# Walkthrough: GDPR Export & Erase - admin exports then erases a candidate's WP Career Board data via WP core privacy tools

> **Set these two first.** Every command and URL below uses them, so the
> walkthrough runs on any machine rather than the one it was written on:
>
> ```bash
> WCB_PATH="$(cd "$(git rev-parse --show-toplevel)/../../.." && pwd)"
> WCB_SITE="$(wp --path="$WCB_PATH" option get home)"
> ```
>
> `bin/qa-fixtures.sh` derives the same root the same way, so the two agree.

**Why this journey exists:** owner decision D3 (1.8.0). One registry (`wcb_personal_data_providers`, `modules/gdpr/class-gdpr-module.php`) drives the core Tools > Export/Erase Personal Data screens and user deletion. An erasure anonymises applications (employer keeps job, status and dates as "Deleted candidate") and deletes everything personal: profile, files, resumes, saved items, email history, and Pro alerts and bell. Guests are found by the email they applied with.

## Steps

1. As `varundubey`, open `$WCB_SITE/wp-admin/export-personal-data.php?autologin=varundubey`, add a request for `sarah.chen`'s email and run **Download personal data** → the archive has groups Job applications (cover letter, answers, resume file name per application), Career Board profile, Email history, and with Pro Resumes, Job Alerts, Notifications.
2. Repeat step 1 for the email of a guest who applied without an account → their applications and email history are exported.
3. Open `$WCB_SITE/wp-admin/erase-personal-data.php`, add a request for the guest's email and run **Force erase personal data** → the notice says applications are kept without personal details; the employer's applicant list shows "Deleted candidate" with no email, cover letter or files.
4. Ban a test candidate (Candidates > Ban), then erase their data → the ban is still in place and the result message says so.
5. As a test candidate with one resume (Pro), two applications with a CV, go to **Candidate Dashboard > Settings > Delete my account**; the form says profile, resumes and files are deleted, employers keep applications without name and contact details, and shows the grace days. Confirm with password + DELETE, then run the deletion without waiting: `wp eval 'update_user_meta( <ID>, "_wcb_deletion_scheduled_at", time() - 1 );' && wp cron event run wcb_process_account_deletions` → user gone; both applications read "Deleted candidate" for the employer; the CV URL returns 404; resume page gone; credit ledger rows (Pro) have `user_id` 0.
6. `wp_wcb_gdpr_log` has one `export` and one `erase` row per request (hashed IP).
7. Settings > Advanced shows **Keep Email History (days)** = 180. `wp cron event run wcb_prune_logs` deletes email-log (and Pro bell) rows older than that and leaves recent rows.
8. tail debug.log diff → ZERO new fatal / warning lines.

## Teardown

```bash
wp post list --post_type=user_request --field=ID 2>/dev/null \
  | xargs -r -n1 wp post delete --force 2>/dev/null || true
```

Delete the test candidate's leftover anonymised applications and the test job. Keep `wcb_gdpr_log` rows (permanent record).

## Notes

- Automated coverage: `tests/test-personal-data.php` (28 assertions: registry, member and guest export/erase, ban kept, full `wp_delete_user` cascade, Pro providers, retention).
- Pro adds its providers to the same filter (`modules/privacy/class-privacy-module.php`); there is no separate Pro exporter or eraser.
- Hooks exercised: `wcb_personal_data_providers` (steps 1-5, the registry every provider joins) and `wcb_logs_pruned` (step 7, fired after `wcb_prune_logs` with the UTC cutoff; Pro's bell prunes on it).
