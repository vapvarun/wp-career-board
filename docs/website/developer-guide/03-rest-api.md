# REST API Reference

Use the `wcb/v1` REST namespace to read and manage jobs, applications, candidates, employers and companies from your own code or app. Every endpoint class extends `WCB\Api\RestController`, which owns the shared response envelope and the abilities-aware permission helper. All routes below are relative to `/wp-json/wcb/v1`.

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

A small set of endpoints permit guest access: the read-only jobs, companies, candidates, employers and search endpoints, the candidate and employer registration endpoints, and the apply endpoint. A guest can apply unless **Require login to apply** is on under **Settings > Applications**; then a logged-out request gets `401 wcb_login_required`. A logged-in user must hold the `wcb/apply-jobs` ability.

Abuse prevention on submission endpoints comes from the anti-spam module: an always-on honeypot field plus an optional CAPTCHA provider (Cloudflare Turnstile, Google reCAPTCHA v3 or v2). Some public routes also have hourly per-IP limits. See [Abuse prevention](#abuse-prevention).

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

### Jobs

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `GET` | `/jobs` | guest OK | List jobs with filters: `search` (alias `s`), `category`, `location`, `type`, `experience`, `tag` (each takes one slug or a comma list, any of), `remote`, `salary_min`, `salary_max`, `board` (alias `board_id`), `company`, `author`, `open` (only jobs taking applications), `sort` (`relevance`, `newest`, `oldest`, `salary`, `closing`), `per_page` (1-100; unset uses **Jobs Per Page**), `page` |
| `GET` | `/jobs/{id}` | guest OK | Single job (full detail) |
| `POST` | `/jobs` | `wcb/post-jobs` | Create a job. Send `featured: true` to ask for featured placement: the job is still created if that charge fails, and the response carries `feature_error`. A 402 `wcb_insufficient_credits` carries `cost`, `balance` and `purchase_url` |
| `PUT` `PATCH` | `/jobs/{id}` | author or company member with `wcb/post-jobs`, or `wcb/manage-settings` | Update a job. Moderators can set `featured` here |
| `DELETE` | `/jobs/{id}` | same as update | Move a job to the trash |
| `POST` | `/jobs/{id}/approve` | moderator | Approve a pending job |
| `POST` | `/jobs/{id}/reject` | moderator | Reject a pending job (requires `reason`) |
| `POST` | `/jobs/{id}/bookmark` | `wcb/bookmark-jobs` (any logged-in member by default) | Toggle a saved job |
| `POST` | `/jobs/{id}/feature` | job owner | Pay to feature a job. Added by WP Career Board Pro |
| `POST` | `/jobs/{id}/report` | logged-in | Report a published job for moderation. A second report from the same user is ignored |
| `POST` | `/jobs/{id}/resolve-flag` | moderator | Dismiss or unpublish a flagged job |
| `GET` | `/jobs/{id}/applications` | `wcb/view-applications` and the job's author, or `wcb/manage-settings` or `wcb/moderate-jobs` | List applications for a job. `page`, `per_page` (max 100), optional `status`. Returns `counts` (`total`, `by_status`) for the whole job, not just the page. |
| `GET` | `/jobs/{id}/applications/export` | the job's author or `wcb/manage-settings` | Stream the job's applicants as a CSV file. Send the REST nonce as `_wpnonce` when calling from a link. |

Reopening or republishing an expired or closed job is a status
update, not a dedicated route: `PATCH /jobs/{id}` with
`{ "status": "publish" }`, which is what the employer dashboard's
**Reopen** button sends. It gives the job a fresh deadline, charges
a paid board through the `wcb_job_payment` filter, and fires
`wcb_job_republished`.

Every job card returned by `GET /jobs` and `GET /jobs/{id}` carries viewer-relative fields: `is_bookmarked`, `has_applied`, `application_status` and `viewer_can_apply`. They are computed per request, so they stay correct for each requester. A guest gets `is_bookmarked: false`, `has_applied: false`, `application_status: null` and `viewer_can_apply: true`.

### Applications

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `POST` | `/jobs/{id}/apply` | guest or `wcb/apply-jobs` | Submit an application. Guests are allowed unless **Require login to apply** is on. You cannot apply to your own job |
| `GET` | `/applications/{id}` | the candidate, the job's author, or `wcb/manage-settings` | Single application detail |
| `DELETE` | `/applications/{id}` | candidate owner or `wcb/manage-settings`; needs `wcb/withdraw-application` and **Allow Withdraw** on (Settings > Applications) | Withdraw: keeps the application as `withdrawn` (409 once it has an outcome) |
| `PUT` | `/applications/{id}/status` | `wcb/view-applications` and the job's author, or `wcb/manage-settings` | Change status (`submitted`, `reviewing`, `shortlisted`, `rejected`, `hired`). Returns `changed` (false for a same-status save) and `notified`; 409 on an application that already has an outcome |
| `GET` | `/candidates/{id}/applications` | that candidate or `wcb/manage-settings` | Candidate's application history, paginated, with `counts` for all of them. |
| `PUT` | `/applications/{id}/rating` | same as status | Set a 1-5 rating (0 clears it). Never shown to candidates |
| `GET` `POST` | `/applications/{id}/notes` | same as status | List or add private hiring notes |
| `DELETE` | `/applications/{id}/notes/{note}` | same as status | Delete a note |
| `GET` | `/files/{id}` | file owner, staff, or either side of an application | Download a private resume file. The website uses `?wcb_file=<id>`; the app uses this route |
| `POST` | `/candidates/resume-upload` | `wcb/manage-resume` | Upload a resume file |

### Candidates

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `POST` | `/candidates/register` | guest | Register a new candidate |
| `GET` | `/candidates/{id}` | guest OK | Candidate profile |
| `PUT` | `/candidates/{id}` | self or admin | Update profile |
| `GET` | `/candidates/{id}/bookmarks` | self or admin | List saved jobs (read-only - toggle via `POST /jobs/{id}/bookmark`) |
| `GET` | `/candidates/{id}/saved-companies` | self or admin | List saved companies (read-only - toggle via `POST /companies/{id}/bookmark`) |
| `GET` | `/candidates/{id}/saved-resumes` | self or admin | List saved resumes (read-only) |
| `POST` | `/candidates/me/privacy/{action}` | logged-in | Personal data self-service: `export` or `erase` |

### Account sign-in and verification

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `POST` | `/auth/app-password` | guest | Trade a website login for a WordPress Application Password. Off unless **App Password Sign-In** is on; requires HTTPS. |
| `DELETE` | `/auth/app-password` | logged-in | Revoke the credential making the request (app sign-out) |
| `POST` | `/auth/verify-email/resend` | guest | Send a new confirmation link to an unconfirmed account (5 an hour per IP) |

### Account

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `GET` | `/account` | logged-in | Read `display_name`, `email` and `email_optout` (the optional emails the member turned off) |
| `PUT` | `/account` | logged-in | Update `display_name`, `email`, `new_password` and `email_optout`. Changing the email or the password needs `current_password` |

### Account deletion

Members can delete their own account from the app. Deletion is scheduled with a grace period (`wcb_account_deletion_grace_days` filter, default 14 days) rather than run at once. For the window the account is suspended and its Application Passwords are revoked. The daily `wcb_process_account_deletions` cron then deletes anything past its date with `wp_delete_user()`. A member can cancel during the window.

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `DELETE` | `/me` | logged-in | Request deletion of the caller's own account (`password` + `confirm: "DELETE"`); returns `202` when scheduled, `200` when deleted immediately (0-day grace) |
| `GET` | `/me/deletion` | logged-in | Pending-deletion status (`active` or `scheduled` + `scheduled_for`) |
| `DELETE` | `/me/deletion` | logged-in | Cancel a pending deletion |

Administrator accounts (`manage_options`) cannot be deleted through
this route.

### Member safety - Report and block

Members can report and block other members.

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `POST` | `/users/{id}/report` | logged-in | Report a member (`reason` enum: `spam`, `scam`, `fake_profile`, `harassment`, `offensive`; `details` optional) - a second report from the same reporter is ignored |
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
| `POST` | `/employers` | `wcb/manage-company` | Create a company directly (not self-registration) |
| `GET` | `/employers/{id}` | guest OK | Employer detail. `{id}` is the company ID |
| `PUT` | `/employers/{id}` | the company's author with `wcb/manage-company`, or `wcb/manage-settings` | Update an employer profile |
| `GET` | `/employers/{id}/jobs` | guest OK | Employer's job postings |
| `GET` | `/employers/{id}/applications` | the company's author with `wcb/view-applications`, or `wcb/manage-settings` | Applications across the employer's jobs |
| `POST` | `/employers/{id}/logo` | same as update | Upload the company logo (file field `logo`) |
| `GET` | `/employers/me/jobs` | `wcb/access-employer-dashboard` | The current employer's own jobs |
| `GET` | `/employers/me/applications` | `wcb/access-employer-dashboard` | Applications across the current employer's jobs |
| `GET` | `/companies` | guest OK | List companies. Filters: `search`, `industry`, `size`, `page`, `per_page` (max 100) |
| `POST` | `/companies/{id}/bookmark` | `wcb/bookmark-jobs` | Toggle a saved company |
| `POST` | `/companies/{id}/trust` | `wcb/manage-settings` | Set a company's `trust_level` (`verified`, `trusted`, `premium`, or empty to clear) |

There is no `GET /employers` list route. An employer's profile is the same `wcb_company` post type companies use, so a public employer directory is served by `GET /companies`.

### Search and settings

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `GET` | `/search` | guest OK | Unified search across jobs and companies |
| `GET` | `/settings/app-config` | guest OK | Frontend boot config consumed by the Interactivity blocks and the mobile/companion app |

Plugin settings are saved through the admin Settings page (the
WordPress Settings API), not a REST write route.

`GET /settings/app-config` returns the boot config for the blocks and the mobile app, including `feature_toggles` (such as `reporting`, `blocking`, `account_deletion` and the Pro-gated flags), a `legal` object (`privacy_policy_url`, `terms_url`, `eula_url`, `community_guidelines_url`, `abuse_contact_email`), branding fields (`accent_color`, `logo_url`, `login_bg_url`, `dark_mode_default`), `min_app_version` (filter `wcb_min_app_version`), `contract_version` and `app_enabled` (filter `wcb_app_enabled`, defaults to whether Pro is active). The whole payload passes through `wcb_rest_app_config` before it is returned; see the [hooks reference](02-hooks-reference.md).

### Admin

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `GET` | `/admin/emails/log` | admin | Paginated transactional-email send log |
| `POST` | `/admin/emails/test` | admin | Fire a test send for a named email template |
| `POST` | `/admin/dismiss-banner` | admin | Mark an admin banner dismissed for the current user |
| `GET` `POST` | `/admin/industries` | admin | Read or save the industries list (**Settings > Industries**) |

### Import and setup wizard

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `GET` | `/import/status` | admin | Poll a running import's progress |
| `POST` | `/import/run` | admin | Start or step a content import |
| `POST` | `/wizard/create-pages` | admin | Create the Career Board pages |
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

You inherit the response envelope, `current_user_id()`, `permission_error()` (401 or 403) and the abilities-aware `check_ability( $ability )` helper. Pass the ability slug you want to gate on.

## Abuse prevention

Job, application and sign-up submissions are checked by the anti-spam module: an always-on honeypot field plus an optional CAPTCHA provider (Cloudflare Turnstile, Google reCAPTCHA v3 or v2), configured under **Settings > Anti-Spam**. It hooks `wcb_pre_job_submit`, `wcb_pre_application_submit` and `wcb_pre_registration`, and rejects a spam request before the record is created.

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
