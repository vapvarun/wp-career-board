# Application editor

You can review one applicant in a single admin screen: their details, cover letter, resume, answers, status and status history. You can change the status from the same screen.

## Open an application

Go to **Career Board > Applications** and click an application. Click a job title in the list to show only that job's applicants.

## What the screen shows

| Section | What it shows |
|---|---|
| Applicant card | Avatar, name, email, applied date, the job, and a link to the candidate profile for members |
| Cover letter | The cover letter text |
| Application answers | Answers to any [custom application questions](12-custom-fields.md) |
| Resume | The attached resume, with **Open** and **Download** links |
| Status and history | The current status and every change with date, from and to status, and who made it |
| Change status | A status list and a **Save** button |
| Quick actions | **Shortlist**, **Mark Hired**, **Reject** and **Message** |

## Change the status

Pick a status and click **Save**. You can choose Submitted, Reviewing, Shortlisted, Rejected or Hired. The picker is not shown for withdrawn applications or applications whose job was closed or removed.

Each change is written to the status history once, and setting a status the application already has does nothing.

## Quick actions

**Shortlist**, **Mark Hired** and **Reject** set the status in one click, the same as the picker.

**Message** opens your mail program with a message to the applicant. It is hidden when the applicant left no email.

## Emails sent on a status change

- Reviewing, Shortlisted and Hired send the candidate the "Application Status Changed" email.
- Rejected sends the candidate the "Application Not Selected" email.
- Withdrawn sends nothing to the candidate.

Edit these under [Email notifications](02-email-notifications.md).

## Bulk actions

On the Applications list, select rows and choose:

- Mark as Reviewing, Shortlisted, Rejected or Hired
- Export to CSV - see [CSV export](../for-employers/09-csv-export.md)
- Move to Trash

In the Trash view, the bulk actions are **Restore** and **Delete Permanently**.

## Embed the parts elsewhere

Each section is also a widget you can place with the `[wcb_widget]` shortcode:

```
[wcb_widget id="application/applicant-card" application_id="987"]
[wcb_widget id="application/cover-letter" application_id="987"]
[wcb_widget id="application/custom-answers" application_id="987"]
[wcb_widget id="application/resume-preview" application_id="987"]
[wcb_widget id="application/status-timeline" application_id="987"]
[wcb_widget id="application/status-changer" application_id="987"]
[wcb_widget id="application/quick-actions" application_id="987"]
```

The status changer and quick actions only render for users who have the `wcb_view_applications` capability. Everyone else sees nothing.

## Who can use it

The Employer role has `wcb_view_applications`. See [Capabilities and roles](14-capabilities-and-roles.md).

## See also

- [Review applications](../for-employers/04-review-applications.md)
- [Email notifications](02-email-notifications.md)
- [Custom fields](12-custom-fields.md)
