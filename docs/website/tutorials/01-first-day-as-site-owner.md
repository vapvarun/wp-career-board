# Your First Day as a Site Owner

A complete walkthrough from "I just installed the plugin" to "my first
employer posted their first job and a candidate applied." Plan ~60-90
minutes for a thorough run. If you want to skim, the section
headings below let you jump.

## What you'll have at the end

- A working job board at `/find-jobs/` and `/companies/` (a public
  `/find-candidates/` directory is a Pro feature).
- One employer account that can post jobs.
- One candidate account that can apply.
- Email notifications wired and tested.
- A real test job published and a test application submitted.

## Before you start

You need:

- WordPress 6.9+ on PHP 8.1+ (the plugin checks this on activation).
- An admin account on the site.
- The ability to send email from the site (SMTP plugin, host SMTP, or
  the site already sending email reliably).
- A theme that doesn't aggressively override `.entry-content` styles.
  Most modern themes work; some opinionated ones (Astra Pro, certain
  GeneratePress configs) need a custom CSS sweep - that's covered
  later.

If you want to test Pro features (AI, advanced credits, application
pipeline, multi-board), install Pro too. The flow below assumes
Free-only first, since Pro adds onto the same foundation.

## Step 1 - Install

1. **Plugins → Add New → Upload Plugin.** Pick the `wp-career-board.zip`
   you downloaded.
2. Activate. The plugin spins up:
   - 3 database tables (jobs aren't a table - they're a CPT). Free
     creates its own tables; the credit ledger is a Pro table, not a
     Free one.
   - 3 custom roles: Employer, Candidate, and Job Moderator (the
     internal slug for Job Moderator stays `wcb_board_moderator` for
     back-compat). Banning an employer is a flag on the account, not a
     separate role.
   - 13 custom capabilities (see
     [admin-guide/14-capabilities-and-roles.md](../admin-guide/14-capabilities-and-roles.md)).
   - Five CPTs: `wcb_job`, `wcb_company`, `wcb_application`,
     `wcb_resume`, and the admin-only `wcb_board`.
3. The Setup Wizard launches automatically. Don't dismiss it - walk it.

If you can't see the Setup Wizard, navigate to
**WP Admin → Career Board → Setup**.

## Step 2 - Walk the Setup Wizard

The wizard has two steps:

1. **Create Pages** - the wizard creates the pages your board needs and
   maps them in Settings. The pages created are:
   - **Find Jobs** (search + filters + listings).
   - **Companies** (the company directory).
   - **Employer Registration** (sign-up form for new employers).
   - **Employer Dashboard** (includes Post a Job).
   - **Candidate Dashboard** (includes the resume builder and account
     settings).
   - **Post a Job** (the standalone job form).

   If a matching page already exists (it already contains the relevant
   Career Board block), the wizard reuses it instead of creating a
   duplicate.
2. **Sample Data** - optionally install demo companies and jobs so the
   board isn't empty while you test. You can remove the sample data
   later from **Career Board → Settings → Import** without re-running
   the wizard.

There is no "what's your board for / who can post / how are postings
paid for" questionnaire - those choices live in **Career Board →
Settings** (Job Listings, Pages, Notifications, Emails) and you set
them after the wizard.

Finish the wizard. You land on the Career Board settings screen.

## Step 3 - Test email sending

Career Board sends nine transactional emails covering the application
and job lifecycle: application confirmation (to the candidate),
application received (to the employer/admin), application status
changed, guest application, deadline reminder, job approved, job
pending review, job rejected, and job expired. It does not send its own
welcome, email-verification, or password-reset emails - those are
handled by WordPress core. If your site can't send email, everything
downstream breaks silently.

1. **Career Board → Settings → Emails.** Each template row has a
   **Send test** button that emails the current admin a preview. Click
   it on any template.
2. If you receive it within 30 seconds: green light, move on.
3. If you don't: install **WP Mail SMTP** or **Fluent SMTP**, configure
   your provider (SendGrid, Mailgun, Postmark, Amazon SES, your host's
   SMTP), and retest.

This is the single most-overlooked step. Customers report "no
applications coming in" - 70% of the time it's "applications came in,
the email failed, employer never knew." Fix this on day one.

## Step 4 - Set up email sender details

**Career Board → Settings → Notifications.** This tab holds the three
sender settings:

- **From Name** - usually your site name, not "WordPress." Defaults to
  your site name.
- **From Email** - must match your sending domain (DMARC / DKIM /
  SPF). If your site is `example.com`, the from email should be
  `noreply@example.com` or similar. Defaults to the site admin email.
- **Admin Notification Email** - where new-application alerts go when
  the posting employer hasn't set a custom address. Defaults to the
  site admin email.

The individual email templates (application received, application
status changed, job approved, etc.) and their enable/disable toggles
live on the separate **Emails** tab. Open each there to review the
copy, toggle it on or off, and send yourself a test.

- **Application status changed** - to the candidate. Keep enabled. This
  is the single most important candidate touchpoint after submission.

## Step 5 - Add a Find Jobs link to your menu

The plugin created the pages but didn't wire your menu.

1. **Appearance → Menus.**
2. Add: Find Jobs, Companies, Candidate Dashboard, Employer Dashboard,
   Post a Job. (A public Find Candidates directory is a Pro feature - add
   it only if Pro is installed.)
