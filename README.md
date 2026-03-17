# WP Career Board

A modern, Gutenberg-native job board for WordPress. Employers post jobs, candidates apply, admins moderate — all without page reloads. Built on the WordPress Interactivity API and REST API.

**Free core plugin** — works standalone, enhanced with BuddyPress/BuddyBoss integration and optional Reign/BuddyX Pro theme support.

## Requirements

- WordPress 6.9+
- PHP 8.1+

## Features

- **Job listings** — searchable, filterable archive block with salary, remote, and keyword filters
- **Job posting** — employer-facing post form block with reCAPTCHA v3 anti-spam
- **Applications** — guest or registered application flow; duplicate prevention; status tracking
- **Moderation** — admin approval queue with rejection reasons and email notifications
- **Email notifications** — 6 automated events (submitted, approved, rejected, status changed, expired)
- **GDPR** — WordPress Privacy API: data export and erasure for candidates
- **SEO** — `JobPosting` schema.org structured data on every job page
- **Roles** — `wcb_employer`, `wcb_candidate`, `wcb_board_moderator` with Abilities API
- **Blocks** — 10 Gutenberg blocks, all server-rendered with Interactivity API stores
- **Social sharing** — X, LinkedIn, copy-link buttons on job single
- **BuddyPress** — member type integration, activity streams

## Blocks

| Block | Purpose |
|-------|---------|
| `wcb/job-listings` | Filterable job archive |
| `wcb/job-search` | Live keyword search |
| `wcb/job-filters` | Salary, remote, type, location filters |
| `wcb/job-single` | Full job detail with apply button |
| `wcb/job-form` | Employer job submission form |
| `wcb/employer-dashboard` | Employer: manage jobs + applications |
| `wcb/candidate-dashboard` | Candidate: saved jobs + applications |
| `wcb/company-profile` | Public company profile page |
| `wcb/company-archive` | Company directory |
| `wcb/featured-jobs` | Curated featured job widget |

## Installation

1. Upload the `wp-career-board` folder to `/wp-content/plugins/`
2. Activate the plugin in **Plugins → Installed Plugins**
3. Complete the Setup Wizard that launches on first activation
4. Add blocks to your pages via the Gutenberg editor

## Development

```bash
npm install
npm run build     # compile blocks
npm run start     # watch mode

grunt pot         # generate translation template
grunt release     # full build + i18n + zip
grunt version --ver=1.0.0   # bump version
```

## Slug / Prefix / Namespace

- **Slug:** `wp-career-board`
- **Prefix:** `wcb_`
- **Namespace:** `WCB\`
- **REST namespace:** `wcb/v1`
- **Text domain:** `wp-career-board`

## Pro Version

[WP Career Board Pro](https://wbcomdesigns.com) adds: multi-board management, credit system with Stripe, resume builder + PDF export, drag-and-drop Kanban pipeline, AI job matching, job alerts, maps with radius search, PWA, analytics dashboard, and more.
