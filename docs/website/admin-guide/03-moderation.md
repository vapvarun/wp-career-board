# Moderation

You can review jobs before they go live, act on reported jobs and members, and suspend or ban accounts that break your rules.

## How moderation works

When **Auto-Publish Jobs** is turned **off** (the default), every job submitted by an employer goes to a **Pending** state and must be approved by an admin before it appears on the job board.

When **Auto-Publish Jobs** is turned **on**, submitted jobs go live immediately without review.

To toggle moderation: **WP Career Board → Settings → Jobs → Auto-Publish Jobs**

## Reviewing pending jobs

1. Go to **WP Career Board → Jobs** in wp-admin
2. Click the **Pending Review** filter at the top of the list
3. Click any job title to open the full edit screen and review the content

## Approving a job

**Quick approval (from the list):**
1. Hover over the job in the list
2. Click **Approve** under the title and confirm

You can also tick several pending jobs and choose the **Approve** bulk action.

**Full review (from the edit screen):**
1. Open the job in the wp-admin editor
2. Review all details
3. Change the status to **Published** in the Post Status panel
4. Click **Update**

When a job is approved, the employer receives an email notification.

## Rejecting a job

1. Hover over the pending job in the list.
2. Click **Reject**.
3. Optionally type a reason and confirm.

The job moves to Draft and the employer gets the **Job Rejected** email, which includes your reason if you gave one.

## Managing existing jobs

Admins have full control over all jobs from **WP Career Board → Jobs**:

- **Edit** any job (correct errors, add missing info)
- **Trash** or **Delete Permanently** spam or low-quality listings (administrators only). Deleting a job with many applicants finishes quickly: its open applications are marked **Job removed** in background batches of 200, and candidates are still told, using the job title saved with their application. The applications stay in the candidate's history

## Reported jobs (flagged) {#reported-jobs-flagged}

Any logged-in member who does not own the job can report a published job from
its page ("Report this job", with a reason). Reports are stored on the job and
deduplicated per user, so one person reporting the same job repeatedly
counts once.

Moderators and admins review reports from **WP Career Board → Jobs**:

1. Click the **Flagged** view at the top of the list (it appears, with
   a count, only when one or more jobs have open report flags).
2. The **Flags** column shows how many open reports each job has.
3. Resolve a flagged job with the row or bulk actions (the row action
   is **Dismiss flag**; the bulk action is **Dismiss flags**):
   - **Dismiss flag(s)** - clears the open reports and leaves the job
     published (the report was not actionable). A job that was hidden
     by reports goes back on the site.
   - **Unpublish** - takes the job down and clears its reports.

**Auto-hide.** When a set number of different members report the same
job (3 by default), it is taken off the site as **Pending** until you
review it. Only reports from members with standing count toward the
number: an account at least a week old, or a member who has applied to a
job or has a published job. Every report is still recorded so you can
see it; the Jobs list shows it as **Hidden: reported**. Change the
number, or set 0 to turn this off, under **Settings → Jobs → Hide a job
after this many reports**. You are emailed on a job's first report and
again when it is hidden, not on every report.

Resolving flags requires the **Moderate Jobs** capability
(`wcb_moderate_jobs`), the same gate as approving and rejecting jobs.

## Member moderation (reporting, blocking, suspending)

You can also act on members, not just listings. This is separate from reported jobs above.

### Members reporting members

A logged-in member can report another member for spam or advertisement, a scam, a fake or impersonating profile, harassment, or offensive content. Reporting the same member twice counts once.

Open reports show as a warning badge with the report count on **Career Board → Candidates** and **Career Board → Employers**. Each list has a **Reported** view for members with open reports. **Dismiss reports** on the row clears them. You are emailed on a member's first report.

### Members blocking members {#members-blocking-members}

A logged-in member can block another member through the REST API, which the companion app uses. Blocking is mutual: once either side blocks the other, neither one sees the other's job listings or single job pages. A member can list and undo their own blocks through the same API.

### Suspending candidates

Administrators can suspend a candidate from **Career Board → Candidates**:

1. Go to **Career Board → Candidates**.
2. Hover a candidate's row and click **Suspend**, or select several rows and choose **Suspend** from the bulk actions.

A suspended candidate loses Career Board abilities such as applying and saving jobs. Click **Restore** (or the **Restore** bulk action) to lift the suspension.

The **Delete** bulk action opens WordPress's own Delete Users screen to confirm. Deleting a candidate removes their personal data and keeps their past applications for employers as "Deleted candidate".

### Banning employers

Administrators can use **Ban** on **Career Board → Employers** to stop an employer posting and take their live and pending jobs and their company page off the site. **Unban** puts back exactly what was live or pending. A job you edited, approved or deleted while the ban was on is left as you set it. Suspending a candidate hides their public resume the same way. In the Jobs list a hidden job shows as **Hidden: employer banned**.

## Admin notifications

Admins receive a **New Job Pending Review** email when an employer submits a job for approval, and a **Report Received** email when a job or member is first reported (and when a job is hidden by reports). Notification content can be customized in **Settings → Emails**.
