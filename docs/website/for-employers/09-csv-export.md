# Bulk Applicant CSV Export

Export selected applications from the admin list table to a UTF-8 CSV
spreadsheet — one row per applicant, ready to drop into Google Sheets,
Excel, or your ATS.

## Where to find it

In `wp-admin`, navigate to **Career Board → Applications**. The list
table now ships a **Bulk actions** dropdown with an **Export selected
to CSV** option.

## How to use it

1. Filter / search the list down to the applications you want
   (status filters, job filters, date ranges all work).
2. Tick the row checkboxes (or the column-header checkbox to select
   the whole filtered set).
3. Open the **Bulk actions** dropdown, choose **Export selected to
   CSV**, click **Apply**.
4. The browser downloads `wcb-applications-YYYY-MM-DD.csv`.

## Columns in the export

| Column | Source |
|---|---|
| `applicant_name` | Application post title |
| `applicant_email` | Application meta (or candidate user email) |
| `job_title` | Linked `wcb_job` post title |
| `job_id` | Numeric ID for joining back to the jobs table |
| `status` | Application status slug (`submitted`, `reviewing`, `shortlisted`, `rejected`, `wcb_closed`, `wcb_expired`) |
| `applied_at` | ISO 8601 timestamp |
| `cover_letter` | Plain-text version of the cover letter (multi-line preserved with `\n`) |
| `resume_url` | Direct link to the uploaded resume file |

If you've added custom application fields via the
[`wcb_application_form_fields_groups`](../admin-guide/12-custom-fields.md)
filter, those fields automatically appear as additional columns.

## Encoding

UTF-8 with a BOM so Excel renders non-ASCII names correctly without
manual import-wizard configuration. Multi-line cover letters preserve
newlines using standard CSV quoted-string semantics.

## Permissions

Only users with the `wcb_manage_applications` ability can export.
Site admins always have it; employers can be granted via the
Abilities API.
