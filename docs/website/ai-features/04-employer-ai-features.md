# AI Features for Employers

> **Pro feature surface.** Employers on Free still get the full
> job-posting form, the full applications screen, and standard candidate
> review. The AI-assisted shortcuts below appear when the site has Pro
> active and the relevant provider configured.

This page covers what employers can do with AI in 1.4.3: the **Job
Description Writer** during posting, and **AI applicant ranking** with
fit scores, reasons, and TL;DR summaries on the Employer Dashboard.

## AI Job Description Writer

A button on the post-a-job form that turns a few form fields into a job
description draft. Employers always review and edit the output before
publishing - nothing is auto-posted.

### Where it lives

On the **Post a Job** form (Employer Dashboard or `/post-a-job/`), above
the description editor:

- A "Generate with AI" button (icon + label). Present on both the full
  job form and the simple job form.
- Visible only when:
  - Pro is active.
  - The `wcb_ai_description_enabled` filter resolves to true (Pro's
    `AiModule::is_enabled()` returns true when an analysis or embedding
    provider is configured).
  - The employer's role has `wcb_post_jobs`.

### What gets sent to the AI

The writer endpoint (`POST /wcb/v1/jobs/ai-description`) accepts three
fields:

| Field | Source on the form |
|---|---|
| `title` | Job title input |
| `company_type` | Company type / industry input |
| `location` | Job location input |

These are composed into a prompt asking the model to write a compelling
job description (role overview, responsibilities, requirements) and to
return clean semantic HTML (`<h3>`, `<p>`, `<ul><li>`), no markdown or
code fences.

### The flow

1. **Fill in the basics:** job title, company, location, type.
2. **Click "Generate with AI."** Spinner appears for 5-15 seconds
   (depends on provider).
3. **A structured-HTML draft appears** in the editor.
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
- **Each generation costs API credits** (~$0.003 on OpenAI's pricing per
  generation). Regenerating to get the tone right is still cheap.

### Limits

- **Output is provider-determined** - no per-request token cap in the
  plugin code.
- **Provider quality matters.** Claude (Sonnet) writes more naturally;
  OpenAI is a bit more formal; Ollama / llama3 is the weakest writer.
- **Rate limit:** 30 AI calls per user per hour, shared across all AI
  features.

## AI applicant ranking

Pro scores each application against its job (0-100 fit + a one-line
reason + a neutral TL;DR summary) and surfaces it right on the Employer
Dashboard - no custom code required.

### Where it lives

On the **Employer Dashboard** applications view, when an analysis
provider is configured (the `wcb_ai_ranking_available` filter is true):

- A **"Rank by AI fit"** control sorts the loaded applications best-first.
- Each applicant row shows an **AI fit score** (e.g. "87%"), the
  **reason**, and a **TL;DR summary** of the candidate's background.
- The detail view shows the same fit, reason, and summary.

Under the hood the dashboard calls
`GET /wcb/v1/ai/ranked-applications/{job_id}`, which returns each
application's `{application_id, score, reason, summary}` sorted by score.

### Caching - you are not re-billed

Fit score, reason, and summary are cached per application in post meta
(`_wcbp_ai_fit_score`, `_wcbp_ai_fit_reason`, `_wcbp_ai_summary`,
`_wcbp_ai_scored_at`). Re-opening the dashboard reuses cached values;
ranking only computes applications that have never been scored. A force
re-score is available via `AiModule::score_application( $id, true )` from
a custom integration.

### Auto-score on submit (optional)

Under **Settings -> AI Settings**, enable **"Auto-score applicants on
submit"** (`wcbp_ai_auto_rank`). When on, Pro hooks
`wcb_application_submitted` and schedules a background
`wcbp_ai_score_application` cron event ~30 seconds after each new
application, so the dashboard shows AI fit instantly without anyone
clicking "Rank by AI fit." It's skipped when no analysis provider is
configured.

### What the score means

- **80-100** - strong match. Resume / profile genuinely aligns with the
  job. Worth interviewing.
- **60-79** - partial match. Some required skills present, others
  missing. Read in full before deciding.
- **40-59** - weak match. Limited overlap. Useful as triage signal, not
  as a filter.
- **Below 40** - minimal match. Most fields aren't aligned.

The score is one signal, not the answer. Culture fit, location, salary
expectations, and soft skills are not captured.

### How it's computed

Pro sends the **job title** and the **candidate's resume text** to the
analysis provider with a JSON prompt asking for
`{score, reason, summary}`. The candidate's resume text comes from the
Pro Resume module via the `wcbp_candidate_resume_data` filter (wired in
Pro core - no add-on required). The model reply is parsed even when it's
wrapped in code fences, so scores are real rather than always zero.

### Important caveats

- **Don't auto-reject by score.** AI bias is real; a low score on a
  great candidate (e.g. a career-changer) shouldn't short-circuit human
  review. Pro never auto-rejects.
- **Sparse resumes score low.** A candidate who hasn't filled in the
  Resume Builder gives the AI little to work with.
- **Re-score is on demand.** Cached scores stay until you force a
  recompute or auto-score handles a brand-new application.

### Privacy considerations

Ranking is the most data-heavy AI feature - each applicant's resume text
is sent to the analysis provider on first scoring (then cached). If your
hiring policy doesn't allow that, **set the analysis provider to
Ollama** to keep everything on your server.

## What employers get without Pro

| Capability | Free | Pro 1.4.3 |
|---|---|---|
| Post a job | Yes | Yes |
| Manual job description editor | Yes | Yes |
| AI Description Writer button | No | Yes |
| Review applicants | Yes | Yes |
| Sort by date, status | Yes | Yes |
| AI fit score + reason on the dashboard | No | Yes |
| AI TL;DR applicant summaries | No | Yes |
| One-click "Rank by AI fit" | No | Yes |
| Auto-score applicants on submit | No | Yes |
| Application pipeline (Kanban) | No | Yes |
| Bulk actions on applications | Yes | Yes |

## Where to go next

- [03-candidate-ai-features.md](03-candidate-ai-features.md) - the other
  side of the table.
- [05-blocks-shortcodes-and-developers.md](05-blocks-shortcodes-and-developers.md) -
  the block, REST, and filter surface.
- [06-troubleshooting.md](06-troubleshooting.md) - when something
  misbehaves.
</content>
