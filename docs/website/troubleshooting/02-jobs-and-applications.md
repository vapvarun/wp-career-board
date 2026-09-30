# Troubleshooting: jobs and applications

Each entry gives the symptom, the cause and the fix.

## A job is missing from Find Jobs

**Symptom.** A job exists in wp-admin or in the employer's dashboard, but it is not in the listings.

**Cause and fix.** Check in this order:

1. **Status.** Only **Published** jobs list. In **Career Board → Jobs**, Pending, Draft, Closed, Expired and Rejected jobs do not. A job that shows **Awaiting payment** was approved but its employer's credit balance did not cover it (needs Pro credits); it goes live once they top up.
2. **Deadline.** A job past its deadline becomes Expired within the hour (hourly WP-Cron) and leaves the listings, feeds and sitemap. Its page still opens and says it has expired. Reopen it from the employer dashboard for a new deadline. If WP-Cron does not run on your site, run `wp wcb job run-expiry` or set up a real cron for `wp-cron.php`.
3. **Hidden by moderation.** In the Jobs list a hidden job shows **Hidden: reported** (enough members with standing reported it; the default is 3) or **Hidden: employer banned**. Use **Dismiss flag** on a reported job, or unban the employer, and the job returns.
4. **Filters.** The visitor's active filters (shown as pills above the results) or a filter in the address bar can hide it. Click **Clear all**.
5. **Cache.** A full-page cache can serve an old listing. Purge it.

## A job page says the job has expired but the employer wants it live

**Cause.** The deadline passed, or the job's listing length ran out.

**Fix.** The employer clicks **Reopen** in **My Jobs**. It gets a fresh deadline with the same default as a new job (paid boards charge credits). An admin can set the job back to Published in the Jobs screen.

## There is no apply button

**Cause and fix.**

- The job's deadline passed or it was closed. An expired job page offers no way to apply.
- **Require login to apply** is on and the visitor is signed out. The page shows **Sign in to apply**.
- The visitor is an employer. A member who posts jobs cannot apply, and nobody can apply to their own job.
- **Require Candidate Role** is on and the visitor does not hold the Candidate role.
- The employer set an **Apply URL**; the page shows **Apply on Company Site** instead of the on-site form.

## A guest application did not link to the account after registering

**Symptom.** A candidate applied as a guest, then created an account, but **My Applications** is empty.

**Cause.** Guest applications are linked to an account once, at the moment the account is created, and only when the account's email is exactly the email used on the application. Up to 200 matching applications are moved. They are not moved if the account already existed when the person applied, or if they registered with a different email.

**Fix.** Nothing is lost: the employer still sees the application under the guest email. Ask the candidate to sign in before applying next time, and to use the same email they registered with.

## An employer cannot change an application's status

**Cause.** **Withdrawn**, **Closed** (position closed) and removed-job applications are final. The status control is replaced by a badge, and a change through the API answers 409. Reopening the job restores Closed applicants to their earlier status.

## "Too many sign-ups from your network"

**Symptom.** New members get a 429 error "Too many sign-ups from your network. Please try again in an hour." even though few people are signing up.

**Cause.** Sign-ups are limited to 5 an hour per IP address. The limit reads the connection's address. Behind a proxy or CDN that is the proxy's address, so every visitor shares one count.

**Fix.** Make your proxy pass the real address to PHP (for example the web server's real-IP setting), or change the limit in code with the `wcb_registration_rate_limit` filter (0 turns it off). Guest applications have a similar limit of 10 an hour. See [Abuse prevention](../developer-guide/03-rest-api.md#abuse-prevention).

## A candidate cannot open a resume file

**Symptom.** A resume link returns "File not found".

**Cause.** Resume and CV files are private. Only the candidate, site staff and the employer the candidate applied to can open them. Anyone else, including a signed-out visitor, gets the same "File not found" as for a missing file.

**Fix.** Sign in as the employer who received the application, or as staff. If files were moved by the 1.8.0 upgrade and some are still in the public uploads folder, Site Health names them under **Tools → Site Health**. Fix the folder permissions, then run `wp wcb migrate files`. If Site Health says candidate files can be fetched directly, your server ignores `.htaccess` (common on nginx): add the rule Site Health prints.

## Jobs from a Google for Jobs test are missing fields

**Cause.** Google leaves out a job with no location unless it is remote, and a job that has ended gets no markup.

**Fix.** Turn on **Require a location** under **Settings → Jobs**, give the job a location or mark it Remote, and check that the job has not passed its deadline. See [Google for Jobs and Social Sharing](../admin-guide/17-google-for-jobs.md).
