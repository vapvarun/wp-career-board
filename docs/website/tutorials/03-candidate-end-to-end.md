# Candidate End-to-End: Finding a Job with WP Career Board

A complete walkthrough from "I just landed on the board" to "I got
hired." This is what you'd hand to a candidate explaining how the
site actually works.

Free flow throughout. Pro-only features are flagged inline.

## What a typical job search looks like

| Phase | Time | What you do |
|---|---|---|
| Discover | Hour 0 | Browse the board, get a feel for what's listed |
| Set up | Day 0 | Register, build profile, upload resume |
| Search | Day 0 onward | Find roles that fit, save them, set alerts |
| Apply | Day 0-7 | Apply to a curated shortlist, not 50 random jobs |
| Track | Day 0-30 | Watch status updates, withdraw if not interested anymore |
| Outcome | Day 7-60 | Get hired, or learn from the rejections |

## Step 1 - Browse before you register

Don't register first. Look around.

1. Open `/find-jobs/` on the board.
2. Use the filters: category, location, type, remote-friendly, and the
   salary range slider (a Free feature on the Find Jobs page).
3. Read 3-5 listings that look interesting. You're calibrating: what
   roles does this board actually have? Are they your level? Right
   industry?
4. Open a couple of company profiles to see what employers look like.

If nothing catches your eye, the board may not be the right fit for
you - better to know now than after building a profile.

## Step 2 - Register

When you've found roles worth applying to:

1. Open the Candidate Dashboard while logged out and click **Log in** -
   it links to the site's standard WordPress login/registration screen.
   (There is no separate candidate-registration page.) You can also
   apply as a guest from a job's apply form without an account at all.
2. Register a normal account (name, email, password).
3. Log in. On most boards any logged-in member can use the candidate
   experience straight away. If the site owner enabled "Require
   Candidate Role," they assign you the Candidate role first.

## Step 3 - Build your candidate profile

**Candidate Dashboard → Profile.**

Don't skip this - applications that come from a fully-built profile
look more credible than ones from a blank account.

### Free profile fields

The Free candidate profile is intentionally lightweight:

- **Email** - your contact address.
- **Phone** - optional contact number.
- **Location** - city and country. If you're open to relocating, say
  so in the bio.
- **Bio / About Me** - a rich-text editor. Two or three paragraphs:
  what you've built, what you're looking for, what you're not looking
  for. Be specific.

Your display name, email, and password are edited under **Candidate
Dashboard → Settings** (Account Settings).

### Richer profile and resume fields (Pro)

A structured profile - headline, skills, work experience, education,
languages, links, a profile photo, an "Open to Work" flag, and a public
candidate profile URL discoverable in the employer candidate directory -
ships with WP Career Board Pro (the resume builder and candidate
directory). If the board runs Pro, fill those in too; in Free your
applications carry your bio plus your uploaded resume.

## Step 4 - Your resume

In Free, you attach a resume **file** to each application from the
apply panel - there is no resume stored on the dashboard. The site
owner sets whether the resume is required and the maximum file size
(**Settings → Job Listings → Application Resume File Size**, default
5 MB, range 1-20 MB).

- **PDF preferred.** Word docs work too. Accepted formats: PDF, DOC,
  DOCX.
- **Keep it small.** Most resumes are well under the default 5 MB cap.

> **Pro:** the **My Resumes** tab and the **Resume Builder** (the
> "Edit Resume" view) are Pro features. With Pro you build a resume on
> the dashboard, pick a saved resume in one tap when applying (the PDF
> is generated and attached automatically), and - with an AI provider -
> auto-fill the builder from an uploaded resume. See
> [../ai-features/03-candidate-ai-features.md](../ai-features/03-candidate-ai-features.md).

## Step 5 - Set up job alerts (Pro)

Job alerts require WP Career Board Pro. In Free you check the board
yourself (or bookmark roles, Step 6). When Pro is installed, the
**Job Alerts** tab in the Candidate Dashboard becomes active:

1. **Candidate Dashboard → Job Alerts → New Alert.**
2. Keywords: "senior frontend" or "data engineer" - be specific.
3. Location: city / country / "Remote."
4. Frequency: daily for active job search, weekly if you're casually
   browsing.
5. Save.

You'll get an email when matching jobs are posted. Set up 2-3 alerts
covering your search variations.

> **Pro features:** semantic matching widens alerts beyond exact
> keyword match (a "backend" alert will surface "server-side"
> postings) when an embedding provider is configured.

## Step 6 - Search and shortlist

Once your profile is good, the rhythm is:

1. **Check the board** daily / weekly (or your Pro alerts if Pro is on).
2. **Read the listings** that match. Don't apply yet.
3. **Bookmark the interesting ones** - click the bookmark icon. They
   land in **Candidate Dashboard → Saved Jobs.**
4. **Read the company profile** for each. If the company seems wrong
   for you, unbookmark.
5. **Apply only to your shortlist** - usually 3-8 per week is the right
   pace. Mass-applying to 50 listings is counterproductive.

> **Pro tip:** if AI Chat Search is on the board, try natural language
> queries:
> ```
> Senior React role, remote-friendly, US time zones, $130k+
> ```
> The results rank by relevance, not by date. Helpful when keyword
> search isn't finding the right roles.

### Spot a bad listing? Report it

