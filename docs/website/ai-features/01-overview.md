# AI Features Overview

WP Career Board can use AI to improve three parts of the hiring flow:
**job description writing**, **natural-language job search**, and
**candidate-to-job matching**. All AI features ship in the **Pro**
plugin; the Free plugin defines the gate filter so an add-on or Pro
can wire AI in without changes elsewhere.

This page summarises what's actually in the 1.1.1 release, what's
gated behind the AI provider being configured, and what's queued for a
later release. If you're on Free, treat this as a feature preview: the
flows here are what you get when you upgrade.

## What ships today (1.1.1)

| Feature | Surface | Who uses it |
|---|---|---|
| **Job embeddings** | Background: every new job is embedded on `wcb_job_created` into the `wcb_ai_vectors` table. | System |
| **AI Chat Search block** | `wcb/ai-chat-search` block. Candidate types a natural-language query; closest-matched jobs come back via the `/wcb/v1/ai/match` REST endpoint. | Candidates |
| **AI Job Description Writer** | "Generate with AI" button on the post-a-job form, gated behind `wcb_ai_description_enabled`. Sends title + company type + location to the provider. | Employers |
| **Candidate-to-job match data (REST)** | `GET /wcb/v1/candidates/{id}/matches` returns top-N matches for a candidate. No built-in UI surface in 1.1.1 - consumed by add-ons or custom templates. | Add-on developers |
| **Application ranking data (REST)** | `GET /wcb/v1/ai/ranked-applications/{job_id}` returns each application's AI fit score (0-100). No built-in admin column UI in 1.1.1. | Add-on developers |

In Free, all five paths are gated. The Chat Search block returns an
empty list, the description button is hidden, the match / rank REST
routes aren't registered. No broken UI surfaces in Free.

## What's queued for a later release

Listed honestly so expectations are calibrated:

- **Resume parsing** - the `wcbp_candidate_resume_data` filter exists
  for an add-on to supply parsed data, but Pro does not ship a parser
  in 1.1.1.
- **AI fit-score column on the applications admin screen** - data is
  available via REST, but the column UI isn't registered.
- **Sort by AI fit score** - depends on the column above.
- **Per-application refresh button** - manual recomputation is via
  `AiModule::rank_applications()` from a custom integration; no
  built-in button.
- **"Test connection" + "Reindex all jobs" buttons in AI Settings** -
  validation happens only on real calls; existing jobs (before AI was
  enabled) don't auto-backfill embeddings.
- **WP-CLI `wp wcb ai *` commands** - not registered.
- **AI lifecycle action hooks** (e.g. `wcbp_ai_job_embedded`,
  `wcbp_ai_application_scored`) - not fired in 1.1.1; instrument from
  your add-on by wrapping the public AiModule methods.
- **Semantic match for Pro Job Alerts** - alerts use keyword match in
  1.1.1.

If any of these is a blocker for your use case, file on the support
board so it can be prioritised for the next release.

## Free vs Pro at a glance

| | Free | Pro |
|---|---|---|
| **Job posting form** | Full editor | Full editor + AI description writer button |
| **Candidate dashboard** | Resume upload, manual profile fill | Same (Pro does NOT auto-parse in 1.1.1) |
| **Search bar** | Keyword search, taxonomy filters | Keyword + filters + AI Chat Search block |
| **Applications screen** | List, status filter, manual review | Same UI as Free in 1.1.1; AI ranking data available via REST for custom UI |
| **AI Settings tab** | Hidden | Visible under **WP Admin → Career Board → Settings → AI Settings** |
| **Database** | Standard tables only | Adds `wcb_ai_vectors` for embeddings |

## When AI is worth turning on

**Turn it on if:**

- Your board has more than ~30 active jobs and candidates struggle to
  find the right one with keyword search alone.
- You want the description-writer assist to speed up job posting.
- You're building an add-on or custom UI that wants AI match / rank
  data from the REST endpoints.

**Skip it if:**

- You're under 20 active jobs and a handful of applications a week -
  the description writer might still help, but match / rank don't
  move the needle yet.
- You can't share job / candidate text with a third-party LLM
  provider for privacy / compliance reasons. (Use **Ollama** locally;
  see [02-setup-and-providers.md](02-setup-and-providers.md).)
- You expect resume parsing, AI-ranked applications visible in the
  admin, or AI-powered alerts in 1.1.1 - those aren't shipping yet.

## How it works under the hood

When AI is enabled and a provider is configured:

1. **On job publish:** Pro hooks `wcb_job_created` and calls
   `AiModule::generate_job_embedding()`. The job title + content are
   embedded by the configured provider and stored as a binary-packed
   float vector in `wcb_ai_vectors`.
2. **On AI Chat Search:** the candidate's query is embedded the same
   way, then cosine-similarity-compared against every stored job
   vector. Top 10 matches are returned via `POST /wcb/v1/ai/match`.
3. **On description writer:** the form sends title + company type +
   location to `POST /wcb/v1/jobs/ai-description`. The provider
   returns a description; the editor inserts it.
4. **On candidate matching (REST):** the candidate's resume data
   (provided by an add-on via `wcbp_candidate_resume_data`) is
   embedded and matched against job vectors.
5. **On application ranking (REST):** each application's candidate
   resume data is sent to the completions provider with a 0-100
   scoring prompt. Returns `[{application_id, score, reason}]`.

All paths go through the same `AiModule` and the configured provider
(OpenAI / Anthropic Claude / Ollama). One provider config drives all
features - no per-feature API key.

## What gets sent to the AI provider

The privacy-relevant breakdown:

| Feature | What's sent |
|---|---|
| Job embedding (on publish) | The job title and description as plain text. |
| AI Chat Search | The candidate's typed query string. |
| AI Description Writer | The job title, company type, and location. |
| Candidate match / rank (REST) | The candidate's resume data array, as supplied to `wcbp_candidate_resume_data`. |

For privacy-sensitive deployments, run **Ollama** locally - see
[02-setup-and-providers.md](02-setup-and-providers.md). Nothing leaves
your server.

## What it doesn't do (1.1.1)

- **No auto-rejection.** Application ranking returns scores; nothing
  acts on them automatically. Every decision still lands with the
  employer.
- **No auto-matching emails.** Pro Job Alerts (when active) use
  keyword match in 1.1.1, not AI semantic match.
- **No content generation outside the writer.** AI does not auto-fill
  candidate bios, company "about us" sections, etc.
- **No data sold or shared.** All API calls are between your site and
  the configured provider directly - Wbcom never sees or proxies the
  data.

## Where to go next

- [02-setup-and-providers.md](02-setup-and-providers.md) - pick a provider,
  enter your key, save settings.
- [03-candidate-ai-features.md](03-candidate-ai-features.md) - what
  candidates do with AI Chat Search.
- [04-employer-ai-features.md](04-employer-ai-features.md) - the
  description writer flow and how to access ranking data.
- [05-blocks-shortcodes-and-developers.md](05-blocks-shortcodes-and-developers.md) -
  block + REST + filter surface for add-on authors.
- [06-troubleshooting.md](06-troubleshooting.md) - common errors and
  what to do about them.
