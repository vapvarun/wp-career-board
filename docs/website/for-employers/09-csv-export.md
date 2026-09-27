# Bulk Applicant CSV Export

Export applications from the admin list table to a UTF-8 CSV
spreadsheet - one row per applicant, ready to drop into Google Sheets,
Excel, or your ATS. Export everything your filters match, or only the
rows you tick.

## Where to find it

In `wp-admin`, navigate to **Career Board → Applications**. The list
table ships a **Bulk actions** dropdown with an **Export to CSV**
option.

## How to use it

**Everything that matches (any size):**

1. Narrow the list: a status tab, a search, or click a job title in the
   **Job** column to show only that job's applicants (a "Job: ..." chip
   appears; click its × to clear it).
2. Click **Export all matching to CSV** above the table. Every matching
   application is exported, across all pages, not just the 20 on screen.

**Only some rows:**

1. Tick the row checkboxes.
2. Choose **Export to CSV** from **Bulk actions** and click **Apply**.

The browser downloads `wcb-applications-YYYY-MM-DD-HHMMSS.csv`.

## Columns in the export

The CSV has these columns, in this order:

| Column | Source |
|---|---|
| `ID` | Application post ID |
| `Job ID` | Numeric ID of the linked job, for joining back to the jobs table |
| `Job Title` | Linked `wcb_job` post title |
| `Applicant Name` | Candidate display name, or the guest name for guest applications |
| `Applicant Email` | Candidate user email, or the guest email |
| `Status` | Application status as shown in admin (Submitted, Reviewing, Shortlisted, Rejected, Hired, Withdrawn, Job removed) |
| `Submitted` | Application post date |
| `Cover Letter` | The cover letter text (multi-line preserved using CSV quoted-string semantics) |
| `Resume URL` | Direct link to the uploaded resume file, when one was attached |

## Encoding

UTF-8 with a BOM so Excel renders non-ASCII names correctly without
manual import-wizard configuration. Multi-line cover letters preserve
newlines using standard CSV quoted-string semantics.

Any cell that starts with `=`, `+`, `-` or `@` is written with a leading
apostrophe, so a cover letter such as `=HYPERLINK(...)` opens as text
instead of running as a spreadsheet formula.

## Permissions

The export is available on the admin Applications screen, and each row
is included only for applications the current user can edit (the
standard `edit_post` capability check per application). Site admins can
export all; an employer reaching the screen exports only their own
applications.
