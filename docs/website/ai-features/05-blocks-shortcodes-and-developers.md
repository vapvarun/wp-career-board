# AI Blocks, Shortcodes & Developer Hooks

> **Mostly Pro.** The blocks and hooks documented below are registered
> by the Pro plugin. Free defines the gate filter (`wcb_ai_description_enabled`)
> so add-ons can pretend Pro is there for testing.

This page documents how to embed AI features on a page (block + shortcode),
and the hooks an integrator can use to extend or override AI behaviour.

## AI Chat Search block

The user-facing natural-language search bar.

### Block reference

| Property | Value |
|---|---|
| **Name** | `wcb/ai-chat-search` |
| **Editor namespace** | Career Board → AI |
| **Render mode** | Server-side render (`render.php`), Interactivity API on the frontend |
| **Pro-only?** | Yes — block is registered by Pro only |

### Attributes

| Attribute | Type | Default | Description |
|---|---|---|---|
| `placeholder` | string | `Describe your ideal job...` | Input placeholder text. |
| `buttonLabel` | string | `Search` | Submit button label. |
| `maxResults` | int | `10` | How many job cards to show. Clamp: 1–50. |
| `minQueryLength` | int | `4` | Below this length the input doesn't fire (avoids embedding cost on accidental keystrokes). |
| `showRelevance` | bool | `true` | Show / hide the per-result relevance bar. |
| `themeStyle` | string | `default` | One of `default`, `compact`, `minimal`. |

### How to add it to a page

In the block editor:

1. Open the page in **Edit**.
2. Add a new block, search for **AI Chat Search**, and insert.
3. In the block sidebar, tweak the placeholder / max results / style
   as needed.
4. Save / publish the page.

### Shortcode equivalent

Every Career Board block has a shortcode wrapper so it works in
classic editors, page builders that don't speak blocks, and Pattern
Library entries:

```
[wcb_ai_chat_search
   placeholder="Try: remote React job, US time zones, $100k+"
   button_label="Find jobs"
   max_results="20"
   min_query_length="6"
   show_relevance="true"
   theme_style="compact"]
```

Attribute names are snake_case in the shortcode form (the block uses
camelCase). The shortcode wraps the block in the same Interactivity
API context, so it behaves identically on the front end.

### Render contract

Server-side, the block:

1. Reads attributes and outputs an Interactivity API root with
   `wp-interactive="wcb/ai-chat-search"`.
2. Loads the `view.js` module.
3. On submit, the JS calls `POST /wp-json/wcb/v1/ai/chat-search` with
   `{ query, max_results }` and renders the response.

If Pro is inactive or AI is disabled, the block renders nothing —
no broken markup, no PHP notices, no JS errors.

## AI Fit Score column

This is **not** a block — it's a column on the applications admin
screen, registered automatically when Pro AI is on. There's no
front-end shortcode equivalent because the data is admin-only.

If you want to expose it elsewhere (e.g. a custom employer dashboard
template), use the REST endpoint:

```
GET /wp-json/wcb/v1/applications/{id}/ai-score
→ { "score": 78, "reason": "Strong React + TypeScript, mid-senior fit" }
```

Requires `wcb_view_applications` capability.

## Developer hooks

The complete AI hook surface. Hooks are stable as of 1.1.1 — additions
are non-breaking, removals follow a one-version deprecation cycle.

### Filters (Free namespace, used by Pro)

| Hook | Default | Use case |
|---|---|---|
| `wcb_ai_description_enabled` | `false` | Return `true` to surface the description writer button even on a Free install. Used by Pro to flip it on. |

```php
add_filter( 'wcb_ai_description_enabled', '__return_true' );
```

### Filters (Pro namespace, `wcbp_*`)

| Hook | Default | Use case |
|---|---|---|
| `wcbp_ai_provider_drivers` | OpenAI, Claude, Ollama | Register a custom AI provider driver. |
| `wcbp_ai_provider_requires_api_key` | `true` for OpenAI/Claude, `false` for Ollama | Override whether the active provider needs a key. |
| `wcbp_candidate_resume_data` | Plain extracted text | Reshape the data sent to the AI for parsing / matching. |

### Action hooks the AI features fire

These all live in the Pro plugin and fire after the relevant AI
operation completes. Subscribe to log, audit, or react.

| Hook | Args | When |
|---|---|---|
| `wcbp_ai_job_embedded` | `$job_id, $provider, $tokens_used` | After a job is successfully embedded. |
| `wcbp_ai_resume_parsed` | `$candidate_id, $parsed_data, $provider` | After a resume is parsed. |
| `wcbp_ai_application_scored` | `$application_id, $score, $reason` | After an application is scored. |
| `wcbp_ai_description_generated` | `$job_id_or_null, $bullets, $output, $tokens_used` | After the description writer runs. |
| `wcbp_ai_chat_search_run` | `$query, $matched_ids, $duration_ms` | After an AI Chat Search query is matched. Useful for analytics. |
| `wcbp_ai_provider_error` | `$provider, $error, $context` | When any AI call fails. Hook this to alert / log / disable. |

