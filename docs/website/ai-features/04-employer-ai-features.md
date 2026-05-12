# AI Features for Employers

> **Pro feature surface.** Employers on Free still get the full
> job-posting form, the full applications screen, and standard
> candidate review. The AI-assisted shortcuts below only appear when
> the site has Pro installed, licensed, and a provider configured.

This page covers what employers can do with AI in 1.1.1: the
**Job Description Writer** during posting, and the **AI ranking REST
data** for application triage.

## AI Job Description Writer

A button on the post-a-job form that turns a few form fields into a
job description draft. Employers always review and edit the output
before publishing - nothing is auto-posted.

### Where it lives

On the **Post a Job** form (Employer Dashboard or `/post-a-job/`),
above the description editor:

- A "Generate with AI" button (icon + label).
- Visible only when:
  - Pro is active.
  - The `wcb_ai_description_enabled` filter resolves to true (Pro's
    `AiModule::is_enabled()` returns true when a provider + API key
    are configured).
  - The employer's role has `wcb_post_jobs`.

### What gets sent to the AI

The writer endpoint (`POST /wcb/v1/jobs/ai-description`) accepts three
fields in 1.1.1:

| Field | Source on the form |
|---|---|
| `title` | Job title input |
| `company_type` | Company type / industry input |
| `location` | Job location input |

These three are concatenated into a prompt asking the model to write
a compelling job description with responsibilities and requirements,
in plain text.

> **Note:** 1.1.1 does NOT accept "key bullets" as input. If you want
> to inject role-specific bullets, edit the prompt template by
> filtering at the network level (e.g. a custom `pre_http_request`
> filter), or wait for a later release that adds a `details` field.

### The flow

1. **Fill in the basics:** job title, company, location, type.
2. **Click "Generate with AI."** Spinner appears for 5-15 seconds
   (depends on provider - Claude is fastest, Ollama on CPU is
   slowest).
3. **A drafted description appears** in the editor (replacing the
   editor contents).
4. **Edit it.** Add company-specific details, tone, anything the AI
   missed. The output is a starting point.
5. **Submit the job** as normal.

### Tips for employers

- **A good title produces a better description.** "Senior Backend
  Engineer (Rust + Tokio, billing service)" produces a more specific
  draft than "Software Engineer."
- **The output is generic by default.** AI doesn't know your company.
  Plan to edit at least 30% of the draft for accuracy and tone.
- **Run it through your own eye before publishing.** First-pass drafts
  often miss specific perks, your diversity statement, or HR legal
  language. Treat it like a junior intern's first draft.
- **Each generation costs API credits** (~$0.003 on OpenAI's pricing
  per generation). Regenerating 5 times to get the tone right is still
  under $0.02.

### Limits

- **Output is provider-determined** - no per-request token cap in the
  plugin code.
- **Provider quality matters.** Claude writes more naturally; OpenAI is
  a bit more formal; Ollama / llama3 is the weakest writer.
- **Rate limit:** 30 AI calls per user per hour, shared across all AI
  features.
- **Returns plain text** - the editor inserts whatever the provider
  returned, including any newline handling quirks.

## AI Application Ranking (REST data only in 1.1.1)

Pro can score each application against the job description (0-100 +
one-line reason) via REST. The score column is **not rendered in the
admin applications screen** in 1.1.1 - the data is available, but a
visible UI surface is queued for a later release.

### How to fetch it

```http
GET /wp-json/wcb/v1/ai/ranked-applications/{job_id}
Authorization: <session cookie, user with wcb_view_applications>
```

Returns:

```json
[
  { "application_id": 41, "score": 87, "reason": "Strong React + TypeScript, mid-senior fit" },
  { "application_id": 42, "score": 65, "reason": "Generalist background; some required skills missing" },
  ...
]
```

### Where this is useful today

Even without a built-in admin column, the data is useful in three
common patterns:

- **Custom dashboard widget** - hook `dashboard_setup`, fetch the
  endpoint for jobs the current user owns, display top 5 scored
  applicants per job.
- **CSV export augmentation** - extend the applications CSV exporter
  (Pro feature) to add an AI Score column.
- **Slack / email digest** - schedule a cron that fetches ranking and
  posts the top-3 per active role to a channel.

### What the score means

- **80-100** - strong match. Resume / profile genuinely aligns with the
  job. Worth interviewing.
- **60-79** - partial match. Some required skills present, others
  missing. Read in full before deciding.
- **40-59** - weak match. Limited overlap. Useful as triage signal,
  not as a filter.
- **Below 40** - minimal match. Most fields aren't aligned.

The score is one signal, not the answer. Other things to weigh -
culture fit, location, salary expectations, soft skills - are not
captured.

### How it's computed

Pro sends the **job title** and the **candidate's resume data**
(as supplied by an add-on hooking `wcbp_candidate_resume_data`) to
the configured completions provider with a structured prompt asking
for `{score, reason}`. The result is returned in the REST response;
there's no built-in cache layer in 1.1.1 - every call re-scores.

### Important caveats

- **No `wcbp_candidate_resume_data` filter implementation in Pro core.**
  Without an add-on that hooks this filter and returns the candidate's
  data, the ranking endpoint returns empty strings to the AI -
  resulting in low scores across the board. Wire up the filter (or
  install an add-on that does) before relying on the rankings.
- **Don't auto-reject by score.** AI bias is real; a low score on a
  great candidate (e.g. a career-changer) shouldn't short-circuit
  human review.
- **Recomputation is manual.** No background refresh - call the
  endpoint when you want fresh scores.

### Privacy considerations

This is the most data-heavy AI feature - every applicant's resume
data is sent to the LLM provider on each scoring event. If your
hiring policy doesn't allow that, **switch to Ollama** to keep
everything on your server.

## Combined workflow tip

Today's practical combo:

1. Use **Description Writer** to draft a focused job posting (good
   descriptions produce better embeddings and downstream scoring).
2. As applicants arrive, periodically query
   `/ai/ranked-applications/{job_id}` from a custom dashboard or
   tooling to prioritise who to review first.
3. Wait for a future release for the in-admin column + sort.

## What employers get without Pro

| Capability | Free | Pro 1.1.1 |
|---|---|---|
| Post a job | Yes | Yes |
| Manual job description editor | Yes | Yes |
| AI Description Writer button | No | Yes |
| Review applicants | Yes | Yes |
| Sort by date, status | Yes | Yes |
| AI fit-score column in admin | No | No (queued; REST data is available) |
| Application pipeline (Kanban) | No | Yes |
| Bulk actions on applications | Yes | Yes |

## Where to go next

- [03-candidate-ai-features.md](03-candidate-ai-features.md) - the
  other side of the table.
- [05-blocks-shortcodes-and-developers.md](05-blocks-shortcodes-and-developers.md) -
  the REST + filter surface and how to build the missing admin column.
- [06-troubleshooting.md](06-troubleshooting.md) - when something
  misbehaves.
