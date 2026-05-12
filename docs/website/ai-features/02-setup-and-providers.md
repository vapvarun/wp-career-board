# Setup & Providers

> **Pro feature.** The AI Settings panel only appears when WP Career
> Board Pro is installed, active, and licensed. On Free this page is
> hidden — but the rest of this doc applies once you upgrade.

WP Career Board Pro supports **three** AI providers. Pick one based on
your hosting setup, privacy needs, and budget. You can switch at any
time (existing vectors are reused if the embeddings model matches).

## Provider comparison

| Provider | Embeddings model | Completions model | Where it runs | Cost ballpark |
|---|---|---|---|---|
| **OpenAI** | `text-embedding-3-small` (1536-dim) | `gpt-4o-mini` | OpenAI servers (USA) | $0.02 per 1M embedding tokens, $0.15 / 1M input + $0.60 / 1M output for completions |
| **Anthropic Claude** | Not supported | `claude-haiku-4-5` | Anthropic servers (USA) | $0.80 / 1M input + $4.00 / 1M output for completions. **Requires OpenAI or Ollama for the embeddings half** of search / matching. |
| **Ollama** | `nomic-embed-text` (768-dim) | `llama3` (8B by default) | Your server / your hardware | Free (your compute) |

**Recommended default: OpenAI.** Best balance of quality, cost, and
zero-setup. Use Claude when you specifically want better job-description
copy. Use Ollama when candidate / resume data must not leave your
server.

## Cost estimation — what you'll actually pay (OpenAI)

Rough back-of-envelope for a typical mid-size board:

| Activity | Tokens per event | Events per month | Monthly cost |
|---|---|---|---|
| Job publish (embedding) | ~300 in | 100 new jobs | ~$0.001 |
| Resume upload (parse) | ~2000 in / ~500 out | 200 uploads | ~$0.10 |
| AI Chat Search query | ~50 in | 5,000 searches | ~$0.0005 |
| Application Ranking | ~2500 in / ~150 out | 800 applications | ~$1.40 |
| Job Description Writer | ~800 in / ~600 out | 100 generations | ~$0.42 |
| **Total per month** | | | **≈ $2** |

For a large board (10× the above), expect **$15–$25 / month**. Set a
monthly cap on your OpenAI billing dashboard — that's the surest way to
catch a runaway loop. Claude is roughly 5–10× the per-token cost of
OpenAI; Ollama is free but uses your CPU / GPU.

## Step 1 — pick a provider

1. Go to **WP Admin → Career Board → Settings → AI**.
2. Click the **Provider** dropdown.
3. Choose: **OpenAI**, **Anthropic Claude**, **Ollama**, or
   **Disabled**.

If you can't see the AI tab, check that Pro is active and the license
is valid (**Settings → License**).

## Step 2 — enter your key (or base URL)

### OpenAI