If a listing looks like a scam, spam, an expired/already-filled role, or
is misleading or offensive, you can flag it for the site's moderators.

1. Open the job page while logged in (you can't report your own listing).
2. Click **Report this job**.
3. Pick a reason: Scam or fraudulent, Spam or advertisement, Expired or
   already filled, Inaccurate or misleading, or Offensive or
   inappropriate.
4. Submit. You'll see a short confirmation.

Reporting is one click per person per job - if you report the same job
again nothing changes. A moderator reviews flagged jobs and either
dismisses the flag (the listing was fine) or unpublishes the job (the
listing was bad). You won't get a personal reply, but you've done your
part to keep the board clean.

## Step 7 - Apply

For each shortlisted job:

1. Click **Apply** on the job page.
2. The form pre-fills your contact details from your account. Verify
   it's right.
3. **Cover letter** - write 4-6 sentences specific to this role. Don't
   paste a generic letter.
4. **Resume** - upload a resume file (or, with Pro, pick a saved
   resume; your most recent one is pre-selected).
5. **Submit.**

You'll get a confirmation email. The application is now visible in
**Candidate Dashboard → My Applications.**

### What to write in a cover letter (paragraph by paragraph)

If the application form has a "Cover letter" textarea (most do):

- **Paragraph 1:** why this specific job. Name the role, name something
  specific about the company.
- **Paragraph 2:** why you. One or two concrete examples from your
  background that match what the role needs.
- **Paragraph 3:** logistics if relevant. Notice period, location,
  start-date constraints.

Don't:

- Restate your resume.
- Apologise for any gap.
- Use "To Whom It May Concern" - the employer's name is usually on the
  company profile.

## Step 8 - Track applications

**Candidate Dashboard → My Applications.**

Each row shows:

- **Job title + company.**
- **Status** - Submitted, Reviewing, Shortlisted, Hired, Rejected,
  Withdrawn, Job Removed.
- **Submitted date** + **last update date.**

When the employer changes your status, you get an email and the
dashboard updates.

### What each status means

- **Submitted** - they have it; no decision yet. Most applications
  stay here for several days before the employer looks.
- **Reviewing** - they've opened it. Activity, but no decision yet.
- **Shortlisted** - under serious consideration. Expect interview
  outreach.
- **Hired** - congratulations. Confirm details with the employer
  outside the platform.
- **Rejected** - not moving forward. The application is closed.
- **Withdrawn** - you (or the system) pulled the application out.
- **Job Removed** - the employer or admin deleted the job posting
  while your application was in flight. Your application is preserved
  in your history.

### What to do if your application sits at "Submitted" forever

- **Most employers take 1-2 weeks to triage.** Be patient.
- **After 3 weeks**, you can email the employer directly through their
  company profile (if they listed contact) for a quick "any update?"
  ping. Once is fine - don't repeat.
- **After 4 weeks with no movement**, assume soft rejection. Move on.

## Step 9 - Withdraw if your situation changes

If you got an offer elsewhere or you're no longer interested:

1. **My Applications → click the role → Withdraw.**
2. Confirm.
3. The employer is notified.

This frees up your "I've already applied" status - if a duplicate of
the role comes up in 3 months, you can re-apply.

## Step 10 - Manage saved jobs and alerts over time

Your search will shift. Update accordingly:

- **Saved Jobs** - periodically clear out ones that expired or
  you no longer want.
- **Job Alerts (Pro)** - if Pro is installed, adjust keywords when your
  alerts get too noisy or too quiet.
- **"Open to Work" flag (Pro)** - the candidate directory and the
  Open-to-Work flag are Pro features. If the board runs Pro, turn the
  flag off once you accept an offer.

## Step 11 - Delete your account when you're done

If you've landed a job and don't want lingering data:

1. **Candidate Dashboard → Settings.** Use **Export my data** to request
   a copy, or **Delete my account** to request erasure.
2. Both actions send a confirmation email to your registered address.
   Click the link in the email to confirm.
3. The request enters WordPress's standard privacy queue and is
   processed by the site administrator. On erasure your applications and
   resumes are deleted and your account is removed.

This runs through WordPress's built-in privacy request flow, so it is
GDPR / privacy compliant. Erasure is irreversible - keep any data you
want before confirming.

## Common candidate mistakes

- **Empty profile, single application.** Employers click through to
  your profile when reviewing. If it's empty, the application looks
  half-hearted regardless of resume quality.
- **One resume, 50 applications.** Tailor at least the cover letter
  per role. Generic = visible.
- **Following up too aggressively.** One polite ping after 3 weeks is
  fine. Daily emails to the employer hurt your candidacy.
- **Ignoring rejection emails.** Read them - sometimes employers
  include genuinely useful feedback or invite you to apply for a
  future role.
- **Forgetting to turn off "Open to Work" after landing a job (Pro).**
  On Pro boards your new employer's HR might be browsing the candidate
  directory and notice.

## Where to go next

- [02-employer-end-to-end.md](02-employer-end-to-end.md) - what the
  employer sees on the other side.
- [../for-candidates/10-troubleshooting.md](../for-candidates/10-troubleshooting.md) -
  if anything specific isn't working.
- [../ai-features/03-candidate-ai-features.md](../ai-features/03-candidate-ai-features.md) -
  if the board has AI features turned on.
