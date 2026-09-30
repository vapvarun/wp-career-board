# Review Applications

The **Applications** view in the Employer Dashboard lets you work through the applicants for each of your jobs. You pick a job, then review its applicants in either a List or a Board (Kanban) view and update each applicant's status.

![Employer Dashboard - Applications View](../images/employer-dashboard-applications.png)

## Accessing applications

1. Open the **Employer Dashboard**
2. Click **Applications** in the sidebar (under the HIRING section). Its badge shows your total applicant count.
3. Select a job from the job selector at the top. Until you select a job, the view prompts you to choose one.

## List view vs board view

A **List / Board** toggle sits above the applicants:

- **List** - a split panel: applicants on the left, the selected applicant's full detail (cover letter, resume, status) on the right.
- **Board** - a Kanban board with one column per status (Submitted, Reviewing, Shortlisted, Hired, Rejected). Drag a card to another column, or use the **Move to** menu on the card, to change the status. Cards are focusable: press Enter or Space (or click) to open the applicant's details. A note under the board counts applications that are not on it (closed, withdrawn or removed). The board, the list, and the status emails all stay in sync.

Both views are included in the free version.

## What you see per applicant

Each applicant row or card shows:

- **Applicant name and initials avatar**
- **Email address** (in the detail panel)
- **Which job they applied to**
- **Application date**
- **Current status** - see statuses below

## Filtering by status

Status filter pills above the list narrow the applicants for the selected job: **All**, **Submitted**, **Reviewing**, **Shortlisted**, **Rejected**, **Hired**. Each pill shows a live count for the whole job, not just the applicants loaded so far.

The dashboard loads 50 applicants at a time. Click **Load more applicants** at the end of the list for the next 50.

## Application statuses

| Status | When to use |
|---|---|
| **Submitted** | Application received - not yet reviewed |
| **Reviewing** | You are actively reviewing this candidate |
| **Shortlisted** | Candidate is worth moving forward |
| **Rejected** | No longer considering this applicant |
| **Hired** | Offer accepted - position filled |
| **Withdrawn** | The candidate withdrew. Read-only: you cannot change it |
| **Closed** | You closed the job before deciding on this applicant. Read-only |

Candidates see **Rejected** as "Not selected" and **Closed** as "Position closed". Withdrawn, Closed and removed-job applications are final. Only reopening the job gives Closed applicants back their earlier status.

## Updating application status

In **List view**, change an applicant's status from the status control in the detail panel. In **Board view**, drag the card to a different column or pick a status in its **Move to** menu. Either way the change is saved immediately with no page reload, and the candidate is notified ("Status updated. The candidate has been notified."). Setting the status they already have changes nothing and sends nothing ("No change. The candidate was not notified.").

Setting **Rejected** sends the candidate a respectful "not selected" email instead of the generic status email. Guests who applied with an email address are notified too. If an applicant left no email address you see "This applicant left no email address, so they were not notified."

## Rating and private notes

Open an applicant to find **Your rating and notes**. Click a star to rate from 1 to 5, and click the same star again to clear it. Add notes for your hiring team below; you can delete your own notes. Only the job's employer and site staff see ratings and notes. Candidates never do, and they are removed if the application is later anonymised.

## Export applicants to CSV

Click **Export CSV** above the applicants to download the selected job's applicants as a spreadsheet. See [Applicant CSV Export](./09-csv-export.md).

## Ranking applicants by AI fit (Pro)

When WP Career Board Pro is active and an AI provider is configured, a **Rank by AI fit** button appears above the applicant list. It scores each applicant against the job, sorts the list best-first, shows a fit-score badge on each applicant, and surfaces a one-line TL;DR summary plus the reasoning in the detail panel. Without Pro and a provider this button does not appear.

> **With WP Career Board Pro:** the fixed five-status system is replaced by a fully customizable stage pipeline (for example Screening - Interview - Offer - Hired/Rejected). The free List and Board views still apply; Pro lets you define the stages those columns represent. See [Application Pipeline](./06-application-pipeline.md).

## Reviewing the resume

The detail panel shows the applicant's cover letter, their answers to your screening questions, and, when a resume was attached, **View Resume** and **Download Resume** links. Resume files are private: only the candidate, site staff and the employer the candidate applied to can open them. If the applicant is a registered candidate with a public profile, you can also open their full profile to read their experience and education on the site.

## Contacting applicants

Use the applicant's email address to reach out from your mail client. All communication happens outside the plugin - WP Career Board does not have a built-in messaging system in the free version.

## When candidates withdraw

If a candidate withdraws, you get an email and the application stays in your list with a **Withdrawn** badge instead of a status picker. You cannot change its status. The candidate may apply again later, which shows up as a new application.

## What employers cannot do

A member who posts jobs cannot apply to jobs, and nobody can apply to their own job.
