# AI Troubleshooting

Most AI issues fall into one of five categories: provider connection,
quota, missing embeddings, slow performance, or unexpected output.
This page covers each.

If you're on Free and don't see the AI tab at all — that's expected,
not a bug. AI Settings only appears with Pro active and licensed. See
[01-overview.md](01-overview.md) for the feature gate.

## "Invalid API key" / "Authentication failed"

The most common setup error.

**Check:**

1. **Right key for the right provider.** OpenAI keys start `sk-`,
   Anthropic keys start `sk-ant-`, Ollama doesn't use a key (Base
   URL only). Copy-paste from the provider dashboard, not from a
   stale email.
2. **No leading / trailing whitespace.** Particularly when pasting
   from a terminal — a hidden newline breaks auth.
3. **Key isn't disabled or expired.** Confirm on the provider
   dashboard. OpenAI shows last-used time, useful for spotting an
   accidentally-revoked key.
4. **Right organisation / project (OpenAI).** If your OpenAI account
   has multiple projects, the key must belong to the project that has
   the billing source attached. Verify with **Test connection**.

If the key works in `curl` but not in the plugin, check **wp-cron**
isn't being blocked by a host firewall that strips outbound HTTPS to
non-whitelisted domains.

## "Quota exceeded" / "Rate limit hit"

You've hit either a monthly budget cap or per-minute rate ceiling.

**OpenAI:**
- Dashboard → Limits → check current usage vs. cap.
- Raise the monthly cap (Billing → Limits) or wait for the next
  billing cycle.
- If it's a per-minute rate limit (`429`), reduce the **Reindex all
  jobs** batch size on **Settings → AI → Advanced** (default 50; drop
  to 10 if you have many jobs).

**Anthropic:**
- Console → Limits → adjust monthly cap.
- Anthropic also enforces per-minute and per-day rate limits — for
  bulk reindex, set the batch size to 10 or lower.

**Ollama:**
- Doesn't have a quota — but it can run out of memory. Watch
  `dmesg` for OOM-killer events while reindexing.

## "Connection refused" / Ollama unreachable

Ollama isn't running, isn't reachable from the WP host, or the Base
URL is wrong.

1. Is the Ollama service up? `systemctl status ollama` (or
   `ps aux | grep ollama` on non-systemd hosts).
2. Is it bound to the right address? Default is `localhost:11434`.
   Test from the WP host: `curl http://localhost:11434/api/tags`.
3. Are the required models installed?
   `ollama list` should show both `nomic-embed-text` and `llama3`. If
   not: `ollama pull nomic-embed-text && ollama pull llama3`.
4. If Ollama is on a different server, the Base URL needs to point
   there (`http://10.0.0.5:11434`) AND Ollama needs to bind to that
   interface (`OLLAMA_HOST=0.0.0.0:11434 systemctl restart ollama`).

## AI Chat Search returns no results

**The most common cause: embeddings haven't been generated yet.**

1. Check **Settings → AI** for the **"Indexed X of Y jobs"** counter.
   If X is less than Y, indexing is still in progress — wait or click
   **Reindex all jobs**.
2. If X = Y but you still get no results, the query may be very
   different in length from the jobs (e.g. one word vs. multi-paragraph
   descriptions). Increase **Min query length** if candidates type too
   short queries, or lower the relevance threshold (Settings →
   Advanced → AI similarity threshold; default 0.3).
3. If you just switched providers (especially from OpenAI 1536-dim
   to Ollama 768-dim), the existing embeddings are dimension-mismatched
   — click **Reindex all jobs** to regenerate.

## "Indexed X of Y" never moves

The background indexer isn't running. Causes:

1. **WP-Cron is broken.** Check `wp cron event list` from WP-CLI. If
   the `wcb_ai_index_pending_jobs` event isn't listed, re-register
   it by deactivating + reactivating Pro.
2. **Cron is disabled site-wide** (`DISABLE_WP_CRON` constant). Add a
   system cron hitting `/wp-cron.php` every 5 minutes.
3. **A previous failure left jobs in error state.** Check
   `Settings → AI → Last index error`. Common: the provider rejected a
   single oversized job (description > 8000 tokens). Edit the job to
   shorter, or use the **Force re-embed** action on that specific job
   from the post edit screen.

## AI Description Writer button missing

1. **Pro inactive / unlicensed.** Check **Career Board → Settings →
   License**.
2. **Provider not configured.** AI Settings → Provider must be set to
   something other than **Disabled**.
3. **Employer doesn't have `wcb_post_jobs`.** Check via
   `wp user list-caps {login} | grep wcb_`.
4. **`wcb_ai_description_enabled` filter is returning false.** Some
   add-ons override this. Search active plugins for that hook.