3. The Employer Dashboard / Post a Job links can be in the menu OR
   accessible only via the employer dashboard once they log in -
   your choice based on whether employers self-register or you onboard
   them manually.
4. Save.

## Step 6 - Create your first test employer

Don't post a job from your admin account - that hides bugs. Create
an actual employer and test the flow.

1. **Open a private window** so you stay logged in as admin in the main
   browser.
2. Visit `/employer-registration/` (or whatever you mapped the
   employer-registration page to).
3. Register with a real email you can check (e.g. your-name+test@gmail.com).
   The account is created with the Employer role.
4. Log in as that employer (registration uses the standard WordPress
   account flow; Career Board does not send a separate welcome email).

## Step 7 - Post the first test job

Still as the test employer:

1. Click **Post a Job** from the employer dashboard.
2. Fill in:
   - Title: "Test Job - Senior Frontend Engineer"
   - Description: a paragraph or two.
   - Category: any (or create one inline).
   - Location: any city.
   - Type: Full-time.
   - Application: leave as "Apply through this site" (not "External
     URL"). External-URL testing comes later.
3. Submit.

If you set the posting cost to free, the job goes straight to **Published**.
If you set "requires admin approval," it sits at **Pending Review** - go
back to your admin window and approve it from **WP Admin → Career Board →
Jobs**.

The same Jobs screen is also where you handle reported listings: when a
logged-in visitor reports a job (scam, spam, expired, misleading, or
offensive), a **Flagged** filter appears at the top of the list. Open it,
review the flagged job and the reasons in the Flags column, then either
**Dismiss flag** (the listing is fine) or **Unpublish** (the listing is
bad) from the row or bulk actions.

Verify the job appears on `/find-jobs/`. If it doesn't:

- Check the job's status (Published, not Draft).
- Check the deadline isn't in the past.
- Check your theme isn't redirecting `/find-jobs/` somewhere.

## Step 8 - Create your first test candidate

1. Private window (or a different browser / incognito).
2. There is no separate candidate-registration page. Open the Candidate
   Dashboard while logged out - it shows a **Log in** button that links
   to the standard WordPress login/registration screen. Register a
   normal WordPress account there.
3. Log in. By default any logged-in member can use the candidate
   experience (apply, save jobs, build a resume) without a dedicated
   Candidate role. If you turned on **Settings → Job Listings → Require
   Candidate Role**, assign the Candidate role to the account first.
4. Fill in profile: name, headline ("Senior Frontend Engineer"),
   skills, location.
5. Upload a resume PDF (any sample resume works).

## Step 9 - Apply to the test job

As the candidate:

1. Open `/find-jobs/` from the candidate's logged-in browser.
2. Click the test job.
3. Click **Apply**.
4. The application form pre-fills from the candidate's profile + resume.
5. Add a cover-letter paragraph.
6. Submit.

You should see a "thanks - application submitted" confirmation.

## Step 10 - Verify the employer side

Back in the employer window:

1. **Employer Dashboard → Applications.** The test application should
   be visible.
2. Click into the application. The candidate's resume should be
   attached and downloadable.
3. **Email check** - did the new-application email arrive at the
   employer's inbox? If not, return to Step 3 and fix email sending.
4. Move the application's status to "Reviewing." Save.
5. **Candidate email check** - did the candidate receive a
   "your application status changed" email? If not, status-change
   notifications are off - re-check **Settings → Notifications**.
6. Move the application to "Shortlisted," then "Hired." Each one fires
   an email to the candidate.

## Step 11 - Verify the candidate dashboard

Candidate window:

1. **Candidate Dashboard → My Applications.**
2. The test application should show status "Hired."
3. **Saved Jobs** - bookmark another job from `/find-jobs/`. Confirm
   it appears here.
4. **Profile** - verify the profile is editable and changes save.

## Step 12 - Clean up your test data

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

- **[02-employer-end-to-end.md](02-employer-end-to-end.md)** - what the
  real employer flow looks like (you've already done this once).
- **[04-monetizing-your-board.md](04-monetizing-your-board.md)** - if
  you want to charge for postings, get this set up early.
- **[../admin-guide/14-capabilities-and-roles.md](../admin-guide/14-capabilities-and-roles.md)**
  - granting `wcb_post_jobs` to specific staff or a third-party HR
  role.
- **[../ai-features/01-overview.md](../ai-features/01-overview.md)** -
  if you've also installed Pro and want to enable AI features.

## Common day-one mistakes to avoid

- **Skipping the email test.** Everything breaks silently if email
  doesn't send. Always test before you announce the board.
- **Posting jobs from the admin account.** Your admin sees everything
  and skips role gates. Always test as a real employer / candidate.
- **Skipping the deadline.** Newly posted jobs default to the listing
  lifetime set under **Settings → Job Listings → Default listing
  lifetime (days)** (default 30, range 1-365). A job is moved to the
  expired status by the daily expiry cron once it passes its deadline.
- **Not wiring the menu.** Employers and candidates can't navigate
  if the menu doesn't link to dashboards. Easy to forget; users
  notice immediately.
- **Forgetting Pro's license activation.** If you also installed Pro,
  activate the license under **Settings → License**. The license drives
  automatic updates only - Pro features keep working without it, but you
  won't receive update notifications until it is activated.