### Registering a custom AI provider

If you want to use Cohere, Mistral, a private LLM, etc., implement
the `AiDriverInterface` and register it:

```php
<?php
namespace MyAddon\AI;

use WCBPro\Modules\AI\AiDriverInterface;

class CohereDriver implements AiDriverInterface {

    public function embed( string $text ): array|\WP_Error {
        // Call Cohere embed endpoint, return float[] vector.
        // On failure return new \WP_Error( 'wcbp_ai_error', $message );
    }

    public function complete( string $prompt, array $options = array() ): string|\WP_Error {
        // Call Cohere chat endpoint, return string.
    }

    public function id(): string {
        return 'cohere';
    }

    public function label(): string {
        return __( 'Cohere', 'my-addon' );
    }
}

add_filter( 'wcbp_ai_provider_drivers', function ( array $drivers ): array {
    $drivers['cohere'] = new \MyAddon\AI\CohereDriver();
    return $drivers;
});
```

Once registered, "Cohere" appears in the Provider dropdown on **Career
Board → Settings → AI**. The user enters their API key as normal.

### Reshape resume data sent to AI

`wcbp_candidate_resume_data` lets you redact, augment, or restructure
the data sent to the provider for parsing / matching.

```php
add_filter( 'wcbp_candidate_resume_data', function ( array $data, int $candidate_id ): array {
    // Example: strip out exact dates of birth before sending to AI.
    unset( $data['dob'] );

    // Example: add internal employer rating to the data sent for matching.
    $data['internal_rating'] = (int) get_user_meta( $candidate_id, 'internal_rating', true );

    return $data;
}, 10, 2 );
```

### React to scoring events

```php
add_action( 'wcbp_ai_application_scored', function ( int $app_id, int $score, string $reason ): void {
    // Auto-notify employer for very strong matches.
    if ( $score >= 90 ) {
        $job_id    = (int) get_post_meta( $app_id, '_wcb_job_id', true );
        $author_id = (int) get_post_field( 'post_author', $job_id );
        wp_mail(
            get_userdata( $author_id )->user_email,
            'Strong applicant match',
            "AI scored an applicant {$score}/100 for {$job_id}: {$reason}"
        );
    }
}, 10, 3 );
```

### Auto-alert on AI errors

```php
add_action( 'wcbp_ai_provider_error', function ( string $provider, \WP_Error $error, array $context ): void {
    if ( WP_DEBUG ) {
        error_log( "AI error ({$provider}): " . $error->get_error_message() );
    }
    // Optional: send a Slack alert on the third consecutive failure.
});
```

### REST endpoints

These are the endpoints AI features call internally. You can call them
from your own code with the appropriate capability.

| Method | Route | Requires | Body / Query |
|---|---|---|---|
| POST | `/wcb/v1/ai/chat-search` | nothing (public) | `{ query, max_results }` |
| POST | `/wcb/v1/ai/describe` | `wcb_post_jobs` | `{ title, bullets, tone? }` |
| POST | `/wcb/v1/ai/parse-resume` | own candidate row or `wcb_view_applications` | `{ resume_id }` |
| POST | `/wcb/v1/ai/score-application` | `wcb_view_applications` | `{ application_id }` |
| GET | `/wcb/v1/ai/status` | nothing | Provider + readiness state |
| POST | `/wcb/v1/ai/reindex-all` | `wcb_manage_settings` | Triggers background reindex |
| POST | `/wcb/v1/ai/test-connection` | `wcb_manage_settings` | Runs the provider self-check |

All endpoints return `WP_Error` on failure with a meaningful code
(`wcbp_ai_provider_error`, `wcbp_ai_provider_disabled`,
`wcbp_ai_no_credits`, etc.). Nonces are required for non-GET routes.

## CLI commands

If WP-CLI is available:

```bash
# Reindex all published jobs against the current provider.
wp wcb ai reindex --network-wide=false

# Test the configured provider (same as Settings → Test connection).
wp wcb ai test

# Score a specific application from the CLI.
wp wcb ai score-application 12345

# Show the current AI status.
wp wcb ai status
```

These are Pro-only commands. The base `wp wcb` namespace is shared
with Free.

## Where to go next

- [02-setup-and-providers.md](02-setup-and-providers.md) — provider setup.
- [06-troubleshooting.md](06-troubleshooting.md) — error catalogue.
- For the full plugin hook reference (~92 Free + 30 Pro hooks), see the
  developer guide:
  [../developer-guide/02-hooks-reference.md](../developer-guide/02-hooks-reference.md).
