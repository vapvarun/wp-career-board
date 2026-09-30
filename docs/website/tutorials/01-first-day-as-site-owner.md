# Your first day as a site owner

You can go from a fresh install to a live job board with a test employer, a published job and a submitted application. This walkthrough covers each step in order. Set aside about an hour.

## What you will have at the end

- A working job board at `/find-jobs/` and a company directory at `/find-companies/`.
- One employer account that can post jobs.
- One candidate account that can apply.
- Email notifications tested.
- A published test job and a test application.

## Before you start

You need:

- WordPress 6.9 or newer on PHP 8.1 or newer.
- An admin account on the site.
- A way to send email from the site, such as an SMTP plugin or your host's mail.

If you also want to try Pro features, install Pro after this walkthrough. The steps below use Free only.

## Step 1 - Install and activate

1. Go to **Plugins > Add New > Upload Plugin** and upload the `wp-career-board.zip` file.
2. Click **Activate**.
3. The Setup Wizard opens. Walk through it rather than dismissing it.

If you do not see the wizard, open **Career Board > Settings** and click **Run Setup Wizard**.

The plugin adds three roles: Employer, Candidate and Job Moderator. Learn what each can do in [Capabilities and roles](../admin-guide/14-capabilities-and-roles.md).

## Step 2 - Walk through the setup wizard

The admin menu is hidden while the wizard runs. Click **Exit setup** at the top if you need to leave early. A stepper across the top lets you go back to any finished step. The steps are:

1. **Pages** - creates the pages your board needs and assigns them in Settings:
   - Find Jobs
   - Employer Dashboard
   - Candidate Dashboard
   - Find Companies
   - Post a Job
   - Employer Registration

   If a page that already contains the matching Career Board block exists, the wizard reuses it.
2. **Sign-ups** - turn on **Let people sign up** so candidates and employers can create their own accounts, and turn on **Confirm email addresses** to hold new accounts until they click a link in their inbox.
3. **Jobs** - choose whether jobs publish immediately and set the default listing length in days.
4. **Emails** - set the sender name, sender email and the address for admin alerts.
5. **Spam Protection** - keep the hidden honeypot only, or add Cloudflare Turnstile or Google reCAPTCHA and enter its keys.
6. **Sample Data** - install demo categories, job types, companies and jobs so the board is not empty while you test.

Each settings step has **Save & Continue** and **Skip for now**. You can change every answer later under **Career Board > Settings**.

To remove the sample data later, go to **Career Board > Settings > Import** and click **Remove Sample Data**.

## Step 3 - Test email sending

If your site cannot send email, employers never hear about applications. Test this first.

1. Go to **Career Board > Settings > Emails**.
2. In the **Email templates** table, click **Send test** on any row. The test goes to your admin email address.
3. If it does not arrive, install an SMTP plugin such as WP Mail SMTP or Fluent SMTP, connect your mail provider, and test again.

Every test send is recorded in the **Email activity log** on the same tab, with a Sent or Failed status.

## Step 4 - Set the sender details

On **Career Board > Settings > Emails**, the **Sender** card holds three settings:

- **From Name** - the name shown on Career Board emails. Defaults to your site name.
- **From Email** - the sender address. Use an address on your own domain so mail passes SPF, DKIM and DMARC checks. Defaults to the site admin email.
- **Admin Notification Email** - where admin alerts go, such as a new job waiting for review or a reported job. Defaults to the site admin email.

Below the Sender card, the **Email templates** table lists every email with its recipient, subject and an **Enabled** checkbox. Click **Edit** on a row to change the message. Keep **Application Status Changed** enabled, because it is how candidates hear about progress.

## Step 5 - Add the pages to your menu

The wizard creates the pages but does not change your menu.

1. Go to **Appearance > Menus**.
2. Add Find Jobs, Find Companies, Candidate Dashboard, Employer Dashboard and Post a Job.
3. Save the menu.

## Step 6 - Create a test employer

Test as a real employer instead of posting from your admin account. Admin accounts skip role checks, which hides problems.

1. Open a private browser window so you stay logged in as admin in your main window.
2. Visit `/employer-registration/`.
3. Choose **Hire Talent**, then fill in your name, email, password and company details. Use an email address you can read.
4. If **Email Verification** is on, open the confirmation link in the email, then sign in. If it is off, you are signed in straight away and receive a Welcome email.

