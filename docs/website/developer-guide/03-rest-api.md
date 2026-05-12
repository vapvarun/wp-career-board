# REST API Reference

WP Career Board exposes **37 REST endpoints** under the `wcb/v1`
namespace. Every endpoint extends `WCB\Api\REST_Controller`, which
owns the shared response envelope, permission helper, and
rate-limit middleware.

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
(Users → Profile → Application Passwords) and use HTTP Basic auth.

A small set of endpoints permit guest access — the apply endpoint
when "Allow guest applications" is enabled in Settings, and the
read-only jobs listing endpoint. Guest endpoints use the
`__return_true` permission_callback and rely on rate-limit
middleware for abuse prevention.

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

The `wcb_*` prefix on error codes is the plugin's namespace —
addons should mirror this convention with their own prefix.

## Routes by area

### Jobs

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `GET` | `/jobs` | guest OK | List jobs with filters: `s`, `category`, `location`, `type`, `experience`, `remote`, `salary_min`, `salary_max`, `board_id`, `per_page`, `page` |
| `GET` | `/jobs/{id}` | guest OK | Single job (full detail) |
| `POST` | `/jobs` | employer | Create a job |
| `PUT` | `/jobs/{id}` | author or admin | Update a job |
| `DELETE` | `/jobs/{id}` | author or admin | Delete a job |
| `POST` | `/jobs/{id}/republish` | author | Republish an expired job |
| `POST` | `/jobs/{id}/approve` | moderator | Approve a pending job |
| `POST` | `/jobs/{id}/reject` | moderator | Reject a pending job (requires `reason`) |
| `GET` | `/jobs/{id}/applications` | author or admin | List applications for a job |

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
| `GET` | `/candidates/{id}/bookmarks` | self | Saved jobs |
| `POST` | `/candidates/{id}/bookmarks` | self | Save/unsave a job |
| `DELETE` | `/candidates/account` | self | GDPR erasure request |

### Employers and companies

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `POST` | `/employers/register` | guest | Register a new employer |
| `GET` | `/employers/{id}` | self or admin | Employer detail |
| `PUT` | `/employers/{id}` | self | Update profile |
| `GET` | `/employers/{id}/jobs` | self or admin | Employer's job postings |
| `GET` | `/companies` | guest OK | List companies with filters |
| `GET` | `/companies/{id}` | guest OK | Single company |
| `PUT` | `/companies/{id}` | owner | Update company profile |

### Admin and settings

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `GET` | `/settings` | admin | Read every Career Board setting |
| `POST` | `/settings` | admin | Update settings |
| `GET` | `/admin/email-log` | admin | Recent transactional-email send log |
| `POST` | `/admin/email-log/clear` | admin | Truncate the email log |

### Setup wizard

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `POST` | `/wizard/install-pages` | admin | Create the 7 required pages |
| `POST` | `/wizard/sample-data` | admin | Install demo content |
| `POST` | `/wizard/complete` | admin | Mark wizard as finished |

## Modifying responses

Every Career Board response goes through a `wcb_rest_prepare_*`
filter (see [02-hooks-reference.md](02-hooks-reference.md)) before
it's returned. To add a custom field to the jobs response:

```php
add_filter( 'wcb_rest_prepare_job', function ( $row, $post, $request, $context ) {
    $row['custom_score'] = my_score_function( $post->ID );
    return $row;
}, 10, 4 );
```

The `$context` parameter is one of `single`, `collection`, or
`embed` — use it to tailor the response to where it's being read.

## Adding new routes

The cleanest way is to extend `WCB\Api\REST_Controller`:

```php
namespace MyAddon;

class My_Endpoint extends \WCB\Api\REST_Controller {

    public function register_routes(): void {
        register_rest_route(
            $this->namespace,  // wcb/v1
            '/my-thing/(?P<id>\d+)',
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_item' ),
                'permission_callback' => array( $this, 'check_ability' ),
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

You get rate-limit middleware, the response envelope, and the
ability-check helper for free. Route-path disjointness with the
plugin's own routes is enforced by the architecture-checks gate
(invariant A3 in Pro's INVARIANTS.yaml).

## Rate limiting

The plugin enforces per-IP rate limits on `POST`/`PUT`/`DELETE`
endpoints. Limits are configurable via:

```php
add_filter( 'wcb_rate_limit_per_minute', function ( $limit, $route ) {
    if ( '/apply' === $route ) {
        return 5; // Tighter limit on apply endpoint
    }
    return $limit; // Default 20/min
}, 10, 2 );
```
