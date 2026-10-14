# Edit a job in wp-admin

You can create or change any job from **Career Board > Jobs**. The **Job Details** box on the edit screen holds the fields below. Administrators use it to create or change jobs; employers use the Employer Dashboard. A Job Moderator works from the Jobs queue to approve or reject, as described in [Moderation](03-moderation.md).

## Job Details fields

| Field | What it does |
|---|---|
| **Company** | Pick the company from the list. When no company exists yet, a text box takes the name instead |
| **Currency**, **Per**, **Salary Min**, **Salary Max** | The pay range. Leave both amounts blank to hide the salary. Amounts are saved as typed, with no rounding |
| **Application Deadline** | The last day the job takes applications. Leave it empty to use the board's listing length, **Default listing length (days)** under **Settings > Jobs** |
| **No expiry (stays open until closed)** | Tick for an evergreen job. The deadline is cleared and the job never expires by date |
| **Apply URL** | Optional link to apply on your own site. The public page then shows **Apply on Company Site** instead of the on-site form |
| **Apply Email** | Optional. Shown on the public page as an **Apply Email** link |
| **Remote-friendly position** | Marks the job as remote. A job whose location is Remote is also treated as remote everywhere |
| **Featured listing** | Lists the job first until the featured period ends. See [Featured Listing Expiry](08-featured-expiry.md) |

Leaving the deadline empty without ticking **No expiry** fills in the board's default deadline when you save. A job saved this way expires on schedule, which is why an admin-created job always ends unless you tick the box.

Employers cannot pick a deadline later than the board's listing length; they can only shorten it. Users who can moderate jobs are not capped.

## Review a candidate's resume

The **Applications** list (as a row action) and the application screen (a button on the applicant card) show **View resume** when the candidate has a built resume (a Pro feature). On a Free-only site the link is hidden. It opens the resume attached to that application, or the candidate's latest published resume. The **Candidates** list shows the same link. Resume files and uploaded documents are private and download as files; they never open in the browser tab. See [GDPR and privacy](04-gdpr.md).
