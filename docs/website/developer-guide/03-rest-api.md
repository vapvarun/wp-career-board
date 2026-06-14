# REST API Reference

WP Career Board registers **41 REST routes** under the `wcb/v1`
namespace. Every endpoint extends `WCB\Api\RestController`, which
owns the shared response envelope and the abilities-aware
permission helper.

## Authentication

Most endpoints require a logged-in WordPress user and a valid
nonce. Use the standard WP REST nonce in headers:

```js
fetch( '/wp-json/wcb/v1/jobs', {
    headers: { 'X-WP-Nonce': wpApiSettings.nonce },
    credentials: 'same-origin'
})
```

For server-to-server calls, generate an application password
(Users -> Profile -> Application Passwords) and use HTTP Basic auth.

A small set of endpoints permit guest access - the read-only jobs,
companies, and search endpoints, the candidate/employer
registration endpoints, and the apply endpoint when "Allow guest
applications" is enabled in Settings. Guest endpoints use the
`__return_true` permission_callback.

Abuse prevention on submission endpoints comes from the anti-spam
module (an always-on honeypot field plus an optional CAPTCHA
provider - Google reCAPTCHA v3 or Cloudflare Turnstile), which
hooks `rest_pre_dispatch` and rejects spammy requests before they
reach the handler. There is no per-IP request-rate limiter.

## Response envelope

Every endpoint returns either a `WP_REST_Response` (success) or a
`WP_Error` (failure). Success shape varies by endpoint; failure
shape is consistent:

```json
{
    "code": "wcb_invalid_status",
    "message": "Invalid status.",
    "data": { "status": 400 }
}
```

The `wcb_*` prefix on error codes is the plugin's namespace -
addons should mirror this convention with their own prefix.

## Routes by area

All routes below are relative to `/wp-json/wcb/v1`.

### Jobs

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `GET` | `/jobs` | guest OK | List jobs with filters: `s`, `category`, `location`, `type`, `experience`, `remote`, `salary_min`, `salary_max`, `board_id`, `per_page`, `page` |
| `GET` | `/jobs/{id}` | guest OK | Single job (full detail) |
| `POST` | `/jobs` | employer | Create a job |
| `PUT` | `/jobs/{id}` | author or admin | Update a job |
| `DELETE` | `/jobs/{id}` | author or admin | Delete a job |
| `POST` | `/jobs/{id}/approve` | moderator | Approve a pending job |
| `POST` | `/jobs/{id}/reject` | moderator | Reject a pending job (requires `reason`) |
| `POST` | `/jobs/{id}/bookmark` | logged-in | Toggle a saved/bookmarked job |
| `POST` | `/jobs/{id}/report` | logged-in | Report a job for moderation (deduped per user) |
| `POST` | `/jobs/{id}/resolve-flag` | moderator | Dismiss or unpublish a flagged job |
| `GET` | `/jobs/{id}/applications` | author or admin | List applications for a job |

Republishing an expired job is available via WP-CLI
(`wp wcb job ...`) and the admin Jobs screen, not as a dedicated
REST route.

### Applications

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `POST` | `/jobs/{id}/apply` | candidate (or guest if enabled) | Submit application |
| `GET` | `/applications/{id}` | candidate or job-owner | Single application detail |
| `DELETE` | `/applications/{id}` | candidate owner | Withdraw application |
| `PUT` | `/applications/{id}/status` | employer/admin | Change status (submitted/reviewing/shortlisted/rejected/hired) |
| `GET` | `/candidates/{id}/applications` | self or admin | Candidate's application history |
| `POST` | `/candidates/resume-upload` | candidate | Upload a resume PDF |

### Candidates

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `POST` | `/candidates/register` | guest | Register a new candidate |
| `GET` | `/candidates/{id}` | self or admin | Candidate profile |
| `PUT` | `/candidates/{id}` | self | Update profile |
| `GET`/`POST` | `/candidates/{id}/bookmarks` | self | Read or toggle saved jobs |
| `GET`/`POST` | `/candidates/{id}/saved-companies` | self | Read or toggle saved companies |
| `GET`/`POST` | `/candidates/{id}/saved-resumes` | self | Read or toggle saved resumes |
| `POST` | `/candidates/me/privacy/{action}` | self | GDPR self-service: `export` or `erase` personal data |

