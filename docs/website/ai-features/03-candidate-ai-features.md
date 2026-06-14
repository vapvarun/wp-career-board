# AI Features for Candidates

> **Pro feature surface.** These flows activate when WP Career Board Pro
> is active and the relevant AI provider is configured. On Free the
> keyword search and standard apply flow still work.

This page covers what candidates experience with AI on (1.4.3): the
**AI Chat Search**, **AI candidate matches** ("Recommended for you"),
and the **AI cover-letter writer** in the apply panel.

## AI Chat Search

A chat-style natural-language search box that returns semantically
relevant jobs - not just keyword matches.

### Where it lives

The AI Chat Search block (`wcb/ai-chat-search`) is placed on a page via
the block editor. When Pro sets up, an **AI Job Search** page carrying
this block is created automatically. The site owner can also add the
block to the **Find Jobs** page, above the standard keyword search. If
your board doesn't have it, ask the admin to add the block.

### What candidates type

Anything natural-language. Examples that work well:

- "Remote React job, US time zones, $100k+"
- "Junior data analyst position, willing to relocate to Berlin"
- "Marketing role at a B2B SaaS startup, 10-50 people, hybrid OK"
- "Senior backend engineer, Rust or Go, no on-call"

What doesn't work as well:

- One-word queries ("developer"). For a single word, the standard
  keyword search is faster and more predictable.
- Queries that depend on context outside the listings ("the job from
  last week's newsletter"). The AI doesn't know about your other pages.

### What candidates see

The block presents a conversation-style box:

- The typed query appears as a message bubble.
- A spinner for 1-3 seconds while the query is embedded and matched.
- An assistant reply noting how many jobs matched, followed by a list of
  matched job cards (title, company), ranked by **relevance**, not date.

The results render in-place via the Interactivity API - no page reload
happens when they refine the query. If a search fails, the box shows a
"Search failed. Please try again." message rather than breaking.

### What it can't do

- **It won't enforce filters you didn't type.** "Any job" returns the
  top jobs by general relevance. The standard keyword filters still work
  as a fallback.
- **It doesn't store search history per candidate.** Each query is
  embedded, matched, and forgotten on the backend.
- **It doesn't auto-recommend jobs by email.** Job Alerts use keyword /
  filter match, not AI semantic match.
- **It needs job embeddings to exist.** Embeddings are generated at
  `wcb_job_created`. Jobs from before AI was enabled appear only after
  the admin runs **Index existing jobs** (see
  [02-setup-and-providers.md](02-setup-and-providers.md#backfilling-embeddings-for-existing-jobs)).
- **It has a per-user rate limit:** 30 AI calls per hour across all AI
  features. Heavy testers will hit a 429 and need to wait.

### Tips for candidates

- **Be specific about non-negotiables.** "Remote" surfaces; "no on-call"
  surfaces; "must use modern frameworks" is too vague.
- **Salary specifics help.** "$80-100k" filters better than "good
  salary."
- **Include location AND remote preference.** "Remote, US time zones,
  preferably East Coast" is a complete instruction.
- **If results feel off**, refine - add one more constraint and resubmit.

## AI candidate matches ("Recommended for you")

Beyond typing a query, AI can match jobs to a candidate's resume
automatically. The candidate's resume (built in the Pro Resume Builder)
is flattened into text, embedded, and compared against every job vector.
The top jobs are returned as "Recommended for you" cards.

This runs through `GET /wcb/v1/candidates/{id}/matches` for a specific
candidate, and `POST /wcb/v1/ai/match` for the currently signed-in
candidate. It needs an **embedding provider** (OpenAI or Ollama)
configured and job embeddings to exist.

Quality depends on how complete the candidate's resume is - the more
sections filled in (experience, skills, summary), the sharper the
matches.

## AI cover-letter writer

In the job apply panel, a candidate can generate a tailored cover letter
instead of writing one from scratch.

### The flow

1. Open a job and start to apply.
2. Click **Generate with AI** next to the cover-letter field.
3. Pro sends the candidate's resume text plus this job (title and a
   content excerpt) to the analysis provider via
   `POST /wcb/v1/jobs/{job_id}/ai-cover-letter`.
4. A first-person draft (about 150-200 words, using only resume-supported
   details) is inserted into the cover-letter field.
5. The candidate **edits** the draft, then submits the application as
   normal.

### Notes

- The button appears only when Pro is active and the analysis provider
  is configured (the apply panel reads an `aiCoverEnabled` flag).
- The draft uses only details the resume supports - it won't invent
  experience. A sparse resume produces a thin letter.
- Same 30-calls-per-hour rate limit applies.
- If the candidate applies with a Resume Builder resume that has no PDF,
  Pro generates and attaches the PDF automatically, so the application
  still carries a downloadable file.

## How resume data reaches the AI

Pro's **Resume module** implements the `wcbp_candidate_resume_data`
filter: it returns the candidate's grouped resume sections, which the AI
module flattens into text for matching, ranking, and cover letters. This
is wired in Pro core - no add-on is required. Resumes imported from WP
Job Manager Resumes are read too (legacy experience / education meta),
so matching and cover letters work on migrated data.

There is no separate "auto-parse an uploaded PDF into profile fields"
step - candidates fill in the Resume Builder (or import), and that
structured data is what feeds the AI.

## What candidates get without Pro

| Capability | Free | Pro 1.4.3 |
|---|---|---|
| Browse all jobs | Yes | Yes |
| Keyword search + filters | Yes | Yes |
| Apply to jobs | Yes | Yes |
| Resume upload (attaches to applications) | Yes | Yes |
| Save jobs to bookmarks | Yes | Yes |
| Get email notifications | Yes | Yes |
| Natural-language AI search (AI Chat Search block) | No | Yes |
| "Recommended for you" AI candidate matches | No | Yes |
| AI cover-letter writer in the apply panel | No | Yes |
| Semantic match in job alerts | No | No |

## Where to go next

- [04-employer-ai-features.md](04-employer-ai-features.md) - the other
  side of the table.
- [02-setup-and-providers.md](02-setup-and-providers.md) - if you're also
  the site admin.
</content>
