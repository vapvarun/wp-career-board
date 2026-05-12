# Multi-Language Job Board

How to run a job board that serves multiple languages, using either
WPML or Polylang. Covers translation strategy, how jobs and
applications behave across languages, and the gotchas to plan around.

If you only serve one language, skip this — but the same principles
apply if you ever decide to expand.

## Two valid strategies

### Strategy 1: Mirror jobs across languages

Each job exists once per language. A "Senior Engineer" post in
English has a French counterpart "Ingénieur Senior" — same role,
different translation.

- **Best for:** boards where the same employer hires across multiple
  language markets, and you want each language community to feel
  native.
- **Cost:** the employer (or you) maintain both copies. Mismatched
  copies — common because translations drift — confuse candidates.

### Strategy 2: Bilingual single listings

Each job exists once, in the employer's language. Candidates browse
in their chosen language but see the original-language listing if no
translation exists.

- **Best for:** boards where roles are often remote / international
  and employers post in whatever language they're most comfortable
  with.
- **Cost:** less duplication, but candidates need to be comfortable
  reading at least the employer's language for some listings.

Most real boards use **Strategy 2** because Strategy 1 demands ongoing
translation labour. Strategy 1 makes sense only when you have
in-house translators or the employers translate before posting.

## What translates and what doesn't

Either strategy, these elements work differently:

| Element | What gets translated |
|---|---|
| **Job title + description** | Strategy 1: per copy. Strategy 2: one copy in source language. |
| **Categories / taxonomy** | Always translated. "Engineering" / "Ingénierie" share the same term ID. |
| **Locations** | Usually not translated (city names — "Paris" stays "Paris"). |
| **Company profiles** | Per profile; if a company hires in multiple languages, they translate the profile once. |
| **Candidate profiles** | Per candidate; the candidate writes their bio in their language of choice. |
| **Email notifications** | Per language; the candidate / employer's user preference determines which template is sent. |
| **UI strings (block labels, buttons)** | Career Board ships with WPML / Polylang-compatible `__()` calls; the plugin shipped translation files handle UI. |
| **Application form custom fields** | Per language (Pro: Field Builder). |

## Setup with WPML

### Prerequisites

- WPML Multilingual CMS + WPML String Translation add-on installed.
- Languages configured in WPML.
- WP Career Board Free or Pro.

### Step 1 — Enable Career Board CPTs in WPML

1. **WPML → Settings → Post Types Translation.**
2. For each Career Board CPT, set translatable:
   - `wcb_job` — **Translatable - use translation if available, fallback to default language.**
   - `wcb_company` — same setting.
   - `wcb_candidate` — same setting if you want candidate profiles
     translatable; usually No (candidates write in their own
     language).
   - `wcb_application` — **Not translatable** (applications are
     written by the candidate in their language and shouldn't be
     duplicated).
3. **WPML → Settings → Taxonomies Translation:** enable for
   `wcb_job_category`, `wcb_job_type`, `wcb_job_region`.

### Step 2 — Configure custom field translation

Career Board stores job details in post meta. For each meta key WPML
should sync or translate:

1. **WPML → Settings → Custom Field Translation.**
2. Recommended:
   - `_wcb_job_salary_min`, `_wcb_job_salary_max` — **Copy** (numbers
     are the same across languages).
   - `_wcb_job_external_url` — **Copy**.
   - `_wcb_job_deadline` — **Copy**.
   - `_wcb_company_id` — **Copy**.
   - `_wcb_job_description_bullets` (if you use them) —
     **Translate**.

If you've added custom fields via Pro's Field Builder, decide
case-by-case based on whether the data is language-specific.

### Step 3 — Translate UI strings

1. **WPML → String Translation.**
2. Filter by domain `wp-career-board` (Free) and
   `wp-career-board-pro` (Pro).
3. Career Board ships PO/MO files for several languages; if your
   language is one of them, the UI strings are pre-translated. Sync
   them in WPML String Translation to import.
4. For untranslated strings, edit inline in WPML and save.

### Step 4 — Run a sample translation

1. Create a job in your default language.
2. In the post editor, find the WPML language switcher and create the
   translation copy. Translate the title and description.
3. Save the translation.
4. Test on the frontend: switch language at the top of the site →
   confirm the translated listing renders.
5. Test the apply flow in the secondary language — confirm form
   labels are translated, the email template the candidate gets is
   in their language.

## Setup with Polylang

### Prerequisites

- Polylang (free or Pro).
- Languages configured under **Languages → Languages.**

