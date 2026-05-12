# Your First Day as a Site Owner

A complete walkthrough from "I just installed the plugin" to "my first
employer posted their first job and a candidate applied." Plan ~60–90
minutes for a thorough run. If you want to skim, the section
headings below let you jump.

## What you'll have at the end

- A working job board at `/find-jobs/`, `/companies/`, `/find-candidates/`.
- One employer account that can post jobs.
- One candidate account that can apply.
- Email notifications wired and tested.
- A real test job published and a test application submitted.

## Before you start

You need:

- WordPress 6.5+ on PHP 8.1+ (the plugin checks this on activation).
- An admin account on the site.
- The ability to send email from the site (SMTP plugin, host SMTP, or
  the site already sending email reliably).
- A theme that doesn't aggressively override `.entry-content` styles.
  Most modern themes work; some opinionated ones (Astra Pro, certain
  GeneratePress configs) need a custom CSS sweep — that's covered
  later.

If you want to test Pro features (AI, advanced credits, application
pipeline, multi-board), install Pro too. The flow below assumes
Free-only first, since Pro adds onto the same foundation.

## Step 1 — Install

1. **Plugins → Add New → Upload Plugin.** Pick the `wp-career-board.zip`
   you downloaded.
2. Activate. The plugin spins up:
   - 3 database tables (jobs aren't a table — they're a CPT — but
     applications, saved jobs, and the credit ledger live in dedicated
     tables).
   - 3 custom roles: Candidate, Board Moderator, Banned Employer.
   - 12 custom capabilities (see
     [admin-guide/14-capabilities-and-roles.md](../admin-guide/14-capabilities-and-roles.md)).
   - Default pages: Find Jobs, Companies, Find Candidates, Candidate
     Dashboard, Employer Dashboard, Post a Job, Employer Registration.
3. The Setup Wizard launches automatically. Don't dismiss it — walk it.

If you can't see the Setup Wizard, navigate to
**WP Admin → Career Board → Setup**.

## Step 2 — Walk the Setup Wizard

The wizard collects the four things that change per site:

1. **What's your job board for?** — public job listings, internal
   hiring, university careers, etc. This sets reasonable defaults
   (e.g. internal-hiring boards default to requiring login to apply).
2. **Who can post jobs?** — Editor role (default), a custom role, or
   anyone who registers. For most public boards, "anyone who registers
   gets `wcb_post_jobs`" is right. Internal boards usually pick Editor.
3. **How are postings paid for?** — Free, credits per posting, or
   subscription. You can change this later. For first-day setup, pick
   **Free** unless you've already decided on a business model.
4. **Notification email** — where new-application alerts go when the
   posting employer hasn't set a custom address. Usually the site
   admin email.

The wizard then verifies pages are correctly mapped (Find Jobs page
exists, Candidate Dashboard exists, etc.) and offers to fix any
missing slots.

Finish the wizard. You land on the main settings page.

## Step 3 — Test email sending

Career Board sends ~15 different transactional emails (application
received, application status changed, job published, password reset,
verify your address, etc.). If your site can't send email, everything
downstream breaks silently.

1. **Career Board → Settings → Notifications → Test email.** Enter
   your own email address. Send.
2. If you receive it within 30 seconds: green light, move on.
3. If you don't: install **WP Mail SMTP** or **Fluent SMTP**, configure
   your provider (SendGrid, Mailgun, Postmark, Amazon SES, your host's
   SMTP), and retest.

This is the single most-overlooked step. Customers report "no
applications coming in" — 70% of the time it's "applications came in,
the email failed, employer never knew." Fix this on day one.

## Step 4 — Set up email notifications

**Career Board → Settings → Notifications.** The defaults are
reasonable but worth a once-over:

- **From name** — usually your site name, not "WordPress."
- **From email** — must match your sending domain (DMARC / DKIM /
  SPF). If your site is `example.com`, the from email should be
  `noreply@example.com` or similar.
- **New application received** — to employer + admin by default. Keep
  both unless you have a reason.
- **Application status changed** — to candidate. Keep enabled. This is
  the single most important candidate touchpoint after submission.
- **Welcome emails** — to candidates and employers on registration.
  Keep enabled; the contents are friendly and customisable.

## Step 5 — Add a Find Jobs link to your menu

The plugin created the pages but didn't wire your menu.

1. **Appearance → Menus.**
2. Add: Find Jobs, Companies, Find Candidates (if you want the
   candidate directory public), Candidate Dashboard, Post a Job.
3. The Employer Dashboard / Post a Job links can be in the menu OR
   accessible only via the employer dashboard once they log in —
   your choice based on whether employers self-register or you onboard
   them manually.
