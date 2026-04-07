# Settings

Configure WP Career Board from **WP Career Board → Settings** in wp-admin. Settings are organized into tabs.

![Settings Page — Job Listings Tab](../images/settings-job-listings.png)

## Job Listings Tab

Controls how jobs behave on your board.

| Setting | Default | Description |
|---|---|---|
| **Auto-Publish Jobs** | Off | When on, submitted jobs go live immediately without admin approval |
| **Jobs Per Page** | 10 | Number of jobs shown per page in the listings grid |
| **Job Expiry (days)** | 30 | Jobs close automatically after this many days; 0 = no expiry |
| **Deadline Auto-Close** | Off | Automatically closes jobs when their application deadline passes |
| **Allow Withdraw** | Off | Lets candidates withdraw their own applications |
| **Default Salary Currency** | USD | Site-wide default currency for new job postings; employers can override per job |

## Pages Tab

Links each feature to its dedicated page. If the Setup Wizard ran successfully, these are filled in automatically.

| Setting | Purpose |
|---|---|
| **Jobs Archive Page** | The main job board browse page (Find Jobs) |
| **Employer Dashboard Page** | The employer's management page |
| **Candidate Dashboard Page** | The candidate's tracking page |
| **Company Archive Page** | The public company directory |

If a page assignment is blank, the related functionality (e.g., "View your dashboard" links in emails) won't work correctly. Always fill these in.

> **With WP Career Board Pro:** a "Resume Builder Page" setting is also shown here.

## Notifications Tab

Controls the sender name, from email, and admin notification email address used by all WCB emails. See [Email Notifications](./02-email-notifications.md) for the full guide.

## Emails Tab

Lets you enable or disable each individual email notification and customize its subject line and body. See [Email Notifications](./02-email-notifications.md) for placeholders and customization options.

## Import Tab

One-click migration from WP Job Manager. See [Import & Migration](./05-import.md) for the full guide.

## Antispam Tab

Configure reCAPTCHA v3 for job application and registration forms. Enter your reCAPTCHA Site Key and Secret Key to enable bot protection.

## Pro-Only Tabs

When **WP Career Board Pro** is active, six additional tabs appear:

| Tab | What It Controls |
|---|---|
| **Pipeline** | Application stage configuration (Kanban hiring workflow) |
| **Credits** | Credit settings, product-to-credit mappings, detected payment providers, and credits-per-job-post value |
| **Field Builder** | Custom fields for jobs, companies, and candidates |
| **AI Settings** | Configures the AI provider key for AI Chat Search and job description generation |
| **Job Feed** | RSS/JSON feed settings for job listing aggregators |
| **Boards** | Multi-board engine: create and manage independent job boards |

## Saving Settings

Click **Save Changes** at the bottom of any tab. Settings are saved per-tab — you don't need to switch tabs before saving.
