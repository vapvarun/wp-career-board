# Adding Blocks to Pages

You can build your job board pages by adding blocks in the WordPress editor. Each block handles one part of the job board, and each has a shortcode for page builders.

## Available blocks

Search the block inserter for "Career Board" or the block name to find them. (There is also a **WP Career Board** category in the pattern inserter for the bundled page patterns - Full Job Board, Post a Job Form, Employer Dashboard, Candidate Dashboard, and Company Directory.)

| Block | What It Does |
|---|---|
| **Candidate Dashboard** | Candidate dashboard with a sidebar that includes Overview, My Applications, Saved Jobs, Saved Companies, Profile and Settings. With Pro it also shows sections such as My Resumes and Job Alerts. |
| **Company Archive** | Interactive company directory with grid/list toggle and industry/size filters. |
| **Company Profile** | Public company profile with owner inline-edit and active job listings. |
| **Employer Dashboard** | Employer dashboard with a sidebar that includes Overview, My Jobs, Post a Job, Applications, Profile, Saved Jobs, Saved Companies and Settings. A Credits section appears when the Credit System (Pro) is on. Applications has a List / Board toggle. The Board groups applicants into status columns, and its Move to menu changes an applicant's status. |
| **Employer Registration** | Unified registration form for both employers and candidates. Users choose "Find a Job" (candidate) or "Hire Talent" (employer) on the same form. |
| **Featured Jobs** | Grid of featured jobs. Good for homepages. |
| **Job Alerts CTA** | A card that prompts candidates to create a job alert. It shows only when Pro job alerts are on. |
| **Job Filters** | Taxonomy filter dropdowns (category, type, location, experience) for the job listings grid. |
| **Job Form** | Step-by-step form (Basics, Details, Categories, Preview) for employers to post new jobs. |
| **Job Form (Single-Page)** | Every field on one screen. Use it in a sidebar, modal or partner page. |
| **Job Listings** | Reactive job listings grid with load-more and bookmark toggle. Updates on search/filter without page reload. |
| **Job Search** | Keyword search bar that drives the job listings grid. |
| **Job Search Hero** | Full-width search form with optional category, location and job type dropdowns, in a horizontal or vertical layout. Good for homepages. |
| **Job Single** | Full job detail view with an apply panel and a "Report this job" control. |
| **Job Stats** | Stat strip showing the number of jobs, companies and candidates. |
| **Recent Jobs** | List of the most recently published jobs. Good for sidebars or homepages. |
| **Similar Companies** | A sidebar card listing companies related to the one being viewed. |

> **WP Career Board Pro** adds more blocks, including AI Chat Search, Application Kanban, Credit Balance, Featured Candidates, Featured Companies, Job Alerts, Job Map, My Applications, Open to Work, Resume Builder, Resume Map, Resume Search Hero and Resume Single. See the Pro docs, page named **Pro Blocks Reference**.

## Adding a block to a page

1. Open any page in the WordPress editor (Gutenberg)
2. Click the **+** button to add a block
3. Search for "Career Board" or the block name
4. Click the block to insert it

## The job board page (recommended layout)

For the main jobs page, add these blocks in order:

1. **Job Search** - the search input
2. **Job Filters** - the filter dropdowns
3. **Job Listings** - the results

The blocks on one page work together, so a search or filter updates the listings.

## Configuring block settings

Some blocks have settings you can adjust in the block sidebar:

**Job Listings:**
- **Job board** - show only jobs assigned to one board; "All boards" shows everything
- **Layout** - Grid (default) or List. Grid offers 3 columns or 4 columns.
- **Jobs per page (0 uses the site default)**
- **Show filter sidebar**
- **Show page heading** - off by default; leave it off when the page already has a heading
- **Filter sidebar** - reorder the filter groups (Job type, Experience, Category, Tags, Location, Job board, Salary) with Move up and Move down, and hide any you do not need. Settings are per-block, so each Job Listings placement can have its own filter order.

**Job Search Hero:**
- **Layout** - horizontal (default) or vertical
- **Search placeholder** and **Button label**
- **Show category filter**, **Show location filter**, **Show job type filter**

**Featured Jobs:**
- **Number of jobs** (default: 3)
- **Section title**
- **Show "View all" link**

**Recent Jobs:**
- **Number of jobs** (default: 5)
- **Section title**
- **Show "View all" link**

**Job Stats:**
- **Show Jobs count**, **Show Companies count**, **Show Candidates count**

Job Form (Single-Page) and Company Archive have no editor settings. You can still set their options in a shortcode. See below.

To access these settings, click the block in the editor and look at the **Block** panel in the right sidebar.

## The setup wizard vs manual setup

The Setup Wizard creates pages with the correct blocks already placed. Add blocks yourself if:
- You want to embed the job board on an existing page.
- You want a custom layout.
- You skipped the wizard.

> **Tip:** Check **Career Board > Settings > Pages** to see which pages are assigned.

## Using shortcodes (classic editor)

If you use the Classic Editor or a page builder without block support, use shortcodes instead. Every block in the table has a shortcode:

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

Paste the shortcode into any page or post. It shows the same output as the block. A shortcode also accepts the block's attributes, for example `[wcb_job_listings boardId="42" perPage="6"]`. Other examples are `[wcb_job_form_simple boardId="42" compact="true"]` and `[wcb_company_archive perPage="12" layout="list"]`.
