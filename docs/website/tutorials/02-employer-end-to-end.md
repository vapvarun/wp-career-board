# Employer End-to-End: Hiring with WP Career Board

A complete walkthrough of one hiring round from the employer's
perspective: register, set up your company, post the role, review
applicants, hire, and close out. This is what you'd hand to a new
employer joining your board.

Free flow throughout. Pro-only steps are flagged inline.

## What a typical hiring round looks like

| Phase | Time | What you do |
|---|---|---|
| Setup | Day 0 | Register, set up company profile, buy credits if needed |
| Post | Day 0 | Draft the role, publish |
| Promote | Day 0–3 | Share on socials, internal channels |
| Review | Day 3–14 | Read applications, shortlist, interview |
| Decision | Day 14–21 | Make offers, mark hired, close the role |
| Close out | Day 21+ | Archive the role, manage company profile for next round |

## Step 1 — Register as an employer

Most boards have a public registration link. Look for one of these:

- A button on the main job board (`/find-jobs/`) saying "Post a Job."
- A link in the site menu to "Employers" or "Hire Talent."
- A direct URL like `/employer-registration/`.

If you can't find it, your site admin may not have wired the link.
Email them — registration is open but unlinked.

Fill in:

- Your name and email.
- A password (or a magic-link if the site uses that).
- Your company name (used to create your company profile).
- Optional: phone, role at the company.

You'll get a welcome email. Some sites require you to verify your
email by clicking the link before you can post — if so, do that
first. Then log in.

## Step 2 — Complete your company profile

You're now in the **Employer Dashboard**. Before posting your first
job, complete the company profile — applicants see it on every job.

**Employer Dashboard → Company.**

- **Logo** — square or rectangular, at least 200×200, max 2 MB. PNG /
  JPG. Most boards display 120–200 px.
- **Banner** — optional, 1200×400 or similar wide format.
- **Tagline** — one sentence (under 100 chars). "Building open source
  developer tools" is good; "We are a leading provider of innovative
  solutions" is filler.
- **About** — two or three paragraphs. What you do, who you serve,
  what's the team like. Specifics beat marketing copy.
- **Website** — full URL including `https://`.
- **Locations** — at least one city. Multi-location companies can list
  several.
- **Size** — 1–10, 11–50, 51–200, etc. Helps candidates pre-filter.
- **Founded** — year. Optional but adds trust signal.
- **Industry** — pick the closest match.

Save. Open your company in a private window — you should see a public
company page at `/company/your-slug/`. If it looks empty or wrong, go
back and fix.

## Step 3 — Acquire posting credits (if your board uses them)

**If posting is free on this board,** skip this step.

**If the board uses credits:**

1. **Employer Dashboard → Credits.** Your current balance shows at
   the top.
2. Click **Buy Credits.**
3. Pick a package (1, 5, 10, 20 credits — depends on what the admin
   configured).
4. Complete the checkout. WooCommerce / PMPro / MemberPress flows
   depend on what the site uses.
5. After the order completes, your balance updates within 1–2 minutes
   (sometimes faster).

If your balance doesn't update:

- Refresh the dashboard.
- Check your order is marked "Completed" (not "Processing" or
  "Pending Payment").
- Contact the site admin with your order number if it still doesn't
  show.

## Step 4 — Draft and post your first job

**Employer Dashboard → Post a Job.**

### Fill the basic info

- **Title** — write the title a candidate would search for. "Senior
  Frontend Engineer" beats "Software Engineer III" beats "Code
  Wizard." Be specific without being clever.
- **Company** — auto-filled from your profile. Verify it's right.
- **Job type** — Full-time / Part-time / Contract / Internship /
  Temporary.
- **Category** — one or more. Don't dump everything in "Other."
- **Location** — city, state, country. If remote, set both a "Remote"
  flag AND a primary city (helps with time-zone matching).
