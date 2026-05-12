# AI Features Overview

WP Career Board can use AI to improve four parts of the hiring flow:
**job search**, **resume parsing**, **job description writing**, and
**application ranking**. All AI features are part of the **Pro**
plugin — the Free plugin ships the hook surface and the disabled
default so a custom add-on or Pro can wire AI in without changes
elsewhere.

This section explains what AI does, when it's worth enabling, and how
to set it up. If you're on Free, treat this as a feature preview: the
flows below are what you get when you upgrade. The setup, privacy, and
cost notes apply equally either way.

## The four AI features

| Feature | Who uses it | What it does | Plan |
|---|---|---|---|
| **AI Chat Search** | Candidates | Natural-language search: "remote React job, $100k+, US time zones" returns semantically matched listings | Pro |
| **Resume Parsing** | Candidates | Extracts skills, experience, education from an uploaded resume into structured profile fields | Pro |
| **AI Description Writer** | Employers | Generates a draft job description from a title + key bullets, writable / editable inline | Pro |
| **AI Application Ranking** | Employers / admins | Scores each applicant against the job (0–100) with a one-line reason — useful when you have dozens of applicants per role | Pro |

In Free, the same code paths exist but skip cleanly: the AI Chat Search
block returns an empty result set, the description writer button is
hidden, and the ranking column doesn't render. There is no broken UI
in Free — the AI surface is fully behind a feature gate.

## Free vs Pro at a glance

| | Free | Pro |
|---|---|---|
| **Job posting form** | Full editor | Full editor **+** AI description writer button |
| **Candidate dashboard** | Resume upload, manual profile fill | Resume upload **+** auto-parse to profile fields |
| **Search bar** | Keyword search, taxonomy filters | Keyword search, taxonomy filters **+** AI Chat Search block |
| **Applications screen** | List, status filter, manual review | List, status filter, manual review **+** AI fit score (0–100) per applicant |
| **AI Settings panel** | Hidden | Visible under **Career Board → Settings → AI** |
| **Database** | Standard tables only | Adds `wcb_ai_vectors` for embeddings |
| **Cron jobs** | Standard cron | Standard cron **+** background embedding generation on job publish |

## When AI is worth turning on

**Turn it on if:**

- Your board has more than ~30 active jobs and candidates struggle to
  find the right one with keyword search alone.
- You get 20+ applications per role and reviewing each one in full takes
  too long.
- Most of your traffic is from candidates whose first language isn't
  English — natural-language search bridges the keyword gap.
- You post niche / technical roles where the job title doesn't match
  the actual skills (a "Full Stack Engineer" who really needs Rust
  experience won't surface to a "Rust Developer" keyword searcher).

**Skip it if:**

- You're under 20 active jobs and a handful of applications a week —
  AI doesn't move the needle and the API spend isn't worth it.
- You can't share candidate or job text with a third-party LLM
  provider for privacy / compliance reasons. (You can still run
  **Ollama** locally — see [02-setup-and-providers.md](02-setup-and-providers.md).)
- You don't have someone who can keep an eye on monthly API costs and
  cut them off if a runaway script pushes spend up.

## How it works under the hood

When AI is enabled and a provider is configured, here's what happens
behind the scenes — useful to understand if you're debugging "why
isn't search returning the job I expect?"

1. **On job publish:** the plugin asks the configured embeddings
   provider to convert the title + description into a 1536-dim
   floating-point vector. That vector is stored in the
   `wcb_ai_vectors` table, keyed by job ID.
2. **On resume upload:** Pro asks the completions provider to extract
   structured fields (skills array, experience list, education list)
   from the resume PDF. The result is saved to candidate post meta.
3. **On AI Chat Search:** the candidate's query is embedded the same
   way, then cosine-similarity-compared against every stored job
   vector. Top N matches are returned ranked.
4. **On application ranking:** the candidate's profile + the job
   description are sent to the completions provider with a scoring
   prompt; the returned `{score, reason}` is cached against the
   application row.
5. **On description writer:** the title + key bullets are sent to the
   completions provider with a job-description prompt; the response is
   inserted directly into the editor.

All five paths go through the same `AiModule` and the configured
**provider** (OpenAI / Anthropic Claude / Ollama). One provider
config drives all features — there is no per-feature API key.

## What gets sent to the AI provider

This is the privacy-relevant breakdown. Worth understanding before you
enable.

| Feature | Sent to provider |
|---|---|
| AI Chat Search | The candidate's typed query (e.g. "senior frontend in Berlin"). No personally identifying info unless they type it. |
| Resume Parsing | The full extracted text of the uploaded resume — including the candidate's name, contact info, work history. |
| AI Description Writer | The job title, key bullets, and any context the employer types in. The employer is the one composing — no candidate data is involved. |
| AI Application Ranking | The job description AND the candidate's parsed profile (or resume text if not parsed). Sensitive — applies to every applicant on every refresh unless cached. |

If you're concerned about resume / candidate data leaving your server,
run **Ollama** locally. Setup is documented in
[02-setup-and-providers.md](02-setup-and-providers.md).

## What it doesn't do

Some things AI is *not* doing here, so you know:

- **No auto-rejection.** AI Application Ranking surfaces a score and a
  one-line reason — it never auto-rejects, hides, or shuffles
  applications. Every decision still lands with the employer.
- **No auto-matching emails.** When a candidate's resume is parsed, the
  plugin doesn't send out unsolicited "you might like this job"
  emails. Job alerts use the standard alert system (manual setup or
  Pro job alerts), not AI matching for outreach.
- **No content generation outside the writer.** AI doesn't auto-fill
  candidate profile bios, employer "about us" sections, or anything
  else. Only the four documented entry points generate text.
- **No data sold or shared.** All API calls are between your site
  and the configured provider directly — Wbcom never sees or
  proxies the data.

## Where to go next

- [02-setup-and-providers.md](02-setup-and-providers.md) — Pick a provider,
  enter your key, save settings.
- [03-candidate-ai-features.md](03-candidate-ai-features.md) — What candidates
  actually do with AI Chat Search and resume parsing.
- [04-employer-ai-features.md](04-employer-ai-features.md) — Description
  writer and application ranking walkthroughs.
- [05-blocks-shortcodes-and-developers.md](05-blocks-shortcodes-and-developers.md) —
  Embedding AI blocks, shortcodes, and developer hooks for
  customisation.
- [06-troubleshooting.md](06-troubleshooting.md) — Common errors and what
  to do about them.
