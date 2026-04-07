# WP Career Board — The Free Job Board for WordPress

**The first job board plugin built entirely on Gutenberg blocks and the WordPress Interactivity API.** No shortcodes, no jQuery, no page reloads.

Every other WordPress job board plugin was built on technology from 2012. WP Career Board fills that gap with a modern, block-native architecture that works with any theme and integrates deeply with BuddyPress communities.

**Free forever.** Not freemium. Not a trial.

---

## What's Included (Free)

| Feature | What It Does |
|---------|-------------|
| **Employer Dashboard** | Post jobs, review applications, manage company profile — all frontend, no wp-admin |
| **Candidate Dashboard** | Track applications, save jobs, manage profile with real-time status updates |
| **Application Tracking** | 5 statuses (Submitted → Reviewing → Shortlisted → Rejected → Hired) with inline updates |
| **Guest Applications** | Candidates apply without creating an account |
| **Company Profiles** | Public pages with logo, description, active listings, and verified badges |
| **Email Notifications** | 8 templates — approval, rejection, expiry, application receipt, status changes |
| **JobPosting Schema** | Automatic schema.org structured data for Google for Jobs indexing |
| **reCAPTCHA v3** | Spam protection on application forms |
| **GDPR Compliance** | WordPress Privacy API export and erasure for candidate data |
| **BuddyPress Integration** | Works with BuddyPress, BuddyBoss, and BuddyX Pro |
| **Theme Compatibility** | Works with any block theme. Perfect with Reign Theme and BuddyX Pro |

## 5-Minute Setup

1. Install the plugin
2. Run the Setup Wizard — creates Find Jobs, Employer Dashboard, Candidate Dashboard, and Registration pages automatically
3. Your job board is live

## Works With Any Theme. Perfect With Reign and BuddyX.

WP Career Board uses standard Gutenberg blocks — it works with any block-compatible theme. If you're running **Reign Theme** or **BuddyX / BuddyX Pro**, styling is automatic. Zero CSS required.

## 10 Gutenberg Blocks

`wcb/job-listings` · `wcb/job-search` · `wcb/job-filters` · `wcb/job-single` · `wcb/job-form` · `wcb/employer-dashboard` · `wcb/candidate-dashboard` · `wcb/company-profile` · `wcb/company-archive` · `wcb/featured-jobs`

Drop any block on any page. Server-rendered with Interactivity API stores for instant interactions.

---

## WP Career Board Pro

When your job board grows, [WP Career Board Pro](https://store.wbcomdesigns.com/wp-career-board-pro/) extends it with:

- **Kanban Hiring Pipeline** — custom stages with drag-and-drop (the feature no other WP job board has)
- **Multi-Board Engine** — unlimited independent boards from one install
- **Resume Builder & Search** — structured resumes with employer search + PDF export
- **WooCommerce Credit System** — charge employers to post via WooCommerce, PMPro, or MemberPress
- **Custom Field Builder** — 17 field types, no code required
- **AI Job Descriptions** — OpenAI, Anthropic Claude, or self-hosted Ollama
- **Job Alerts** — email notifications for matching new jobs
- **Job Feed** — XML feeds for Indeed, LinkedIn, Google Jobs

### Pricing

| Tier | Sites | Annual | Lifetime |
|------|-------|--------|----------|
| Personal | 1 | $49 | $149 |
| Developer | 5 | $79 | $199 |
| Agency | Unlimited | $149 | $399 |

All tiers include every Pro feature. 30-day money-back guarantee.

---

## Links

| | |
|---|---|
| Store | [store.wbcomdesigns.com/wp-career-board](https://store.wbcomdesigns.com/wp-career-board/) |
| Documentation | [store.wbcomdesigns.com/wp-career-board/docs](https://store.wbcomdesigns.com/wp-career-board/docs/) |
| Pro Version | [store.wbcomdesigns.com/wp-career-board-pro](https://store.wbcomdesigns.com/wp-career-board-pro/) |
| Support | [wbcomdesigns.com/support](https://wbcomdesigns.com/support/) |

## Requirements

- WordPress 6.9+
- PHP 8.1+

## Development

```bash
npm install && npm run build   # compile blocks
grunt dist                     # build distribution zip
```

**Slug:** `wp-career-board` · **Prefix:** `wcb_` · **Namespace:** `WCB\` · **REST:** `wcb/v1`
