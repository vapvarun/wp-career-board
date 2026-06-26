---
feature: block wp-career-board/employer-dashboard — Applications tab
roles: employer, admin
surface: frontend block + REST (GET /employers/{id}/applications, PATCH /applications/{id}/status)
last_walked: 2026-06-26
---

# Applications review — full browser walkthrough

**What it is:** Where an employer reviews applicants per job, changes application status, reads cover letters, and opens/downloads resumes.
**Where it lives:** `/employer-dashboard/` → **Applications** tab (`HIRING → Applications`, `actions.switchToApplications`). Deep-link a single job's applicants with `?job_apps=<jobId>`.

## As anonymous / logged-in non-employer
1. `/employer-dashboard/` logged out → dashboard gate (Sign In). A plain subscriber gets the "for employers" gate. Application reads require `wcb/view-applications`.

## As employer
1. `?autologin=wcb_demo_employer` → **Applications** tab. A job selector lists the employer's jobs that have applications, with a search box; before picking one: "Select a job above to view its applications." + **Go to My Jobs**.
2. Pick a job (or arrive via the **N applications** chip in My Jobs / `?job_apps=<id>`) → applicants load via `GET /employers/{id}/applications` (employer sees only their own jobs' applicants).
3. **Filter pills with live counts:** All · New (submitted) · Reviewing · Shortlisted · Rejected · Hired. **Layout toggle:** **List** (split panel) vs **Board** (Kanban by status).
4. **List layout:** left = applicant rows (avatar, name, submitted date, unread dot); rows are keyboard-operable (`role="button"`, `tabindex="0"`, Enter/Space via `handleRowKeydown`). Click a row → right detail panel.
5. **Detail panel:** header (avatar, name, email, date) + a **status `<select>`** (Submitted / Reviewing / Shortlisted / Rejected / Hired). Change it → `actions.updateAppStatus` PATCHes `/applications/{id}/status`; inline `role="status"` confirmation "Status updated. The candidate has been notified." (failure → "Could not update the status. Please try again."). The status change fires the `app_status` email to the candidate.
6. **Cover Letter** section renders the applicant's note. **Resume** section (shown only when a resume exists) offers **View Resume** (the resume permalink) and **Download Resume** (the file URL) chips — both open in a new tab (`target="_blank" rel="noopener noreferrer"`).
7. **Board layout:** drag an applicant card between status columns → `actions.onColumnDrop` routes through the same `applyStatusChange()` (same endpoint, same candidate notification).
8. **Pro only:** a **Rank by AI fit** button appears above the list when `wcb_ai_ranking_available` is true (hidden in Free); ranked cards then show an AI score/summary.

## As admin
1. `?autologin=1` → wp-admin → **Career Board → Applications** (`wcb_application` CPT) lists every application; the admin application-detail screen mirrors the same status field. Status set here also notifies the candidate.

## Themes & states
- Reign, BuddyX light, **BuddyX dark** at 1440px + 390px. Split panel stacks on mobile; status badges and the select stay readable in dark mode.
- Empty/edge: job with no applicants → "No applications yet for this job."; no jobs with apps → selector hidden; load failure → `role="alert"` error; resume-less application → Resume section omitted cleanly.

## Contracts guarded
- REST↔JS: applicant list shape (id, applicant_name, email, status, submitted_at, cover letter, resume permalink + URL) matches what `view.js` renders; status PATCH body `{ status }` matches the `ApplicationsEndpoint` enum.
- Status enum parity: the `<select>`, filter pills, and Board columns all use the same five statuses (submitted/reviewing/shortlisted/rejected/hired).
- Security: applications scoped to the employer of the job (`wcb/view-applications`); resume links are not exposed to anonymous.
- a11y: keyboard-navigable applicant rows, `role="status"` status confirmation, 40px tap targets; dark-mode-readable badges.