## AI Fit Score column missing

Same triage as above, plus:

1. **Job hasn't been embedded yet.** The score row uses the embedding
   in some implementations — if the job is "Pending embed," the
   column hides for that row.
2. **Application has no readable content.** If the candidate uploaded
   only an image-based resume that couldn't be parsed, the score may
   be `N/A` instead of a number — re-parse, or score using profile
   data only.

## Description writer returns gibberish / wrong language

1. **Provider quality.** Ollama / llama3 is the weakest writer of the
   three. Switch to OpenAI or Claude for production use; keep Ollama
   for local-only privacy use cases.
2. **Bullets are too vague.** "Backend role" produces generic output.
   Add specifics (stack, scale, location, level, salary).
3. **Wrong language in bullets.** The AI generates in the same
   language as your input. Write bullets in the target language.
4. **Token cap hit.** If your bullets are very long, the model truncates
   before finishing. Aim for under 200 words of bullets total.

## AI Fit Score looks wrong / unfair

1. **Resume parsing was sparse.** Open the candidate's profile — if
   most fields are empty, the AI didn't have enough to work with.
   Re-parse from the candidate dashboard.
2. **Job description was changed after scoring.** Click the per-row
   refresh icon to re-compute against the updated description.
3. **The reason explains it.** Read the one-line reason text — it
   tells you what the AI did and didn't find. Often the "low score"
   is justifiable on review.
4. **Bias concern.** Don't auto-reject by score. Use it for triage
   order only.

## Resume parse drops important fields

1. **PDF is image-only.** AI can't read scans without OCR. Convert
   to text-selectable PDF first.
2. **Non-standard section headings.** "What I've Been Up To" instead
   of "Experience" can throw off the parser. Tell candidates to use
   standard headings.
3. **Multi-column layout.** Dense two-column resumes parse worse than
   single-column. Recommend single-column for parse-friendliness.
4. **Custom fields:** if you've added custom profile fields and want
   the AI to populate them, hook `wcbp_candidate_resume_data` to
   inject the field schema (see
   [05-blocks-shortcodes-and-developers.md](05-blocks-shortcodes-and-developers.md)).

## "Provider returned empty response"

Rare. Either:

1. The provider hit an internal error and returned an empty body. Retry
   the action — usually transient.
2. The plugin's `wp_remote_request` got a connect timeout (default 30s).
   On slow hosts, increase via:
   ```php
   add_filter( 'wcbp_ai_request_timeout', function () { return 60; });
   ```
3. (Ollama) The model is still loading on first request after server
   restart. Try once more — subsequent requests are fast.

## AI spend is suddenly higher than expected

1. **Reindex was triggered.** Settings → AI → Last reindex date. A
   full reindex costs ~$0.001 per job on OpenAI — for 5,000 jobs that's
   $5 in one go. Not a problem unless it's happening daily.
2. **A loop or accidental cron.** Check `Settings → AI →
   Embedding events (last 24h)` for the counter. Anything over
   ~2× your published-jobs count in 24h is suspicious.
3. **An add-on is over-calling.** A custom plugin hooking
   `save_post_wcb_job` could be triggering re-embed on every save.
   `composer ci:quick` would catch loop patterns; otherwise grep your
   custom plugins for `wcbp_ai_*` action dispatches.
4. **OpenAI dashboard → Activity** shows request counts per day. If
   the spike doesn't match a known reindex, set the monthly cap to a
   safe number and investigate.

## Disable AI quickly in an emergency

1. **Settings → AI → Provider → Disabled.** Save.
2. **OR:** at the WP-CLI level:
   ```bash
   wp option update wcbp_ai_provider none
   ```
3. **OR:** at the database level:
   ```sql
   UPDATE wp_options SET option_value = 'none' WHERE option_name = 'wcbp_ai_provider';
   ```

All AI calls stop. UI elements that depend on AI disappear cleanly.
No data is lost; you can re-enable later.

## How to file an AI bug report

If something genuinely doesn't work and these steps didn't help, file
on the support board with:

1. The provider you're using (OpenAI / Claude / Ollama).
2. What you did to trigger the issue (exact buttons / queries).
3. What you expected vs. what you saw.
4. Anything from `wp-content/debug.log` matching `wcb_ai_` or
   `wcbp_ai_`.
5. The output of `wp wcb ai status` if WP-CLI is available.

Don't include actual candidate resumes or full applicant data in the
report — describe shape, not contents.

## Where to go next

- [01-overview.md](01-overview.md) — what each AI feature does at a
  glance.
- [02-setup-and-providers.md](02-setup-and-providers.md) — the provider
  comparison and setup walkthrough.
