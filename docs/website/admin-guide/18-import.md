# Import from WP Job Manager

You can move your jobs, applications and resumes from WP Job Manager into WP Career Board. Go to **Career Board > Settings > Import**.

The importer copies data. It does not change or delete anything in WP Job Manager. It runs in batches with a progress bar, so large imports do not time out.

## Safe to run again

Each imported record remembers which WP Job Manager post it came from. If you run an import again, records that are already imported are skipped, not duplicated or overwritten.

## Import jobs

1. Go to **Career Board > Settings > Import**.
2. Check the WP Job Manager Jobs card. It shows how many jobs were found and how many are already imported. It also previews how many filled jobs will be closed, how many company pages will be created, and how many applications are waiting.
3. Click **Import All Jobs**.
4. Wait for the progress bar to finish.

What comes across:

- Title, description, categories, job types and tags.
- Location, salary, currency and pay period (hourly, monthly or yearly). Other pay periods are not carried over.
- Deadline (from the expiry date, or the job duration), featured flag and remote flag.
- The application email or URL.
- A company page built from the company name, website, tagline, Twitter and logo. A company with the same website or name is reused, so a company's jobs share one page. An employer with no company is linked to it.
- Filled jobs are imported as Closed. Expired jobs are imported as Expired. Jobs that are neither live nor expired are imported as Pending.

WP Job Manager can be turned off after the import.

From the command line, `wp wcb migrate wpjm --dry-run` prints the preview without writing anything, and `wp wcb migrate wpjm` runs the import.

## Import applications

If the WP Job Manager Applications add-on is active, a second card moves its applications onto the imported jobs. Import the jobs first.

| WP Job Manager status | WP Career Board status |
|---|---|
| New | Submitted |
| Interviewed | Shortlisted |
| Offer | Shortlisted |
| Hired | Hired |
| Rejected | Rejected |
| Archived | Rejected |

- The application goes to the candidate's account when the email matches a member. Otherwise it becomes a guest application.
- The message becomes the cover letter.
- Attached files are listed as links in the cover letter. The files stay where WP Job Manager stored them.
- No emails are sent during the import.

Command line: `wp wcb migrate wpjm-applications [--dry-run]`.

## Import resumes

The WP Job Manager Resumes card is available when WP Career Board Pro is active. It imports candidate name and bio, professional title, contact email, location, photo, video URL, resume file, featured flag, expiry date, education, work experience, links and resume categories.

Command line: `wp wcb migrate wpjm-resumes [--dry-run]`.

## After importing

1. Open **Career Board > Jobs** and check the statuses and key fields.
2. Open **Career Board > Settings > Pages** and confirm the page assignments.
3. Go to **Settings > Permalinks** in WordPress and click **Save Changes**.
4. Review categories and job types under **Career Board > Job Categories** and **Job Types**.

## Limits

- Custom fields added by WP Job Manager extensions are not mapped. Re-enter them by hand.
- The importer does not delete WP Job Manager data. Remove WP Job Manager yourself once you are happy with the result.
