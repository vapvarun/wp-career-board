# AI Block & Developer Surface

> **Pro-only surface.** The block, REST endpoints, and Pro filters below
> are registered by `wp-career-board-pro`. The Free plugin defines the
> gate filters (`wcb_ai_description_enabled`, `wcb_ai_ranking_available`,
> `wcb_ai_matching_available`, `wcb_ai_completion_available`) and the UI
> surfaces so Pro - or an add-on shipping its own AI driver - can flip
> them on.

This page documents the AI surface that ships in 1.4.3.

## AI Chat Search block

The candidate-facing natural-language search bar (chat-style).

### Block reference

| Property | Value |
|---|---|
| **Name** | `wcb/ai-chat-search` |
| **Editor category** | Widgets |
| **Render mode** | Server-side render (`render.php`) + Interactivity API frontend |
| **Pro-only?** | Yes - registered by Pro only |

### Attributes

One attribute is exposed:

| Attribute | Type | Default | Description |
|---|---|---|---|
| `placeholder` | string | `Describe your ideal job...` | Input placeholder text. |

If you need more (button label, max results, relevance bar), copy
`blocks/ai-chat-search/render.php` into a child plugin and customise.

### How to add it to a page

1. An **AI Job Search** page carrying this block is created
   automatically when Pro sets up. To add it elsewhere:
2. Open the page in the block editor.
3. Add a new block; search for **AI Chat Search**.
4. Optionally edit the placeholder text in the block sidebar.
5. Save / publish.

### Render contract

Server-side, the block:

1. Hard-gates on `AiModule::is_enabled()`. If AI is off, visitors get
   nothing; users with the `wcb/manage-settings` ability see a "AI search
   is not configured" hint pointing to AI Settings.
2. Reads the `placeholder` attribute and outputs an Interactivity API
   root with a chat-message area, an input/submit form, and a results
   area.
3. Loads `view.js` as a script module (no jQuery, no admin-ajax). It
   imports the shared `@wcb/fetch` helper (15s AbortController timeout).
4. On submit, the JS POSTs `{ query }` to `/wcb/v1/ai/match` with the
   `wp_rest` nonce. It reads `data.jobs` from the response and renders
   the matched job cards (title + company).

## AI Description Writer (Free hook, Pro behaviour)

The post-a-job form (full and simple) has a "Generate with AI" button
next to the description editor, gated behind `wcb_ai_description_enabled`:

```php
// In Free's job form render
if ( apply_filters( 'wcb_ai_description_enabled', false ) ) : ?>
    <button ... data-wp-on--click="actions.generateDescription">
        Generate with AI
    </button>
<?php endif;
```

Pro's `AiModule::is_enabled()` returns `true` for this filter when an
analysis or embedding provider is configured. An add-on can short-circuit
Pro:

```php
add_filter( 'wcb_ai_description_enabled', '__return_true' );
```

When clicked, the JS calls `POST /wcb/v1/jobs/ai-description` with
`title`, `company_type`, and `location`. The response is
`{ description: "<h3>...</h3>..." }` (structured HTML) which the JS
injects into the editor.

## AI cover-letter writer (apply panel)

The job-single apply panel shows a "Generate with AI" cover-letter button
when Pro is active and the completion provider is configured. On click
the JS POSTs to `/wcb/v1/jobs/{job_id}/ai-cover-letter` and inserts the
returned `{ cover_letter: "..." }` text into the cover-letter field for
the candidate to edit before submitting.

## REST endpoints

Five AI endpoints exist. All live in
`api/endpoints/class-ai-endpoint.php` and inherit from
`WCB\Pro\Api\ProRestController`.

| Method | Route | Permission | Body / Query | Response |
|---|---|---|---|---|
| POST | `/wcb/v1/ai/match` | logged-in user | `{ query }` (current user implied for matching) | enriched match cards (see below) |
| GET | `/wcb/v1/candidates/{id}/matches` | own candidate row OR `wcb/manage-ai` | path `id` | enriched match cards |
| GET | `/wcb/v1/ai/ranked-applications/{job_id}` | `wcb/view-applications` | path `job_id` | `[{application_id, score, reason, summary}]` |
| POST | `/wcb/v1/jobs/ai-description` | `wcb/post-jobs` | `title`, `company_type`, `location` | `{ description }` |
| POST | `/wcb/v1/jobs/{job_id}/ai-cover-letter` | logged-in user | path `job_id` | `{ cover_letter }` |

