# Troubleshooting (Candidates)

Common things candidates run into, with the practical fix for each.
If you're an employer, see `for-employers/12-troubleshooting.md`.

## "I applied but never heard anything back"

That's almost always normal - most employers review applications in
batches. Check **Candidate Dashboard → My Applications** to verify
your application is there.

- **Status "Submitted"** - the employer has it, hasn't acted yet.
  No action from you needed.
- **Status "Reviewing"** - they've looked at it.
- **Status "Shortlisted"** - you're under consideration. Expect
  follow-up.
- **Status "Hired"** - congratulations, you got the job.
- **Status "Rejected"** - not a fit for this role; the application
  is closed.
- **Status "Withdrawn"** - you (or the system) withdrew it.
- **Status "Job Removed"** - the job posting was taken down. Your
  application is preserved in your history but no further action
  is expected.

If you don't see the application at all in your dashboard, it
didn't submit successfully - try again.

## "The Apply button is missing from a job"

A few reasons this happens:

1. **The job's deadline has passed.** Expired jobs hide the apply
   button. Look for the deadline date on the job page.
2. **The job was filled or unpublished.** If the employer closed
   the posting, the apply button is removed.
3. **The site requires the Candidate role.** Most sites let any
   logged-in member apply, but some turn on "Require Candidate
   Role" - on those, register or sign in with a candidate account
   first.

You usually do not need an account at all: most sites also allow
guest applications. See [Apply as a Guest](09-guest-apply.md).

## "I can't apply - it says 'You have already applied'"

You already submitted to this job. Two ways this happens:

- **You applied as a guest with the same email.** The system blocks
  duplicate guest applications to the same job within 24 hours. If
  you really want to send a different application, register an
  account and apply from there.
- **You applied as a logged-in candidate.** Each candidate can
  apply once. Withdraw the existing application (from your
  dashboard) if you want to re-apply with a different resume.

## "My resume upload failed"

- **File too large.** The default limit is 5 MB. The site admin
  can raise it up to 20 MB, but if your file is over the limit
  you'll see "File must be under X MB." Re-export your PDF with
  smaller image embeds.
- **Wrong format.** Only PDF, DOC, and DOCX are accepted. ODT,
  RTF, TXT, and images are rejected - convert to PDF first.
- **Network blip.** Re-try the upload. Don't hit "Apply" again
  until the upload completes.

## "I can't find a job I saw earlier"

- **It might have expired.** Default listing hides past-deadline
  jobs. Use the search bar to find it by title - expired jobs
  may still be searchable in some configurations.
- **It might have been deleted.** If the employer pulled the
  posting, it's gone.
- **You bookmarked it.** Check **Candidate Dashboard → Saved
  Jobs** - bookmarks survive even if the job changes status.

## "My saved jobs aren't showing up"

- **Make sure you're logged in.** Bookmarks are tied to your
  account; they don't survive logout if you're using a guest
  session.
- **The bookmark might not have saved.** Try clicking the bookmark
  button again. The button highlights when the job is saved.

## "I didn't get the confirmation email after applying"

- **Check your spam / promotions folder.** Most missing emails
  are spam-flagged.
- **The site might have email-sending issues.** This is a
  site-admin problem, not yours - your application IS still
  recorded (verify in your dashboard).
- **Wrong email on file.** If you applied as a guest with a typo
  in your email, the confirmation went to the wrong address.
  The application is recorded but you have no way to track it.
  Register an account and apply again with the right email.

## "I see 'Job no longer available' on one of my applications"

The employer (or a site admin) deleted that job posting after you
applied. Your application is preserved in your history - that's
intentional, so you have a record of having applied. The status
moves to "Job Removed" so you know it's no longer in flight.

If you'd been hired or shortlisted before the deletion, the
employer typically reaches out separately. If you weren't yet,
the role won't move forward through this posting - they may
re-post later, or they may have filled it through another
channel.

## "I'm getting too many job alerts (or too few)"

- **Manage your alerts** at **Candidate Dashboard → Job Alerts**.
  Each alert has saved search filters and a frequency you can edit
  or delete.
- **Email frequency** is per-alert: Instant, Daily, or Weekly. If
  you set up multiple alerts with overlapping filters, you'll get
  multiple emails - consolidate them or lower the frequency.

## "Employers aren't finding my profile"

Employers browse candidates through the Find Candidates archive.
Your profile is **Public by default**, so it appears there as soon
as you have an account. (Profiles can be made Private, in which case
only you and admins can see them - that switch is API-driven and is
exposed in WP Career Board Pro.) To improve your discoverability:

- **Complete your profile.** Open **Candidate Dashboard -> Profile**
  and add a bio, phone, and location. Empty profiles are easy to
  skip past.
- **Keep your account details current** under **Candidate Dashboard
  -> Account Settings** (display name and email).
- **Build a resume.** With WP Career Board Pro, use the Resume
  Builder so employers see your structured experience and
  education, and can download a PDF.

> Some themes (for example BuddyX Pro) also show an "#OpenToWork"
> badge on member profiles. That badge is a theme integration and
> is separate from the Find Candidates archive.

## "I forgot my password"

Use the standard WordPress "Lost password" link on the login page.
You'll get a reset email at the address you registered with.

If you are already signed in and just want to change your password,
use **Candidate Dashboard → Account Settings → Change Password**
instead.

## "I want to delete my account"

Go to **Candidate Dashboard → Account Settings → Privacy & My
Data** and click **Request account deletion**. This sends a
confirmation email to your registered address. After you click the
link in that email, the site administrator processes the erasure,
which permanently removes:

- Your account
- All your applications (employers see "Anonymous candidate" on
  archived applications, not your details)
- Your resume(s)
- Your saved jobs, saved companies, and alerts

This is a GDPR / privacy compliant erasure and cannot be undone.
The same panel also has **Request data export** if you just want a
copy of your data.

## I'm stuck - who do I contact?

Look on the site for a "Contact" link, usually in the footer.
When you reach out, include:

1. The URL of the page where the problem happens.
2. A screenshot of what you see.
3. The time and date.
4. The email address tied to your account.

Don't include passwords, payment info, or sensitive personal
data - just descriptive details.

## Related

- [02-finding-jobs.md](02-finding-jobs.md) - Browsing and searching
- [03-applying-for-jobs.md](03-applying-for-jobs.md) - The apply
  flow
- [04-my-applications.md](04-my-applications.md) - Tracking
  applications
- [07-job-alerts.md](07-job-alerts.md) - Managing alerts (Pro)
- [11-profile-and-account.md](11-profile-and-account.md) - Profile,
  account settings, privacy, and notifications
