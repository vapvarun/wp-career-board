# REST API Reference

WP Career Board registers **56 REST routes** under the `wcb/v1`
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
companies, candidates, employers, and search endpoints, the
candidate/employer registration endpoints, and the apply endpoint.
Guest applications are always allowed - `submit_permissions_check()`
returns `true` unconditionally for a logged-out request, no setting
gates this; a logged-in user must instead hold the `wcb/apply-jobs`
ability. Guest endpoints use the `__return_true` permission_callback
(except the apply endpoint, which uses the custom check above).

Abuse prevention on submission endpoints comes from the anti-spam
module (an always-on honeypot field plus an optional CAPTCHA
provider - Cloudflare Turnstile, Google reCAPTCHA v3 or v2), which
hooks `rest_pre_dispatch` and rejects spammy requests before they
reach the handler. Some public routes also have hourly per-IP limits -
see [Abuse prevention](#abuse-prevention).

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
| `GET` | `/jobs` | guest OK | List jobs with filters: `search` (alias `s`), `category`, `location`, `type`, `experience`, `tag` (each takes one slug or a comma list, any of), `remote`, `salary_min`, `salary_max`, `board` (alias `board_id`), `company`, `author`, `open` (only jobs taking applications), `sort` (`relevance`, `newest`, `oldest`, `salary`, `closing`), `per_page` (1-100; unset uses **Jobs Per Page**), `page` |
| `GET` | `/jobs/{id}` | guest OK | Single job (full detail) |
| `POST` | `/jobs` | employer | Create a job. Send `featured: true` to ask for featured placement: the job is still created if that charge fails, and the response carries `feature_error`. A 402 `wcb_insufficient_credits` carries `cost`, `balance` and `purchase_url` |
| `PUT` `PATCH` | `/jobs/{id}` | author or admin | Update a job. Moderators can set `featured` here |
| `DELETE` | `/jobs/{id}` | author or admin | Delete a job |
| `POST` | `/jobs/{id}/approve` | moderator | Approve a pending job |
| `POST` | `/jobs/{id}/reject` | moderator | Reject a pending job (requires `reason`) |
| `POST` | `/jobs/{id}/bookmark` | logged-in | Toggle a saved/bookmarked job |
| `POST` | `/jobs/{id}/feature` | job owner | Pay to feature a job. Added by WP Career Board Pro, not listed in the Free route count |
| `POST` | `/jobs/{id}/report` | logged-in | Report a job for moderation (deduped per user) |
| `POST` | `/jobs/{id}/resolve-flag` | moderator | Dismiss or unpublish a flagged job |
| `GET` | `/jobs/{id}/applications` | author or admin | List applications for a job. `page`, `per_page` (max 100), optional `status`. Returns `counts` (`total`, `by_status`) for the whole job, not just the page. |
| `GET` | `/jobs/{id}/applications/export` | job owner or `wcb/manage-settings` | Stream the job's applicants as a CSV file. Send the REST nonce as `_wpnonce` when calling from a link. |

Reopening or republishing an expired or closed job is a status
update, not a dedicated route: `PATCH /jobs/{id}` with
`{ "status": "publish" }`, which is what the employer dashboard's
**Reopen** button sends. It gives the job a fresh deadline, charges
a paid board through the `wcb_job_payment` filter, and fires
`wcb_job_republished`.

Since 1.7.0, every job card returned by `GET /jobs` and
`GET /jobs/{id}` carries viewer-relative fields for a logged-in
requester: `is_bookmarked`, `has_applied`, `application_status`, and
`viewer_can_apply`. These are computed per-request
(`JobsEndpoint::enrich_viewer_state()`, batch-fetched - one usermeta
read plus one applications query per page, never per-row) rather
than baked into the shared query cache, so they stay correct across
requesters. A guest or a request with no matching user gets
`is_bookmarked: false`, `has_applied: false`,
`application_status: null`, `viewer_can_apply: false`.

### Applications

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `POST` | `/jobs/{id}/apply` | candidate or guest | Submit application (guests always allowed; a logged-in user needs the `wcb/apply-jobs` ability) |
| `GET` | `/applications/{id}` | candidate or job-owner | Single application detail |
| `DELETE` | `/applications/{id}` | candidate owner | Withdraw: keeps the application as `withdrawn` (409 once it has an outcome). A row whose job is gone is deleted instead. |
| `PUT` | `/applications/{id}/status` | employer/admin | Change status (submitted/reviewing/shortlisted/rejected/hired). Returns `changed` (false for a same-status save, nothing is sent) and `notified`; 409 on a withdrawn or job-removed application. |
| `GET` | `/candidates/{id}/applications` | self or admin | Candidate's application history, paginated, with `counts` for all of them. |
| `PUT` | `/applications/{id}/rating` | job owner or staff | Set a 1-5 rating (0 clears it). Never shown to candidates. |
| `GET` `POST` | `/applications/{id}/notes` | job owner or staff | List or add private hiring notes |
| `DELETE` | `/applications/{id}/notes/{note}` | note author, or staff | Delete a note |
| `GET` | `/files/{id}` | file owner, staff, or either side of an application | Download a private resume file. The website uses `?wcb_file=<id>`; the app uses this route |
| `POST` | `/candidates/resume-upload` | candidate | Upload a resume file |

### Candidates

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `POST` | `/candidates/register` | guest | Register a new candidate |
| `GET` | `/candidates/{id}` | guest OK | Candidate profile (public read; the callback allows any requester) |
| `PUT` | `/candidates/{id}` | self or admin | Update profile |
| `GET` | `/candidates/{id}/bookmarks` | self or admin | List saved jobs (read-only - toggle via `POST /jobs/{id}/bookmark`) |
| `GET` | `/candidates/{id}/saved-companies` | self or admin | List saved companies (read-only - toggle via `POST /companies/{id}/bookmark`) |
| `GET` | `/candidates/{id}/saved-resumes` | self or admin | List saved resumes (read-only) |
| `POST` | `/candidates/me/privacy/{action}` | self | GDPR self-service: `export` or `erase` personal data |

### Account sign-in and verification

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `POST` | `/auth/app-password` | guest | Trade a website login for a WordPress Application Password. Off unless **App Password Sign-In** is on; requires HTTPS. |
| `DELETE` | `/auth/app-password` | credential holder | Revoke the credential making the request (app sign-out) |
| `POST` | `/auth/verify-email/resend` | guest | Send a new confirmation link to an unconfirmed account (5 an hour per IP) |

### Account

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `GET` | `/account` | logged-in | Read `display_name`, `email` and `email_optout` (the optional emails the member turned off) |
| `PUT` | `/account` | logged-in | Update `display_name`, `email`, `new_password` and `email_optout`. Changing the email or the password needs `current_password` |

### Account deletion

Self-service account deletion for the mobile/companion-app surface
(Apple 5.1.1(v)). Added in 1.7.0 - `AccountDeletionEndpoint`,
backed by `WCB\Modules\Account\AccountDeletionService`. Deletion is
scheduled with a grace period (`wcb_account_deletion_grace_days`
filter, default 14 days) rather than run immediately: the account
is suspended (reuses the `_wcb_employer_banned` flag) and its
Application Passwords are revoked for the window, then the daily
`wcb_process_account_deletions` cron finalizes anything past its
date by calling `wp_delete_user()` - the existing delete-user
cascade runs, nothing is re-implemented. Cancelling is never gated
by the suspension the schedule itself applied, so the grace period
is not a one-way door.

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `DELETE` | `/me` | logged-in | Request deletion of the caller's own account (`password` + `confirm: "DELETE"`); returns `202` when scheduled, `200` when deleted immediately (0-day grace) |
| `GET` | `/me/deletion` | logged-in | Pending-deletion status (`active` or `scheduled` + `scheduled_for`) |
| `DELETE` | `/me/deletion` | logged-in | Cancel a pending deletion |

Administrator accounts (`manage_options`) cannot be deleted through
this route.

### Member safety - Report and block

Member-to-member report/block surface for user-generated-content app
review (Apple 1.2). Added in 1.7.0 - `MembersEndpoint`. Reports
reuse the same per-reporter flag shape the Report-a-Job flow uses,
stored as user-meta on the reported member; blocks reuse the
non-unique-usermeta list pattern the job-bookmark feature uses.

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `POST` | `/users/{id}/report` | logged-in | Report a member (`reason` enum: `spam`, `scam`, `fake_profile`, `harassment`, `offensive`; `details` optional) - deduped per reporter |
| `POST` | `/users/{id}/block` | logged-in | Block a member |
| `DELETE` | `/users/{id}/block` | logged-in | Unblock a member |
| `GET` | `/me/blocked` | logged-in | The caller's blocked-members list (batch-loaded, no N+1) |

A member cannot report or block themself (`wcb_cannot_report_self` /
`wcb_cannot_block_self`, 400). A site owner suspends a member from
the admin Candidates screen bulk action, which fires
`wcb_member_suspended` / `wcb_member_unsuspended` - see the hooks
reference.

### Employers and companies

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `POST` | `/employers/register` | guest | Register a new employer |
| `POST` | `/employers` | admin (`wcb/manage-company`) | Create an employer/company directly (admin tooling, not self-registration) |
| `GET` | `/employers/{id}` | guest OK | Employer detail (public read) |
| `PUT` | `/employers/{id}` | self or admin | Update an employer profile |
| `GET` | `/employers/{id}/jobs` | guest OK | Employer's job postings |
| `GET` | `/employers/{id}/applications` | self or admin | Applications across the employer's jobs |
| `POST` | `/employers/{id}/logo` | self or admin | Upload the company logo |
| `GET` | `/employers/me/jobs` | employer | The current employer's own jobs |
| `GET` | `/employers/me/applications` | employer | Applications across the current employer's jobs |
| `GET` | `/companies` | guest OK | List companies with filters (single company is read from this collection) |
| `POST` | `/companies/{id}/bookmark` | logged-in | Toggle a saved company |
| `POST` | `/companies/{id}/trust` | admin (`wcb/manage-settings`) | Cast a trust signal on a company |

There is no `GET /employers` list route - an employer's profile is
the same `wcb_company` post type companies use, so a public employer
directory is served through `GET /companies`, not a dedicated
employers-collection endpoint.

### Search and settings

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `GET` | `/search` | guest OK | Unified search across jobs and companies |
| `GET` | `/settings/app-config` | guest OK | Frontend boot config consumed by the Interactivity blocks and the mobile/companion app |

Plugin settings are saved through the admin Settings page (the
WordPress Settings API), not a REST write route.

`GET /settings/app-config` (`SettingsEndpoint::get_app_config()`)
was extended in 1.7.0 with the mobile-app contract: `feature_toggles`
(`reporting`, `blocking`, `account_deletion`, plus the existing
`guest_apply`/`bookmarks`/Pro-gated flags), a white-label `legal`
object (`privacy_policy_url`, `terms_url`, `eula_url`,
`community_guidelines_url`, `abuse_contact_email`), branding fields
(`accent_color`, `logo_url`, `login_bg_url`, `dark_mode_default`),
`min_app_version` (filterable via `wcb_min_app_version`),
`contract_version`, and `app_enabled` (filterable via
`wcb_app_enabled`, defaults to whether Pro is active). The whole
payload passes through `wcb_rest_app_config` before it's returned -
see the hooks reference. Additive-only: existing keys are never
renamed or retyped.

### Admin

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `GET` | `/admin/emails/log` | admin | Paginated transactional-email send log |
| `POST` | `/admin/emails/test` | admin | Fire a test send for a named email template |
| `POST` | `/admin/dismiss-banner` | admin | Mark an admin banner dismissed for the current user |
| `GET` `POST` | `/admin/industries` | admin | Read or save the industries list (Settings → Industries) |

### Import and setup wizard

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `GET` | `/import/status` | admin | Poll a running import's progress |
| `POST` | `/import/run` | admin | Start or step a content import |
| `POST` | `/wizard/create-pages` | admin | Create the required Career Board pages |
| `POST` | `/wizard/settings` | admin | Save any settings-schema key (and WordPress's `users_can_register`) through the same cleaning rules as the Settings screen |
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

Submission endpoints are protected by the anti-spam module: an always-on honeypot field plus an optional CAPTCHA provider (Cloudflare Turnstile, Google reCAPTCHA v3 or v2), configured under Settings → Anti-Spam. The module validates on the `rest_pre_dispatch` filter and rejects spammy submissions before the route handler runs.

Public routes that cost something also have hourly per-IP limits, answered with `429 wcb_rate_limited`:

| Route | Limit per hour | Change with |
|---|---|---|
| `POST /candidates/register`, `POST /employers/register` | 5 | `wcb_registration_rate_limit` (0 turns it off) |
| `POST /jobs/{id}/apply` as a guest | 10 | - |
| `POST /auth/verify-email/resend` | 5 | - |
| `POST /auth/app-password` | 20 attempts, 5 failures per bucket | `wcb_app_password_max_attempts_per_ip`, `wcb_app_password_max_failures` |

Every limit counts per visitor IP address. By default that is `REMOTE_ADDR`, which behind a proxy or CDN is the proxy's address. Choose the proxy in **Settings > Anti-Spam > Visitor IP address**, or set the `$_SERVER` key in code with `wcb_client_ip_header`. The leftmost address in the header is used, and an invalid one falls back to `REMOTE_ADDR`.

Both sign-up routes also run `wcb_pre_registration` and core's `registration_errors` filter before an account is created.

## Job lifecycle fields

Every job payload carries `accepting_applications` (true only while the job is published and its deadline has not passed) and `closes_at` (the `Y-m-d` deadline, or empty). Use these instead of comparing status and dates in the client. An expired or closed job still answers `GET /jobs/{id}` and keeps its `/jobs/{slug}/` page; it is simply not accepting applications.

## Application status fields

Every application payload carries `status` (slug), `status_label` (worded for the viewer: candidates see "Not selected" where employers and admins see "Rejected") and `status_tone` (`info`, `warning`, `accent`, `success`, `danger` or `neutral`) so clients show the same words and colours as the site.
