# What's New in 1.8.0

WP Career Board and WP Career Board Pro ship in lockstep at 1.8.0.
Install both updates together. This page highlights the
customer-facing changes across the 1.5.0-1.8.0 and 1.3.0-1.4.x
cycles. For the full line-by-line history, see the changelog in
`readme.txt`.

## 1.8.0

A large release: a rebuilt employer and candidate experience, safer defaults for new sites, one rule for when a job ends, Google for Jobs markup, and a consistent look on every theme.

* New      - Employer and candidate dashboards have a new sidebar: your name and role on top, an Overview item, grouped links, and a Post a Job button pinned at the bottom.
* New      - The Credits tab in the employer dashboard shows the balance, purchases, receipts and history. A failed post for lack of credits offers a Buy credits button. Employers can pay to feature a job at posting time or from My Jobs, and featured jobs list first everywhere. Credits and featuring need Pro.
* New      - With Pro, each job row in My Jobs has a Pipeline link to that job's applicants.
* New      - Employers can rate an applicant from 1 to 5 stars and keep private team notes. Candidates never see either.
* New      - The applications Board works with the keyboard and on touch screens. Every card has a Move to menu, so dragging is optional.
* New      - Employers can export the selected job's applicants to CSV from the dashboard. Administrators can export everything that matches their filters.
* New      - A candidate who is not selected gets a respectful "not selected" email instead of the generic status email. Guests who applied with an email now receive status emails too.
* New      - Withdrawing keeps the application as Withdrawn and emails the employer. Closing a job moves undecided applicants to Position closed and emails each one.
* New      - Jobs end at their deadline. An ended job keeps its page as an expired page with similar open jobs instead of a 404, and employers get a Job Ending Soon email 3 days before.
* New      - Company pages and the directory count only open positions.
* New      - Google for Jobs markup built from each job's own data, with controls under Settings > Jobs: Require a location, Default country and Social sharing tags. Each Job Location can carry its own country.
* New      - One job search everywhere: every word of a keyword must match, title matches come first, filters accept several values, and Settings > Jobs has a Default order.
* New      - Alert me saves the whole search, including category, location, tags, remote, salary, board and custom-field filters. Guests type an email address next to the button. Needs Pro.
* New      - New emails: Welcome, Account deletion requested, cancelled and completed, Application not selected, Application withdrawn, Job ending soon, Report received and Confirm your email address. The email editor has a Preview button.
* New      - Members can turn off optional emails (deadline reminders and job ending soon) from the Settings tab of their dashboard. Every email is written in the recipient's language.
* New      - Settings > Brand: one colour and one logo for emails, the mobile app and the installable app.
* New      - Settings > Advanced adds Keep Email History (days) and Remove Data on Delete. Deleting the plugin removes nothing unless Remove Data on Delete is on.
* New      - Setup wizard has a stepper, an Exit setup link and steps for Pages, Sign-ups, Jobs, Emails and Spam Protection. Settings are regrouped by task in the sidebar.
* New      - Job category, type, tag, location and experience archives list that term's jobs with the term name as the title, on block themes and classic themes.
* New      - Site Health flags a theme's own copy of a Career Board template once it falls behind the plugin's. See Template Overrides in the Developer Guide.
* New      - Import from WP Job Manager now brings closed and expired jobs, hourly, monthly and yearly pay, company pages, tags and (with the new Applications import) applications.
* Improve  - New sites start with safer defaults: email confirmation on sign-up, jobs end at their deadline and a location is required. Existing sites keep their settings and see one notice.
* Improve  - Deleting an account or erasing personal data anonymises applications ("Deleted candidate") instead of removing the employer's record. Guests are found by email.
* Improve  - A banned employer's jobs, company and resume leave the site until unbanned. A job hides itself as Pending after 3 reports from members with standing (Settings > Jobs).
* Improve  - Application lists, counts and the admin Applications screen stay fast with thousands of applicants. Dashboards load 50 applications at a time with a Load more button. Deleting a job with many applicants finishes in the background.
* Improve  - One shared page container, heading scale, card, button, form field and empty-state style on Reign, BuddyX and block themes.
* Improve  - Company logos display uncropped, a long company name wraps to two lines, and the notification bell shows relative times.
* Fix      - Post a Job: the description editor's last edit is saved before Next or Publish, and a double-click no longer creates two jobs or skips a step.
* Fix      - Admin dashboard totals, Candidates and Employers counts no longer slow down or cap out on a large site.
* Security - Candidate resumes and generated CVs are stored in private storage and downloaded only by the candidate, staff and the employer they applied to.
* Security - Job fields are validated and cleaned the same way on every path, and an employer can no longer publish a job past moderation.
* Security - Only members allowed to see a candidate can open their profile. Sign-ups are rate limited and checked by the anti-spam gate, and changing an account email needs the current password.
* Dev      - New hooks for custom-field details on the job page, listing filter chips, personal data providers, the settings schema, page definitions and the community notification contract. See the Hooks Reference.
* Dev      - A theme resolving to its own generic single.php no longer blocks the plugin's canonical template; only a theme file matching the exact page name counts as an override.
* Compat   - Aligned with WP Career Board Pro 1.8.0. Install both updates together.