- **Deadline / Apply by** — the date the listing expires. Default 30
  days from posting. Leave blank for "until filled" if your board
  supports it.

### Write the description

The description is the single most important thing about the listing.
A few principles:

- **Open with what the role is**, not what your company does. A
  candidate skims this in 8 seconds — anchor them in the role first.
- **List 5–8 responsibilities** as bullets.
- **List 4–6 requirements** as bullets. Split nice-to-have from must-
  have if relevant.
- **Be honest about expectations.** Time zones, on-call, travel,
  in-office days — say it up front, save time on both sides.
- **Salary range.** If your jurisdiction requires posting salary (NYC,
  parts of EU, California for certain roles), include it. Even where
  not required, posting a range improves application quality
  dramatically.
- **About us / why work here** — last, three or four bullets max.
  Don't repeat the company profile.

> **Pro tip:** if you have Pro installed and AI Description Writer
> enabled, you can paste 4–6 key bullets and click **Generate with AI**.
> Always edit the output — it's a starting point, not a final draft.
> See [../ai-features/04-employer-ai-features.md](../ai-features/04-employer-ai-features.md).

### Application settings

- **Apply through this site** — applications come into the Employer
  Dashboard. Standard flow.
- **Apply on company site** — an external URL. Candidates click out to
  your applicant tracking system (Greenhouse, Lever, Workable, etc.).
  The URL must start with `http://` or `https://`.
- **Apply by email** — applicants send straight to an email address.
  Use this only if you don't want to use the dashboard at all.

If you're new to the board, use **Apply through this site.** It's
the only way Career Board's email + status tracking + AI scoring
(Pro) all work together.

### Other settings

- **Featured listing** — costs extra credits / fee depending on board
  config. Promotes the job to the top of the listing.
- **Anonymous posting** — hide the company name (useful for sensitive
  hires or stealth-mode startups). Candidates see "Confidential."

Click **Submit Job.**

## Step 5 — Wait for approval (if applicable)

Some boards require admin approval for new postings. If yours does:

- Status reads **Pending Review.**
- Site admin gets an email + admin notice.
- Approval typically arrives within 24 hours on a moderated board.

Once approved (or immediately, on auto-approve boards), status flips
to **Published** and the job goes live at `/job/your-job-slug/`.

If it's been more than 48 hours with no movement, follow up with the
site admin — sometimes the approval queue gets missed.

## Step 6 — Promote the listing

Posting alone gets you maybe 5% of the applicants you should get.
Promotion gets you the other 95%.

- **Share the URL** on LinkedIn, Twitter, your company's Slack /
  Discord, internal company channels.
- **Set up a job alert** so candidates who match the criteria get
  notified automatically — if the board has alerts enabled,
  candidates do this on their side.
- **Cross-post to one or two niche boards.** Hacker News Who's Hiring,
  WeWorkRemotely, a Slack jobs channel, etc. Career Board has an RSS
  feed (`/feed/jobs/`) that some aggregators consume automatically.

## Step 7 — Watch applications come in

**Employer Dashboard → Applications.**

Each application shows:

- **Candidate name** and current role / headline.
- **Submitted date.**
- **Status** (defaults to "Submitted").
- **Resume** download link.
- **Cover letter / answers** to any custom questions you added.
- **(Pro)** AI Fit Score 0–100 + one-line reason.

Click into an application to see the full detail and the candidate's
public profile (if they made it public).

You'll get an email per application (as long as the admin has
notifications wired correctly). If applications are arriving faster
than you can read them in real time, set yourself a 9 AM / 4 PM block
to triage rather than reacting to each email.

## Step 8 — Triage applications

A simple triage pass:

1. **Status filter to "Submitted."** Hides applications you've already
   triaged.
2. **Read each in 30–60 seconds.** Focus on resume + cover letter.
   Don't read every detail yet.
3. **Move to "Reviewing"** if you'd consider talking to them — even
   if not yet sure.
