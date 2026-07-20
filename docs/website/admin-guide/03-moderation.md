# Moderation

Moderation controls whether jobs need admin approval before they go live. It prevents spam and low-quality listings on your board.

## How Moderation Works

When **Auto-Publish Jobs** is turned **off** (the default), every job submitted by an employer goes to a **Pending** state and must be approved by an admin before it appears on the job board.

When **Auto-Publish Jobs** is turned **on**, submitted jobs go live immediately without review.

To toggle moderation: **WP Career Board → Settings → Job Listings → Auto-Publish Jobs**

## Reviewing Pending Jobs

1. Go to **WP Career Board → Jobs** in wp-admin
2. Click the **Pending** filter at the top of the list
3. Click any job title to open the full edit screen and review the content

![Admin Jobs - Pending Filter](../images/admin-jobs-pending.png)

## Approving a Job

**Quick approval (from the list):**
1. Hover over the job in the list
2. Click **Approve** under the title

**Full review (from the edit screen):**
1. Open the job in the wp-admin editor
2. Review all details
3. Change the status to **Published** in the Post Status panel
4. Click **Update**

When a job is approved, the employer receives an email notification.

## Rejecting a Job

1. Open the job in the wp-admin editor
2. Change the status to **Draft** or **Trash**
3. Optionally email the employer with a reason (done manually outside the plugin)

## Managing Existing Jobs

Admins have full control over all jobs from **WP Career Board → Jobs**:

- **Edit** any job (correct errors, add missing info)
- **Close** a job that is running too long
- **Delete** spam or low-quality listings

## Reported Jobs (Flagged) {#reported-jobs-flagged}

Any logged-in user can report a published job from its single page
("Report this job", with a reason). Reports are stored on the job and
deduplicated per user, so one person reporting the same job repeatedly
counts once.

Moderators and admins review reports from **WP Career Board → Jobs**:

1. Click the **Flagged** view at the top of the list (it appears, with
   a count, only when one or more jobs have open report flags).
2. The **Flags** column shows how many open reports each job has.
3. Resolve a flagged job with the row or bulk actions (the row action
   is **Dismiss flag**; the bulk action is **Dismiss flags**):
   - **Dismiss flag(s)** - clears the open reports and leaves the job
     published (the report was not actionable).
   - **Unpublish** - takes the job down and clears its reports.

Resolving flags requires the **Moderate Jobs** capability
(`wcb_moderate_jobs`), the same gate as approving and rejecting jobs.

## Member Moderation (Reporting, Blocking, Suspending)

New in 1.7.0 - moderation now covers members, not just job listings.
This layer is separate from Reported Jobs above: it deals with a
member's account and behaviour, not a single listing.

### Members reporting members

Any logged-in member can report another member's profile for a reason
such as spam, scam, a fake profile, harassment, or offensive content.
Reports are deduplicated per reporter (reporting the same member twice
counts once) and accumulate on the reported member's account.

Open reports surface to admins as a warning badge (with the report
count) on the **Career Board → Candidates** screen, next to that
member's Active/Suspended status.

### Members blocking members {#members-blocking-members}

Any logged-in member can block another member directly from their
account. Blocking is mutual: once either side blocks the other,
neither one sees the other's job listings or single job pages -
enforced server-side on both the REST API and the server-rendered
frontend, not just hidden in the browser. A member can review and undo
their own blocks from their blocked-members list in the app.

### Site owners suspending candidates

Admins and moderators can suspend a candidate account directly from
**Career Board → Candidates**:

1. Go to **Career Board → Candidates**.
2. Hover a candidate's row and click **Suspend** (or select multiple
   rows and choose **Suspend** from the Bulk Actions dropdown).
3. A suspended candidate loses every Career Board ability immediately
   - applying, saving jobs, and any other write action - including
   from a mobile client using an Application Password.

Click **Restore** (or the **Restore** bulk action) to lift the
suspension. This uses the same suspend/restore mechanism already used
for employers on the **Career Board → Employers** screen.

## Admin Notifications

Admins receive a **New Job Pending Review** email when an employer submits a job for approval. This is the only admin-facing email notification. Notification content can be customized in **Settings → Emails**.
