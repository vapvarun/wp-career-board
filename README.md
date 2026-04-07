# WP Career Board

A modern, Gutenberg-native job board for WordPress. Employers post jobs, candidates apply, admins moderate — all without page reloads. Built on the WordPress Interactivity API and REST API.

**Free core plugin** — works standalone, enhanced with BuddyPress/BuddyBoss integration and optional Reign/BuddyX Pro theme support.

## Links

| Resource | URL |
|----------|-----|
| **Store Page** | [store.wbcomdesigns.com/wp-career-board](https://store.wbcomdesigns.com/wp-career-board/) |
| **Documentation** | [store.wbcomdesigns.com/wp-career-board/docs](https://store.wbcomdesigns.com/wp-career-board/docs/) |
| **Pro Version** | [store.wbcomdesigns.com/wp-career-board-pro](https://store.wbcomdesigns.com/wp-career-board-pro/) |
| **Blog (Free)** | [wbcomdesigns.com/wp-career-board-free-job-board-wordpress](https://wbcomdesigns.com/wp-career-board-free-job-board-wordpress/) |
| **Blog (Pro)** | [wbcomdesigns.com/wp-career-board-pro-features-guide](https://wbcomdesigns.com/wp-career-board-pro-features-guide/) |
| **Support** | [wbcomdesigns.com/support](https://wbcomdesigns.com/support/) |

## Requirements

- WordPress 6.9+
- PHP 8.1+

## Features

- **Job listings** — searchable, filterable archive block with salary, remote, and keyword filters
- **Job posting** — employer-facing post form block with reCAPTCHA v3 anti-spam
- **Applications** — guest or registered application flow; duplicate prevention; status tracking
- **Moderation** — admin approval queue with rejection reasons and email notifications
- **Email notifications** — 8 automated events (submitted, approved, rejected, status changed, expired, application received)
- **GDPR** — WordPress Privacy API: data export and erasure for candidates
- **SEO** — `JobPosting` schema.org structured data on every job page
- **Roles** — `wcb_employer`, `wcb_candidate`, `wcb_board_moderator` with Abilities API
- **Blocks** — 10 Gutenberg blocks, all server-rendered with Interactivity API stores
- **Social sharing** — X, LinkedIn, copy-link buttons on job single
- **BuddyPress** — member type integration, activity streams
- **Theme compatibility** — works with any block theme; perfect with Reign Theme and BuddyX/BuddyX Pro

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

## Pro Version

[WP Career Board Pro](https://store.wbcomdesigns.com/wp-career-board-pro/) adds:

- **Kanban Hiring Pipeline** — custom stages with drag-and-drop
- **Multi-Board Engine** — unlimited independent boards per install
- **Resume Builder & Search** — structured resumes with employer search + PDF export
- **WooCommerce Credit System** — charge employers to post via WooCommerce, PMPro, or MemberPress
- **Custom Field Builder** — 17 field types for jobs, companies, and candidates
- **AI Job Descriptions** — AI-assisted writing with OpenAI, Anthropic, or Ollama
- **Job Alerts** — email notifications for matching new jobs
- **Job Feed** — XML feeds for Indeed, LinkedIn, Google Jobs

### Pro Pricing

| Tier | Sites | Annual | Lifetime |
|------|-------|--------|----------|
| Personal | 1 | $49 | $149 |
| Developer | 5 | $79 | $199 |
| Agency | Unlimited | $149 | $399 |

All tiers include every Pro feature. 30-day money-back guarantee.

## Development

```bash
npm install
npm run build     # compile blocks
npm run start     # watch mode

grunt pot         # generate translation template
grunt dist        # full build + zip
```

## Slug / Prefix / Namespace

- **Slug:** `wp-career-board`
- **Prefix:** `wcb_`
- **Namespace:** `WCB\`
- **REST namespace:** `wcb/v1`
- **Text domain:** `wp-career-board`