4. Save.

## Step 6 — Create your first test employer

Don't post a job from your admin account — that hides bugs. Create
an actual employer and test the flow.

1. **Open a private window** so you stay logged in as admin in the main
   browser.
2. Visit `/employer-registration/` (or whatever you mapped the
   employer-registration page to).
3. Register with a real email you can check (e.g. your-name+test@gmail.com).
4. Confirm the welcome email arrives.
5. Log in as that employer.

## Step 7 — Post the first test job

Still as the test employer:

1. Click **Post a Job** from the employer dashboard.
2. Fill in:
   - Title: "Test Job — Senior Frontend Engineer"
   - Description: a paragraph or two.
   - Category: any (or create one inline).
   - Location: any city.
   - Type: Full-time.
   - Application: leave as "Apply through this site" (not "External
     URL"). External-URL testing comes later.
3. Submit.

If you set the posting cost to free, the job goes straight to **Published**.
If you set "requires admin approval," it sits at **Pending Review** — go
back to your admin window and approve it from **WP Admin → Career Board →
Jobs**.

Verify the job appears on `/find-jobs/`. If it doesn't:

- Check the job's status (Published, not Draft).
- Check the deadline isn't in the past.
- Check your theme isn't redirecting `/find-jobs/` somewhere.

## Step 8 — Create your first test candidate

1. Private window (or a different browser / incognito).
2. Register from the Candidate Dashboard or from
   `/candidate-registration/` if that page is mapped.
3. Confirm welcome email.
4. Log in.
5. Fill in profile: name, headline ("Senior Frontend Engineer"),
   skills, location.
6. Upload a resume PDF (any sample resume works).

## Step 9 — Apply to the test job

As the candidate:

1. Open `/find-jobs/` from the candidate's logged-in browser.
2. Click the test job.
3. Click **Apply**.
4. The application form pre-fills from the candidate's profile + resume.
5. Add a cover-letter paragraph.
6. Submit.

You should see a "thanks — application submitted" confirmation.

## Step 10 — Verify the employer side

Back in the employer window:

1. **Employer Dashboard → Applications.** The test application should
   be visible.
2. Click into the application. The candidate's resume should be
   attached and downloadable.
3. **Email check** — did the new-application email arrive at the
   employer's inbox? If not, return to Step 3 and fix email sending.
4. Move the application's status to "Reviewing." Save.
5. **Candidate email check** — did the candidate receive a
   "your application status changed" email? If not, status-change
   notifications are off — re-check **Settings → Notifications**.
6. Move the application to "Shortlisted," then "Hired." Each one fires
   an email to the candidate.

## Step 11 — Verify the candidate dashboard

Candidate window:

1. **Candidate Dashboard → My Applications.**
2. The test application should show status "Hired."
3. **Saved Jobs** — bookmark another job from `/find-jobs/`. Confirm
   it appears here.
4. **Profile** — verify the profile is editable and changes save.

## Step 12 — Clean up your test data

Once you're satisfied:

1. Delete the test job from **WP Admin → Career Board → Jobs.**
2. Delete the test application from **WP Admin → Career Board →
   Applications**.
3. Delete the test candidate account from **WP Admin → Users.**
4. Delete the test employer account.

Or keep them and move them to a "test" status so you can iterate. Up
to you.

## What's next

You have a working board. Now you'd usually pick a direction:

- **[02-employer-end-to-end.md](02-employer-end-to-end.md)** — what the
  real employer flow looks like (you've already done this once).
- **[04-monetizing-your-board.md](04-monetizing-your-board.md)** — if
  you want to charge for postings, get this set up early.
- **[../admin-guide/14-capabilities-and-roles.md](../admin-guide/14-capabilities-and-roles.md)**
  — granting `wcb_post_jobs` to specific staff or a third-party HR
  role.
- **[../ai-features/01-overview.md](../ai-features/01-overview.md)** —
  if you've also installed Pro and want to enable AI features.

## Common day-one mistakes to avoid

- **Skipping the email test.** Everything breaks silently if email
  doesn't send. Always test before you announce the board.
- **Posting jobs from the admin account.** Your admin sees everything
  and skips role gates. Always test as a real employer / candidate.
- **Skipping the deadline.** Newly posted jobs default to a 30-day
  deadline. If you want unlimited, change the default under
  **Settings → Job Posting → Default deadline**.
- **Not wiring the menu.** Employers and candidates can't navigate
  if the menu doesn't link to dashboards. Easy to forget; users
  notice immediately.
- **Forgetting Pro's license activation.** If you also installed Pro,
  activate the license under **Settings → License** or Pro features
  silently skip.
