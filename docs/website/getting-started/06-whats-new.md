# What's New in 1.4.3

WP Career Board and WP Career Board Pro ship in lockstep at 1.4.3.
Install both updates together. This page highlights the
customer-facing changes across the 1.3.0 and 1.4.x cycles. For the
full line-by-line history, see the changelog in `readme.txt`.

## 1.4.3

* Fix - The Job Listings block no longer emits PHP warnings when it
  renders with no matching jobs.

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
under **Career Board -> Settings -> Job Listings** (or use the
`wcb_candidate_requires_role` filter) to reserve the candidate
experience for the Candidate role.

### List / Board toggle on the employer dashboard

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

### Account Settings in the dashboard

Candidates and employers can update their display name and email and
change their password directly in the dashboard, instead of being
sent off to wp-login.

![Candidate dashboard overview](../images/candidate-dashboard-overview.png)

### Rejected jobs and Resubmit

Rejected job listings now show as "Rejected" (not "Draft") in the
employer dashboard, with a **Resubmit** action. Resubmitting sends
the job back for admin approval instead of publishing it directly.

### My Jobs and applications fixes

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

* Lockstep: install Free 1.4.3 and Pro 1.4.3 together. The Pro
  dependency check refuses to load against an older Free.
* Versions: the `WCB_VERSION` and `WCBP_VERSION` constants both move
  to `1.4.3`. The stable tag in `readme.txt` matches.
* No data migration is required for the 1.4.x updates.
