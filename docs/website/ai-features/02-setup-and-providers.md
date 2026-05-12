# Setup & Providers

> **Pro feature.** The AI Settings tab only appears when WP Career
> Board Pro is installed, active, and licensed. On Free this page
> applies once you upgrade.

WP Career Board Pro supports **three** AI providers. Pick one based on
your hosting setup, privacy needs, and budget. You can switch at any
time.

## Provider comparison

| Provider | Embeddings model | Completions model | Where it runs | Cost ballpark |
|---|---|---|---|---|
| **OpenAI** | `text-embedding-3-small` (1536-dim) | `gpt-4o-mini` | OpenAI servers (USA) | $0.02 per 1M embedding tokens, $0.15 / 1M input + $0.60 / 1M output for completions |
| **Anthropic Claude** | Not supported | `claude-haiku-4-5` | Anthropic servers (USA) | $0.80 / 1M input + $4.00 / 1M output for completions. Requires OpenAI or Ollama for embeddings. |
| **Ollama** | `nomic-embed-text` (768-dim) | `llama3` (8B by default) | Your server / your hardware | Free (your compute) |

**Recommended default: OpenAI.** Best balance of quality, cost, and
zero setup. Use Claude when you specifically want better
description-writer copy. Use Ollama when content must not leave your
server.

## Cost estimation (OpenAI)

Rough back-of-envelope for a typical mid-size board:

| Activity | Tokens per event | Events per month | Monthly cost |
|---|---|---|---|
| Job publish (embedding) | ~300 in | 100 new jobs | ~$0.001 |
| AI Chat Search query | ~50 in | 5,000 searches | ~$0.0005 |
| Application Ranking (REST) | ~2500 in / ~150 out | 800 scored | ~$1.40 |
| Job Description Writer | ~500 in / ~500 out | 100 generations | ~$0.31 |
| **Total per month** | | | **≈ $1.70** |

For a large board (10× the above), expect **$10-$20 / month**. Set a
monthly cap on your OpenAI billing dashboard - that's the surest way
to catch a runaway loop. Claude is roughly 5-10× the per-token cost
of OpenAI. Ollama is free but uses your CPU / GPU.

## Step 1 - pick a provider

1. Go to **WP Admin → Career Board → Settings → AI Settings**.
2. **Provider** dropdown - choose **OpenAI**, **Anthropic Claude**,
   **Ollama**, or **Disabled (none)**.

If you can't see the AI Settings tab, check that Pro is active and the
license is valid (**Settings → License**).

## Step 2 - enter your key (or base URL)

### OpenAI

