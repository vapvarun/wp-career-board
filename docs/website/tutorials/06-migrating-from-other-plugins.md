# Migrating from another job board plugin

You can move jobs and applications from WP Job Manager into WP Career Board from the Import screen. For other plugins, Pro can import jobs from a CSV file. This page shows the steps.

## Before you migrate

1. **Decide how to cut over.** You can deactivate the old plugin as soon as Career Board is ready, or run both for a while and migrate in stages. Career Board needs the old plugin active while you import, so its data stays readable.
2. **Test on a staging copy first.** Run the import on a copy of your site before you run it on the live site.
3. **Plan for new addresses.** Career Board serves each job at `/jobs/{slug}/`, and the slug is made from the job's title. Addresses from your old plugin do not carry over, so plan redirects (see below).

## Import from WP Job Manager

Free includes importers for WP Job Manager. You need WP Career Board and WP Job Manager both active.

1. Go to **Career Board > Settings > Import**. Each importer is a card that shows whether the source plugin is active, how many records were found, how many are already imported and how many remain.
2. On the **WP Job Manager > Jobs** card, review the preview, then click **Import All Jobs**. The importer carries over:
   - Title and description
   - Location
   - Salary, currency and pay period
   - Deadline or duration
   - Featured flag and remote flag
   - Application email or URL
   - Company pages: name, website, tagline, Twitter and logo. A job is matched to an existing company by website or name, and a new company page is created if there is no match.
   - Categories, job types and tags
   - Filled jobs are imported as Closed, and expired jobs as Expired.
3. On the **WP Job Manager Applications > Applications** card, click **Import All Applications**. Import the jobs first. This moves applications onto the imported jobs with their status, message, candidate and attached file links. No emails are sent.
4. With Pro active, the **WP Job Manager Resumes > Resumes** card imports resumes.

Each migration is safe to run more than once. Records that were already imported are skipped.

The same jobs import is available from WP-CLI:

```
wp wcb migrate wpjm --dry-run
wp wcb migrate wpjm
```

Use `--dry-run` first to see what would be imported. Add `--limit` and `--offset` to import in parts.

## Import from other plugins

Free has no importer for other plugins. With Pro, the **CSV > Jobs** card on the same Import screen imports jobs from a spreadsheet. See the Pro docs page **Migration and CSV import** for the columns and steps.

## Keep your old addresses working

Set up a permanent (301) redirect from your old job address pattern to `/jobs/`. Use a redirects plugin such as Redirection, or a rule in your web server. After the import, check that a sample of old addresses reach the right jobs. Because the new slug comes from the job title, jobs whose titles were edited in the old plugin can end up at a different slug than you expect.

## Test the migration

1. Open `/find-jobs/` and confirm the imported jobs appear.
2. Check a sample of jobs for the title, description formatting and company.
3. Apply to a job as a candidate and check the employer receives it.
4. Test some old addresses to confirm the redirects work.
5. Tell your employers and candidates about the change before you switch over.

## After migration

- Deactivate the old plugin when you are satisfied.
- Career Board has its own roles: Employer, Candidate and Job Moderator. Give the Employer role to the people who post jobs, so they can use the Employer Dashboard.
- Career Board uses its own email templates. Review them under **Career Board > Settings > Emails**.
- The importer carries over only the fields listed above. Recreate anything else, such as extra custom fields, by hand. Pro's field builder can add custom fields. See the Pro docs page **Field builder**.

## Where to go next

- [Your first day as a site owner](01-first-day-as-site-owner.md) - set up Career Board before importing.
- [Employer end to end](02-employer-end-to-end.md) - the flow your employers will use.
