---
id: dashboard-feedback-holds
priority: high
personas: guest, ahmed.karim, adrian.kowalski, qa-rft-member
requires: mu:autologin, seed:jobs, seed:companies, seed:resumes, pro:optional
last_verified: 2026-10-25
bug_ref: 10412854820, 10430648050, 10430719814, 10427548090, 10431435128, 10431407539, 10431286004, 10430670040, 10412868653, 10412904033, 10412873869, 10417182252, 10439524696
---

# Every screen says what actually happened

**Why this journey exists:** a batch of cards where the page told the member something untrue or nothing at all: "No jobs yet" to an employer with eight, "No active jobs" while still loading, "match your filters" with no filter set, "Account created" to someone who already had one, a save confirmation off-screen, a date with no label, a preview without the description, a link to a tab that did not open, a logo button the keyboard could not reach, and the browser's own popup for a missing field.

## Steps

1. As `ahmed.karim` (live jobs, none draft), My Jobs → click Draft, Pending, Closed and Rejected → expect "No jobs match these filters" with Clear filters, never "No jobs yet"; Clear filters → All is pressed, the search box is empty and every job is back. Type `zzqq` in the search → the same filtered empty state (card:10412854820).
2. As an employer with a company and no jobs → My Jobs shows "No jobs yet" with Post Your First Job, and no filter bar.
3. As `ahmed.karim`, delay `/wcb/v1/employers/me/*` by 2.5s and hard-reload Overview → expect none of "No applications yet", "No active jobs." or "Nothing needs your attention right now." while it loads; after load the real panels fill (card:10430648050).
4. With Pro, a member with no notifications → Notifications tab shows the shared empty state: bell icon, bold title, hint line (card:10430719814).
5. With every job, company and resume hidden (cookie-scoped `pre_get_posts`) → `/find-jobs/`, `/companies/`, `/find-candidates/` say "No jobs posted yet", "No companies yet", "No resumes yet" with no Clear filters button; add `?search=zz` (`?wcb_resume_search=zz` on Find Candidates) or type in the search → "No … match your filters" with Clear filters; Clear → back to the unfiltered copy (card:10427548090).
6. As a Subscriber with no Career Board role, `/employer-registration/` → Hire Talent → expect "Set Up Your Employer Profile", button "Continue", "By continuing you agree…"; submit with a company name → "Your profile is ready!" and no "You are now logged in". As a guest the old "Create an Employer Account" / "Create Account" / "Account created!" copy is unchanged (card:10431435128).
7. As a guest on the same form, submit empty → focus moves to First Name with "Enter your first name." under it in dark red and `aria-invalid="true"`; typing clears it; a bad email, empty company and a 5-character password each get their own message in turn; required labels carry a red asterisk (card:10412873869).
8. As `adrian.kowalski`, edit a resume → Save resume → "Resume saved" appears beside the button, in view, inside `role="status"`; force the PUT to 500 → "Could not save your resume. Please try again." beside the button; at 390 nothing scrolls sideways (card:10431407539).
9. `/find-jobs/` → List view → each footer reads "Apply by <date>" (card:10431286004).
10. As `ahmed.karim`, Post a Job with a two-paragraph description → step 4 Preview shows a Description section with both paragraphs as text; typed `<script>` shows as text, never runs (card:10430670040).
11. As a candidate with Pro, a company page → Set Up Alerts → lands on `/candidate-dashboard/#alerts` with Job alerts open (card:10412868653).
12. As `ahmed.karim`, Profile → Tab to the logo control → focus lands on `#wcb-company-logo` with a 2px ring on "Upload Logo"; Space opens the file picker; Tab moves on to Company Name. Upload CV on the candidate dashboard shows the same ring (card:10412904033).
13. A job page as guest → Apply now in the hero and, outlined, in the sticky sidebar; with `add_filter( 'wcb_job_single_sidebar_cta', '__return_false' )` only the hero button (card:10417182252).
13a. As `mateo.ruiz`, `/post-a-job/`, the employer dashboard Post a job tab and a page with the simple job form → each has one visible h1, "Post a Job" (editing a job: "Edit Job"), styled like Find Jobs; a page that starts with its own H1 heading block gets no second one (card:10439524696).
13b. **Signed-out gates have a heading (card:10439833481).** Signed out, `/post-a-job/` and `/employer-registration/` each have one h1 at 1440 and 390. With `users_can_register` off, Employer Registration shows the shared gate card titled `Employer registration` with the closed-registration message and a Sign in button. Restore `users_can_register`.
14. tail debug.log diff → expect ZERO new fatal/warning lines.

## Teardown

Delete the QA-RFT member, its company and any QA-RFT job; remove any temporary mu-plugin.
