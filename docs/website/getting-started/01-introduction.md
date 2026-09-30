# Introduction to WP Career Board

WP Career Board turns a WordPress site into a job board. Employers post jobs, candidates search and apply, and you review and manage everything from wp-admin. Search, filters and applying update in place without a page reload.

## What you can do

**As a site owner:**
- Control jobs, applications, employers and candidates from wp-admin.
- Hold new jobs in a moderation queue until you approve them.
- Set the emails sent for key events.
- Export or erase a person's data with the GDPR tools.

**As an employer:**
- Post jobs with a guided multi-step form.
- Manage your jobs and applications from the employer dashboard.
- Publish a company profile that candidates can see.

**As a candidate:**
- Search and filter jobs by keyword, category, job type, location and experience level.
- Save jobs to apply later.
- Track your applications in the candidate dashboard.
- Apply as any logged-in member. No Candidate role is needed unless you turn on **Require Candidate Role** in **Career Board > Settings > Sign-ups**.

## How it is built

- Every page is a block, and every block also works as a shortcode for page builders and the classic editor. See [Adding blocks](./04-adding-blocks.md).
- Front-end updates use the WordPress Interactivity API instead of jQuery.

## Requirements

- WordPress 6.9 or higher
- PHP 8.1 or higher

## Free and Pro

WP Career Board is a complete job board on its own. WP Career Board Pro is an add-on that adds:

- Resume builder
- Field builder for custom fields
- Application pipeline with custom hiring stages
- Credit system to charge employers for job posts
- Multiple job boards
- Job alerts
- AI tools for descriptions, applicant ranking, recommended jobs and cover letters (you add your own AI provider key)
- Job map
- Job feed in XML format for job aggregators
- CSV job import
- Analytics
- Installable web app (PWA)

See [Pro features](../pro-features/01-job-map.md) and [Installing Pro](./06-pro-license.md).
