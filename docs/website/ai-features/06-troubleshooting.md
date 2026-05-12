# AI Troubleshooting

What can go wrong with AI features in 1.1.1, in order of how often
it actually happens.

If you're on Free and don't see the AI Settings tab at all - that's
expected, not a bug. AI Settings only appears with Pro active and
licensed.

## "Invalid API key" / "Authentication failed"

The most common setup error.

**Check:**

1. **Right key for the right provider.** OpenAI keys start `sk-`,
   Anthropic keys start `sk-ant-`, Ollama doesn't use a key. Copy
   from the provider dashboard, not from a stale email.
2. **No leading / trailing whitespace.** Particularly when pasting
   from a terminal - a hidden newline breaks auth.
3. **Key isn't disabled or expired.** Confirm on the provider
   dashboard. OpenAI shows last-used time, useful for spotting an
   accidentally-revoked key.
4. **Right organisation / project (OpenAI).** If your OpenAI account
   has multiple projects, the key must belong to the project that
   has the billing source attached.

If the key works in `curl` but not in the plugin, check **wp-cron**
isn't being blocked by a host firewall stripping outbound HTTPS to
non-whitelisted domains.

## "Quota exceeded" / "Rate limit hit"

Three different things can produce this kind of error:

### Provider-side monthly cap

**OpenAI:** Dashboard → Limits → check current usage vs. cap.
Raise the monthly cap (Billing → Limits) or wait for the next billing
cycle.

**Anthropic:** Console → Limits → adjust monthly cap.

**Ollama:** Doesn't have a quota - but it can run out of memory.
Watch `dmesg` for OOM-killer events.

### Provider-side per-minute rate limit

If you see HTTP 429 from OpenAI / Anthropic, you're hitting their
per-minute or per-day rate ceiling. Reduce concurrent calls (e.g.
slow down a bulk backfill loop).

### Plugin-side rate limit (Pro)

The AI endpoints enforce a **30 calls per user per hour** ceiling
via transients. Response:

```json
{
  "code": "wcb_rate_limit",
  "message": "AI request limit reached. Please try again later.",
  "data": { "status": 429 }
}
```

This is shared across all four AI REST endpoints per user. Heavy
testing during setup will hit it. Wait an hour, or in dev clear the
transient:

```bash
wp transient delete "wcbp_ai_rate_{user_id}"
```

## "Connection refused" (Ollama)

Ollama isn't running, isn't reachable from the WP host, or the Base
URL is wrong.

1. Is the Ollama service up? `systemctl status ollama` (or
   `ps aux | grep ollama`).
2. Is it bound to the right address? Default is `localhost:11434`.
   Test from the WP host: `curl http://localhost:11434/api/tags`.
3. Are the required models installed?
   `ollama list` should show both `nomic-embed-text` and `llama3`.
   If not: `ollama pull nomic-embed-text && ollama pull llama3`.
4. If Ollama is on a different server, the Base URL needs to point
   there AND Ollama needs to bind to that interface
   (`OLLAMA_HOST=0.0.0.0:11434 systemctl restart ollama`).

## AI Chat Search returns no results

Several possibilities:

### No embeddings exist yet

Embeddings are generated **only at `wcb_job_created`** in 1.1.1.
Jobs that existed before AI was turned on have no vectors. They won't
appear in AI Chat Search.