Registration creates the account with the Employer role and creates the company for it.

## Step 7 - Post the first test job

Still as the test employer:

1. Open the Employer Dashboard and click **Post a Job**.
2. Complete the four steps: **Job Basics**, **Job Details**, **Classify Your Job** and **Preview & Submit**. Give the job a title and description, choose a category, job type and location, and leave **Apply URL** and **Apply Email** empty so candidates apply on your site.
3. Submit.

If **Auto-Publish Jobs** is on under **Settings > Jobs**, the job is published straight away. If it is off, the job waits as Pending. Approve it from **Career Board > Jobs** in your admin window. The employer is emailed when the job is approved.

Check the job on `/find-jobs/`. If it does not appear:

- Confirm its status is Published.
- Confirm its deadline is not in the past.
- Confirm your theme is not redirecting the page.

### Handle reported jobs

When a logged-in visitor reports a job, a **Flagged** filter appears at the top of **Career Board > Jobs**. The visitor picks one reason: scam or fraudulent, spam or advertisement, expired or already filled, inaccurate or misleading, or offensive or inappropriate. Open the Flagged list and read the reasons in the **Flags** column. Then click **Dismiss flag** if the job is fine, or **Unpublish** if it is not.

## Step 8 - Create a test candidate

1. Open another private window.
2. Visit `/employer-registration/` again and choose **Find a Job**. The Candidate Dashboard also shows **Sign In** and **Create an account** buttons when you are logged out.
3. Sign in. By default any logged-in member can apply, save jobs and build a resume, without needing the Candidate role. If you turn on **Require Candidate Role** under **Settings > Sign-ups**, only accounts with the Candidate role can.
4. In the Candidate Dashboard, open **My Resumes**, click **+ New Resume** or **Upload CV**, and add a resume.

## Step 9 - Apply to the test job

As the candidate:

1. Open `/find-jobs/` and click the test job.
2. Click **Apply Now**.
3. Choose a saved resume or upload a file, and write a cover letter. The cover letter is optional.
4. Click **Submit Application**.

The button changes to **Application Submitted**.

## Step 10 - Check the employer side

Back in the employer window:

1. Open the Employer Dashboard and go to **Applications**. Select the job, then select the applicant.
2. Confirm the resume is available to view or download.
3. Check the employer's inbox for the **New application for your job** email. If it is missing, go back to Step 3.
4. Set the status to **Reviewing**.
5. Check the candidate's inbox for the status email. If it is missing, check that **Application Status Changed** is enabled under **Settings > Emails**.
6. Set the status to **Shortlisted**, then **Hired**. Each change emails the candidate.

## Step 11 - Check the candidate dashboard

In the candidate window:

1. Open **My Applications**. The test application shows the status Hired.
2. Bookmark another job on `/find-jobs/`, then open **Saved Jobs** to confirm it appears.
3. Open **Profile** and confirm your changes save.

## Step 12 - Clean up

When you are done:

1. Delete the test job from **Career Board > Jobs**.
2. Delete the test application from **Career Board > Applications**.
3. Delete the test candidate and employer accounts from **Users**.

## What to do next

- [Employer end to end](02-employer-end-to-end.md) - the full employer flow.
- [Candidate end to end](03-candidate-end-to-end.md) - the full candidate flow.
- [Monetizing your board](04-monetizing-your-board.md) - charge for job posts.
- [Capabilities and roles](../admin-guide/14-capabilities-and-roles.md) - give staff the right access.

## Common day-one mistakes

- **Skipping the email test.** Without working email, employers are not told about new applications.
- **Posting jobs from the admin account.** Test as a real employer and candidate.
- **Forgetting the listing length.** A new job runs for the length set under **Settings > Jobs > Default listing length (days)**, which is 30 by default. A board can set its own length, which wins over this one. When ending jobs at their deadline is on, a job leaves the listings within the hour of its deadline. New sites have this on. Older sites can turn it on with the **End jobs at their deadline** button under **Settings > Jobs**.
- **Not adding the pages to the menu.** Visitors cannot reach the dashboards without links.
- **Not activating the Pro license.** If you installed Pro, activate the license under **Settings > License**. It controls updates and the mobile app connection. Pro's web features keep working without it.
