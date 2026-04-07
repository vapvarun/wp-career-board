# Adding Blocks to Pages

WP Career Board uses WordPress blocks to display everything on the frontend. Each block handles a specific part of the job board experience.

## Available Blocks

WP Career Board includes 14 blocks, all registered in the **WP Career Board** category in the block inserter.

| Block | What It Does |
|---|---|
| **Candidate Dashboard** | Tabbed candidate dashboard: My Applications and Saved Jobs. My Resumes and Job Alerts tabs are added by Pro. |
| **Company Archive** | Interactive company directory with grid/list toggle and industry/size filters. |
| **Company Profile** | Public company profile with owner inline-edit and active job listings. |
| **Employer Dashboard** | Tabbed employer dashboard with 6 tabs: Overview, My Jobs, Post a Job, Applications, Company Profile, and Settings. |
| **Employer Registration** | Unified registration form for both employers and candidates. Users choose "Find a Job" (candidate) or "Hire Talent" (employer) on the same form. |
| **Featured Jobs** | Static server-rendered grid of featured (flagged) jobs. Good for homepages. |
| **Job Filters** | Taxonomy filter dropdowns (category, type, location, experience) for the job listings grid. |
| **Job Form** | Multi-step form for employers to post new jobs. |
| **Job Listings** | Reactive job listings grid with load-more and bookmark toggle. Updates on search/filter without page reload. |
| **Job Search** | Keyword search bar that drives the job listings grid. |
| **Job Search Hero** | Full-width search form with optional category, location, and job type filter dropdowns. Horizontal or vertical layout. Good for homepages. |
| **Job Single** | Full job detail view with a slide-in application panel. |
| **Job Stats** | Horizontal stat strip showing total jobs, companies, and candidates. |
| **Recent Jobs** | Static list of the most recently published jobs. Good for sidebars or homepages. |

> **WP Career Board Pro** adds 15 additional blocks: AI Chat Search, Application Kanban, Board Switcher, Credit Balance, Featured Candidates, Featured Companies, Job Alerts, Job Map, My Applications, Open to Work, Find Resumes, Resume Builder, Resume Map, Resume Search Hero, and Resume Single. See the [Pro Blocks Reference](https://docs.wbcomdesigns.com/wp-career-board-pro/pro-blocks/blocks-reference/) for details.

## Adding a Block to a Page

1. Open any page in the WordPress editor (Gutenberg)
2. Click the **+** button to add a block
3. Search for "Career Board" or the block name
4. Click the block to insert it

![Block Inserter — Career Board Blocks](../images/block-inserter.png)

## The Job Board Page (Recommended Layout)

For the main jobs page, use this block arrangement in order:

1. **Job Search** — sits at the top, provides the search input
2. **Job Filters** — sits below search, provides the filter dropdowns
3. **Job Listings** — sits below filters, displays the results

All three blocks are connected — they automatically coordinate with each other on the same page. No configuration required.

![Jobs Page Layout](../images/jobs-page-layout.png)

## Configuring Block Settings

Some blocks have settings you can adjust in the block sidebar:

**Job Listings:**
- **Jobs per page** — how many jobs to show before the "Load more" button
- **Layout** — grid (default) or list view

**Job Search Hero:**
- **Layout** — horizontal (default) or vertical
- **Show Category Filter**, **Show Location Filter**, **Show Job Type Filter** — toggle each filter dropdown on or off

**Featured Jobs:**
- **Jobs per page** — how many featured jobs to display (default: 3)
- **Title** — optional heading above the grid
- **Show "View All" link** — toggle the link to the full job board

**Recent Jobs:**
- **Count** — how many recent jobs to list (default: 5)
- **Show "View All" link** — toggle the link to the full job board

**Company Archive:**
- **Companies per page** — how many companies per page (default: 20)
- **Layout** — grid or list

**Job Stats:**
- Toggle **Show Jobs**, **Show Companies**, **Show Candidates** independently to display only the counts you want.

To access these settings, click the block in the editor and look at the **Block** panel in the right sidebar.

## The Setup Wizard vs Manual Setup

The Setup Wizard creates pages with the correct blocks already placed. You only need to add blocks manually if:
- You want to embed the job board on an existing page
- You want a custom layout or custom page template
- You dismissed the wizard

> **Tip:** If the Setup Wizard already created your pages, you don't need to add blocks manually. Check **WP Career Board → Settings → Pages** to see which pages are currently assigned.

## Using Shortcodes (Classic Editor)

If you're using the Classic Editor or a page builder that doesn't support Gutenberg blocks, you can use shortcodes instead. Every major block has a shortcode equivalent:

| Shortcode | Block |
|---|---|
| `[wcb_job_listings]` | Job Listings |
| `[wcb_job_search]` | Job Search |
| `[wcb_job_form]` | Job Form |
| `[wcb_employer_dashboard]` | Employer Dashboard |
| `[wcb_registration]` | Employer Registration |
| `[wcb_candidate_dashboard]` | Candidate Dashboard |
| `[wcb_company_archive]` | Company Archive |
| `[wcb_job_stats]` | Job Stats |
| `[wcb_recent_jobs]` | Recent Jobs |

Simply paste the shortcode into any page or post content area. The shortcode renders the same output as its Gutenberg block counterpart.
