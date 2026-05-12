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

Two reasons this happens:

1. **You need to log in.** Many sites require a candidate account
   to apply. Register from the Candidate Dashboard if you don't
   have one.
2. **The job's deadline has passed.** Expired jobs hide the apply
   button. Look for the deadline date on the job page.

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

- **File too large.** Default limit is 5 MB. Re-export your PDF
  with smaller image embeds.
- **Wrong format.** PDF works on every site. Word docs (.docx)
  may or may not, depending on site config - convert to PDF if in
  doubt.
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
  Each alert has a keyword, location, and frequency you can edit
  or delete.
- **Email frequency** is per-alert: daily or weekly. If you set up
  multiple alerts overlapping in keywords, you'll get multiple
  emails - consolidate them.

## "My profile says I'm 'Open to Work' but I'm not getting contacted"

The "Open to Work" flag makes you discoverable to employers
searching candidates. To improve visibility:

- **Complete your profile** - add a job title, location, skills,
  and a real resume. Empty profiles rank low in searches.
- **Update the skills section.** Employers search by skill tags,
  and "JavaScript" matches more searches than "JS" or
  "Frontend".
- **Add a clear resume PDF.** Employers ignore profiles without
  an attached resume.

## "I forgot my password"

Use the standard WordPress "Lost password" link on the login page.
You'll get a reset email at the address you registered with.

## "I want to delete my account"

**Candidate Dashboard → Profile → Delete my account.** This
triggers a confirmation email; click the link to permanently
delete:

- Your account
- All your applications (the employers see "Anonymous candidate"
  on archived applications, not your details)
- Your resume(s)
- Your saved jobs and alerts

This is a GDPR / privacy compliant erasure - it cannot be undone
once the confirmation link is clicked.

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
