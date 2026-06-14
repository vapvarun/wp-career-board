# AI Features Overview

WP Career Board can use AI across the hiring flow: **job description
writing**, **natural-language job search**, **candidate-to-job
matching**, **applicant ranking with summaries**, and **cover-letter
drafting**. All AI features ship in the **Pro** plugin. The Free plugin
defines the gate filters (`wcb_ai_description_enabled`,
`wcb_ai_ranking_available`, `wcb_ai_matching_available`) and the UI
surfaces, so Pro wires the AI in without changes elsewhere.

This page summarises what's in the current release (1.4.3), what's
gated behind an AI provider being configured, and how the pieces fit
together. If you're on Free, treat this as a feature preview: the flows
here are what you get when you upgrade.

## What ships today (1.4.3)

The AI feature set matured in Pro 1.3.0 and has shipped in every
release since. All of the following are live in code:

| Feature | Surface | Who uses it |
|---|---|---|
| **Job embeddings** | Background: every new job is embedded on `wcb_job_created` into the `wcb_ai_vectors` table. | System |
| **Index existing jobs (backfill)** | "Index existing jobs" button under **Settings -> AI Settings** embeds jobs that existed before AI was enabled. | Site owners |
| **AI Chat Search block** | `wcb/ai-chat-search` block (a chat-style search box). An **AI Job Search** page carrying this block is auto-created when Pro sets up. Closest-matched jobs come back via `POST /wcb/v1/ai/match`. | Candidates |
| **AI candidate matches ("Recommended for you")** | Top-N jobs matched to a candidate's resume, available via `GET /wcb/v1/candidates/{id}/matches` and surfaced for the current candidate. | Candidates |
| **AI Job Description Writer** | "Generate with AI" button on the post-a-job form, gated behind `wcb_ai_description_enabled`. Sends title + company type + location to the provider; returns structured HTML. | Employers |
| **AI applicant ranking** | One-click "Rank by AI fit" on the Employer Dashboard. Each applicant gets a 0-100 fit score, a one-line reason, and a TL;DR summary, sorted best-first via `GET /wcb/v1/ai/ranked-applications/{job_id}`. | Employers |
| **Applicant TL;DR summaries** | A one-to-two-sentence neutral summary of each applicant's background, shown alongside the fit score in the dashboard list and detail. | Employers |
| **Auto-score on submit** | Optional. When enabled, new applications are scored in the background (cron) so the dashboard shows AI fit instantly. | Employers |
| **AI cover-letter writer** | In the job apply panel, candidates generate a tailored cover letter from their resume and the job, edit it, then submit via `POST /wcb/v1/jobs/{job_id}/ai-cover-letter`. | Candidates |

In Free, every path is gated. The Chat Search block renders nothing
for visitors (and a configure hint for admins), the description and
cover-letter buttons are hidden, the ranking controls don't appear, and
the AI REST routes aren't registered. No broken UI surfaces in Free.

## The two-provider model

AI is configured per task, not as a single global provider:

- **Analysis & ranking (completions)** drives the description writer,
  applicant ranking, TL;DR summaries, the cover-letter writer, and the
  chat search assistant. Choose **Anthropic Claude**, **OpenAI**, or
  **Ollama**.
- **Embedding & matching** drives job embeddings, AI Chat Search, and
  candidate matches. Choose **OpenAI** or **Ollama** (Claude has no
  embeddings API, so it is not an embedding option).

Each provider has its own API key (or base URL for Ollama) and its own
model selection. You can run, for example, Claude for ranking copy and
OpenAI for embeddings at the same time. See
[02-setup-and-providers.md](02-setup-and-providers.md).

## What gets cached and re-billed

Applicant fit scores, reasons, and summaries are cached in application
post meta (`_wcbp_ai_fit_score`, `_wcbp_ai_fit_reason`,
`_wcbp_ai_summary`, `_wcbp_ai_scored_at`). Re-opening the dashboard
never re-bills the model; ranking computes only what is missing. Force a
re-score by passing `$force` to `AiModule::score_application()` from a
custom integration.

Embeddings are stored once per job in `wcb_ai_vectors` and reused for
every search. Query embeddings (chat search, candidate match) are
computed per request and not stored.

## Free vs Pro at a glance

| | Free | Pro |
|---|---|---|
| **Job posting form** | Full editor | Full editor + AI description writer button |
| **Candidate dashboard / resume** | Resume Builder, manual profile fill | Same + resume feeds AI matching, ranking, and cover letters |
| **Search bar** | Keyword search, taxonomy filters | Keyword + filters + AI Chat Search block + AI Job Search page |
| **Apply panel** | Cover-letter field (manual) | Same + "Generate with AI" cover-letter button |
| **Applications screen / dashboard** | List, status filter, manual review | Same + AI fit score, reason, TL;DR, "Rank by AI fit", optional auto-score |
| **AI Settings tab** | Hidden | Visible under **WP Admin -> Career Board -> Settings -> AI Settings** |
| **Database** | Standard tables only | Adds `wcb_ai_vectors` for embeddings |