### Step 1 — Enable CPTs for translation

1. **Languages → Settings → Custom post types and Taxonomies.**
2. Check:
   - `wcb_job`, `wcb_company`, `wcb_application` (uncheck applications
     if you don't want them duplicated).
   - `wcb_job_category`, `wcb_job_type`, `wcb_job_region`.
3. Save.

### Step 2 — Set fallback behavior

Polylang's defaults are reasonable. Set:

- **Languages → Settings → URL modifications:** subdomain, subdirectory,
  or query parameter. Subdirectories (`/fr/`) are most SEO-friendly.
- **Hide URL language information for default language** — recommended
  unless you want `/en/` prefixed for English content.

### Step 3 — Translate UI strings

1. **Languages → Strings translations.**
2. Filter by group "wp-career-board" / "wp-career-board-pro."
3. Translate each string inline.

### Step 4 — Sample translation

Similar to WPML — create a translation post for a job, verify the
frontend renders correctly with language switching.

## Multi-language with AI features (Pro)

If you have Pro AI enabled and run a multi-language board, a few
things to know:

- **Embeddings work cross-language up to a point.** OpenAI's
  `text-embedding-3-small` model is multilingual — a query in French
  can match a job description in English with degraded but usable
  results. For truly cross-language search, you may want to test the
  embedding behavior with your specific language pair.
- **AI Chat Search shows results in the queried language's listings
  by default.** If a candidate queries in French, only the French-side
  listings (Strategy 1) or the source-language listings translated to
  French in display (Strategy 2) are returned.
- **AI Description Writer respects the input language.** If you write
  bullets in French, the description is in French. The AI doesn't
  auto-translate.
- **AI Application Ranking** sends the description + candidate
  profile in their native languages; the model handles multilingual
  scoring reasonably for major language pairs but degrades for
  low-resource languages.

## Common multi-language gotchas

### Different application form submissions per language

The application form on a French job submits to the same REST endpoint
as the English version. The candidate's user account is the same.
Filter by `_wcb_application_lang` meta on the admin side if you need
language-specific reporting.

### Email templates per language

Career Board sends emails in the recipient's language. If the
candidate registered with French as their UI language, they receive
French notifications regardless of which language the job was posted
in.

To verify: in **Settings → Notifications → Email templates**, each
template should be translatable via WPML / Polylang. If not, the
default language template is used as fallback.

### Search engine considerations

- Use **hreflang tags** so Google knows which listing serves which
  language. WPML / Polylang adds these automatically when configured.
- Avoid translating the same job into too many languages if quality
  suffers — Google penalises auto-translated thin content.

### Pro Boards (Multi-Board) and languages

If you use Pro's Multi-Board feature, each board is a separate
container. You can:

- **One board per language** — "English Jobs" and "French Jobs" as
  separate boards.
- **One board, multi-language listings within** — single board, jobs
  per language inside.

Most teams pick "one board, multi-language listings" for simplicity.
"One board per language" only makes sense if the boards serve genuinely
different markets / employers / pricing.

### Currency and salary

Salary is stored as a number + currency code. WPML / Polylang doesn't
auto-convert. Your options:

- **Show source-currency.** Display "$80k–$100k USD" regardless of UI
  language. Simplest, but UX-suboptimal for non-USD candidates.
- **Convert at display time.** Add a custom filter (`wcb_job_salary_display`)
  that converts to the candidate's preferred currency using a
  third-party rate API. More work, better UX.

### Slugs and URLs

When a job is translated, each translation has its own slug:

- English: `/job/senior-engineer/`
- French: `/fr/emploi/ingenieur-senior/`

If you want each translation's slug to match a hand-picked pattern
(e.g. `/fr/job/...` instead of `/fr/emploi/...`), configure that in
WPML's Permalinks settings.

## Testing checklist for multi-language

Before going live:

- Switch UI language in the header — does the job list reload with
  translated content?
- Search a query in language A — do results appear?
- Apply to a translated job — does the form show in the translated
  language? Does the candidate get the email in their UI language?
- Apply to an un-translated job (Strategy 2 only) — does it work
  gracefully? The candidate should see the source-language listing
  but with translated UI chrome.
- Employer dashboard — does it render in the employer's UI language?
- Hreflang tags present on listing pages — view source, confirm.

## Where to go next

- [01-first-day-as-site-owner.md](01-first-day-as-site-owner.md) — make
  sure the base setup is solid before adding translation.
- [../integrations/](../integrations/) — additional integration notes.
- WPML / Polylang docs for translation engine specifics.