## 1.7.0

* New      - Full mobile REST API for companion apps, including an
  app-config endpoint and viewer-relative fields on job cards.
* New      - Members can report other members, and site owners can
  block members and suspend candidates.
* New      - Members can delete their own account from within the app.
* New      - Guest applications are linked to a member account
  automatically when someone registers with the same email address.
* New      - Server-side content filtering for job listings.
* Improve  - Employers can see and manage their jobs and applications
  before creating a company profile.
* Improve  - Accessibility pass across the frontend with stronger text
  contrast and 40px minimum tap targets.
* Improve  - Faster on large sites through indexed application
  lookups, a version-keyed company-list cache, and primed user caches
  that remove per-row lookups.
* Improve  - Old job-view records are pruned automatically on a daily
  schedule.
* Fix      - The employer dashboard no longer shows a "set up your
  company profile first" prompt next to a list of the employer's
  existing jobs.
* Fix      - The public company directory reflects brand edits
  (tagline, industry, size, location) immediately instead of after a
  cache delay.
* Fix      - The jobs archive no longer returns a 404 after the plugin
  is reactivated.
* Fix      - CSV exports are compatible with PHP 8.4 and later.
* Security - Job listing and application detail reads are scoped to
  their owner.
* Security - Blocked members can no longer see listings or single jobs
  on the server-rendered frontend.
* Compat   - Aligned with WP Career Board Pro 1.7.0. Install both
  updates together.

## 1.6.0

* New      - The plugin is now fully translation-ready and bundles
  German, French, Spanish, Dutch and Korean translations; every
  interface string loads through WordPress's standard translation
  system.
* New      - Notification email bodies are now editable per template
  from the Emails settings, each with a ready-to-use default.
* Fix      - Notification emails no longer send with an empty body;
  the message body falls back to a sensible default when left blank.
* Fix      - The "Manage License" link is back on the WP Career Board
  plugins-screen row.
* Compat   - Aligned with WP Career Board Pro 1.6.0. Install both
  updates together.

## 1.5.0

* Improve  - Unified the admin colour tokens onto the same canonical
  namespace as the frontend, so admin and frontend theme consistently
  from one source.
* Improve  - Admin buttons now meet the 40px minimum tap target, and
  the bookmark, layout, view-switch and settings-toggle controls show
  a keyboard focus ring.
* Improve  - Admin status badges and the application detail screen now
  use the semantic colour tokens.
* Fix      - Tinted banners (onboarding notice, form success message,
  status badges) are now readable in BuddyX and BuddyX Pro dark mode
  instead of showing light text on a light background.
* Fix      - The recommended jobs grid no longer collapses its columns
  to zero width.
* Fix      - The settings toggle knob and setup-wizard controls now
  position correctly under right-to-left languages.
* Compat   - Aligned with WP Career Board Pro 1.5.0. Install both
  updates together.

## 1.4.6

* Compat - Aligned with WP Career Board Pro 1.4.6, which reworks the Field
  Builder so custom fields are defined once and applied to every board. The
  free plugin has no functional changes in this release.

## 1.4.5

* Fix - BuddyX 5.1 theme compatibility. WP Career Board now maps its colors to
  BuddyX 5.1's token system (and dark mode), so dashboard navigation labels,
  "View all" links and sidebar buttons render legibly instead of white-on-white
  or as solid coral pills.
* Fix - Custom fields added with the Pro Field Builder now render and save
  correctly on forms for every field type - dropdown, multi-select, date range,
  salary range, video URL, file link, location and repeater. Several previously
  rendered as a plain text box and some did not save.

## 1.4.4

* Fix - The empty-state "Clear filters" button label stays legible under
  themes that force a button text color (such as BuddyX). It previously
  rendered blank where the theme painted the label the same color as the
  button background.

## 1.4.3

