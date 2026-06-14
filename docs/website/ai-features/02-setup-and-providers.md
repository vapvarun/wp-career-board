# Setup & Providers

> **Pro feature.** The AI Settings tab only appears when WP Career
> Board Pro is installed and active. On Free this page applies once you
> upgrade. (Pro features work regardless of license status - the license
> drives automatic updates only.)

WP Career Board Pro chooses AI providers **per task**: one provider for
**analysis & ranking** (completions) and a separate provider for
**embedding & matching**. Each has its own API key and model. You can
switch at any time.

## The two roles

| Role | Powers | Allowed providers |
|---|---|---|
| **Analysis & ranking** (completions) | Description writer, applicant ranking, TL;DR summaries, cover-letter writer, chat assistant | Anthropic Claude, OpenAI, Ollama |
| **Embedding & matching** | Job embeddings, AI Chat Search, candidate matches | OpenAI, Ollama |

Claude has no embeddings API, so it can only be the analysis provider.
If you want Claude for ranking copy, pair it with OpenAI or Ollama for
embeddings.

## Provider comparison

| Provider | Embeddings model (default) | Completions model (default) | Where it runs | Cost ballpark |
|---|---|---|---|---|
| **OpenAI** | `text-embedding-3-small` | `gpt-4o-mini` | OpenAI servers (USA) | $0.02 per 1M embedding tokens, $0.15 / 1M input + $0.60 / 1M output for completions |
| **Anthropic Claude** | Not supported | `claude-sonnet-4-6` | Anthropic servers (USA) | Sonnet-tier per-token pricing for completions. Requires OpenAI or Ollama for embeddings. |
| **Ollama** | `nomic-embed-text` | `llama3` | Your server / your hardware | Free (your compute) |

Each provider's model is selectable on the AI Settings tab - Claude
(Haiku / Sonnet / Opus), OpenAI (completion model + embedding model),
Ollama (model names). Defaults are listed above.

**Recommended default: OpenAI for both roles.** Best balance of quality,
cost, and zero setup. Use Claude as the analysis provider when you want
better description and cover-letter copy. Use Ollama when content must
not leave your server.

## Cost estimation (OpenAI)

Rough back-of-envelope for a typical mid-size board:

| Activity | Tokens per event | Events per month | Monthly cost |
|---|---|---|---|
| Job publish (embedding) | ~300 in | 100 new jobs | ~$0.001 |
| AI Chat Search query | ~50 in | 5,000 searches | ~$0.0005 |
| Applicant ranking + TL;DR | ~2500 in / ~180 out | 800 scored (cached after first) | ~$1.40 |
| Job Description Writer | ~500 in / ~500 out | 100 generations | ~$0.31 |
| Cover-letter writer | ~1500 in / ~250 out | 300 generations | ~$0.50 |
| **Total per month** | | | **~$2.20** |

Scores are cached per application, so re-opening the dashboard does not
re-bill. For a large board (10x the above), expect **$15-$25 / month**.
Set a monthly cap on your OpenAI billing dashboard - that's the surest
way to catch a runaway loop. Ollama is free but uses your CPU / GPU.

## Step 1 - choose providers

1. Go to **WP Admin -> Career Board -> Settings -> AI Settings**.
2. **Analysis & ranking provider** - choose **Anthropic Claude**,
   **OpenAI**, **Ollama**, or **None**.
3. **Embedding & matching provider** - choose **OpenAI**, **Ollama**, or
   **None**.

If you can't see the AI Settings tab, check that Pro is active.

## Step 2 - enter keys (or base URL) and models

Each field on the tab links out to where to get the key, and the Ollama
field notes that it is free and self-hosted.

### OpenAI