4. **Move to "Rejected"** if it's clearly not a fit (wrong role, no
   right-to-work, etc.).
5. **Move to "Shortlisted"** if it's an obvious "yes, let's interview."

Each status move fires an email to the candidate (good — keeps them
informed). "Rejected" sends a courtesy email; you can customise the
template in **WP Admin → Career Board → Settings → Notifications →
Email templates** (admin-only).

> **Pro tip:** if AI Fit Score is enabled, sort by it. Read top 10
> first. Don't auto-reject by score — see
> [../ai-features/04-employer-ai-features.md](../ai-features/04-employer-ai-features.md).

## Step 9 — Interview shortlisted candidates

The board doesn't run interviews — you do that externally. The board
helps you stay organised:

- **Add notes** to each application (private to your account). Use
  these for "interview Tuesday, follow up Friday" or interview
  feedback.
- **(Pro)** Move applications through the **Application Pipeline** —
  a Kanban board with custom stages (Phone screen → Tech interview
  → Final → Offer → Hired).
- **Tag** applications with custom tags if you've enabled the field
  builder (Pro).

## Step 10 — Make a decision

When you've picked your candidate:

1. **Move to "Hired."** Email fires to the candidate.
2. **Email the candidate directly** with the offer — the board's
   "Hired" email is informational, not the offer letter. Always send
   the formal offer separately with details.
3. **Reject the rest.** Move remaining shortlist candidates to
   "Rejected" with a courteous message. Some employers send a short
   personal email instead of relying on the template — your call.

## Step 11 — Close out the role

Once filled:

1. **Edit the job → Status → "Filled" (or close it).** This hides it
   from the public listing. Existing applications stay visible to
   you.
2. **(Optional)** Export the applications list to CSV from
   **Employer Dashboard → Applications → Export** for your records.
3. **(Optional)** Update your company profile with the new hire's
   role / team if you list staff.
4. **Refresh credits** — buy more if you have another role coming up.

## Step 12 — Build a candidate bench (Pro)

If you've installed Pro, the **Find Candidates** feature lets you
search the candidate directory directly:

- **Employer Dashboard → Find Candidates.**
- Search by skill, location, headline, "Open to Work" flag.
- Save candidates to a private list to revisit when a new role opens.
- Send a short message asking if they'd be interested in your next
  role.

Free doesn't include outbound candidate search — only inbound (i.e.
candidates apply to you).

## Common employer mistakes

- **Posting and walking away.** The first 48 hours after posting is
  when most quality applications come in. Be ready to review.
- **No salary range.** You're competing for attention with listings
  that do disclose. Most candidates filter you out without one.
- **Slow status updates.** Candidates check the dashboard for status
  changes. A week of "Submitted" feels like a no — they assume
  rejection. Move to "Reviewing" within 48 hours even if you haven't
  read them fully yet.
- **Rejecting silently.** Sending a "Rejected" status with the default
  email is better than ghosting. Candidates remember employers who
  closed the loop and apply again for future roles.
- **Posting the same job twice.** Confusing for candidates. Edit and
  republish the existing posting instead of creating a duplicate.

## What you should walk away with

After one full hiring round you'll know:

- How long applications take to start arriving on your specific board.
- The ratio of applications to quality matches (helps you decide
  whether to widen or narrow next time).
- What questions you wish you'd added to the application form (Pro:
  field builder).
- Whether the board's defaults work for you or you need to adjust
  (notification settings, default deadline, etc.).

## Where to go next

- [03-candidate-end-to-end.md](03-candidate-end-to-end.md) — see the
  other side of every step you just took.
- [04-monetizing-your-board.md](04-monetizing-your-board.md) — if you
  ARE the site owner deciding how postings get paid for.
- [../for-employers/12-troubleshooting.md](../for-employers/12-troubleshooting.md) —
  the troubleshooting reference for when something doesn't behave.
