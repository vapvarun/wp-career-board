# WP Career Board — Messaging Guide

## Core Positioning

WP Career Board is the job board plugin built for the WordPress block editor — no shortcodes, no jQuery, no page reloads. It runs entirely on the WordPress Interactivity API and Gutenberg blocks, which means it's fast, compatible with every modern WordPress theme, and designed to last.

**Primary audience:** WordPress community site owners running BuddyPress, BuddyBoss, or Reign Theme who want to add a professional job board without paying for a separate SaaS platform.

**Secondary audience:** WordPress agencies building niche job portals for clients.

---

## One-Sentence Value Proposition

> A complete job board for WordPress communities — employer dashboards, candidate profiles, application tracking, and BuddyPress integration, all built on Gutenberg blocks and the Interactivity API.

---

## Taglines (pick one per context)

| Context | Tagline |
|---|---|
| Product page headline | The job board built for WordPress communities |
| Short description | Post jobs. Hire talent. Stay in your community. |
| Reign Theme bundling | A full job board, included with your Reign license |
| Technical audiences | Block-first. Interactivity API. No jQuery. |

---

## Key Messages

### 1. Block-first, not an afterthought
Every screen — job listings, dashboards, application forms — is a Gutenberg block. You place them with the block editor, style them with your theme, and compose pages however you want. No shortcodes to memorize, no template files to override.

### 2. Fast by design
The plugin uses the WordPress Interactivity API instead of jQuery or a custom React bundle. Filters update job listings instantly. Application status changes without a page reload. The result is better Core Web Vitals and a snappier experience for both employers and candidates.

### 3. Community-aware
If your site runs BuddyPress or BuddyBoss, WP Career Board connects automatically. Job posts appear in activity streams. Employer and candidate roles map to BuddyPress member types. The job board feels like part of the community, not a plugin bolted on.

### 4. Included with Reign Theme
Users of Wbcom Designs' Reign Theme get WP Career Board included in their license. One setup wizard, and a professional job board is running on their community site.

### 5. Grows with your needs
The free version covers the full employer-to-hire workflow. When you need more — a Kanban pipeline, multiple boards, resume search, a credit-based monetization model — WP Career Board Pro extends it without replacing it.

---

## Tech Stack (for developer audiences)

| Layer | Technology |
|---|---|
| Language | PHP 8.1+ with strict types |
| WordPress minimum | WP 6.9+ |
| Frontend | WordPress Interactivity API — no jQuery, no React bundle |
| Blocks | Gutenberg blocks registered via `register_block_type_from_metadata()` |
| API | REST API at `/wp-json/wcb/v1/` — every action goes through a typed endpoint |
| Permissions | WordPress Abilities API — fine-grained, auditable |
| SEO | `JobPosting` schema.org (LD+JSON), OpenGraph tags |
| Privacy | WP Privacy API — GDPR export and erasure built in |
| Notifications | `wp_mail()` with customizable email templates |
| Scheduling | WP-Cron for job auto-expiry |
| Spam protection | reCAPTCHA v3 (score-based, invisible) |

**What we deliberately avoided:**
- No shortcodes
- No jQuery or inline `<script>` blocks
- No `admin-ajax.php`
- No hardcoded page templates

---

## Competitor Positioning

| | WP Career Board | WP Job Manager | JobBoardWP |
|---|---|---|---|
| Block-first | Yes — every screen | Partial (shortcodes primary) | No |
| Interactivity API | Yes | No | No |
| BuddyPress integration | First-class | None | None |
| Reign/BuddyX bundling | Yes | No | No |
| Guest applications | Yes (free) | Add-on | Add-on |
| reCAPTCHA (free) | Yes | No | No |
| GDPR (free) | Yes | No | Partial |
| Application pipeline | Pro (Kanban) | Add-on | Pro |
| Multi-board | Pro | No | Pro |
| Built on WP native tech | 100% | Mostly | No |

---

## Proof Points

- Built entirely on WordPress core APIs — no third-party JS framework dependency
- Auto-integrates with Reign Theme, BuddyPress, and BuddyBoss Platform
- Full application tracking: 5 statuses in free, unlimited custom pipeline stages in Pro
- `JobPosting` schema.org outputs valid structured data on every job single page
- GDPR compliance built in — personal data export and erasure in WP Admin → Tools → Privacy
- reCAPTCHA v3 spam protection on both the job form and the application form (free)
- Guest applications — candidates can apply with email only, no registration required

---

## Tone & Voice

**Do:** Direct, specific, written like a senior engineer explaining something to a colleague.

**Don't:** Vague superlatives ("seamless", "powerful", "game-changing"), excessive exclamation points, or filler ("basically", "simply", "just").

**Example (correct):**
> WP Career Board uses the WordPress Interactivity API, which means job filters update live without a page reload and no JavaScript framework is added to your site.

**Example (avoid):**
> Our revolutionary, seamless job board solution leverages cutting-edge technology to transform your hiring workflow!
