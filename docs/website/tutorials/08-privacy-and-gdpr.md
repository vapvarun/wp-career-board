# Privacy and GDPR

You can answer personal-data export and erase requests with WordPress's built-in privacy tools, and members can request their own export or delete their own account from their dashboard. This page shows what Career Board stores and how to handle each request. It is not legal advice.

## What Career Board stores

| Data | Where | What happens on erasure |
|---|---|---|
| User account | WordPress users | Removed when the account is deleted |
| Candidate profile: headline, location, open-to-work, profile visibility, profile resume data | User meta | Deleted |
| Saved jobs, companies and resumes | User meta | Deleted |
| Uploaded resumes and other private files | Private uploads folder | Deleted |
| Applications | `wcb_application` posts | Anonymised (see below) |
| Email history | `wcb_notifications_log` table | Deleted for the person, and pruned automatically after the retention period |
| Job view counts | `wcb_job_views` table | Stores a one-way hash of the visitor's IP address, not the address. Rows older than 90 days are pruned by default. |
| Privacy request log | `wcb_gdpr_log` table | Kept as a record that a request was handled |

With Pro, resumes, job alerts, notification-bell alerts and app devices for members are also deleted on erasure. Pro's credit ledger is kept for accounting, but its rows lose their link to the person.

### Applications are anonymised, not deleted

When a person is erased, their applications stay so employers' hiring records stay accurate. The job, status, status history and dates remain. The name becomes "Deleted candidate", and the email, cover letter, answers and every attached file are removed. The same happens to a guest's applications when the request is for the email address they applied with.

A ban on an account is kept as a safety record and is not lifted by an erasure.

## Step 1 - Update your privacy policy

Career Board does not write your privacy policy. Add a paragraph that covers what you collect (names, emails, resumes, profile details, job postings and applications), why, and how a person can ask for an export or deletion. Tell members they can do this from **Candidate Dashboard > Account > Settings > Privacy & My Data**. If you use Pro AI features, name the AI provider you configured. If you take payments, name your payment provider.

The employer registration form shows a "By creating an account you agree to our Privacy Policy" line that links to the privacy page set under **Settings > Privacy** in WordPress.

## Step 2 - Consent

Career Board does not add a consent checkbox to its forms. If your jurisdiction needs one, add it with a consent plugin, or add a required field through the form field filters `wcb_candidate_form_fields` and `wcb_application_form_fields_groups`.

## Step 3 - Export requests

**A member requests it.** In the Candidate Dashboard, open **Account > Settings > Privacy & My Data** and click **Request data export**. WordPress emails the member to confirm. Once confirmed, the request appears in WordPress's privacy queue and you complete it from there.

**You create it.**

1. Go to **Tools > Export Personal Data**.
2. Enter the person's email address and send the request.
3. WordPress emails the person a link to confirm.
4. Once confirmed, click **Send Export Link**, or download the export yourself.

The export includes the person's applications, profile and saved items, and email history. Guests who applied with an email address are found by that address.

## Step 4 - Erase requests

**A member deletes their own account.** Under **Privacy & My Data**, the member clicks **Delete my account**, enters their password, types DELETE and clicks **Schedule deletion**. The account is locked and signed out everywhere. It is deleted after a grace period of 14 days by default. Signing back in during that time and choosing **Keep my account** stops the deletion. A daily task deletes accounts whose date has passed. Administrator accounts cannot be deleted this way.

You can watch and manage these under **Career Board > Settings > Privacy**. The **Pending account deletions** card lists each member with the date and time left. Click **Keep account** to cancel a deletion for a member who contacts you.

**You erase a person.**

1. Go to **Tools > Erase Personal Data**.
2. Enter the person's email address and send the request.
3. Once the person confirms, WordPress runs the erase, including the Career Board data described above.

Deleting a user under **Users** runs the same erase. Employers have no delete button in their dashboard, so use these tools for employers.

Job listings posted by an employer are not erased automatically by an erase request. Remove them yourself if needed. When a job is permanently deleted, its applications move to the **Job removed** status.

## Step 5 - Retention

Under **Career Board > Settings > Advanced**, **Keep Email History (days)** sets how long the email log and notification history are kept. The default is 180 days and 0 keeps them forever. A daily task deletes older rows.

Applications are not deleted on a schedule, because they are the employer's hiring record. If your policy needs a limit, remove old applications yourself and describe the policy in your privacy notice.

## Step 6 - Cookies and browser storage

Career Board does not set its own cookies. It uses WordPress's standard sign-in cookies. It also remembers a few display choices in the visitor's browser, such as the grid or list layout of the company directory and the open tab of the candidate dashboard. Cover these in your cookie notice as you would for any site.

## Step 7 - Breach response

If you suspect a breach:

1. Work out which accounts and files were exposed.
2. Contain it. Reset admin and employer passwords and disable compromised accounts.
3. Tell affected people and the regulator within the time your law requires. You can list accounts under **Users**, filtered by the Career Board roles.
4. Fix the cause and keep a record of what happened and what you did.

## Common mistakes

- **No consent step where your law needs one.** Add your own.
- **Not naming your providers.** Update the privacy policy when you add payment or AI providers.
- **Deleting rows by hand.** Use the WordPress privacy tools so the Career Board data is erased consistently.
- **Forgetting the activity stream.** With BuddyPress, Free posts an activity entry when a job is published straight away, and Pro can post more. Make sure your privacy notice covers it.

## Where to go next

- [GDPR admin guide](../admin-guide/04-gdpr.md) - the admin reference.
- [Your first day as a site owner](01-first-day-as-site-owner.md) - set up privacy from day one.
- [AI setup and providers](../ai-features/02-setup-and-providers.md) - AI provider details.
