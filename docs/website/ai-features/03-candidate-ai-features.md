# AI Features for Candidates

> **Pro feature set.** These flows only activate when WP Career Board
> Pro is licensed and an AI provider is configured. On Free the search
> still works (keyword + filters) and resumes still upload (manual
> field fill) — but the AI-assisted versions live in Pro.

This page covers what candidates experience when AI is on: the search
bar, the resume upload flow, and the limits of what AI is doing for
them.

## AI Chat Search

A natural-language search input that returns semantically relevant
jobs — not just keyword matches.

### Where it lives

The AI Chat Search block is something the site owner places on a page.
Most boards put it on the **Find Jobs** page, replacing or sitting
above the standard keyword search. If your board doesn't have it, ask
the site admin — it's a `wcb/ai-chat-search` block they add through
the editor.

### What candidates type

Anything natural-language. Examples that work well:

- "Remote React job, US time zones, $100k+"
- "Junior data analyst position, willing to relocate to Berlin"
- "Marketing role at a B2B SaaS startup, 10-50 people, hybrid OK"
- "Senior backend engineer, Rust or Go, no on-call"

What doesn't work as well:

- One-word queries ("developer"). AI Chat Search isn't trying to beat
  Ctrl-F — for one word, the standard keyword search is faster and
  more predictable.
- Queries that only make sense with internal context ("the job from
  last week's newsletter"). The AI doesn't know about your other
  pages.

### What candidates see

After they hit Enter (or wait the auto-submit window — depends on the
site's setting):

- A spinner for 1–3 seconds while the query is embedded and matched.
- A list of jobs ranked by **relevance**, not by date. Each result
  shows the standard listing card.
- Below each result, a small relevance bar — high / medium / low —
  showing how strongly the match was.
- If no jobs match strongly (no result over the threshold), a "no
  strong matches found" message with a link to browse the full board.

The results page is a regular `/find-jobs/?wcb_chat_query=...` URL,
so candidates can bookmark or share it. The search is interactive
(WordPress Interactivity API) — no page reload happens when they
refine the query.

### What it can't do

- **It won't enforce filters you didn't type.** If a candidate types
  "any job," they get the top jobs by general relevance — not "any job
  filtered to Frontend, Berlin, full-time" unless they say so. The
  keyword filters still work as a fallback.
- **It doesn't store search history per candidate.** Each query is
  embedded, matched, and forgotten on the backend (logs aside).
  Candidates can't see "my recent AI searches."
- **It doesn't auto-recommend jobs by email.** That's the Job Alerts
  feature (Pro) — separate path, configured per candidate.
- **It's not real-time.** Newly published jobs get embedded as a
  background job — there may be a 1–2 minute delay between publish
  and search availability. After that the job is fully searchable.

### Tips for candidates

- **Be specific about non-negotiables.** "Remote" is detected; "no
  on-call" is detected; "must use modern frameworks" is too vague to
  filter.
- **Salary specifics help a lot.** "$80–100k" filters better than
  "good salary."
- **Include location AND remote preference.** "Remote, US time zones,
  preferably East Coast" is a complete instruction. "USA" alone is too
  broad.
- **If results feel off**, refine — don't restart. Add one more
  constraint and submit again.

## Resume Parsing

When a candidate uploads a resume on the registration form or the
candidate dashboard, Pro can automatically extract structured data
and populate their profile fields. The candidate then reviews and
confirms.

### Where it happens

- **Candidate registration form** — after the resume upload field, an
  "Auto-fill from resume" button appears (Pro only).
- **Candidate Dashboard → Resume** — a "Parse and update profile"
  button on each uploaded resume.
- **First-time upload during apply flow** — if the candidate is a
  guest applying without an account, parse-on-apply is off by default
  (configurable by the site admin).

### What gets extracted

| Profile field | What AI looks for |
|---|---|
| **Full name** | Top of resume, or contact block. |
| **Email & phone** | Standard contact block. |
| **Headline / current role** | Most recent job title + company. |
| **Skills** | A dedicated skills section, or pulled from job descriptions. |
| **Experience** | Each job entry: company, title, start / end dates, bullet points. |
| **Education** | School, degree, dates. |
| **Languages** | If a dedicated section exists. |
| **Location** | City / country from header or current employer. |

The extracted JSON is shown in a preview panel — the candidate can
**edit any field** before clicking "Apply to my profile."

### What candidates should know

- **The parse isn't always perfect.** Especially on dense, multi-column,
  or image-heavy PDFs. Always review the preview before applying.
- **Sensitive fields stay unparsed.** Date of birth, marital status,
  nationality — even if present in the resume — are ignored. The
  plugin's prompt explicitly excludes them for GDPR / equal-opportunity
  compliance.
- **The original PDF is still attached.** Parsing doesn't replace the
  resume — it just pre-fills your profile so employers see your data
  quickly. The PDF stays on your account and gets attached to every
  application as before.
- **Re-parsing overwrites the fields the AI extracted.** If you've
  manually edited your headline since the last parse, that edit is
  preserved as long as you say "merge" (default) — not "replace" — in
  the preview panel.

### What gets sent to the AI provider

The full extracted text from the PDF — name, contact details, work
history, education. This is one of the most data-rich AI calls in the
plugin. If you don't want resume data leaving your server, the site
admin should switch to the **Ollama** self-hosted provider before
turning on resume parsing.

### Tips for candidates

- **Plain-text-friendly PDFs work best.** A resume exported from Google
  Docs / Word / LaTeX with selectable text gets parsed cleanly. Scans
  of paper resumes (image-only PDFs) parse poorly — convert to text
  first.
- **Standard section headings help.** "Experience" / "Education" /
  "Skills" are reliable. Creative section names like "What I've Been
  Up To" can throw off the parser.
- **Date ranges should be explicit.** "Jan 2022 – Present" parses;
  "since the pandemic" doesn't.
- **Keep skills as a list, not paragraph prose.** Bullet-list skills
  extract better than skills described in long sentences.

## AI for Job Alerts (Pro)

Job Alerts (Pro) can use AI similarity to match newly-published jobs
to a candidate's saved query. The candidate sets up an alert the same
way as standard alerts — keyword, location, frequency — but Pro
expands the matching with semantic similarity, so an alert for
"backend role" will surface a "Server-Side Engineer" job even though
the title doesn't say "backend."

This is automatic when Pro AI is enabled. Candidates don't see it as a
separate feature — their existing alerts just become more useful.

### Behaviour

- Alerts run on the configured cron (daily or weekly per alert).
- Matches are sent the same way (email + in-app notification).
- Below the standard match list, alerts may include an **"AI also
  matched"** section with semantically similar jobs the keyword search
  would have missed.
- Candidates can dismiss "AI also matched" suggestions, which trains
  the alert toward stricter keyword matching for that user.

## What candidates can do without Pro

A reminder: the candidate experience on Free is still complete — you
get everything except the AI-assisted shortcuts.

| Capability | Free | Pro |
|---|---|---|
| Browse all jobs | ✓ | ✓ |
| Keyword search + filters | ✓ | ✓ |
| Apply to jobs | ✓ | ✓ |
| Resume upload | ✓ | ✓ |
| Save jobs to bookmarks | ✓ | ✓ |
| Get email notifications | ✓ | ✓ |
| Natural-language AI search | — | ✓ |
| Auto-parse resume → profile | — | ✓ |
| Semantic match in job alerts | — | ✓ |

## Where to go next

- [04-employer-ai-features.md](04-employer-ai-features.md) — the other
  side of the table.
- [02-setup-and-providers.md](02-setup-and-providers.md) — if you're
  also the site admin.
