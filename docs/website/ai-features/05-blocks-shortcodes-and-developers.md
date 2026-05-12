# AI Block & Developer Surface

> **Pro-only surface.** The block, REST endpoints, and Pro filters
> below are registered by `wp-career-board-pro`. The Free plugin
> defines the gate filter `wcb_ai_description_enabled` so the
> description-writer button can be flipped on by Pro (or by an
> add-on that wants to ship its own AI driver).

This page documents the AI surface that actually ships in 1.1.1.
If a feature isn't listed here, it isn't wired into the codebase yet,
regardless of what marketing materials might say.

## AI Chat Search block

The user-facing natural-language search bar.

### Block reference

| Property | Value |
|---|---|
| **Name** | `wcb/ai-chat-search` |
| **Editor category** | Widgets |
| **Render mode** | Server-side render (`render.php`) + Interactivity API frontend |
| **Pro-only?** | Yes - registered by Pro only |

### Attributes (shipping in 1.1.1)

Only one attribute is exposed on the block today:

| Attribute | Type | Default | Description |
|---|---|---|---|
| `placeholder` | string | `Describe your ideal job...` | Input placeholder text. |

That's deliberate - the block is intentionally minimal in 1.1.1.
Additional attributes (button label, max results, relevance bar, etc.)
are tracked for a future release; if you need them today, copy
`blocks/ai-chat-search/render.php` into a child plugin and customise.

### How to add it to a page

1. Open the page in the block editor.
2. Add a new block; search for **AI Chat Search**.
3. Optionally edit the placeholder text in the block sidebar.
4. Save / publish.

### Render contract

Server-side, the block:

1. Reads the `placeholder` attribute and outputs an Interactivity API
   root.
2. Loads `view.js` as a script module (no jQuery, no admin-ajax).
3. On submit, the JS POSTs to `/wp-json/wcb/v1/ai/match` with the
   current user's session cookie. The response is a list of
   `{job_id, score}` rows; the JS renders the matching job cards.

If Pro is inactive or AI is disabled, the block silently returns an
empty result set - no broken markup, no PHP notice, no JS error.

## AI Description Writer (Free hook, Pro behaviour)

The post-a-job form has a "Generate with AI" button next to the
description editor. It's gated behind the `wcb_ai_description_enabled`
filter:

```php
// In Free's blocks/job-form/render.php
if ( apply_filters( 'wcb_ai_description_enabled', false ) ) : ?>
    <button ... data-wp-on--click="actions.generateDescription">
        Generate with AI
    </button>
<?php endif;
```

Pro's `AiModule::is_enabled()` returns `true` for this filter when a
provider + API key are configured. An add-on can short-circuit Pro and
ship its own driver:

```php
add_filter( 'wcb_ai_description_enabled', '__return_true' );
```

When clicked, the JS calls `POST /wcb/v1/jobs/ai-description` with
`title`, `company_type`, and `location` from the form. The response
returns `{description: "..."}` which the JS injects into the editor.

## REST endpoints (shipping in 1.1.1)

Four AI endpoints exist. All live in
`api/endpoints/class-ai-endpoint.php` and inherit from
`WCB\Pro\Api\ProRestController`.

| Method | Route | Permission | Body / Query |
|---|---|---|---|
| POST | `/wcb/v1/ai/match` | logged-in user | none - implies current user |
| GET | `/wcb/v1/candidates/{id}/matches` | own candidate row OR `wcb/manage-ai` | path id |
| GET | `/wcb/v1/ai/ranked-applications/{job_id}` | `wcb/view-applications` | path job_id |
| POST | `/wcb/v1/jobs/ai-description` | `wcb/post-jobs` | `title`, `company_type`, `location` |

### Rate limiting

Every endpoint applies a transient-based **30 calls per user per
hour** ceiling. Hitting the cap returns:

```json
{
  "code": "wcb_rate_limit",
  "message": "AI request limit reached. Please try again later.",
  "data": { "status": 429 }
}
```

This is global per user across all four endpoints. If you need a
higher ceiling, override the transient logic in a child class - the
limit isn't exposed as a filter in 1.1.1.

### Common error codes

| Code | Meaning |
|---|---|
| `wcb_ai_disabled` (HTTP 503) | Provider unset or API key missing. Returned from `/jobs/ai-description`; other endpoints return an empty list silently. |
| `wcb_rate_limit` (HTTP 429) | Per-user hourly ceiling hit. |
| `rest_forbidden` (HTTP 403) | Caller lacks the ability check. |

## Filters Pro fires (shipping in 1.1.1)

| Filter | Args | Returns | Use case |
|---|---|---|---|
| `wcbp_ai_provider_drivers` | `array $builtin, string $api_key, string $base_url` | array of `slug => factory_callable` | Register a custom AI provider driver. |
| `wcbp_ai_provider_requires_api_key` | `bool $requires_key, string $provider` | bool | Override whether the active provider needs an API key. Useful for self-hosted vLLM / on-prem gateways. |
| `wcbp_candidate_resume_data` | `int $user_id` | `array<string, list<array<string, string>>>` (group => entries) | Return the resume data shape for matching / ranking. Add-ons hooking Pro's resume builder satisfy this so AI can consume it. |

## Filter Pro consumes from Free

