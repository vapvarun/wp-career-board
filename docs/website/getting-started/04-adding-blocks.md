# Adding Blocks to Pages

WP Career Board uses WordPress blocks to display everything on the frontend. Each block handles a specific part of the job board experience.

## Available Blocks

WP Career Board includes 17 blocks. Search the block inserter for "Career Board" or the block name to find them. (There is also a **WP Career Board** category in the pattern inserter for the bundled page patterns - Full Job Board, Post a Job Form, Employer Dashboard, Candidate Dashboard, and Company Directory.)

| Block | What It Does |
|---|---|
| **Candidate Dashboard** | Tabbed candidate dashboard: Overview, My Applications, Saved Jobs, and Account Settings. My Resumes and Job Alerts tabs are added by Pro. |
| **Company Archive** | Interactive company directory with grid/list toggle and industry/size filters. |
| **Company Profile** | Public company profile with owner inline-edit and active job listings. |
| **Employer Dashboard** | Tabbed employer dashboard with 6 tabs: Overview, My Jobs, Post a Job, Applications, Company Profile, and Settings. The Applications tab has a List / Board (Kanban) toggle - the Board groups applicants into Submitted, Reviewing, Shortlisted, Hired, and Rejected columns, and dragging a card changes the applicant's status. |
| **Employer Registration** | Unified registration form for both employers and candidates. Users choose "Find a Job" (candidate) or "Hire Talent" (employer) on the same form. |
| **Featured Jobs** | Static server-rendered grid of featured (flagged) jobs. Good for homepages. |
| **Job Alerts CTA** | A call-to-action card prompting candidates to create a job alert. The alert creation itself is a Pro feature. |
| **Job Filters** | Taxonomy filter dropdowns (category, type, location, experience) for the job listings grid. |
| **Job Form** | Multi-step (4-step) wizard for employers to post new jobs. Best for the full Post-a-Job experience. |
| **Job Form (Single-Page)** | Every field on one screen - sidebar, modal, partner page, single-page-site alternative to the wizard. Submits to the same endpoint and honours the same field-builder filters. Posting only; editing routes through the wizard. |
| **Job Listings** | Reactive job listings grid with load-more and bookmark toggle. Updates on search/filter without page reload. |
| **Job Search** | Keyword search bar that drives the job listings grid. |
| **Job Search Hero** | Full-width search form with optional category, location, and job type filter dropdowns. Horizontal or vertical layout. Good for homepages. |
| **Job Single** | Full job detail view with a slide-in application panel and a "Report this job" control. |
| **Job Stats** | Horizontal stat strip showing total jobs, companies, and candidates. |
| **Recent Jobs** | Static list of the most recently published jobs. Good for sidebars or homepages. |
| **Similar Companies** | A sidebar card listing companies related to the one being viewed. |

> **WP Career Board Pro** adds more blocks, including AI Chat Search, Application Kanban, Credit Balance, Featured Candidates, Featured Companies, Job Alerts, Job Map, My Applications, Open to Work, Resume Builder, Resume Map, Resume Search Hero, and Resume Single. See the Pro documentation for the full Pro blocks reference.

## Adding a Block to a Page

1. Open any page in the WordPress editor (Gutenberg)
2. Click the **+** button to add a block
3. Search for "Career Board" or the block name
4. Click the block to insert it

![Block Inserter - Career Board Blocks](../images/block-inserter.png)

## The Job Board Page (Recommended Layout)

For the main jobs page, use this block arrangement in order:

1. **Job Search** - sits at the top, provides the search input
2. **Job Filters** - sits below search, provides the filter dropdowns
3. **Job Listings** - sits below filters, displays the results

All three blocks are connected - they automatically coordinate with each other on the same page. No configuration required.

![Jobs Page Layout](../images/jobs-page-layout.png)

## Configuring Block Settings

Some blocks have settings you can adjust in the block sidebar:

**Job Listings:**
- **Job board** - show only jobs assigned to a specific board (multi-board sites); "All boards" shows everything
- **Layout** - grid (default) or list view; grid offers a 3 or 4 column choice
- **Jobs per page** - how many jobs to show before the "Load more" button
- **Show filter sidebar** - turn the search box and filter sidebar on or off
- **Show page heading** - print the archive title from the block (off by default; leave off when the page already has a heading)
- **Filter sidebar** - when the sidebar is on, reorder the filter groups (Job type, Experience, Category, Tags, Location, Job board, Salary) with the up/down arrows, and hide any you do not need with the eye toggle. Settings are per-block, so each Job Listings placement can have its own filter order.

**Job Search Hero:**
- **Layout** - horizontal (default) or vertical
- **Show Category Filter**, **Show Location Filter**, **Show Job Type Filter** - toggle each filter dropdown on or off

**Job Form (Single-Page):**
- **Board** - target a specific board (multi-board sites only). Single-board sites can leave this at 0 and the default board is used.
- **Show Company Field** - show or hide the company name field (on by default; useful to hide for staff-only embeds where the company is implied)
- **Compact** - tighter vertical rhythm for sidebars and modals

**Featured Jobs:**
- **Jobs per page** - how many featured jobs to display (default: 3)
- **Title** - optional heading above the grid
- **Show "View All" link** - toggle the link to the full job board

**Recent Jobs:**
- **Count** - how many recent jobs to list (default: 5)
- **Show "View All" link** - toggle the link to the full job board

**Company Archive:**
- **Companies per page** - how many companies per page (default: 20)
- **Layout** - grid or list

**Job Stats:**
- Toggle **Show Jobs**, **Show Companies**, **Show Candidates** independently to display only the counts you want.

To access these settings, click the block in the editor and look at the **Block** panel in the right sidebar.

## The Setup Wizard vs Manual Setup

The Setup Wizard creates pages with the correct blocks already placed. You only need to add blocks manually if:
- You want to embed the job board on an existing page
- You want a custom layout or custom page template
- You dismissed the wizard

> **Tip:** If the Setup Wizard already created your pages, you don't need to add blocks manually. Check **Career Board → Settings → Pages** to see which pages are currently assigned.

## Using Shortcodes (Classic Editor)

If you're using the Classic Editor or a page builder that doesn't support Gutenberg blocks, you can use shortcodes instead. Every Free block has a shortcode equivalent:

| Shortcode | Block |
|---|---|
| `[wcb_job_listings]` | Job Listings |
| `[wcb_job_search]` | Job Search |
| `[wcb_job_search_hero]` | Job Search Hero |
| `[wcb_job_filters]` | Job Filters |
| `[wcb_job_form]` | Job Form (4-step wizard) |
| `[wcb_job_form_simple]` | Job Form (Single-Page) |
| `[wcb_job_single]` | Job Single |
| `[wcb_employer_dashboard]` | Employer Dashboard |
| `[wcb_candidate_dashboard]` | Candidate Dashboard |
| `[wcb_employer_registration]` | Employer Registration (alias: `[wcb_registration]`) |
| `[wcb_company_archive]` | Company Archive |
| `[wcb_company_profile]` | Company Profile |
| `[wcb_job_stats]` | Job Stats |
| `[wcb_recent_jobs]` | Recent Jobs |
| `[wcb_featured_jobs]` | Featured Jobs |
| `[wcb_similar_companies]` | Similar Companies |
| `[wcb_job_alert_card]` | Job Alerts CTA |

Simply paste the shortcode into any page or post content area. The shortcode renders the same output as its Gutenberg block counterpart. Every shortcode also accepts the block's attributes (for example `[wcb_job_listings boardId="42" perPage="6"]`), so you can scope a block from a page builder without writing custom code.
