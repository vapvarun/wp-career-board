# First-hour walk - WP Career Board (Free, then Free + Pro)

Run on a FRESH install (throwaway Docker WordPress, own port), default theme first, then Reign / Astra / Kadence.
Capture mail with bodies (`pre_wp_mail` mu-plugin). Check 1440 and 390. Realistic names only.
Last walked: 1.8.4 (2026-11-12), Twenty Twenty-Five, Free then Pro. Result: core path passes.

## Site admin, minutes 0-60
1. Activate Free: redirects to Setup wizard (`page=wcb-setup`), no error notice.
2. Wizard: Pages > Sign-ups > Jobs > Emails > Spam Protection > Sample data. Check each screen's promise
   against what it installs (6 pages created, `wcb_settings` saved, sample data = 8 jobs, 3 companies).
3. Dashboard: with sample data shows 8 active jobs; after wizard with no data every widget has an empty state.
4. Visitor view: Find Jobs, Find Companies render; Employer Registration / Post a Job / dashboards gate with a sign-in message.
5. Employer registers (Hire Talent), reads the verify email, follows the link, signs in (lands on the employer dashboard).
6. Employer posts a job through the 4-step form; draft survives a reload; owner receives "New job pending approval".
7. Owner approves (Jobs > Approve > confirm modal); employer receives "Your job has been approved"; job is public.
8. Candidate registers (Find a Job), verifies, signs in, applies with a PDF; employer gets "New application", candidate gets "Application submitted".
9. Employer changes status (Shortlisted); candidate receives "Your application status has been updated".
10. Activate Pro: redirects to the Pro mini wizard; dashboard shows the PRO badge, Resumes and Credits menus appear.
11. A plain WordPress subscriber (native register) sees the candidate dashboard; employer pages offer "Register as an employer".

## Decided (not bugs)
- Employers see their company HQ + Remote + Other in the Location dropdown, not every site location (`Locations::get_dropdown_terms`).
- Free application requires a CV upload (PDF/DOC/DOCX, 5 MB).

## Known first-hour findings (cards in Possible Bugs, 2026-11-12)
- Sign-ups default off leaves a closed Employer Registration page in the nav.
- The sign-up page is named Employer Registration but candidates use it.
- Pro mini wizard repeats the four Free steps.
- Approve on the All list removes the row.

## Not covered
Reign / Astra / Kadence themes, RTL, Pro resume builder and credits first-hour journeys, multisite, real SMTP delivery.
