# Post a Job

You can post a job in a few minutes from the **Employer Dashboard**. Click **Post a Job** in the sidebar (under Jobs), the **+ Post a Job** button pinned at the bottom of the sidebar, or **Post Your First Job** on the Overview. The setup wizard also creates a standalone **Post a Job** page (`/post-a-job/`) with the same form.

## Before you post

Log in as a user with the **Employer** role. If you are not logged in, the form asks you to sign in. If you are logged in without the Employer role, it links to the employer registration page. You need a company profile before you can post a new job.

## Post a job step by step

The job form is a 4-step wizard.

### Step 1 - Job Basics

- **Post to Board** - shown only when the site has more than one board.
- **Job Title** - the position name (required).
- **Job Description** - the role, responsibilities and requirements (required). When the site has AI description tools, a **Generate with AI** button appears here.

### Step 2 - Job Details

- **Salary Range** - optional. Pick a currency, enter a minimum and maximum, and choose the period (Year, Month or Hour). Leave it blank to hide the salary from candidates.
- **Remote-friendly position** - tick this for a remote job.
- **Application Deadline** - filled in for you from the board's listing length. You cannot edit it in the form. Ask your site admin if you need a longer listing window.
- **Apply URL** - optional. A full link starting with `http://` or `https://` where candidates apply on your own site. The public job page then shows an **Apply on Company Site** button in place of the on-site apply form.
- **Apply Email** - optional. Shown as an **Apply Email** link on the public job page.

### Step 3 - Classify Your Job

- **Category** - the industry or function.
- **Job Type** and **Experience Level** - pick from the lists your site offers.
- **Location** - pick a location, or choose **Other (enter manually)**. When the site requires a location (the default on new sites), a job needs a location or the Remote option before you can post it.
- **Skills / Tags** - comma-separated. They help candidates find your job by keyword.

### Step 4 - Preview & Submit

Review the details, then click **Post Job**. When you edit an existing job, the button reads **Update Job**.

When the site sells featured placement (Pro), this step also shows a checkbox such as "Feature this job: it lists first for 30 days (2 credits)". See [Your Credit Balance](./10-employer-credit-balance.md#featured-job-upgrades).

## After you submit

- **Moderation on (the default):** you see "Job submitted for review. You'll be notified once it's approved." The job goes live when a moderator approves it.
- **Moderation off:** the job is published straight away and you see "Job posted successfully!" with a link to the listing.

You get an email when a moderator approves or rejects a job. If the site charges credits and your balance is too short when the moderator approves, the job stays Pending and shows **Awaiting payment** in My Jobs until your balance covers it.

## Edit a job

Open **My Jobs** and click **Edit** on the job. The form opens with the current details. Change what you need and click **Update Job**. A published job stays live while you edit it.

## When a job ends

A job runs until its deadline. That is the site's listing length, 30 days by default, unless the board sets its own length.

On sites that end jobs at their deadline (the default on new sites), the job then leaves the job listings. Its page stays up and tells visitors it has expired, so links you shared keep working. Your dashboard shows it as **Expired** with a **Reopen** button, which starts a new listing period with a fresh deadline (the site may charge credits for it). You get a **Job Ending Soon** email 3 days before the deadline. On any site, a job stops taking applications once its deadline has passed. Applications you already received stay as they are, so you can keep reviewing them.

## Close a job early

Use **Close** in **My Jobs** when the role is filled or cancelled. The job leaves the listings and stops taking applications. Every applicant you have not hired or rejected moves to **Closed** and gets one email saying the position is closed. You can reopen the job later, and the closed applications return to their earlier status.

## Use a single-page form instead

If you want the whole form on one screen, use the **Job Form (Single-Page)** block or the `[wcb_job_form_simple]` shortcode. See [Quick Job Form](./08-quick-job-form.md).