### Match card shape

`/ai/match` and `/candidates/{id}/matches` return enriched cards (jobs
that aren't published are skipped):

```json
[
  {
    "job_id": 123,
    "score": 0.82,
    "score_pct": 82,
    "title": "Senior React Engineer",
    "company": "Acme",
    "url": "https://example.com/job/senior-react-engineer/",
    "location": "Remote"
  }
]
```

The list passes through the `wcbp_ai_candidate_matches` filter before it
is returned, so add-ons can re-rank or decorate the cards.

### Rate limiting

Every endpoint applies a transient-based **30 calls per user per hour**
ceiling (`wcbp_ai_rate_{user_id}`). Hitting the cap returns:

```json
{
  "code": "wcb_rate_limit",
  "message": "AI request limit reached. Please try again later.",
  "data": { "status": 429 }
}
```

This is global per user across all five endpoints. The limit isn't
exposed as a filter; override the permission logic in a child class to
change it.

### Common error codes

| Code | Meaning |
|---|---|
| `wcb_ai_disabled` (HTTP 503) | Completion provider unset or its key missing. Returned from `/jobs/ai-description` and `/jobs/{id}/ai-cover-letter`; the match / ranked endpoints return an empty list instead. |
| `wcb_job_not_found` (HTTP 404) | Cover-letter route given an id that isn't a `wcb_job`. |
| `wcb_rate_limit` (HTTP 429) | Per-user hourly ceiling hit. |
| `rest_forbidden` (HTTP 403) | Caller lacks the ability check. |

## Filters Pro fires

| Filter | Args | Returns | Use case |
|---|---|---|---|
| `wcb_ai_description_enabled` | `bool` | bool | True when AI is enabled (any provider). Gates the description button. |
| `wcb_ai_ranking_available` | `bool` | bool | True when the completion provider is configured. Gates the dashboard ranking controls. |
| `wcb_ai_completion_available` | `bool` | bool | True when the completion provider is configured. |
| `wcb_ai_matching_available` | `bool` | bool | True when the embedding provider is configured. Gates matching surfaces. |
| `wcb_pro_ai_enabled` | `bool` | bool | True when Pro AI is active. Free fires it in `api/endpoints/class-settings-endpoint.php` to set the `ai_matching` flag in the `/wcb/v1` app-config payload. |
| `wcbp_ai_provider_drivers` | `array $builtin, string $credential, string $credential` | `array<slug, factory>` | Register a custom AI provider driver. |
| `wcbp_ai_provider_requires_api_key` | `bool $requires_key, string $provider` | bool | Override whether the active provider needs an API key (self-hosted gateways). |
| `wcbp_candidate_resume_data` | `int $user_id` | grouped resume array | Supply a candidate's resume data. Implemented by Pro's Resume module; override to feed your own data. |
| `wcbp_ai_candidate_matches` | `array $enriched, int $user_id` | array | Post-process the enriched match list before it is returned. |
| `wcbp_ai_ranked_applications` | `array $ranked, int $job_id` | array | Post-process the ranked-applications list before it is returned. |
| `wcbp_ai_claude_model` | `string $model` | string | Override the Claude completion model. |

## Free hooks Pro consumes

| Hook | Where | What Pro does |
|---|---|---|
| `wcb_job_created` | Free fires after a job is created | `AiModule::generate_job_embedding()` embeds title + content into `wcb_ai_vectors`. |
| `wcb_application_submitted` | Free fires after an application is submitted | `AiModule::maybe_schedule_scoring()` queues background scoring when `wcbp_ai_auto_rank` is on. |

## Cron action Pro fires

| Action | Args | Fired by | Listener |
|---|---|---|---|
| `wcbp_ai_score_application` | `int $app_id` | Single event scheduled on submit (auto-rank) | `AiModule::run_scheduled_scoring()` scores the application in the background. |

## Abilities used by AI endpoints

| Ability | Used by |
|---|---|
| `wcb/manage-ai` | `/candidates/{id}/matches` when the caller isn't the candidate |
| `wcb/view-applications` | `/ai/ranked-applications/{job_id}` |
| `wcb/post-jobs` | `/jobs/ai-description` |
| `wcb/manage-settings` | the block's admin-only "not configured" hint |

Grant the underlying capability (`wcb_view_applications`, `wcb_post_jobs`,
etc.) - the Abilities API reads it. See
[../admin-guide/14-capabilities-and-roles.md](../admin-guide/14-capabilities-and-roles.md).

## The AiModule public API

If you're writing a Pro add-on that needs AI directly:

```php
use WCB\Pro\Modules\Ai\AiModule;

$ai = new AiModule();

// Configuration checks.
$ai->is_enabled();             // analysis OR embedding configured
$ai->is_completion_enabled();  // analysis provider configured
$ai->is_embedding_enabled();   // embedding provider configured

// Top-N job matches for a candidate.
$matches = $ai->match_candidate_to_jobs( $user_id, 10 );
// list<array{job_id: int, score: float}>

// Score one application (cached; pass true to force a recompute).
$score = $ai->score_application( $app_id );
// array{application_id, score: int, reason: string, summary: string}

// All applications for a job, ranked best-first (each cached).
$ranked = $ai->rank_applications( $job_id );

// Backfill embeddings for existing published jobs (bounded per call).
$count = $ai->backfill_job_embeddings( 500 );

// Flatten a candidate's resume to text (via wcbp_candidate_resume_data).
$text = $ai->candidate_resume_text( $user_id );

// Per-task drivers (for custom prompts).
$completion = $ai->get_completion_driver();
$embedding  = $ai->get_embedding_driver();
$reply  = $completion->complete( 'Your prompt here' ); // string|WP_Error
$vector = $embedding->embed( 'Text to embed' );        // float[]|WP_Error
```

## Registering a custom AI provider

To ship a driver for Cohere, Mistral, a private LLM, etc., implement
`AiDriverInterface` and register it via the filter:

```php
<?php
namespace MyAddon\AI;

use WCB\Pro\Modules\Ai\AiDriverInterface;

class CohereDriver implements AiDriverInterface {

    public function __construct( private string $credential ) {}

    public function embed( string $text ): array|\WP_Error {
        // Call Cohere embed endpoint; return float[] or WP_Error.
    }

    public function complete( string $prompt ): string|\WP_Error {
        // Call Cohere chat endpoint; return string or WP_Error.
    }

    public function provider_name(): string {
        return 'cohere';
    }
}

add_filter(
    'wcbp_ai_provider_drivers',
    function ( array $drivers, string $credential ): array {
        $drivers['cohere'] = static fn(): AiDriverInterface
            => new \MyAddon\AI\CohereDriver( $credential );
        return $drivers;
    },
    10,
    2
);
```

The `complete()` method takes only a prompt string; the `embed()` method
takes a text string and returns a float vector (or `WP_Error`).

## Notes for add-on authors

- **No shortcode wrapper for the AI block** - it is block-editor /
  page-builder only.
- **No `wp wcb ai *` WP-CLI namespace** - drive AI from `wp eval` using
  the `AiModule` public methods above (for example to backfill
  embeddings in bulk).
- **Scores are cached** in application post meta (`_wcbp_ai_fit_score`,
  `_wcbp_ai_fit_reason`, `_wcbp_ai_summary`, `_wcbp_ai_scored_at`). Read
  them directly if you only need the cached value.

## Where to go next

- [01-overview.md](01-overview.md) - what each AI feature does at a glance.
- [02-setup-and-providers.md](02-setup-and-providers.md) - provider setup.
- Pro hooks reference: [docs.wbcomdesigns.com/docs/wp-career-board-pro/developer-guide/03-hooks-reference](https://docs.wbcomdesigns.com/docs/wp-career-board-pro/developer-guide/03-hooks-reference/)
</content>