### Account

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `GET` | `/account` | logged-in | Read the current user's Career Board account profile |
| `PUT` | `/account` | logged-in | Update the current user's account profile |

### Employers and companies

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `POST` | `/employers/register` | guest | Register a new employer |
| `GET` | `/employers` | guest OK | List employers |
| `GET` | `/employers/{id}` | self or admin | Employer detail |
| `GET` | `/employers/{id}/jobs` | self or admin | Employer's job postings |
| `GET` | `/employers/{id}/applications` | self or admin | Applications across the employer's jobs |
| `POST` | `/employers/{id}/logo` | self | Upload the company logo |
| `GET` | `/employers/me/jobs` | employer | The current employer's own jobs |
| `GET` | `/companies` | guest OK | List companies with filters (single company is read from this collection) |
| `POST` | `/companies/{id}/bookmark` | logged-in | Toggle a saved company |
| `POST` | `/companies/{id}/trust` | logged-in | Cast a trust signal on a company |

### Search and settings

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `GET` | `/search` | guest OK | Unified search across jobs and companies |
| `GET` | `/settings/app-config` | guest OK | Frontend boot config consumed by the Interactivity blocks |

Plugin settings are saved through the admin Settings page (the
WordPress Settings API), not a REST write route.

### Admin

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `GET` | `/admin/emails/log` | admin | Paginated transactional-email send log |
| `POST` | `/admin/emails/test` | admin | Fire a test send for a named email template |
| `POST` | `/admin/dismiss-banner` | logged-in | Mark an admin banner dismissed for the current user |

### Import and setup wizard

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `GET` | `/import/status` | admin | Poll a running import's progress |
| `POST` | `/import/run` | admin | Start or step a content import |
| `POST` | `/wizard/create-pages` | admin | Create the required Career Board pages |
| `POST` | `/wizard/sample-data` | admin | Install demo content |
| `POST` | `/wizard/remove-sample-data` | admin | Remove the demo content |
| `POST` | `/wizard/complete` | admin | Mark the setup wizard finished |

## Modifying responses

Career Board responses pass through a `wcb_rest_prepare_*` filter
(see [02-hooks-reference.md](02-hooks-reference.md)) before they
are returned. Check the filter-argument table in the hooks
reference - the signature is not the same for every entity. To add
a custom field to the jobs response:

```php
add_filter( 'wcb_rest_prepare_job', function ( $row, $post ) {
    $row['custom_score'] = my_score_function( $post->ID );
    return $row;
}, 10, 2 );
```

## Adding new routes

The cleanest way is to extend `WCB\Api\RestController`:

```php
namespace MyAddon;

class My_Endpoint extends \WCB\Api\RestController {

    public function register_routes(): void {
        register_rest_route(
            $this->namespace,  // wcb/v1
            '/my-thing/(?P<id>\d+)',
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_item' ),
                'permission_callback' => function (): bool {
                    return $this->check_ability( 'wcb/post-jobs' );
                },
                'args'                => array(
                    'id' => array(
                        'validate_callback' => static fn( $v ) => is_numeric( $v ),
                        'sanitize_callback' => 'absint',
                    ),
                ),
            )
        );
    }

    public function get_item( \WP_REST_Request $request ): \WP_REST_Response {
        // ... your handler
    }
}

add_action( 'rest_api_init', static function () {
    ( new My_Endpoint() )->register_routes();
});
```

You inherit the response envelope, `current_user_id()`,
`permission_error()` (401 vs 403), and the abilities-aware
`check_ability( $ability )` helper - pass the ability slug you
want to gate on. Route-path disjointness with the plugin's own
routes is enforced by the architecture-checks gate (invariant A3
in Pro's INVARIANTS.yaml).

## Abuse prevention

There is no per-IP request-rate limiter. Submission endpoints are
protected by the anti-spam module instead: an always-on honeypot
field plus an optional CAPTCHA provider (Google reCAPTCHA v3 or
Cloudflare Turnstile), configured under Settings -> Anti-Spam. The
module validates on the `rest_pre_dispatch` filter and rejects
spammy submissions before the route handler runs.
