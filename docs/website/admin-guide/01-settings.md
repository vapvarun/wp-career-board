# Settings

You can set how jobs are reviewed and listed, who can sign up, how emails look and which pages hold each feature. Open **Career Board → Settings** in wp-admin. The sidebar groups tabs by the job you came to do, and each tab saves on its own with its own **Save Changes** button. Saving keeps you on the same tab.

The defaults below are what a fresh install uses. A few defaults are safer on sites created from 1.8.0 on; those are marked "new sites". Sites that existed before 1.8.0 keep their settings and see one notice listing the safer defaults.

## Jobs & applications

| Tab | What it controls |
|---|---|
| **Jobs** | Review, listing length, order, salary currency, featured duration, Google for Jobs and sharing |
| **Applications** | Resume rules and withdrawing |
| **Industries** | The industries offered on company profiles, registration and the company directory filter |
| **Import** | Migrate from WP Job Manager. See [Import & Migration](./18-import.md) |

WP Career Board Pro adds more tabs to this group (Boards, Field Builder and Job Feed). Read them in the Pro documentation.

### Jobs

| Setting | Default | Description |
|---|---|---|
| **Auto-Publish Jobs** | Off | On: submitted jobs go live at once. Off: new jobs wait as Pending until approved under Career Board → Jobs |
| **Hide a job after this many reports** | 3 | When this many members with standing report a job, it is taken off the site as Pending until you review it under Jobs → Flagged. 0 turns auto-hide off (0-50). See [Moderation](./03-moderation.md#reported-jobs-flagged) |
| **Default listing length (days)** | 30 | How long a job stays open when the employer sets no deadline. A board's own listing length, if set, wins |
| **When a job ends** | - | Not a switch. At its deadline a job stops taking applications and leaves listings, feeds and the sitemap within the hour. Its page stays up as an expired page (noindex, similar open jobs) so shared links keep working. Employers reopen it with a new deadline. On sites created before 1.8.0 that had this off, past-deadline jobs keep listing until you click **End jobs at their deadline** here. Only jobs that ended in the last 7 days email their employer, so switching on does not email a backlog |
| **Jobs Per Page** | 10 | Jobs per page in the job listings block (1-100) |
| **Default order** | Featured, then newest | How job lists are ordered before a visitor picks a sort: Featured then newest, Closing soonest, Highest salary, or Oldest first. A keyword search always shows the best matches first |
| **Default Salary Currency** | USD | Pre-selected on the job form; employers can pick another per job |
| **Featured Duration (days)** | 30 | How long a job stays featured before it reverts (1-365). See [Featured Listing Expiry](./08-featured-expiry.md) |

The **Search engines and sharing** card on the same tab holds four more settings. They are covered in [Google for Jobs and Social Sharing](./17-google-for-jobs.md): **Google for Jobs** (on), **Require a location** (off; on for new sites), **Default country** (your site language's country) and **Social sharing tags** (on).

### Applications

| Setting | Default | Description |
|---|---|---|
| **Resume Required** | On | Applicants must attach a resume. Turn off where a cover letter is enough |
| **Application Resume File Size (MB)** | 5 | Largest resume file an applicant can upload (1-20). PDF, DOC and DOCX only |
| **Allow Withdraw** | On | Candidates can withdraw their own applications until an outcome. The application stays in the employer's list as Withdrawn |

## Candidates & employers

| Tab | What it controls |
|---|---|
| **Sign-ups** | Open sign-up status, email confirmation, candidate role, login to apply |
| **Privacy** | Pending account deletions and the personal-data request log |

WP Career Board Pro adds Resumes and Credits tabs here. Read them in the Pro documentation.

### Sign-ups

| Setting | Default | Description |
|---|---|---|
| **Open Sign-Up** | Read-only | Shows whether visitors can create accounts. It mirrors WordPress's **Anyone can register** setting under **Settings → General**. When off, the sign-up forms show a "registration closed" notice |
| **Email Verification** | Off; on for new sites | New accounts stay signed out until they open the link in the "Confirm Your Email" email. If that email is switched off under Emails, sign-ups are not held |
| **Require Candidate Role** | Off | Off lets any signed-in member apply and keep a resume. On reserves the candidate experience for users with the Candidate role |
| **Require login to apply** | Off | On: only signed-in members can apply and the job page shows **Sign in to apply**. Off: visitors can apply with a name and email. Guest applications are limited to 10 an hour from one connection |

Sign-ups from one connection are limited to 5 an hour. Developers can change that with the `wcb_registration_rate_limit` filter.

### Privacy

The **Privacy** tab shows two lists:

- **Pending account deletions** - members who asked to delete their account, with the date it runs and a **Keep account** button to cancel it for them. The grace period is 14 days by default.
- **Privacy request log** - every personal-data export and erase request the plugin processed, kept as evidence. Visitor IP addresses are stored only as a one-way hash.

See [GDPR & Privacy](./04-gdpr.md).

## Emails & app

| Tab | What it controls |
|---|---|
| **Brand** | One colour and one logo used by every Career Board email, the mobile app and the installable app (PWA). Your website itself follows your theme |
| **Emails** | Sender, footer text, and each email's on/off switch, subject and body. See [Email Notifications](./02-email-notifications.md) |
| **Mobile App** | Sign-in background, dark mode, legal links and password sign-in for the companion app |

**Brand.** **Brand Colour** (default `#4F46E5`) paints the email header, app buttons and the browser bar of the installable app. Pick a colour white text reads well on. **Logo** comes from the Media Library; a wide image around 400 x 120 px works best. On sites that existed before 1.8.0 the Brand starts as your old email header colour and logo, so emails look the same.

**Emails.** The **Sender** card at the top holds **From Name** (site name), **From Email** (site admin email) and **Admin Notification Email** (site admin email). Below it, the **Email look** card holds the footer text and the **Email templates** list holds one row per email.

**Mobile App.** Its colour and logo come from **Brand**. This tab sets:

| Setting | Default | Description |
|---|---|---|
| **Sign-in background URL** | Empty | Image behind the app's sign-in screen |
| **Dark mode** | Off | Open the app in dark mode by default |
| **Terms of service URL**, **EULA URL**, **Community guidelines URL** | Empty | App stores require published terms. A blank link is sent as empty, not as a placeholder |
| **Abuse contact email** | Empty | Public. Members email it about abuse. Blank shows your privacy page instead |
| **App Password Sign-In** | Off | Lets members type their website password in the app. Leave off if you use two-factor authentication or do not run the app: the app's "Connect with WordPress" option already signs members in through your normal login page |

## Site

| Tab | What it controls |
|---|---|
| **Pages** | Assigns the WordPress pages that hold each Career Board block |
| **Anti-Spam** | Honeypot plus an optional CAPTCHA provider |
| **Integrations** | Install and activate other Wbcom plugins that work with Career Board |
| **Advanced** | Content width, email history retention, Remove Data on Delete |

WP Career Board Pro adds AI Settings, Analytics, Job Alerts and License tabs here. Read them in the Pro documentation.

### Pages

You can choose which WordPress page holds each feature. If the Setup Wizard ran, these are filled in for you. If a page is missing, a banner names it, and **Create Missing Pages** creates only what is missing: it keeps pages you already have and adopts a page that already contains the right block, so it never makes a duplicate.

| Setting | Slug | Purpose |
|---|---|---|
| **Jobs Archive Page** | `/find-jobs/` | The main job board browse page |
| **Employer Dashboard Page** | `/employer-dashboard/` | The employer's management page |
| **Candidate Dashboard Page** | `/candidate-dashboard/` | The candidate's tracking page |
| **Company Directory Page** | `/find-companies/` | The public company directory |
| **Post a Job Page** | `/post-a-job/` | The standalone job-submission page |
| **Employer Registration Page** | `/employer-registration/` | The sign-up page for both employers and candidates |
| **Find Candidates Page** (Pro) | `/find-candidates/` | The candidate directory. An older `/find-resumes/` page still works |
| **Job Map Page** (Pro) | `/job-map/` | Jobs plotted on a map with a radius filter |

The browse pages use `/find-jobs/` and `/find-companies/` because `/jobs/` and `/companies/` belong to the job and company archives.

If a page is not assigned, links that depend on it, such as "View your dashboard" in emails, fall back to the home page. Always fill these in.

### Anti-spam

A honeypot field protects every submission form automatically. For a stronger second layer, choose a CAPTCHA provider:

- **None** - honeypot only (default).
- **Cloudflare Turnstile** - enter the Turnstile Site Key and Secret Key.
- **Google reCAPTCHA v3 (invisible, score)** - enter the Site Key, Secret Key and an optional score threshold (default 0.5).
- **Google reCAPTCHA v2 (invisible badge)** - enter the Site Key and Secret Key. Create the keys in Google's console as reCAPTCHA v2, "Invisible reCAPTCHA badge". This is a different key type than v3.

A CAPTCHA runs only once the chosen provider has both a site key and a secret key saved. Switching providers keeps the keys you entered for the others, so you can switch back without retyping. The provider guards the sign-up, job-submission and job-application forms, and the mobile app is told whether a CAPTCHA is required.

**Visitor IP address.** Sign-ups, guest applications and app sign-ins are limited per visitor IP address. If your site runs behind Cloudflare, a load balancer or another proxy, every visitor reaches WordPress from the proxy's address, so choose your proxy here: Cloudflare (CF-Connecting-IP), a load balancer or proxy (X-Forwarded-For) or an Nginx proxy (X-Real-IP). Leave it on **Direct connection** otherwise, because anyone can send these headers.

### Advanced

| Setting | Default | Description |
|---|---|---|
| **Content Width (px)** | 0 (follow your theme) | How wide Career Board pages run. Leave at 0 to follow your theme. Set 720-1920 only if plugin pages need to differ from the rest of the site |
| **Keep Email History (days)** | 180 | How long the email log and notification history are kept before a daily task deletes them. 0 keeps them forever (0-3650) |
| **Remove Data on Delete** | Off | Off keeps your data when you delete the plugin, so a reinstall picks up where you left off. On: deleting Free (or Pro) erases jobs, applications, companies, resumes, uploaded resume files, credit balances, settings and roles. It cannot be undone. Deactivating never deletes anything. The WordPress pages the plugin created are not deleted either |

## Saving settings

Click **Save Changes** at the bottom of a tab. Each tab saves separately and saving one tab never changes another.