1. Sign in at [platform.openai.com](https://platform.openai.com/).
2. **Billing → Payment methods** — add a card. OpenAI requires a
   payment method even for low-volume use.
3. **API keys → Create new secret key.** Copy the `sk-...` value
   immediately (you can't view it again — only its prefix).
4. Paste into the **API Key** field on the AI Settings page.
5. Set a **Monthly budget** on the OpenAI dashboard. Recommendation:
   $20 / month for a starting board, and bump up if you actually hit
   the cap.

### Anthropic Claude

1. Sign in at [console.anthropic.com](https://console.anthropic.com/).
2. Add a payment method (same reason — Anthropic requires one).
3. **API keys → Create key.** Copy the `sk-ant-...` value.
4. Paste into the **API Key** field.
5. **Important:** Claude doesn't do embeddings. The plugin will warn
   you that AI Chat Search and candidate matching need a secondary
   provider. Easiest path: also enter an OpenAI key for the
   embeddings half. The plugin uses Claude for completions and OpenAI
   for embeddings transparently.

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
   a GPU or use the smaller `llama3:8b-instruct-q4_K_M` quantised
   variant.

## Step 3 — save and verify

1. Click **Save AI Settings**.
2. A green confirmation notice appears.
3. The plugin runs a self-check: a tiny test embedding + a tiny test
   completion against your provider. If either fails you'll get a red
   error notice with the provider's response (e.g. "Invalid API key,"
   "Quota exceeded," "Connection refused").
4. To re-run the self-check at any time, click the **Test connection**
   button.

## Step 4 — backfill embeddings for existing jobs

When you turn AI on for the first time on a board with existing
published jobs, those jobs have no embeddings yet — they won't appear
in AI Chat Search results until they're indexed.

The plugin queues a background job to embed all published jobs.
Progress shows on the AI Settings page as **"Indexed X of Y jobs."**

Speed depends on the provider:
- OpenAI: ~50 jobs / minute.
- Claude: N/A (Claude isn't used for embeddings).
- Ollama: ~10–30 jobs / minute depending on hardware.

You can keep working — the indexer respects cron pacing and won't
block your server. To force-refresh all embeddings (e.g. you switched
providers and want a clean re-index), click **Reindex all jobs**.

## Privacy and data flow per provider

| Provider | Where the data goes | Logged? | Used for training? |
|---|---|---|---|
| **OpenAI** | OpenAI's US servers via TLS | Yes, by OpenAI (kept 30 days for abuse monitoring) | No, business-tier API is opted out of training by default |
| **Anthropic Claude** | Anthropic's US servers via TLS | Yes, by Anthropic (kept up to 30 days) | No, API data is not used for training |
| **Ollama** | Stays on your server. Never leaves. | Only if you log it yourself | N/A |

If you're under GDPR / CCPA / HIPAA constraints and can't share
candidate-identifying text with an external LLM, **use Ollama**. The
description writer and application ranking still work, just slower.

## Switching providers

You can change providers at any time without losing data.

- **Existing job vectors:** preserved as-is. If you switch from OpenAI
  (1536-dim) to Ollama (768-dim) the dimensions don't match, so AI
  Chat Search returns no results until you reindex. The plugin warns
  about this on switch and offers the reindex button.
- **Existing application rankings:** cached per application. They
  remain visible but won't be re-computed until you click the per-row
  refresh button on the applications screen.
- **Existing resume parses:** preserved on the candidate's profile.
  Re-parsing is a manual action from the candidate dashboard.

## Disabling AI

Set **Provider** to **Disabled** and save. Effects:

- AI Chat Search block returns an empty result set silently. Your page
  still loads — just no AI search.
- AI description writer button disappears from the post-a-job form.
- AI fit score column disappears from the applications screen.
- Background embedding cron is skipped.
- Stored `wcb_ai_vectors` rows are kept (no destructive change). If you
  re-enable AI later, search resumes against the existing data.
- API calls stop immediately.

To fully clean up: deactivate Pro, and on uninstall the AI tables go
with it.

## Common setup errors

See [06-troubleshooting.md](06-troubleshooting.md) for the full list. The
top three:

- **"Invalid API key"** — wrong key or wrong provider selected. Re-copy
  from the provider dashboard. OpenAI keys start `sk-`, Anthropic keys
  start `sk-ant-`. Strip whitespace.
- **"Connection refused" (Ollama)** — Ollama isn't running, or the
  Base URL is wrong. `ps aux | grep ollama` to confirm; restart with
  `systemctl restart ollama` if needed.
- **"Quota exceeded"** — your OpenAI or Anthropic monthly cap is hit.
  Raise the cap in the provider dashboard or wait for next billing
  cycle.

## Where to go next

- [03-candidate-ai-features.md](03-candidate-ai-features.md) — the
  candidate-facing flows.
- [04-employer-ai-features.md](04-employer-ai-features.md) — the
  employer-facing flows.