**Fix:** backfill manually. See the
[02-setup-and-providers.md](02-setup-and-providers.md#backfilling-embeddings-for-existing-jobs)
section for the WP-CLI snippet.

### Provider was switched

If you changed providers (e.g. OpenAI → Ollama), the dimension count
changes (1536 → 768). Cosine similarity returns 0 for every mismatched
pair, so search returns nothing. **Re-embed all jobs** against the new
provider using the same backfill snippet.

### The query is too short / too vague

The block renders results from `match_candidate_to_jobs`, which
embeds the query and returns top-10 by cosine. Very short queries
("dev") return whatever's broadly closest - usually not useful. Tell
candidates to type at least a phrase.

### Provider call failed silently

`AiModule::match_candidate_to_jobs()` returns an empty array on any
provider error. Check `wp-content/debug.log` for entries matching
`wcb_ai` or `wcbp_ai` around the time of the failed search.

## AI Description Writer button is missing

1. **Pro inactive / unlicensed.** Check **Career Board → Settings →
   License**.
2. **Provider not configured.** AI Settings → Provider must be set to
   something other than None/Disabled, AND a valid API key (or Base
   URL for Ollama) must be saved.
3. **Employer doesn't have `wcb_post_jobs`.** Check via
   `wp user list-caps {login} | grep wcb_`.
4. **`wcb_ai_description_enabled` filter is returning false.** An
   add-on may be overriding it. `grep -rn "wcb_ai_description_enabled"
   wp-content/plugins/` to see who's hooking it.

## Description Writer returns gibberish / wrong language

1. **Provider quality.** Ollama / llama3 is the weakest writer of the
   three. Switch to OpenAI or Claude for production use; keep Ollama
   for local-only privacy use cases.
2. **Inputs are sparse.** The writer uses only `title`, `company_type`,
   and `location` from the form. Vague inputs produce vague output.
3. **Wrong language.** The AI generates in the same language as the
   inputs. To get a French description, write title / company /
   location in French.

## "AI is not configured" (HTTP 503)

Returned by `POST /wcb/v1/jobs/ai-description` when:

- Provider is unset (`wcbp_ai_provider` is empty or `none`).
- Provider is set but the matching credential (`wcbp_ai_api_key` for
  OpenAI / Claude, `wcbp_ai_base_url` for Ollama) is empty.

Open **AI Settings**, confirm both fields are populated, save.

## Application ranking returns zero scores across the board

Almost always means `wcbp_candidate_resume_data` filter isn't
satisfied. Pro core does NOT ship a resume-parser; this filter is
the integration point an add-on should hook to supply candidate data
for the AI to score against.

Without the filter being satisfied, the AI receives empty strings
and returns low scores for everyone.

**Fix:** install or write an add-on that hooks
`wcbp_candidate_resume_data`, accepts a `$user_id`, and returns an
array of resume sections. The `AiModule::resume_to_text()` flattens
it into a space-separated string for the prompt.

## Application ranking endpoint returns 403

The caller doesn't have `wcb_view_applications`. Grant via:

```bash
wp user add-cap {login} wcb_view_applications
```

or use a role manager plugin to add the capability to the relevant
role.

## "Provider returned empty response"

Rare. Either:

1. The provider hit an internal error and returned an empty body.
   Retry the action - usually transient.
2. The plugin's `wp_remote_request` got a connect timeout.
3. (Ollama) The model is still loading on first request after server
   restart. Try once more - subsequent requests are fast.

## AI spend is suddenly higher than expected

1. **Manual backfill running.** If someone ran the WP-CLI backfill
   snippet recently, that's the cost.
2. **A loop or accidental cron.** Check OpenAI / Anthropic dashboard
   activity for spike timing. Grep your custom plugins for hooks on
   `wcb_job_created` - if a custom plugin re-saves jobs frequently,
   each save fires an embedding call.
3. **OpenAI dashboard → Activity** shows request counts per day. If
   the spike doesn't match a known event, set the monthly cap to a
   safe number and investigate.

## Disable AI quickly in an emergency

Three paths:

1. **AI Settings → Provider → None / Disabled.** Save.
2. **WP-CLI:**
   ```bash
   wp option update wcbp_ai_provider none
   ```
3. **Database:**
   ```sql
   UPDATE wp_options SET option_value = 'none' WHERE option_name = 'wcbp_ai_provider';
   ```

All AI calls stop immediately. UI elements that depend on AI
(description writer button, chat search results) hide cleanly. No data
is lost.

## Things that look broken but aren't

Listed because they regularly get filed as bugs:

- **No "Test connection" button.** Not built in 1.1.1. Verify via a
  real generation.
- **No "Indexed X of Y jobs" counter.** No bulk indexer ships.
  Existing jobs don't auto-backfill on first enable.
- **No AI fit-score column on the applications admin screen.** REST
  data is available; the UI column is queued for a later release.
- **No `wp wcb ai *` WP-CLI commands.** Use direct PHP via
  `wp eval` if you need to drive AI from the CLI.
- **No `wcbp_ai_job_embedded` / `wcbp_ai_*_completed` action hooks.**
  No observability hooks fire from AI in 1.1.1.

These are real gaps, not bugs - filing a "missing feature" report
against them is fine and goes into the backlog.

## How to file an AI bug report

If something genuinely doesn't work and these steps didn't help, file
on the support board with:

1. The provider you're using (OpenAI / Claude / Ollama).
2. What you did to trigger the issue (exact buttons / queries).
3. What you expected vs. what you saw.
4. Anything from `wp-content/debug.log` matching `wcb_ai` or
   `wcbp_ai`.
5. The provider dashboard activity log timestamp around the failure
   (helps correlate with API-side issues).

Don't include actual candidate resumes or full applicant data - shape,
not contents.

## Where to go next

- [01-overview.md](01-overview.md) - feature map for 1.1.1.
- [02-setup-and-providers.md](02-setup-and-providers.md) - provider
  comparison and setup walkthrough.
- [05-blocks-shortcodes-and-developers.md](05-blocks-shortcodes-and-developers.md) -
  REST + filter surface for working around the missing UI bits.
