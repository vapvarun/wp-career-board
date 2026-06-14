# AI Troubleshooting

What can go wrong with AI features in 1.4.3, in order of how often it
actually happens.

If you're on Free and don't see the AI Settings tab at all - that's
expected, not a bug. AI Settings only appears with Pro active.

## "Invalid API key" / "Authentication failed"

The most common setup error.

**Check:**

1. **Right key for the right provider.** OpenAI keys start `sk-`,
   Anthropic keys start `sk-ant-`, Ollama doesn't use a key. Copy from
   the provider dashboard, not from a stale email.
2. **No leading / trailing whitespace.** Particularly when pasting from
   a terminal - a hidden newline breaks auth.
3. **Key isn't disabled or expired.** Confirm on the provider dashboard.
4. **Right organisation / project (OpenAI).** If your OpenAI account has
   multiple projects, the key must belong to the project that has the
   billing source attached.
5. **You actually entered a new key.** Key fields render empty after
   saving (keys are never written back into the page for security). An
   empty field after save means the stored key is kept - it does not
   mean the key was lost. To replace it, type the new value.

If the key works in `curl` but not in the plugin, check that the host
firewall isn't stripping outbound HTTPS to non-whitelisted domains.

## "Quota exceeded" / "Rate limit hit"

Three different things can produce this kind of error:

### Provider-side monthly cap

**OpenAI:** Dashboard -> Limits -> check current usage vs. cap. Raise the
monthly cap or wait for the next billing cycle.

**Anthropic:** Console -> Limits -> adjust monthly cap.

**Ollama:** Doesn't have a quota - but it can run out of memory. Watch
`dmesg` for OOM-killer events.

### Provider-side per-minute rate limit

HTTP 429 from OpenAI / Anthropic means you're hitting their per-minute or
per-day ceiling. Reduce concurrent calls (e.g. slow down a bulk
backfill).

### Plugin-side rate limit (Pro)

The AI endpoints enforce a **30 calls per user per hour** ceiling via a
transient. Response:

```json
{
  "code": "wcb_rate_limit",
  "message": "AI request limit reached. Please try again later.",
  "data": { "status": 429 }
}
```

