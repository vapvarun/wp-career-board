# AI Features (Pro)

WP Career Board Pro adds AI-powered features to your job board: natural language search, automatic candidate-to-job matching, and AI-assisted application ranking. All features run through a single configurable provider.

> **Requires WP Career Board Pro** with a valid license key.

## What AI Unlocks

| Feature | What It Does |
|---------|-------------|
| **AI Chat Search** | Candidates type a natural language query ("remote React job, $100k+") and get semantically matched listings |
| **Candidate Matching** | The board automatically scores candidates against open jobs using vector embeddings |
| **Application Ranking** | Admins can rank all applications for a job by AI fit score (0-100) with a plain-language reason |

## How It Works

When a job is published, the plugin generates a vector embedding of the job title and description and stores it in the `wcb_ai_vectors` database table. When a candidate searches or applies, their resume or query is embedded the same way and compared against all job vectors using cosine similarity. The top matches are returned ranked by relevance.

## Provider Options

Three AI providers are supported. Choose the one that fits your hosting and privacy requirements.

| Provider | Embeddings | Completions | Notes |
|----------|-----------|-------------|-------|
| **OpenAI** | text-embedding-3-small | gpt-4o-mini | Recommended for most sites |
| **Anthropic Claude** | Not supported | claude-haiku-4-5 | Use OpenAI or Ollama for embeddings when Claude is active |
| **Ollama (self-hosted)** | nomic-embed-text | llama3 | Runs locally -- no data leaves your server |

## Setup

### Step 1: Choose a Provider

1. Go to **Career Board -> Settings -> AI**
2. Open the **Provider** dropdown
3. Select your provider (or **Disabled** to turn off all AI features)

### Step 2: Enter Your API Key or Base URL

**OpenAI**

- Paste your secret key (`sk-...`) into the **API Key** field
- Get a key at [platform.openai.com/api-keys](https://platform.openai.com/api-keys)

**Anthropic Claude**

- Paste your Anthropic key (`sk-ant-...`) into the **API Key** field
- Claude does not support embeddings -- candidate matching and AI Chat Search require OpenAI or Ollama as the provider

**Ollama (self-hosted)**

- Install Ollama on your server: `curl -fsSL https://ollama.com/install.sh | sh`
- Pull the required models: `ollama pull nomic-embed-text && ollama pull llama3`
- Enter the **Base URL** (default: `http://localhost:11434`)
- No API key is needed

### Step 3: Save

Click **Save AI Settings**. A confirmation notice confirms the settings were stored.

## AI Chat Search Block

Add the **AI Chat Search** block to any page to give candidates a natural language search bar.

1. Open the page in the block editor
2. Insert the **AI Chat Search** block (`wcb/ai-chat-search`)
3. Optionally change the **Placeholder** attribute (default: "Describe your ideal job...")
4. Publish the page

The block uses the WordPress Interactivity API -- no page reload occurs when candidates search.

## Performance Notes

- Vector matching is computed in PHP using cosine similarity
- Performance is acceptable for boards with fewer than 10,000 published jobs
- Embeddings are stored once per job on publish and re-generated on update
- Each provider call has a 30-second timeout (Ollama: 60 seconds for embeddings, 120 seconds for completions)

## Disabling AI Features

Set **Provider** to **Disabled -- AI features off**. No API calls are made. The AI Chat Search block returns an empty result set silently.
