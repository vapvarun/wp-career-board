# Find Resumes

> **Pro feature** — Requires WP Career Board Pro.

The **Find Resumes** block displays a public-facing archive of candidate resumes. Employers and admins can browse candidate profiles filtered by skills, location, and job title — without waiting for candidates to apply.

## How It Works

Candidates build their resumes using the Resume Builder. When a candidate sets a resume to **Public**, it appears in the Find Resumes archive. Resumes set to **Private** are never shown here.

Employers can browse the archive, filter by skills and location, and click through to a full resume page.

## Adding Find Resumes to a Page

1. Create a new page (e.g., "Find Candidates" or "Resume Database")
2. In the WordPress editor, add the **Find Resumes** block
3. Publish the page

No settings configuration is required — the block renders all public resumes automatically.

## Filtering

Visitors can filter resumes by:
- **Skills** — matches against the Skills section of each resume
- **Location** — matches against the candidate's listed location
- **Job Title** — keyword match against the candidate's most recent job title

Filters update the list without a page reload.

## Candidate Privacy

Candidates control their resume visibility:
- **Private** (default) — visible only to employers who receive an application from that candidate
- **Public** — visible in the Find Resumes archive

Candidates can change visibility at any time from the Resume Builder or their dashboard.

## Linking to Resume Single Page

When a visitor clicks a resume in the archive, they are taken to the **Resume Single** page — a full formatted view of that candidate's resume. The Resume Single block handles this display.

To enable this:
1. Create a page with the **Resume Single** block
2. Assign it in **WP Career Board → Settings → Pages → Resume Page**