| Filter | Where | Effect |
|---|---|---|
| `wcb_ai_description_enabled` | Free `blocks/job-form/render.php` | Pro's `AiModule::is_enabled()` returns true when a provider + key are configured. |

## Action hook Pro listens to

| Hook | Where | What Pro does |
|---|---|---|
| `wcb_job_created` | Free fires after a job post is created | Pro's `AiModule::generate_job_embedding()` embeds the title + content and stores in `wcb_ai_vectors`. |

Pro does **not** fire any custom `do_action` events around the AI
flow in 1.1.1. If you need to observe an AI operation (e.g. log every
embedding cost), wrap the relevant call yourself or extend
`AiEndpoint` in a child class.

## Abilities used by AI endpoints

These are the WordPress Abilities (registered under the `wcb/`
namespace) the AI endpoints check:

| Ability | Used by |
|---|---|
| `wcb/manage-ai` | `/candidates/{id}/matches` when the caller isn't the candidate |
| `wcb/view-applications` | `/ai/ranked-applications/{job_id}` |
| `wcb/post-jobs` | `/jobs/ai-description` |

Grant the underlying capability (`wcb_view_applications`,
`wcb_post_jobs`, etc.) - the Abilities API reads it. See
[../admin-guide/14-capabilities-and-roles.md](../admin-guide/14-capabilities-and-roles.md).

## The AiModule public API

If you're writing a Pro add-on that needs to talk to AI directly:

```php
use WCB\Pro\Modules\Ai\AiModule;

$ai = new AiModule();

// Quick check before doing anything else.
if ( ! $ai->is_enabled() ) {
    return;
}

// Top-N job matches for a candidate.
$matches = $ai->match_candidate_to_jobs( $user_id, 10 );
// Returns: list<array{job_id: int, score: float}>

// All applications for a job ranked by AI score.
$ranked = $ai->rank_applications( $job_id );
// Returns: list<array{application_id: int, score: int, reason: string}>

// Direct provider access (for custom prompts).
$driver = $ai->get_driver();
$reply  = $driver->complete( 'Your prompt here' );
$vector = $driver->embed( 'Text to embed' );
```

Each call goes through the configured provider (`openai`, `claude`,
`ollama`, or one registered via `wcbp_ai_provider_drivers`).

## Registering a custom AI provider

To ship a driver for Cohere, Mistral, a private LLM, etc., implement
`AiDriverInterface` and register it via the filter:

```php
<?php
namespace MyAddon\AI;

use WCB\Pro\Modules\Ai\AiDriverInterface;

class CohereDriver implements AiDriverInterface {

    public function __construct( private string $api_key ) {}

    public function embed( string $text ): array|\WP_Error {
        // Call Cohere embed endpoint; return float[] vector
        // or WP_Error on failure.
    }

    public function complete( string $prompt, array $options = array() ): string|\WP_Error {
        // Call Cohere chat endpoint; return string or WP_Error.
    }

    public function provider_name(): string {
        return 'cohere';
    }
}

add_filter(
    'wcbp_ai_provider_drivers',
    function ( array $drivers, string $api_key, string $base_url ): array {
        $drivers['cohere'] = static fn(): AiDriverInterface
            => new \MyAddon\AI\CohereDriver( $api_key );
        return $drivers;
    },
    10,
    3
);
```

Once registered, "Cohere" becomes a valid value for the
`wcbp_ai_provider` option - set it in the AI Settings tab or via
WP-CLI / direct option update.

## What 1.1.1 does NOT ship (set expectations honestly)

Several capabilities that you might expect from a "modern AI job board"
are not in 1.1.1. Listed so add-on authors know where to focus and
customers aren't surprised:

- **Resume parsing** - the filter `wcbp_candidate_resume_data` exists
  so an add-on can supply parsed data, but Pro does NOT ship a
  resume-parser. The data flow assumes whatever produces the resume
  data hooks the filter.
- **No AI fit-score column on the applications admin screen** -
  the REST endpoint `/ai/ranked-applications/{job_id}` returns the
  data, but the admin column UI isn't registered. Render it yourself
  via a custom column registration if you need it.
- **No shortcode wrapper for the AI block** - the block is
  block-editor / page-builder only in 1.1.1.
- **No WP-CLI command surface for AI** - the WP-CLI namespace
  `wp wcb ai *` does not exist.
- **No background indexer / reindex button** - embeddings are
  generated once at `wcb_job_created` only. Existing jobs from before
  AI was enabled do NOT auto-backfill. If you need backfill, iterate
  jobs and call `AiModule::generate_job_embedding()` directly.
- **No "Test connection" button** - configuration is validated only
  when a real AI call is made.
- **No action hooks around the AI lifecycle** (no
  `wcbp_ai_job_embedded`, no `wcbp_ai_application_scored`, etc.).
  If you need to observe these events, wrap the public AiModule
  methods in your add-on.

These are honest gaps, not bugs - they're features queued for a
later release. If any are blockers for your use case, file on the
support board so they get prioritised correctly.

## Where to go next

- [01-overview.md](01-overview.md) - what each AI feature does at a glance.
- [02-setup-and-providers.md](02-setup-and-providers.md) - provider setup.
- Pro hooks reference: [docs.wbcomdesigns.com/docs/wp-career-board-pro/developer-guide/03-hooks-reference](https://docs.wbcomdesigns.com/docs/wp-career-board-pro/developer-guide/03-hooks-reference/)
