# Applicant CSV export

You can download applications as a UTF-8 CSV spreadsheet, one row per applicant, ready for Google Sheets, Excel or your ATS. Employers export from their dashboard. Site admins can also export from wp-admin.

## Employers: export from your dashboard

1. Open **Employer Dashboard > Applications** and select a job.
2. Click **Export CSV** above the applicants.

The browser downloads every applicant for that job, not just the 50 loaded on screen. Only the job's owner and site staff can export it.

## Admins: export from wp-admin

Go to **Career Board > Applications** in wp-admin.

**Export everything that matches:**

1. Narrow the list with a status tab, a search, or by clicking a job title in the **Job** column. A "Job: ..." chip appears. Click its x to clear it.
2. Click **Export all matching to CSV** above the table. Every matching application is exported across all pages, not just the rows on screen.

**Export only some rows:**

1. Tick the row checkboxes.
2. Choose **Export to CSV** from **Bulk actions** and click **Apply**.

The browser downloads a file named `wcb-applications-YYYY-MM-DD-HHMMSS.csv`.

## Columns

Employers and admins get the same columns, in this order.

| Column | What it holds |
|---|---|
| `ID` | The application ID |
| `Job ID` | The ID of the job, for matching back to your jobs |
| `Job Title` | The job's title |
| `Applicant Name` | The candidate's display name, or the guest name for a guest application |
| `Applicant Email` | The candidate's email, or the guest email |
| `Status` | The application status: Submitted, Reviewing, Shortlisted, Rejected, Hired, Withdrawn, Closed or Job removed |
| `Submitted` | The date and time the application was submitted |
| `Cover Letter` | The cover letter text. Line breaks are kept |
| `Resume URL` | A link to the uploaded resume file, when one was attached. The link works only for people allowed to open the file |
| `Screening Answers` | The applicant's answers to the job's screening questions, one `Question: answer` per line |

## Encoding

The file is UTF-8 with a byte-order mark, so Excel shows non-ASCII names correctly without the import wizard.

A cell that starts with `=`, `+`, `-` or `@` is written with a leading apostrophe. A cover letter such as `=HYPERLINK(...)` therefore opens as text and does not run as a spreadsheet formula.

## Permissions

The wp-admin export includes only applications the current user can edit. Admins can export all applications.
