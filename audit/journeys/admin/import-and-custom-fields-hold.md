---
id: import-and-custom-fields-hold
priority: high
personas: varundubey, mateo.ruiz, adrian.kowalski
requires: mu:autologin, seed:jobs, seed:companies, seed:resumes, pro:optional
last_verified: 2026-10-24
bug_ref: 10427554352, 10427552850, 10421858162, 10412755150, 10412714930
---

# Imports keep what the owner typed, and custom fields read as part of the form

**Why this journey exists:** the CSV importer read "80,000" as 80 and "$80000" as 0, the WP Job Manager importer dropped every salary that was not a bare number, a re-import set approved jobs back to Pending and wiped descriptions left blank, Field Builder fields sat with no space between them under a theme-sized heading, and the job editor's "Featured image" box suggested listings would show it.

## Steps

1. With Pro, Settings > Import > CSV: import a row with `salary_min` `"80,000"` and `salary_max` `120k` → the job stores 80000 and 120000. A row with `salary_max` `Negotiable` → imported, Warnings: 1, row errors list `salary_max "Negotiable" is not a single amount - left unchanged.` (card:10427554352).
2. WP Job Manager import of `job_listing` posts with `_job_salary` `$80,000` and `80k` → each job stores 80000 as min and max; `Negotiable` leaves salary empty (card:10427554352).
3. Import a CSV as pending, publish the job, re-import a CSV with that title and a blank `status` and `description` → the job stays Published and keeps its description; a new salary in the file is applied. The import help text says re-imports keep the current status (card:10427552850).
4. With Pro, Field Builder: one group per form (job, company, candidate, resume, application) with text, textarea, date, select, checkbox, radio, multi-select, date range and URL fields → on Post a Job, Employer Profile, Candidate Profile, the resume editor and the Apply form: 16px between fields, label 4px above its input, group heading in the small uppercase section style with a rule under it, radio and multi-select options spaced, the date range From/To side by side at 1440 and stacked at 390, no horizontal scroll (card:10421858162).
5. Edit a job in wp-admin → the image box reads "Share image" / "Set share image"; the job page's `og:image` uses it and falls back to the company logo; listings and the job page show the company logo (card:10412714930, card:10412755150).
6. tail debug.log diff → expect ZERO new fatal/warning lines.

## Teardown

Delete QA-RFT jobs, WPJM test posts and Field Builder groups.