1. Sign in at [platform.openai.com](https://platform.openai.com/).
2. **Billing -> Payment methods** - add a card. OpenAI requires a
   payment method even for low-volume use.
3. **API keys -> Create new secret key.** Copy the `sk-...` value
   immediately (you can't view it again).
4. Paste into the **OpenAI API Key** field on the AI Settings tab.
5. Optionally set the OpenAI completion model and embedding model
   (defaults `gpt-4o-mini` and `text-embedding-3-small`).
6. Set a **Monthly budget** on the OpenAI dashboard. Recommendation:
   $20 / month for a starting board, and raise if you actually hit it.

### Anthropic Claude

1. Sign in at [console.anthropic.com](https://console.anthropic.com/).
2. Add a payment method.
3. **API keys -> Create key.** Copy the `sk-ant-...` value.
4. Paste into the **Anthropic API Key** field.
5. Optionally pick the Claude model (Haiku / Sonnet / Opus; default
   `claude-sonnet-4-6`).
6. **Remember:** Claude can only be the analysis provider. Set the
   embedding provider to OpenAI or Ollama as well, or AI Chat Search and
   candidate matching stay off.

### Ollama (self-hosted)

1. Install on your server: `curl -fsSL https://ollama.com/install.sh | sh`
2. Pull the required models:
   ```bash
   ollama pull nomic-embed-text
   ollama pull llama3
   ```
   On a 4 GB VPS this takes a few minutes and uses about 5 GB of disk
   for both models.
3. Confirm Ollama is reachable: `curl http://localhost:11434/api/tags`
   should return a JSON list including both models.
4. In **AI Settings**, set the **Ollama Base URL** to
   `http://localhost:11434` (or wherever Ollama is bound). No API key is
   needed.
5. Optionally set the Ollama completion/embedding model names (defaults
   `llama3` and `nomic-embed-text`).
6. **Server sizing:** completions on llama3 require ~6 GB RAM and run
   slowly on CPU-only servers. For decent latency, run on a host with a
   GPU or use a smaller quantised variant
   (`llama3:8b-instruct-q4_K_M`).

## Step 3 - save

Click **Save AI Settings**. Configuration is stored in these options:

| Option | Holds |
|---|---|
| `wcbp_ai_completion_provider` | Analysis & ranking provider slug |
| `wcbp_ai_embedding_provider` | Embedding & matching provider slug |
| `wcbp_ai_openai_key` | OpenAI API key |
| `wcbp_ai_anthropic_key` | Anthropic API key |
| `wcbp_ai_ollama_url` | Ollama base URL |
| `wcbp_ai_openai_model` / `wcbp_ai_openai_embedding_model` | OpenAI models |
| `wcbp_ai_anthropic_model` | Claude model |
| `wcbp_ai_ollama_model` / `wcbp_ai_ollama_embedding_model` | Ollama models |
| `wcbp_ai_auto_rank` | Auto-score applicants on submit (on/off) |

> The legacy single-provider options `wcbp_ai_provider`,
> `wcbp_ai_api_key`, and `wcbp_ai_base_url` were removed in 1.3.0. AI
> reads only the per-task options above.

### Key security

API keys are never written back into the settings page HTML. Each key
field renders empty with a "saved" indicator and only updates when you
type a new value. A stored key is not exposed in the page source - so an
empty key field after saving is expected, not a bug.

### Verifying your setup

There is no separate "test connection" button. Configuration is
validated the first time a real AI call happens. To verify:

- **Analysis provider:** open the post-a-job form and click **Generate
  with AI** - a successful generation confirms the completion provider +
  key.
- **Embedding provider:** run **Index existing jobs** (below) and then
  search on the AI Job Search page - results confirm the embedding
  provider + key.

## Backfilling embeddings for existing jobs

Embeddings are generated automatically at `wcb_job_created` time. Jobs
that existed **before** AI was enabled have no embeddings and won't
appear in AI Chat Search or candidate matches until you backfill.

The AI Settings tab has an **"Index existing jobs"** button that calls
`AiModule::backfill_job_embeddings()` and reports how many jobs were
embedded. Each call is bounded (up to a few hundred jobs) so the request
stays responsive; run it again to continue paging through a large
catalog.

The button is disabled with a clear note when no embedding provider
(OpenAI or Ollama) is set, and explains why if you try - Claude cannot
index for matching.

For very large catalogs you can also loop in WP-CLI:

```bash
wp eval '
$ai = new \WCB\Pro\Modules\Ai\AiModule();
$n  = $ai->backfill_job_embeddings( 500 );
echo "Embedded $n jobs\n";
'
```

Run it off-hours - each call is a provider API request.

## Privacy and data flow per provider

| Provider | Where data goes | Logged by provider | Used for training? |
|---|---|---|---|
| **OpenAI** | OpenAI's US servers via TLS | Yes (kept up to 30 days for abuse monitoring) | No, API data is opted out of training by default |
| **Anthropic Claude** | Anthropic's US servers via TLS | Yes (kept up to 30 days) | No, API data is not used for training |
| **Ollama** | Stays on your server. Never leaves. | Only if you log it yourself | N/A |

If you're under GDPR / CCPA / HIPAA constraints, **use Ollama** for both
roles.

## Switching providers

You can change providers at any time without losing data:

- **Existing job vectors** are kept per provider. If you switch the
  embedding provider from OpenAI (1536-dim) to Ollama (768-dim) the
  dimensions don't match and cosine similarity returns 0 for every
  comparison - so AI Chat Search returns no results until you re-run
  **Index existing jobs** against the new provider.
- **Existing options** are simply overwritten on save. A blank key field
  keeps the previously stored key (see "Key security" above).

## Disabling AI

Set both provider dropdowns to **None** and save. Effects:

- `AiModule::is_enabled()` returns `false`.
- AI Chat Search block renders nothing for visitors (admins see a
  configure hint).
- The description writer, cover-letter, and ranking controls disappear.
- `/ai/match`, `/candidates/{id}/matches`,
  `/ai/ranked-applications/{job_id}` return empty lists;
  `/jobs/ai-description` and `/jobs/{id}/ai-cover-letter` return a 503
  with `wcb_ai_disabled`.
- No new embeddings are generated.
- Stored `wcb_ai_vectors` rows and cached scores are kept (no
  destructive change). If you re-enable AI later, everything resumes
  against the existing data.

To fully clean up: deactivate Pro, and on uninstall the AI table goes
with it.

## Common setup errors

The top three:

- **"Invalid API key"** - wrong key or wrong provider selected. Re-copy
  from the provider dashboard. OpenAI keys start `sk-`, Anthropic keys
  start `sk-ant-`. Strip whitespace.
- **"Connection refused" (Ollama)** - Ollama isn't running or the Base
  URL is wrong. `ps aux | grep ollama` to confirm; restart with
  `systemctl restart ollama` if needed.
- **"AI is not configured" (503)** - the completion provider is None, or
  set but missing its key. Open AI Settings and finish the analysis &
  ranking provider.

See [06-troubleshooting.md](06-troubleshooting.md) for the full list.

## Where to go next

- [03-candidate-ai-features.md](03-candidate-ai-features.md) - the
  candidate-facing flows (AI Chat Search, matches, cover letters).
- [04-employer-ai-features.md](04-employer-ai-features.md) - the
  employer-facing flows (description writer + ranking).
</content>
