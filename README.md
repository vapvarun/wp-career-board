# WP Career Board

A job board for WordPress built on Gutenberg blocks and the Interactivity API, with employer and candidate dashboards, application tracking and company profiles.

Version 1.8.1 | Requires WordPress 6.9, PHP 8.1 | GPL-2.0-or-later

## What it does

**Jobs**
- Frontend job form for employers, with a moderation queue (pending, approved, rejected) and automatic expiry with deadline reminders.
- Job listings with search and filters, plus a single job page.
- JobPosting structured data (schema.org) for Google for Jobs.

**Employers and companies**
- Employer dashboard on the frontend: post jobs, review applications, manage the company profile. No wp-admin needed.
- Public company profiles and a company archive.

**Candidates and applications**
- Candidate dashboard: applications, saved jobs, profile.
- Applications move through submitted, reviewing, shortlisted, rejected and hired. Candidates can also withdraw.
- Guest applications, with a confirmation email to the applicant.
- Applications CSV export per job.

**Email**
- 18 transactional emails: job approved, rejected, pending, expiring and expired; application received, status change, withdrawn and guest; account verification, welcome, report received and the account deletion flow.

**Trust and privacy**
- Spam protection with reCAPTCHA v3, reCAPTCHA v2 or Cloudflare Turnstile.
- Report and block users.
- WordPress Privacy API export and erasure for candidate data.

**Integrations and migration**
- BuddyPress (employer and candidate member types, activity entries), Reign Theme and BuddyX Pro.
- Import from WP Job Manager with `wp wcb migrate`.

## Who it is for

Site owners who want to run a job board, careers listing or hiring directory on WordPress, and agencies building one for a client. It works with any block theme and styles itself automatically under Reign and BuddyX Pro.

## Get it

| | |
|---|---|
| Plugin page and download | [store.wbcomdesigns.com/wp-career-board](https://store.wbcomdesigns.com/wp-career-board/) |
| Product site | [wpcareerboard.com](https://wpcareerboard.com/) |
| Pro add-on | [WP Career Board Pro](https://store.wbcomdesigns.com/wp-career-board-pro/) - adds a Kanban hiring pipeline, multiple boards, resumes, credits, AI tools and more. See the store page for pricing. |

The plugin is not listed on wordpress.org. Download it from the store page.

## Documentation

| | |
|---|---|
| Documentation hub | [docs.wbcomdesigns.com/products/wp-career-board](https://docs.wbcomdesigns.com/products/wp-career-board/) |
| Store documentation | [store.wbcomdesigns.com/wp-career-board/docs](https://store.wbcomdesigns.com/wp-career-board/docs/) |
| Release notes | [wbcomdesigns.com/release-notes/wp-career-board](https://wbcomdesigns.com/release-notes/wp-career-board/) |

## Requirements

- WordPress 6.9 or newer
- PHP 8.1 or newer

## Installation

1. Download the plugin zip from the store page.
2. In wp-admin go to Plugins > Add New > Upload Plugin, upload the zip and activate.
3. Run the Setup Wizard. It creates the job, dashboard and registration pages, sets sign-up, job, email and spam-protection options, and can add sample data.
4. Open the new jobs page on the frontend.

## For developers

**Identifiers**

| | |
|---|---|
| Slug and text domain | `wp-career-board` |
| Function and hook prefix | `wcb_` |
| PHP namespace | `WCB\` |
| Post types | `wcb_job`, `wcb_application`, `wcb_company` (and `wcb_resume` from Pro) |

**Blocks** (17, in `blocks/`, all under `wp-career-board/`)

`job-listings`, `job-search`, `job-search-hero`, `job-filters`, `job-single`, `job-form`, `job-form-simple`, `job-stats`, `featured-jobs`, `recent-jobs`, `job-alert-card`, `employer-dashboard`, `employer-registration`, `candidate-dashboard`, `company-profile`, `company-archive`, `similar-companies-card`.

**REST API.** Namespace `wcb/v1`. Main routes: `/jobs`, `/jobs/{id}/apply`, `/jobs/{id}/applications`, `/applications/{id}/status`, `/employers`, `/candidates`, `/companies`, `/search`, `/me`, `/settings/app-config`. Reference: [REST API](https://docs.wbcomdesigns.com/products/wp-career-board/developer-guide/03-rest-api/).

**Hooks.** Actions and filters are listed in the [Hooks Reference](https://docs.wbcomdesigns.com/products/wp-career-board/developer-guide/02-hooks-reference/). Wizard steps can be extended with the `wcb_wizard_steps` filter and emails with `wcb_registered_emails`.

**WP-CLI.** Command group `wp wcb`: `status`, `abilities`, `job` (list, approve, reject, expire, run-expiry), `application` (list, update), `migrate` (wpjm, wpjm-applications, wpjm-resumes, files) and `scale` (seed, benchmark, teardown). Reference: [WP-CLI](https://docs.wbcomdesigns.com/products/wp-career-board/developer-guide/04-wp-cli/).

**Template overrides.** Copy `single-wcb_job.php` or `single-wcb_company.php` into your theme to replace the plugin template. See [Template overrides](https://docs.wbcomdesigns.com/products/wp-career-board/developer-guide/06-template-overrides/).

**Build**

```bash
npm install && npm run build   # compile blocks
grunt dist                     # build distribution zip
```

## Support and licence

Support: [wbcomdesigns.com/support](https://wbcomdesigns.com/support/)

Licensed under GPL-2.0-or-later.
