# Video Script: Product Overview

**Duration:** 2:30
**Purpose:** Explain what WP Career Board is and why someone would use it
**Tone:** Helpful, direct — like a colleague showing you something useful
**Target:** WordPress site owners considering a job board

---

## Script

### INTRO (0:00 – 0:20)

**VISUAL:** Job listings page on a community site — cards loading, someone typing in search, results filtering live

**NARRATOR:**
"If you want a job board on your WordPress site, you've probably looked at the options and found a lot of plugins built on shortcodes and jQuery — technologies from 2012. WP Career Board is different. It's built entirely on Gutenberg blocks and the WordPress Interactivity API. Let me show you what that means in practice."

---

### THE JOB BOARD (0:20 – 0:50)

**VISUAL:** Jobs page — filter by keyword, location, job type, salary range, remote toggle. Results update live.

**NARRATOR:**
"This is the job listings page. Candidates can filter by keyword, location, job type, category, salary range, and whether the role is remote. Every filter updates the list without a page reload — that's the Interactivity API. No jQuery, no custom React bundle, no extra libraries."

**VISUAL:** Job single page — full description, company info, Apply button, social share buttons

**NARRATOR:**
"Each job has its own page with a full description, company profile, and apply button. The page outputs valid schema.org JobPosting structured data automatically — so your jobs are eligible for Google Jobs without any extra SEO plugin."

---

### EMPLOYER FLOW (0:50 – 1:20)

**VISUAL:** Employer Dashboard — overview stats, jobs list, post a job button

**NARRATOR:**
"Employers manage everything from a frontend dashboard. No wp-admin access needed."

**VISUAL:** Multi-step job form — step 1 (basics), step 2 (details), step 3 (review)

**NARRATOR:**
"The job form is a guided multi-step process. Title, location, type, salary, description, deadline. They submit — and the job either publishes immediately or goes to the admin moderation queue, depending on your settings."

**VISUAL:** Applications view — list of applicants, status badges, status dropdown

**NARRATOR:**
"When applications come in, employers see them here. They can move each applicant through five statuses — Submitted, Reviewing, Shortlisted, Rejected, or Hired. Every status change sends an automatic email to the candidate."

---

### CANDIDATE FLOW (1:20 – 1:45)

**VISUAL:** Candidate Dashboard — My Applications tab, saved jobs, overview panel

**NARRATOR:**
"Candidates have their own dashboard to track every application they've submitted. They can see the current status, save jobs for later, and withdraw if they change their mind. And they can apply as a guest — no account required."

---

### ADMIN (1:45 – 2:05)

**VISUAL:** Setup Wizard — two-step wizard creating pages

**NARRATOR:**
"Setup takes two minutes. The Setup Wizard creates all the pages you need — Jobs, Post a Job, Employer Dashboard, Candidate Dashboard — with the blocks already inserted."

**VISUAL:** Settings page — tabs: Job Listings, Pages, Notifications, Emails

**NARRATOR:**
"The settings are straightforward — moderation, notifications, email templates, page assignments. Nothing you'll need to read a manual to understand."

---

### INTEGRATIONS (2:05 – 2:20)

**VISUAL:** Reign Theme community site with job board matching the design

**NARRATOR:**
"WP Career Board is included with Reign Theme. If you're already on Reign, the job board inherits your design automatically."

**VISUAL:** BuddyPress activity stream showing a job post

**NARRATOR:**
"BuddyPress and BuddyBoss Platform integrate automatically — job posts appear in the activity stream, employer and candidate roles map to member types."

---

### CLOSE (2:20 – 2:30)

**VISUAL:** wbcomdesigns.com product page

**NARRATOR:**
"The free version handles the full employer-to-hire workflow. WP Career Board Pro adds a Kanban pipeline, multiple boards, resume search, WooCommerce credit system, and more. Link in the description."

**ON-SCREEN TEXT:** wbcomdesigns.com/downloads/wp-career-board/

---

## Production Notes

**Visual style:** Screen recordings from local development — real UI, not mockups. Light interface theme. Clean browser window, no bookmarks bar.

**Music:** Understated, instrumental. Low volume — narration is primary.

**Screenshots needed:**
- `jobs-page-layout.png` — job listings page with filters visible
- `job-single-page.png` — job single with apply button
- `employer-dashboard-overview.png` — employer dashboard
- `job-form-step1.png` — job form step 1
- `employer-dashboard-applications.png` — applications with status badges
- `candidate-dashboard-applications.png` — candidate dashboard
- `setup-wizard-welcome.png` — wizard step 1
- Reign Theme site with job board running (screenshot needed from Reign demo)
- BuddyPress activity stream with a job post (screenshot needed)
