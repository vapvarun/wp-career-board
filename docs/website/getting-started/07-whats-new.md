# What's new

You can use this page to see recent changes that affect how you set up and run your job board. For the full history, see the changelog in `readme.txt`.

## In 1.8.2

* **Post a Job.** The page has a heading, the step bar names the current step on phones, the preview includes the description, and the confirmation offers **View my jobs** and **Post another job**. See [Post a Job](../for-employers/02-post-a-job.md).
* **Location order.** The job form's Location list follows the **Order** you set, and term lists have a sortable **Order** column. See [Categories, types, locations and experience levels](../admin-guide/19-job-terms.md).
* **Admin lists.** Candidates has a **Suspended** view and Employers has a **Banned** view. See [Moderation](../admin-guide/03-moderation.md).
* **Getting started.** The admin checklist ticks **Invite an employer** once an employer exists. See [Setup Wizard](./03-setup-wizard.md).
* **Find Jobs.** The search term stays in the box after a reload or a shared link, and the closing date reads "Apply by".
* **Multi-currency salary.** The salary filter and the **Highest salary** sort keep jobs paid in other currencies. See [Salary range filter](../for-candidates/08-salary-filter.md).
* **Privacy log.** Guest requests and account deletions through WordPress appear in the **Privacy** tab, and email history retention no longer prunes early on sites west of UTC.
* **Job saves.** The API and admin editor reject an empty title and a minimum salary above the maximum, and clamp `per_page` to 100. See [Error codes](../developer-guide/07-error-codes.md).
* **Companies.** Renaming a company updates its jobs and search.
* **Pipeline (Pro).** Moving an application past the first stage sets it to Reviewing. See [Application pipeline](../for-employers/06-application-pipeline.md).
* **Themes and touch.** Link-styled buttons stay un-underlined in Astra, and job page and dashboard controls are at least 40px tall on touch screens.

## In 1.8.1

* **Several companies per employer.** An employer can run more than one company. My Jobs, Applications and Post a Job cover all of them, with a company filter. See [Employer overview](../for-employers/01-overview.md).
* **Several categories in a search.** Find Jobs and the search bar accept more than one job category at a time.
* **Needs attention.** The employer Overview lists applications waiting for review and jobs closing within 7 days, and each figure opens the list it counts.
* **Admins post for free.** Site admins post, move, republish and feature jobs without spending credits.
* **Post a Job.** The description has a formatting toolbar, and the form looks the same on the dashboard and on any page you place it.
* **Cleaner dashboards.** Each row shows one main action with the rest in a menu, empty lists say what to do next, and the applicant panel shows empty stars and "Not rated" until you rate someone.
* **Credits only where they matter.** The Credits item shows when a board would charge the employer or they hold credits.
* **Brand colour.** Links and labels in your brand colour stay readable whatever colour you pick, in light and dark mode.
* **Employer deadlines.** Employers pick their own application deadline when they post, up to the board's listing length. See [Post a Job](../for-employers/02-post-a-job.md).
* **Admin job editor.** The **Job Details** box has **Apply URL** and **Apply Email** fields and a **No expiry** option. See [Edit a job in wp-admin](../admin-guide/20-edit-a-job-in-admin.md).
* **Term order.** Categories, job types, locations, experience levels and tags have an **Order** field, and child categories and locations nest under their parent. See [Categories, types, locations and experience levels](../admin-guide/19-job-terms.md).
* **Load more is a link.** **Load more jobs** goes to the next page of results (`/find-jobs/page/2/`), so job pages work without JavaScript and search engines can reach every job. New sites show 12 jobs per page.
* **Filters in the address bar.** Shared Find Jobs links open with the same filters, and the Back button restores them.
* **View resume.** A **View resume** link shows on the admin Applications list, the application screen and the Candidates list.
* **Guests with an account.** A guest who types the email of an existing account is asked to sign in instead of creating an unlinked application. Earlier guest applications move to an account only after it confirms its email.
* **Private files.** Resumes and other documents always download as files; private profiles and private job details answer "not found" to everyone else.
* **Remote jobs.** A job located Remote is treated as remote on the job page, in filters, search, feeds and Google for Jobs.
* **Saved lists.** Saved Companies show the company logo and Saved Resumes show the candidate's photo.
* **Deleting a user.** Their jobs close and their companies return to draft unless you reassign them.

## Earlier changes (1.8.0 and before)

* **Setup wizard.** It has a stepper, an **Exit setup** button, and steps for Pages, Sign-ups, Jobs, Emails, Spam Protection and Sample Data.
* **Dashboards.** The employer and candidate dashboards have a sidebar with an Overview item, grouped links and a **Post a Job** button on the employer side.
* **Applications board.** Every card on the Board view has a **Move to** menu, so you do not have to drag cards.
* **Ratings and notes.** Employers can rate an applicant and keep private notes on an application.
* **Applicant export.** Employers can export applicants to CSV from the employer dashboard, and administrators can export from the Applications screen.
* **Job end dates.** Jobs end at their deadline when **End jobs at their deadline** is on under **Career Board > Settings > Jobs**. Employers get an email 3 days before a job ends.
* **Google for Jobs.** Under **Career Board > Settings > Jobs** you can set **Require a location**, **Default country** and **Social sharing tags**.
* **Job order.** **Default order** under **Career Board > Settings > Jobs** sets how listings are sorted.
* **Emails.** There are emails for Welcome, Account deletion requested, cancelled and completed, Application not selected, Application withdrawn, Report received and Confirm your email address. Members can turn off the optional deadline reminder and job ending soon emails in the Settings section of their dashboard.
* **Closed jobs.** When a job closes, undecided applicants move to Position closed.
* **Personal data.** Deleting an account or erasing personal data keeps the employer's application record and shows the candidate as "Deleted candidate".
* **Brand.** **Career Board > Settings > Brand** sets your brand colour and logo.
* **Advanced settings.** **Keep Email History (days)** and **Remove Data on Delete** are under **Career Board > Settings > Advanced**. Deleting the plugin keeps your data unless **Remove Data on Delete** is on.

## Upgrading

Install WP Career Board and WP Career Board Pro updates together. Pro will not run with an older Free version and shows an admin notice.
