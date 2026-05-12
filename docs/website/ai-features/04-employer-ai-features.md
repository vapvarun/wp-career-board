# AI Features for Employers

> **Pro feature set.** Employers on Free still get the full job-posting
> form, the full applications screen, and standard candidate review.
> The AI-assisted shortcuts on this page only appear when the site has
> Pro installed, licensed, and a provider configured.

This page covers the two AI features employers actually interact with:
the **Job Description Writer** during posting, and the **AI Fit
Score** on the applications screen.

## AI Job Description Writer

A button on the post-a-job form that turns a title + a few bullets
into a full, well-structured job description draft. Employers always
review and edit the output before publishing — nothing is auto-posted.

### Where it lives

On the **Post a Job** form (Employer Dashboard or `/post-a-job/`
page), inside the description editor area:

- A small **"Generate with AI"** button (icon + label) above the
  description editor.
- Visible only when Pro is active, the AI provider is configured, and
  the employer's role has `wcb_post_jobs`.

### The flow

1. **Fill in the basics:** job title, company, location, type
   (full-time / contract / etc.).
2. **In the "Key points" input** that appears next to the AI button,
   type 3–7 bullets covering what makes the role specific. Example:
   ```
   - Mid-senior backend role
   - Python, Django, AWS
   - Building API for a fintech startup
   - 5 day hybrid in NYC, 2 days WFH
   - Stock options, $130-160k base
   ```
3. **Click "Generate with AI."** A spinner appears for 5–15 seconds
   (depends on provider — Claude is fastest, Ollama on CPU is
   slowest).