1. Sign in at [platform.openai.com](https://platform.openai.com/).
2. **Billing → Payment methods** - add a card. OpenAI requires a
   payment method even for low-volume use.
3. **API keys → Create new secret key.** Copy the `sk-...` value
   immediately (you can't view it again - only its prefix).
4. Paste into the **API Key** field on the AI Settings tab.
5. Set a **Monthly budget** on the OpenAI dashboard. Recommendation:
   $20 / month for a starting board, and raise if you actually hit it.

### Anthropic Claude

1. Sign in at [console.anthropic.com](https://console.anthropic.com/).
2. Add a payment method.
3. **API keys → Create key.** Copy the `sk-ant-...` value.
4. Paste into the **API Key** field.
5. **Important:** Claude doesn't do embeddings. AI Chat Search and
   candidate matching need a secondary embeddings provider. Easiest
   path is to also configure OpenAI - the embeddings half uses OpenAI
   while completions go through Claude.

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
4. In **AI Settings**, set the **Base URL** to `http://localhost:11434`
   (or wherever Ollama is bound). No API key is needed.
5. **Server sizing:** completions on llama3 require ~6 GB RAM and run
   slowly on CPU-only servers. For decent latency, run on a host with
   a GPU or use a smaller quantised variant
   (`llama3:8b-instruct-q4_K_M`).

## Step 3 - save

Click **Save AI Settings**. Configuration is stored in three options:
`wcbp_ai_provider`, `wcbp_ai_api_key`, `wcbp_ai_base_url`.

There is **no separate "test connection" button** in 1.1.1 -
configuration is validated the first time a real AI call happens. To
verify your setup is correct, open the post-a-job form (with the
description-writer button enabled) and click **Generate with AI** -
a successful generation confirms the provider + key are working.

## Backfilling embeddings for existing jobs

Embeddings are generated **only at `wcb_job_created` time** in 1.1.1.
If you turn on AI for the first time on a board with existing
published jobs, those jobs have no embeddings - they won't appear in
AI Chat Search results.

In 1.1.1, there is **no built-in reindex button**. To backfill, the
options are:

**Option A** - re-save each job (any change saves a new vector).
Manual; works for small boards.

**Option B** - one-off WP-CLI snippet:

```bash
wp eval '
$jobs = get_posts( array(
    "post_type"      => "wcb_job",
    "post_status"    => "publish",
    "posts_per_page" => -1,
    "fields"         => "ids",
));
$ai = new \WCB\Pro\Modules\Ai\AiModule();
foreach ( $jobs as $id ) {
    $ai->generate_job_embedding( $id );
    echo "Embedded $id\n";
}
'
```

Run this off-hours - each call is a provider API request. On OpenAI a
5000-job backfill takes a few minutes and costs ~$0.10.

A first-class reindex tool is queued for a later release.

## Privacy and data flow per provider

| Provider | Where data goes | Logged by provider | Used for training? |
|---|---|---|---|
| **OpenAI** | OpenAI's US servers via TLS | Yes (kept 30 days for abuse monitoring) | No, business-tier API is opted out of training by default |
| **Anthropic Claude** | Anthropic's US servers via TLS | Yes (kept up to 30 days) | No, API data is not used for training |
| **Ollama** | Stays on your server. Never leaves. | Only if you log it yourself | N/A |

If you're under GDPR / CCPA / HIPAA constraints, **use Ollama**.

## Switching providers

You can change providers at any time without losing data:

- **Existing job vectors** are kept as-is. If you switch from OpenAI
  (1536-dim) to Ollama (768-dim) the dimensions don't match and
  cosine similarity returns 0 for every comparison - in practice AI
  Chat Search will then return no results until you backfill against
  the new provider (see "Backfilling embeddings" above).
- **Existing options** (`wcbp_ai_provider`, `wcbp_ai_api_key`,
  `wcbp_ai_base_url`) are simply overwritten on save.

## Disabling AI

Set **Provider** to **None / Disabled** and save. Effects:

- `AiModule::is_enabled()` returns `false`.
- AI Chat Search block silently returns an empty result set.
- AI description writer button disappears (the
  `wcb_ai_description_enabled` filter resolves to false).
- `/ai/match`, `/candidates/{id}/matches`,
  `/ai/ranked-applications/{job_id}`, and `/jobs/ai-description`
  return empty / 503 silently.
- No new embeddings are generated.
- Stored `wcb_ai_vectors` rows are kept (no destructive change). If
  you re-enable AI later, search resumes against the existing data.

To fully clean up: deactivate Pro, and on uninstall the AI tables go
with it.

## Common setup errors

The top three:

- **"Invalid API key"** - wrong key or wrong provider selected.
  Re-copy from the provider dashboard. OpenAI keys start `sk-`,
  Anthropic keys start `sk-ant-`. Strip whitespace.
- **"Connection refused" (Ollama)** - Ollama isn't running or the
  Base URL is wrong. `ps aux | grep ollama` to confirm; restart with
  `systemctl restart ollama` if needed.
- **"Quota exceeded"** - your OpenAI or Anthropic monthly cap is hit.
  Raise the cap in the provider dashboard or wait for the next
  billing cycle.

See [06-troubleshooting.md](06-troubleshooting.md) for the full list.

## Where to go next

- [03-candidate-ai-features.md](03-candidate-ai-features.md) - the
  candidate-facing flow (AI Chat Search).
- [04-employer-ai-features.md](04-employer-ai-features.md) - the
  employer-facing flow (description writer + ranking data).
