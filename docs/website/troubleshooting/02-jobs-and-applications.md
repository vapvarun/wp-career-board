# Troubleshooting: jobs and applications

Find your symptom below. Each entry gives the cause and the fix.

## A job is missing from Find Jobs

**Symptom.** A job exists in wp-admin or in the employer's dashboard, but it is not in the listings.

Check in this order:

1. **Status.** Only Published jobs list. In **Career Board > Jobs**, Pending, Draft, Closed, Expired and Rejected jobs do not. A job marked **Awaiting payment** was approved and is waiting for the employer's credits (Pro credits).
2. **Deadline.** A job past its deadline leaves the listings, feeds and sitemap at the next hourly check. Its page still opens and says it has expired. The employer can reopen it from the dashboard for a new deadline.
3. **End jobs at their deadline.** On sites that were set up before this option existed, jobs past their deadline stay listed until you click **End jobs at their deadline** under **Career Board > Settings > Jobs > When a job ends**. The button only shows while the option is off.
4. **Hidden by moderation.** In the Jobs list, a hidden job shows **Hidden: reported** or **Hidden: employer banned**. A job is hidden after enough members with standing report it (3 by default). Use **Dismiss flag** on a reported job, or unban the employer, and the job returns.
5. **Filters.** The visitor's active filters, shown as pills above the results, or a filter in the address bar can hide it. Click **Clear all**.
6. **Cache.** A full-page cache can serve an old listing. Purge it.

If WP-Cron does not run on your site, run `wp wcb job run-expiry` or set up a real cron job for `wp-cron.php`. The command does nothing while **End jobs at their deadline** is off.

## An expired job needs to go live again

The employer clicks **Reopen** on the job in their dashboard. The job gets a new deadline, using the same default length as a new job.

## There is no apply button

- The job's deadline passed or it was closed. An expired job page offers no way to apply.
- **Require login to apply** is on and the visitor is signed out. The page shows **Sign in to apply**.
- The visitor is an employer. A member who posts jobs cannot apply, and nobody can apply to their own job.
- **Require Candidate Role** is on and the visitor does not hold the Candidate role.
- The employer set an **Apply URL**. The page shows **Apply on Company Site** instead of the on-site form.

## A guest application did not link to the account after registering

**Symptom.** A candidate applied as a guest, then created an account, but **My Applications** is empty.

**Cause.** Guest applications are linked to an account when the account is created, and only when the account's email is exactly the email used on the application. Up to 200 matching applications are moved. They are not moved if the account already existed when the person applied, or if they registered with a different email.

**Fix.** Nothing is lost. The employer still sees the application under the guest email. Ask the candidate to sign in before applying next time, and to register with the same email.

## An employer cannot change an application's status

**Cause.** **Withdrawn**, **Closed** (position closed) and removed-job applications are final. The status control is replaced by a badge, and a change through the API answers 409. Reopening the job restores Closed applicants to their earlier status.

## "Too many sign-ups from your network"

**Symptom.** New members get a 429 error "Too many sign-ups from your network. Please try again in an hour." even though few people are signing up.

**Cause.** Sign-ups are limited to 5 an hour per IP address. Behind a proxy or CDN every visitor arrives from the proxy's address, so they all share one count.

**Fix.** Go to **Settings > Anti-Spam > Visitor IP address** and choose your proxy (for example Cloudflare). You can also change the limit in code with the `wcb_registration_rate_limit` filter (0 turns it off). Guest applications have a similar limit of 10 an hour. See [Abuse prevention](../developer-guide/03-rest-api.md#abuse-prevention).

## A candidate cannot open a resume file

**Symptom.** A resume link returns "File not found".

**Cause.** Resume and CV files are private. Only the candidate, site staff and the employer the candidate applied to can open them. Anyone else, including a signed-out visitor, gets the same "File not found" as for a missing file.

**Fix.** Sign in as the employer who received the application, or as staff.

If Site Health under **Tools > Site Health** says some candidate files could not be moved out of the public uploads folder, fix the folder permissions, then run `wp wcb migrate files`.

If Site Health says candidate files can be fetched directly, your server ignores `.htaccess` (common on nginx). Add the rule Site Health prints.

## Jobs in a Google for Jobs test are missing fields

**Cause.** Google leaves out a job with no location unless it is remote, and a job that has ended gets no markup.

**Fix.** Turn on **Require a location** under **Settings > Jobs**, give the job a location or mark it Remote, and check that the job has not passed its deadline. See [Google for Jobs and social sharing](../admin-guide/17-google-for-jobs.md).
