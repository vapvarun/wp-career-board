# What's new

You can use this page to see recent changes that affect how you set up and run your job board. For the full history, see the changelog in `readme.txt`.

## In 1.8.4

* **Sign-up on multisite.** Candidate and employer sign-up follows the network registration setting, so a subsite whose network closes registration refuses sign-up.
* **Job forms.** Logging out clears an unsent draft. The board list shows every board. A deadline past the board maximum is moved to the maximum with a notice, and the success screen shows the date that was saved. The single-page form says when a job is held for review. See [Post a Job](../for-employers/02-post-a-job.md).
* **Locations.** A remote job shows one Remote chip, the job form lists one Other choice, and an on-site job with no country is left out of Google for Jobs and flagged in the Jobs list.
* **Apply.** A candidate who has applied before sees "Using your last CV" with a Replace link, so the next application is one click.
* **Setup wizard.** Skip for now on Sign-ups saves the toggle as shown, and the sample data box starts unticked.
* **My Jobs.** **View** is hidden for a job that has no public page yet.
* **My Resumes.** Each row shows whether the resume is Public or Private.
* **Links.** Custom field, company and candidate profile links keep only real web addresses. A bare domain is saved as https, and the form shows an error for anything else.
* **Job page.** Links in Additional details are underlined and use the link colour.
* **Plain permalinks.** The notification bell, the applications export and the resume links work when permalinks are Plain.
* **Deadlines.** A past deadline is refused before anything is saved, in the admin editor as well, so a refused job no longer leaves a Pending job behind.
* **Require a location.** The admin job editor now follows it: a job without a location or Remote is saved as a draft with a notice. The Apply URL field refuses an email address, and the overview Getting started checklist no longer counts the sample jobs as your first job.
* **For developers.** New helpers `Text::web_url()`, `BoardsModule::options_for_employer()` and `JobDeadline::max_days()`. The REST `permalink` is empty for a job without a public page. See [Hooks reference](../developer-guide/02-hooks-reference.md).

## In 1.8.3

* **Times and your timezone.** Every date and time is saved in UTC and shown in the timezone set in **Settings > General**, the same on admin screens, member pages, emails and job pages. Existing dates are converted when you update.
* **Deadlines.** A job stays open until the end of its deadline day in your site's timezone.
* **Setup wizard.** Sample data adds companies and jobs but no user accounts. With Wbcom Demo Installer active, the wizard opens it instead. The wizard can also create a Jobs menu with Find Jobs, Companies and Post a Job. See [Setup Wizard](./03-setup-wizard.md).
* **Job forms.** What an employer types survives a page refresh or an ended login, and **Start over** clears it. See [Post a Job](../for-employers/02-post-a-job.md).
* **Roles.** Becoming an employer or candidate keeps the site's default role, and saving a member on the Edit User screen keeps their Career Board role. See [Capabilities and roles](../admin-guide/14-capabilities-and-roles.md).
* **Cover letters and bios.** Paragraphs and bullet lists are kept after saving.
* **Applying as a guest.** The name and email fields are full width in the apply panel. An application from a deleted member reads "Deleted candidate".
* **Dashboards.** Candidate and employer dashboards open only tabs that exist on your site, so a Pro tab link without Pro lands on Overview.
* **Reign.** On Reign 8.0.9 and later, job, company and dashboard pages show one page heading.
* **Safer updates.** Each WP Career Board and Pro update package is checked against its release signature before it installs. In this release the result is only recorded, and updates install as before.
* **For developers.** wcb/v1 sends every time as ISO 8601 with its offset, and jobs add `deadline_day`. New filters: `wcb_display_timezone` and `wcb_title_owning_blocks`. See [REST API](../developer-guide/03-rest-api.md) and [Hooks reference](../developer-guide/02-hooks-reference.md).

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