* New - The Job Listings block filter sidebar is now customizable. In the
  block settings you can reorder the filter groups (Job type, Experience,
  Category, Tags, Location, Job board, Salary) and hide any you do not need.
  Settings are per block, so each Job Listings placement can differ.
* Improve - WP Career Board now adapts to BuddyX and BuddyX Pro 5.1 light
  and dark color modes. Card avatars, dashboards, buttons, and widgets
  re-color correctly in dark mode (previously only Reign's dark mode was
  handled).
* Fix - The Job Listings block no longer emits PHP warnings when it
  renders with no matching jobs.
* Dev - New `wcb_notification_created` action fires whenever a notification
  is created, so a central notification center (such as BuddyNext) can
  mirror Career Board notifications.

## 1.4.2

* Fix - Banning an employer now takes effect. The Employers admin
  screen (Career Board -> Employers) gains Ban and Unban actions
  (per-row and bulk) plus a Status column. Banning an employer
  immediately removes every Career Board ability from that account.

## 1.4.0 - AI-assisted hiring and a Kanban board

This is the largest release of the cycle. The headline is AI-assisted
hiring on the dashboards, a List / Board (Kanban) view for
applications, and the change that lets any logged-in member apply
without a dedicated Candidate role.

### Any logged-in member can apply

Any logged-in member can now apply to jobs, save jobs, build a resume
(Pro), and use the candidate dashboard without being given a separate
Candidate role - ideal when the job board is part of a community site.
If you want stricter separation, turn on **Require Candidate Role**
under **Career Board -> Settings -> Sign-ups** (or use the
`wcb_candidate_requires_role` filter) to reserve the candidate
experience for the Candidate role.

### List / board toggle on the employer dashboard

The Employer Dashboard Applications tab now has a **List / Board**
toggle. The Board groups applicants into status columns - Submitted,
Reviewing, Shortlisted, Hired, and Rejected. Drag a card to change an
applicant's status, and the board, list, status emails, and AI
ranking all stay in sync. (Pro adds custom hiring stages on top of
this built-in board.)

![Employer dashboard applications](../images/employer-dashboard-applications.png)

### AI hiring tools (require Pro and an AI provider)

WP Career Board exposes AI hooks that Pro answers when an AI provider
is configured:

* **Applicant ranking** - rank a job's applicants by AI fit. Each
  applicant shows a fit-score badge, the list sorts best-first, and
  the applicant detail shows the reasoning.
* **TL;DR summaries** - each applicant shows a one-line summary on
  load once scored.
* **Recommended for you** - the Candidate Dashboard overview shows a
  set of AI-matched jobs.
* **Write with AI** - the apply panel can draft a cover letter from
  the candidate's resume and the job, ready to edit before applying.
* **Generate with AI** - the job form can auto-generate a structured
  job description (headings, paragraphs, and bullet lists).

### Sample data without re-running the wizard

You can install or remove the demo/sample data straight from
**Career Board -> Settings**, without re-running the setup wizard.

### Notifications panel redesign

The dashboard Notifications panel was redesigned with clearer read
and unread states, **Mark all read** and **Clear all** controls, and
an always-visible per-row delete button (40px tap target on mobile).
Notifications that pointed at the homepage now render non-clickable
instead of bouncing to the home page.

## 1.3.0 - Account self-service and clearer moderation

### Account settings in the dashboard

Candidates and employers can update their display name and email and
change their password directly in the dashboard, instead of being
sent off to wp-login.

![Candidate dashboard overview](../images/candidate-dashboard-overview.png)

### Rejected jobs and resubmit

Rejected job listings now show as "Rejected" (not "Draft") in the
employer dashboard, with a **Resubmit** action. Resubmitting sends
the job back for admin approval instead of publishing it directly.

### My jobs and applications fixes

* A newly posted job appears in My Jobs immediately, without a manual
  page reload.
* A job posted before you saved a company profile is adopted into My
  Jobs when the company is created.
* Saving a company from its profile page persists across reloads.

## Email and notification quality

The **Test Send** button on the Emails settings tab
(**Career Board -> Settings -> Emails**) succeeds even when a template
is toggled off, so admin previews no longer report a false "Failed".
Test sends are logged separately so they do not pollute production
delivery metrics.

![Emails settings tab](../images/settings-emails.png)

## Upgrade notes

* Lockstep: install Free and Pro 1.8.0 together. Pro will not load against a Free version older than 1.8.0.
* Existing sites keep their current settings. A one-time notice under Career Board lists the safer defaults new sites start with.
* Candidate files move to private storage in the background after the update. Nothing needs to be done.
* No data migration is required.