## When AI is worth turning on

**Turn it on if:**

- Your board has more than ~30 active jobs and candidates struggle to
  find the right one with keyword search alone.
- You want the description-writer assist to speed up job posting.
- You triage many applicants per role and want a fit-score plus TL;DR
  to prioritise reviews.
- You want candidates to draft cover letters from their resume in one
  tap.

**Skip it if:**

- You're under 20 active jobs and a handful of applications a week. The
  description writer might still help, but matching and ranking won't
  move the needle yet.
- You can't share job / candidate text with a third-party LLM provider
  for privacy / compliance reasons. (Use **Ollama** locally; see
  [02-setup-and-providers.md](02-setup-and-providers.md).)

## How it works under the hood

When AI is enabled and the relevant provider is configured:

1. **On job publish:** Pro hooks `wcb_job_created` and calls
   `AiModule::generate_job_embedding()`. The job title + content are
   embedded by the configured embedding provider and stored as a
   binary-packed float vector in `wcb_ai_vectors`.
2. **On AI Chat Search:** the candidate's query is embedded the same
   way, then cosine-similarity-compared against every stored job
   vector. Top 10 matches are returned via `POST /wcb/v1/ai/match`.
3. **On candidate matching:** the candidate's resume text (built by the
   Pro Resume module via the `wcbp_candidate_resume_data` filter) is
   embedded and matched against job vectors via
   `GET /wcb/v1/candidates/{id}/matches`.
4. **On description writer:** the form sends title + company type +
   location to `POST /wcb/v1/jobs/ai-description`. The completion
   provider returns a structured-HTML description; the editor inserts
   it.
5. **On applicant ranking:** each application's job title + candidate
   resume text is sent to the completion provider with a JSON scoring
   prompt. Returns `{score, reason, summary}` per application, cached in
   post meta. `GET /wcb/v1/ai/ranked-applications/{job_id}` returns the
   list sorted best-first.
6. **On auto-score (optional):** when `wcbp_ai_auto_rank` is on, Pro
   hooks `wcb_application_submitted` and schedules a background
   `wcbp_ai_score_application` cron event so the score is ready before
   the employer looks.
7. **On cover-letter generation:** the candidate's resume text + the job
   are sent to the completion provider via
   `POST /wcb/v1/jobs/{job_id}/ai-cover-letter`; the returned draft is
   inserted into the cover-letter field for editing.

Embedding paths go through the configured embedding driver; completion
paths through the configured completion driver. Both drivers are
OpenAI / Anthropic Claude / Ollama (or one registered via
`wcbp_ai_provider_drivers`).

## What gets sent to the AI provider

The privacy-relevant breakdown:

| Feature | What's sent |
|---|---|
| Job embedding (on publish / backfill) | The job title and description as plain text. |
| AI Chat Search | The candidate's typed query string. |
| AI candidate matches | The candidate's flattened resume text. |
| AI Description Writer | The job title, company type, and location. |
| Applicant ranking / TL;DR | The job title and the applicant's flattened resume text. |
| AI cover letter | The job title, a job-content excerpt, and the candidate's resume text. |

For privacy-sensitive deployments, run **Ollama** locally - see
[02-setup-and-providers.md](02-setup-and-providers.md). Nothing leaves
your server.

## What it doesn't do

- **No auto-rejection.** Ranking returns scores; nothing acts on them
  automatically. Every decision still lands with the employer.
- **No auto-matching emails.** Job Alerts use keyword / filter match,
  not AI semantic match.
- **No content generation outside the writer and cover-letter tools.**
  AI does not auto-fill candidate bios, company "about us" sections, or
  similar.
- **No data sold or shared.** All API calls are between your site and
  the configured provider directly - Wbcom never sees or proxies the
  data.

## Where to go next

- [02-setup-and-providers.md](02-setup-and-providers.md) - pick your
  completion and embedding providers, enter keys, choose models, save.
- [03-candidate-ai-features.md](03-candidate-ai-features.md) - AI Chat
  Search, candidate matches, and the AI cover-letter writer.
- [04-employer-ai-features.md](04-employer-ai-features.md) - the
  description writer, applicant ranking, TL;DR summaries, and
  auto-score.
- [05-blocks-shortcodes-and-developers.md](05-blocks-shortcodes-and-developers.md) -
  block + REST + filter surface for add-on authors.
- [06-troubleshooting.md](06-troubleshooting.md) - common errors and
  what to do about them.
</content>
</invoke>
