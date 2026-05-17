# AI Features for Candidates

> **Pro feature surface.** These flows only activate when WP Career
> Board Pro is licensed and an AI provider is configured. On Free the
> keyword search and standard apply flow still work.

This page covers what candidates experience when AI is on in 1.1.1:
the AI Chat Search bar. (Other AI capabilities you may have heard
about - resume parsing, AI-powered alerts - are not shipping in
1.1.1; see [01-overview.md](01-overview.md#whats-queued-for-a-later-release)
for the queued list.)

## AI Chat Search

A natural-language search input that returns semantically relevant
jobs - not just keyword matches.

### Where it lives

The AI Chat Search block is something the site owner places on a page
via the block editor. Most boards put it on the **Find Jobs** page,
above the standard keyword search. If your board doesn't have it, ask
the site admin to add the `wcb/ai-chat-search` block.

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
  last week's newsletter"). The AI doesn't know about your other
  pages.

### What candidates see

After they hit Enter:

- A spinner for 1-3 seconds while the query is embedded and matched.
- A list of jobs ranked by **relevance**, not by date. Each result
  is a job card.

The results render in-place via the Interactivity API - no page
reload happens when they refine the query.

### What it can't do

- **It won't enforce filters you didn't type.** If a candidate types
  "any job," they get the top jobs by general relevance - not "any
  job filtered to Frontend, Berlin, full-time" unless they say so. The
  standard keyword filters still work as a fallback.
- **It doesn't store search history per candidate.** Each query is
  embedded, matched, and forgotten on the backend.
- **It doesn't auto-recommend jobs by email.** The board's standard
  Job Alerts system handles outreach; in 1.1.1 alerts use keyword
  match, not AI semantic match.
- **It's not real-time for very fresh jobs.** Embeddings are
  generated synchronously at `wcb_job_created`, so the window between
  job publish and AI-searchable is typically under a second - but the
  embedding step depends on the provider being reachable.
- **It has a per-user rate limit:** 30 AI calls per hour across all
  AI features. Heavy testers will hit a 429 and need to wait.

### Tips for candidates

- **Be specific about non-negotiables.** "Remote" surfaces; "no
  on-call" surfaces; "must use modern frameworks" is too vague.
- **Salary specifics help.** "$80-100k" filters better than "good
  salary."
- **Include location AND remote preference.** "Remote, US time zones,
  preferably East Coast" is a complete instruction.
- **If results feel off**, refine - add one more constraint and
  resubmit.

## What about resume parsing?

WP Career Board Pro 1.1.1 does **not** ship a resume-parser. The
filter `wcbp_candidate_resume_data` exists so an add-on can supply
parsed data into the AI matching / ranking flow, but Pro itself does
not auto-fill profile fields from an uploaded resume.

In practice this means: candidates upload a resume the usual way and
fill in their profile fields manually. The resume PDF gets attached
to their applications as a file - employers can download and read it
themselves.

When a resume parser ships in a later release, this page will be
updated to document the flow.

## What about AI in Job Alerts?

Pro Job Alerts run keyword + filter matching in 1.1.1. They do not use
semantic / AI matching to widen results. If you set up an alert for
"backend role," it surfaces jobs with "backend" in title or content -
not jobs that say "server-side" but mean the same thing.

Semantic alert matching is queued for a later release.

## What candidates get without Pro

| Capability | Free | Pro 1.1.1 |
|---|---|---|
| Browse all jobs | Yes | Yes |
| Keyword search + filters | Yes | Yes |
| Apply to jobs | Yes | Yes |
| Resume upload (attaches to applications) | Yes | Yes |
| Save jobs to bookmarks | Yes | Yes |
| Get email notifications | Yes | Yes |
| Natural-language AI search (AI Chat Search block) | No | Yes |
| Auto-parse resume into profile fields | No | No (queued) |
| Semantic match in job alerts | No | No (queued) |
| Top-N "jobs that match my profile" (via REST) | No | Yes (custom UI required) |

## Where to go next

- [04-employer-ai-features.md](04-employer-ai-features.md) - the other
  side of the table.
- [02-setup-and-providers.md](02-setup-and-providers.md) - if you're
  also the site admin.