This is shared across all five AI REST endpoints per user. Heavy testing
during setup will hit it. Wait an hour, or in dev clear the transient
(replace `{user_id}` with the user's ID):

```bash
wp transient delete "wcbp_ai_rate_{user_id}"
```

## "Connection refused" (Ollama)

Ollama isn't running, isn't reachable from the WP host, or the Base URL
is wrong.

1. Is the Ollama service up? `systemctl status ollama` (or
   `ps aux | grep ollama`).
2. Is it bound to the right address? Default is `localhost:11434`. Test
   from the WP host: `curl http://localhost:11434/api/tags`.
3. Are the required models installed? `ollama list` should show both
   `nomic-embed-text` and `llama3`. If not:
   `ollama pull nomic-embed-text && ollama pull llama3`.
4. If Ollama is on a different server, the Base URL needs to point there
   AND Ollama needs to bind to that interface
   (`OLLAMA_HOST=0.0.0.0:11434 systemctl restart ollama`).

## AI Chat Search returns no results

Several possibilities:

### No embeddings exist yet

Embeddings are generated at `wcb_job_created`. Jobs that existed before
AI was turned on have no vectors and won't appear.

**Fix:** click **"Index existing jobs"** on the AI Settings tab to
backfill. See
[02-setup-and-providers.md](02-setup-and-providers.md#backfilling-embeddings-for-existing-jobs).

### Embedding provider not configured

AI Chat Search needs an **embedding** provider (OpenAI or Ollama), not
just the analysis provider. If you only set Claude, matching is off -
Claude has no embeddings API. Set the embedding provider too.

### Embedding provider was switched

If you changed the embedding provider (e.g. OpenAI -> Ollama), the
dimension count changes (1536 -> 768). Cosine similarity returns 0 for
every mismatched pair, so search returns nothing. **Re-run "Index
existing jobs"** against the new provider.

### The query is too short / too vague

Very short queries ("dev") return whatever's broadly closest - usually
not useful. Tell candidates to type at least a phrase.

### Provider call failed

`AiModule::match_candidate_to_jobs()` returns an empty array on any
provider error, and the block shows "Search failed. Please try again."
Check `wp-content/debug.log` for entries around the failed search.

## AI Description Writer button is missing

1. **Pro inactive.** Check that Pro is active.
2. **No provider configured.** AI Settings -> at least one provider must
   be set with a valid key (or Base URL for Ollama).
3. **Employer doesn't have `wcb_post_jobs`.** Check via
   `wp user list-caps {login} | grep wcb_`.
4. **`wcb_ai_description_enabled` filter is returning false.** An add-on
   may be overriding it.

## Description Writer returns gibberish / wrong language

1. **Provider quality.** Ollama / llama3 is the weakest writer. Switch
   the analysis provider to OpenAI or Claude for production.
2. **Inputs are sparse.** The writer uses only `title`, `company_type`,
   and `location`. Vague inputs produce vague output.
3. **Wrong language.** The AI generates in the same language as the
   inputs. Write title / company / location in the target language.

## "AI is not configured" (HTTP 503)

Returned by `POST /wcb/v1/jobs/ai-description` and
`POST /wcb/v1/jobs/{id}/ai-cover-letter` when:

- The **analysis & ranking provider** (`wcbp_ai_completion_provider`) is
  unset or `none`, OR
- It's set but the matching credential is empty
  (`wcbp_ai_openai_key` / `wcbp_ai_anthropic_key` for OpenAI / Claude,
  `wcbp_ai_ollama_url` for Ollama).

Open **AI Settings**, finish the analysis & ranking provider, save.

## Applicant ranking returns zero scores across the board

1. **Sparse or missing resume.** Scores come from the candidate's resume
   text. A candidate who hasn't filled in the Resume Builder gives the AI
   little to score. The resume-data hook (`wcbp_candidate_resume_data`)
   is wired in Pro core, so the connection itself works - the issue is
   usually empty resumes.
2. **Analysis provider not configured.** Ranking needs the completion
   provider set with a valid key.
3. **A model returning malformed JSON.** The scorer extracts the first
   `{...}` object and tolerates code fences, but a provider that returns
   no JSON at all yields a zero. Check `debug.log` and consider switching
   the analysis provider.

## "Rank by AI fit" does nothing / control missing

1. **Analysis provider not configured.** The dashboard ranking control
   is gated on `wcb_ai_ranking_available` (completion provider
   configured).
2. **No applications loaded for the job.** Ranking sorts the loaded list;
   if there are no applications there's nothing to rank.
3. **Scores are cached.** Re-ranking won't re-bill or change cached
   scores. To force a recompute, call
   `AiModule::score_application( $id, true )`.

## Auto-score doesn't run

1. **Toggle off.** Enable "Auto-score applicants on submit" in AI
   Settings (`wcbp_ai_auto_rank`).
2. **Analysis provider not configured.** Auto-score is skipped when the
   completion provider isn't set.
3. **WP-Cron not firing.** Auto-score runs on a scheduled single event
   (`wcbp_ai_score_application`) ~30s after submission. On a low-traffic
   site WP-Cron may lag - confirm cron is running
   (`wp cron event list`).

## Application ranking endpoint returns 403

The caller doesn't have `wcb_view_applications`. Grant via:

```bash
wp user add-cap {login} wcb_view_applications
```

or use a role manager plugin.

## Cover-letter button missing or returns 503

1. **Pro inactive or analysis provider not configured** - the apply
   panel reads an `aiCoverEnabled` flag that depends on the completion
   provider being set.
2. **404 instead of a letter** - the route was given an id that isn't a
   published `wcb_job`.
3. **Thin letter** - the writer only uses resume-supported details. Fill
   in the Resume Builder for a richer draft.

## "Provider returned empty response"

Rare. Either:

1. The provider hit an internal error and returned an empty body. Retry -
   usually transient.
2. The plugin's HTTP request hit a connect timeout (the shared fetch
   helper aborts after 15s on the block side).
3. (Ollama) The model is still loading on first request after a server
   restart. Try once more - subsequent requests are fast.

## AI spend is suddenly higher than expected

1. **A backfill running.** "Index existing jobs" (or the WP-CLI backfill)
   embeds every selected job - that's the cost.
2. **Forced re-scoring.** Normal dashboard use reuses cached scores; a
   custom integration calling `score_application( $id, true )` re-bills.
3. **A custom plugin re-saving jobs.** Each `wcb_job_created` fires an
   embedding call. Grep your custom code for hooks on `wcb_job_created`.
4. **Provider dashboard -> Activity** shows request counts per day. If a
   spike doesn't match a known event, set the monthly cap and
   investigate.

## Disable AI quickly in an emergency

Three paths:

1. **AI Settings -> set both providers to None.** Save.
2. **WP-CLI:**
   ```bash
   wp option update wcbp_ai_completion_provider none
   wp option update wcbp_ai_embedding_provider none
   ```
3. **Database:**
   ```sql
   UPDATE wp_options SET option_value = 'none'
   WHERE option_name IN ( 'wcbp_ai_completion_provider', 'wcbp_ai_embedding_provider' );
   ```

All AI calls stop immediately. UI elements that depend on AI hide
cleanly. Stored embeddings and cached scores are kept.

## Things that look broken but aren't

- **Empty API-key fields after saving.** Keys are never written back into
  the page for security. The stored key is intact.
- **No "Test connection" button.** Verify via a real generation or a
  search after indexing.
- **No `wp wcb ai *` WP-CLI commands.** Use `wp eval` with the
  `AiModule` public methods.
- **Re-ranking shows the same scores.** Scores are cached per application
  on purpose, so re-opening the dashboard never re-bills.
- **"Index existing jobs" disabled.** It greys out with a note when no
  embedding provider is set - Claude alone cannot index for matching.

## How to file an AI bug report

If something genuinely doesn't work and these steps didn't help, file on
the support board with:

1. The providers you're using (analysis + embedding).
2. What you did to trigger the issue (exact buttons / queries).
3. What you expected vs. what you saw.
4. Anything from `wp-content/debug.log` matching `wcb_ai` or `wcbp_ai`.
5. The provider dashboard activity timestamp around the failure.

Don't include actual candidate resumes or full applicant data - shape,
not contents.

## Where to go next

- [01-overview.md](01-overview.md) - feature map.
- [02-setup-and-providers.md](02-setup-and-providers.md) - provider
  comparison and setup walkthrough.
- [05-blocks-shortcodes-and-developers.md](05-blocks-shortcodes-and-developers.md) -
  REST + filter surface.
</content>
