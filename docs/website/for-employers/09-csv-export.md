# Bulk Applicant CSV Export

Export selected applications from the admin list table to a UTF-8 CSV
spreadsheet - one row per applicant, ready to drop into Google Sheets,
Excel, or your ATS.

## Where to find it

In `wp-admin`, navigate to **Career Board → Applications**. The list
table ships a **Bulk actions** dropdown with an **Export to CSV**
option.

## How to use it

1. Filter / search the list down to the applications you want
   (the status and job filters on the list table all work).
2. Tick the row checkboxes (or the column-header checkbox to select
   the whole visible set).
3. Open the **Bulk actions** dropdown, choose **Export to CSV**,
   click **Apply**.
4. The browser downloads `wcb-applications-YYYY-MM-DD-HHMMSS.csv`.

## Columns in the export

The CSV has these columns, in this order:

| Column | Source |
|---|---|
| `ID` | Application post ID |
| `Job ID` | Numeric ID of the linked job, for joining back to the jobs table |
| `Job Title` | Linked `wcb_job` post title |
| `Applicant Name` | Candidate display name, or the guest name for guest applications |
| `Applicant Email` | Candidate user email, or the guest email |
| `Status` | Application status slug (`submitted`, `reviewing`, `shortlisted`, `rejected`, `hired`) |
| `Submitted` | Application post date |
| `Cover Letter` | The cover letter text (multi-line preserved using CSV quoted-string semantics) |
| `Resume URL` | Direct link to the uploaded resume file, when one was attached |

## Encoding

UTF-8 with a BOM so Excel renders non-ASCII names correctly without
manual import-wizard configuration. Multi-line cover letters preserve
newlines using standard CSV quoted-string semantics.

## Permissions

The export is available on the admin Applications screen, and each row
is included only for applications the current user can edit (the
standard `edit_post` capability check per application). Site admins can
export all; an employer reaching the screen exports only their own
applications.