4. **A drafted description appears** in the editor with:
   - An opening "About the role" paragraph.
   - Bulleted responsibilities.
   - Bulleted requirements / nice-to-haves.
   - A closing "About us" / "Why work here" paragraph (if the
     employer's company profile has copy AI can pull from).
5. **Edit it.** Add company-specific details, tone, anything the AI
   missed. The output is a starting point, not the final answer.
6. **Click "Regenerate"** if the whole draft feels off. The button
   keeps your bullets but re-runs the prompt — useful if the first
   draft was too formal / too casual / wrong tone.
7. **Submit the job** as normal.

### What gets sent to the AI provider

The job title, type, location, and your bullets. Optionally the
company profile copy (so the "Why work here" paragraph reflects your
actual company). No candidate data — this is pre-posting, before any
applications exist.

### Tips for employers

- **The bullets matter more than you'd think.** A vague bullet
  ("backend role") produces a generic description. A specific one
  ("backend role using Rust + Tokio on a billing service handling 50k
  req/s") produces a description that filters out candidates who
  aren't a fit.
- **Include salary if you want it in the output.** AI won't invent a
  number. If you write "$80–120k," it lands in the description.
- **Add company tone as a bullet.** "Write in a casual,
  developer-to-developer tone" or "professional, formal" is honoured.
- **Don't paste the entire job description back in as a bullet.** That
  short-circuits the generator. Use bullets for facts; let the AI
  handle prose.
- **Run it through your own eye before publishing.** Description writer
  drafts often miss your company's specific perks, your diversity
  statement, or the legal language your HR team wants. Treat it like
  a first-pass intern who needs review.

### Limits

- **Output is capped at ~800 words** to prevent runaway generations.
- **The provider matters.** Claude writes more naturally; OpenAI is a
  bit more formal; Ollama (llama3) is the weakest writer of the three
  — usable for outlines, less great for finished copy.
- **Each generation costs API credits.** A single description
  generation is ~$0.005 on OpenAI's pricing. If you regenerate 5
  times to get the tone right, that's still under $0.05 — but at
  scale (100 generations / day × $0.005 = $0.50/day) it adds up.
- **Doesn't translate.** AI generates in the same language as your
  bullets. To get a French description, write your bullets in French.

## AI Application Ranking

A score column on the applications screen showing how well each
applicant matches the job — 0–100 with a one-line explanation.

### Where it lives

**Career Board → Applications** (admin), or the per-job applications
screen accessed from the Employer Dashboard. The **AI Fit** column
appears when:

- Pro is active and the AI provider is configured.
- The job has been embedded (visible if there's no "pending embed"
  badge on the job).
- The employer / admin viewing has `wcb_view_applications`.

### What employers see

For each application row:

- A score: **0–100**.
- A one-line reason: e.g. "Strong React + TypeScript experience,
  matches mid-senior level."
- A small refresh icon to re-compute the score (e.g. after a candidate
  re-uploads their resume).

The column is **sortable** — click the header to rank applications
descending by AI fit. Combined with the standard "Status" filter, it
turns "I have 80 applications, where do I start?" into "show me the
top 10 unread by fit."

### What the score means

- **80–100** — strong match. Resume / profile genuinely aligns with the
  job. Worth interviewing.
- **60–79** — partial match. Some required skills present, others
  missing. Read in full before deciding.
- **40–59** — weak match. Limited overlap, but might be worth a quick
  scan if you're short on better applicants.
- **Below 40** — minimal match. Most fields aren't aligned. Usually
  safe to skip if you have higher-scoring candidates available.

The score is one signal, not the answer. Other things to weigh —
culture fit, location, salary expectations, soft skills — are not
captured.

### How it's computed

The plugin sends the **job description** and the **candidate's parsed
profile** (or full resume text if not parsed) to the configured
completions provider with a structured prompt asking for `{score: 0-100,
reason: "..."}`. The result is cached against the application row in
the database. Re-computation only happens when:

- The employer clicks the per-row refresh icon, or
- The candidate's profile / resume is updated, or
- The job description is edited.

No background job re-ranks on a schedule — that would be expensive and
noisy.

### Tips for employers

- **Don't auto-reject below a threshold.** AI bias is a real concern;
  a low score on a great candidate (e.g. a career-changer) shouldn't
  short-circuit human review. Use the score for triage order, not for
  filtering.
- **Read the reason, not just the number.** A 65 might be more
  interesting than an 85 if the reason is "good cultural fit but
  light on the AWS side" vs. "matches every required skill but no
  team-lead experience."
- **The score reflects what was sent.** If a candidate's resume parse
  is sparse, their score will be low because the AI didn't get full
  information — re-parse from the candidate's dashboard to fix.
- **Refresh after major edits.** If you tighten the job description
  after the first wave of applicants, click "Reindex this job" and
  then refresh per-row scores.

### Privacy considerations

This is the most data-heavy AI feature in the plugin — every applicant's
profile / resume text is sent to the LLM provider on each scoring
event. If your hiring policy doesn't allow that:

- **Switch to Ollama** to keep everything on your server (slower but
  no data leaves).
- **Disable AI Application Ranking** while leaving other features on
  — there's no dedicated toggle in 1.1.1, but you can hide the column
  with a per-board CSS rule or by filtering the column registration
  hook. Future versions will expose a per-feature toggle.

## Combined workflow tip

The two AI features pair well:

1. Use the **Description Writer** to draft a focused job posting (good
   descriptions → better embeddings → better scoring downstream).
2. Use **AI Fit Score** when applicants roll in to rank by relevance,
   then move down the list reviewing each in detail.

A specific, focused description gets you both better-matched candidates
*and* more accurate scoring on the applications you do get.

## What employers get without Pro

| Capability | Free | Pro |
|---|---|---|
| Post a job | ✓ | ✓ |
| Manual job description editor | ✓ | ✓ |
| AI Description Writer | — | ✓ |
| Review applicants | ✓ | ✓ |
| Sort by date, status | ✓ | ✓ |
| AI Fit Score column + sort | — | ✓ |
| Application pipeline (Kanban) | — | ✓ |
| Bulk actions on applications | ✓ | ✓ |

## Where to go next

- [03-candidate-ai-features.md](03-candidate-ai-features.md) — the
  other side of the table.
- [05-blocks-shortcodes-and-developers.md](05-blocks-shortcodes-and-developers.md) —
  embedding AI blocks, shortcode equivalents, hook customisation.
- [06-troubleshooting.md](06-troubleshooting.md) — when things misbehave.
