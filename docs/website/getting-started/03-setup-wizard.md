# Setup Wizard

You can set up your job board in the Setup Wizard. It creates your pages, sets your basic options for sign-ups, jobs, emails and spam protection, and can add sample data.

## How it works

A stepper runs across the top of the wizard. The WordPress admin menu is hidden while the wizard runs. Click **Exit setup** in the wizard header to leave at any time. You can click a step you have already reached in the stepper to reopen it.

The Sign-ups, Jobs, Emails and Spam Protection steps have **Save & Continue** and **Skip for now**. Every answer can be changed later in **Settings**.

## The steps

### 1. Pages

Creates the pages your board needs, each with the correct block already placed. Pages you already have are kept. The step lists each page as "Already set up" or "Will be created".

| Page | Block(s) | Purpose |
|---|---|---|
| Find Jobs | Heading + Job Search + Job Listings | Main job board browse page |
| Post a Job | Job Form | Multi-step form for employers to submit listings |
| Employer Registration | Employer Registration | Unified registration for both employers and candidates (users choose "Find a Job" or "Hire Talent") |
| Employer Dashboard | Employer Dashboard | Employer manages jobs + applications |
| Candidate Dashboard | Candidate Dashboard | Candidate tracks applications + saved jobs |
| Find Companies | Company Archive | Browsable company directory |

### 2. Sign-ups

- **Let people sign up** - candidates and employers create their own accounts. This is WordPress's "Anyone can register" setting. Off: only you can add users, and the sign-up forms show a "registration closed" notice.
- **Confirm email addresses** - new accounts click a link in their inbox before they can post or apply. Stops fake and mistyped sign-ups.

### 3. Jobs

- **Publish jobs without review** - when off, every new job waits for your approval under **Career Board > Jobs** before candidates see it.
- **Default listing length** - how long a job stays open when the employer sets no deadline.
- **Salary currency** - pre-selected on the job form; employers can still pick another.

### 4. Emails

- **Sender name** - shown as "From" on every Career Board email.
- **Sender email** - use an address on your own domain so emails don't land in spam.
- **Send admin alerts to** - new jobs waiting for review, reports, and other admin notices go here.

### 5. Spam protection

Choose a CAPTCHA option and enter its site key and secret key. The options are None (hidden honeypot only), Cloudflare Turnstile, Google reCAPTCHA v3 and Google reCAPTCHA v2 (invisible badge). A hidden honeypot always protects the sign-up, apply and post-a-job forms. See [Settings > Anti-spam](../admin-guide/01-settings.md#anti-spam).

### 6. Sample data

Optionally install sample categories, job types, companies and demo jobs, so you can see how the board looks before you add real data. Click **Finish Setup** to complete.

### Extra steps with Pro

WP Career Board Pro adds steps named License, How Employers Pay and Pro Pages.

## Running the wizard again

If you dismissed the wizard or need to create missing pages:

1. Go to **Career Board > Settings**.
2. Click **Re-run Setup Wizard** at the bottom of the page.

Pages you already have are kept and reused.

## After the wizard

Once complete, your site has a working job board. Next steps:

- **[Configure settings](../admin-guide/01-settings.md)** - review moderation, listing length, and page assignments
- **[Set up email notifications](../admin-guide/02-email-notifications.md)** - customize the emails sent to employers and candidates
- **[Review page assignments](../admin-guide/01-settings.md#pages)** - confirm each page in the Pages settings tab if anything needs adjusting
