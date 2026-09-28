# WP Career Board — Hook Reference

Customer-facing extension points for theme integrators and add-on
developers. Stable across Free + Pro: every form-field hook listed here
works the same whether the form is in Free or Pro.

> The `wcb_` prefix family is the **shared customer-facing surface**.
> Pro internals also expose `wcbp_*` hooks, but theme integrators rarely
> need them. Stick to `wcb_*` unless documented otherwise.

## The Field-Group Schema

Every "form-fields" filter returns the same shape — an array of *groups*,
each containing *fields*:

```php
return array(
    array(
        'id'     => 'partner',                      // unique slug
        'label'  => __( 'Partner', 'my-theme' ),    // shown as section heading
        'fields' => array(
            array(
                'key'         => '_wcb_partner_id', // post-meta key (or arbitrary ID for non-meta data)
                'label'       => __( 'Partner', 'my-theme' ),
                'type'        => 'select',          // text | email | tel | url | number | date | textarea | select | checkbox
                'required'    => true,              // optional — defaults false
                'placeholder' => __( 'Choose…', 'my-theme' ),
                'description' => __( 'Helper text below the field', 'my-theme' ),
                'options'     => array(             // required for `select`; ignored for other types
                    1 => 'Acme',
                    2 => 'Beta',
                ),
            ),
        ),
    ),
);
```

Fields rendered via this schema bind their values to the block's
Interactivity API store via `data-wp-on--input="actions.updateCustomField"`
and `data-wcb-field="<key>"`. Submitted values land in the `custom_fields`
property on the REST request body.

## Form-fields filters (one per form)

| Form | Filter name | Args | Where rendered |
|---|---|---|---|
| **Job Form (multi-step wizard)** | `wcb_job_form_fields` | `(array $groups, int $board_id)` | After step 1 fields |
| **Job Form (single-page)** | `wcb_job_form_fields` (shared) | same | After all default fields |
| **Application form (apply modal)** | `wcb_application_form_fields_groups` | `(array $groups, int $job_id)` | After cover letter, before submit |
| **Company / Employer Profile** | `wcb_company_form_fields` | `(array $groups, int $company_id)` | Below default company fields |
| **Candidate Profile** | `wcb_candidate_form_fields` | `(array $groups, int $candidate_id)` | Below default candidate fields |
| **Resume Builder** *(Pro)* | `wcb_resume_form_fields` | `(array $groups, int $resume_id)` | After default resume sections |
| **Resume Form Simple** *(Pro)* | `wcb_resume_form_fields` (shared) | same | After default fields |

Add-ons targeting **both** the wizard and the single-page job form add a
custom field once via `wcb_job_form_fields` and it shows in both. Same
applies to Pro's resume-builder + resume-form-simple sharing
`wcb_resume_form_fields`.

### Where the answers show up

Since 1.7.1, answers to `wcb_application_form_fields_groups` fields are not
just stored — they are surfaced on all three entry points: the
`custom_fields` key of every application REST envelope
(`/applications/{id}` and `/employers/me/applications`), an "Application
answers" pane in the employer dashboard's applicant detail, and the
`application/custom-answers` widget in the wp-admin application metabox.
Labels are read from the live filter output, so a field you stop
registering stops being displayed (its meta row is left alone).

Values are written and read through `\WCB\Core\FormCustomFields`.
`save_values()`, `load_values()` and `labelled_values()` all take an
optional trailing `string $key_prefix` applied to the meta key after
`sanitize_key()` — applications pass
`ApplicationsEndpoint::FIELD_META_PREFIX` (`_wcb_application_field_`), every
other form passes nothing and keeps writing the bare field key.

## Initial-state filters (modify Interactivity API state)

For state keys beyond field values — e.g. computed flags, lookup data
pre-fetched server-side — use the per-form initial-state filter:

| Form | Filter |
|---|---|
| Job Form (wizard) | `wcb_job_form_initial_state` |
| Job Form (simple) | `wcb_job_form_simple_initial_state` |
| Resume Builder *(Pro)* | `wcb_resume_form_initial_state` |
| Resume Form Simple *(Pro)* | `wcb_resume_form_simple_initial_state` |

```php
add_filter( 'wcb_job_form_initial_state', function( $state, $attributes ) {
    $state['partnerOptions'] = my_theme_get_partner_list();
    return $state;
}, 10, 2 );
```

## Action hooks (raw HTML injection — escape hatch)

Use these only when the declarative filter above can't model your need
(e.g. interactive widgets that aren't simple form fields).

| Form / step | Action |
|---|---|
| Job Form wizard, step 1 | `wcb_job_form_step1_fields` |
| Job Form wizard, step 2 | `wcb_job_form_step2_fields` |
| Job Form wizard, step 3 | `wcb_job_form_step3_fields` |
| Job Form wizard, step 4 (preview) | `wcb_job_form_step4_preview` |
| Job Form simple | `wcb_job_form_simple_extra_fields` |
| Application form (legacy) | `wcb_application_form_fields` |

The application action is **legacy** — prefer the new
`wcb_application_form_fields_groups` filter for new code.

## Listings + REST hooks

For filtering the job listings block by custom relationships (partners,
sponsors, brands, etc.):

| Hook | Purpose |
|---|---|
| `wcb_jobs_allowed_meta_filters` | Allowlist post-meta keys for `?meta_<key>=<value>` REST query params on `/wcb/v1/jobs`. Any `_wcb_*` namespaced key is allowed by default (since 1.2.0); this filter is for opting in custom or non-WCB meta keys. |
| `wcb_jobs_post_filter` | Post-process the prepared job array before REST response |
| `wcb_job_response` | Shape an individual job's REST response (legacy alias; prefer `wcb_rest_prepare_job`) |
| `wcb_job_listings_query_args` | Modify the listings block's initial query |
| `wcb_job_listings_board_options` | Add custom chips to the listings filter bar |
| `wcb_job_listing_data` | Shape per-card data on the listings block |
| `wcb_board_options_for_employer` | `(array $options, int $user_id)` — restrict the job-form Boards picker. Pro uses this to hide BuddyPress group boards the current employer is not a member of. Filter receives the full board list and returns the filtered set. |
| `wcb_page_needs_frontend_assets` | `(bool $needs)` — opt a request context into the shared `frontend.css` / `frontend-tokens.css` / `frontend-components.css` stylesheets when the post_content-based detector cannot see WCB block markup. Use for BuddyPress profile / group tabs, page-builder lazy renderers, and any surface that calls `render_block()` after `wp_head` has run. Without this, primitives like `.wcb-hidden` do not resolve and Interactivity toggles render both states stacked. |

## REST response filters (`wcb_rest_prepare_*`)

Canonical pattern for decorating any prepared REST resource. Mirrors WP
core's `rest_prepare_<post_type>` convention so Pro and third-party
extensions can attach extra fields to every prepared response without
patching the Free codebase.

| Filter | Resource | Args | Purpose |
|---|---|---|---|
| `wcb_rest_prepare_job` | Job | `(array $data, WP_Post $job, WP_REST_Request\|null $request)` | Decorate the prepared job response. Sibling to legacy `wcb_job_response` (still fires for back-compat). |
| `wcb_rest_prepare_application` | Application | `(array $data, WP_Post $app, WP_REST_Request\|null $request, string $viewer_role)` | Decorate the prepared application response. `viewer_role` is `candidate`, `employer`, or `admin` — indicates which role-aware shape was generated so consumers can decorate safely without leaking employer-only fields back to candidates. Also fires on the candidate dashboard list (`viewer_role = 'candidate'`) and the employer applications list (`viewer_role = 'employer'`). |
| `wcb_rest_prepare_company` | Company | `(array $data, WP_Post $company, WP_REST_Request\|null $request)` | Decorate the prepared company response. Fired both by the companies endpoint and the employer endpoint's company sub-resource. |
| `wcb_rest_prepare_candidate` | Candidate | `(array $data, WP_User $user, WP_REST_Request\|null $request)` | Decorate the prepared candidate response. |

Example — append a custom field to every job response:

```php
add_filter( 'wcb_rest_prepare_job', function ( array $data, WP_Post $job ): array {
    $data['featured_until'] = (string) get_post_meta( $job->ID, '_my_featured_until', true );
    return $data;
}, 10, 2 );
```

Example — only decorate the employer view of an application (so the
candidate response stays minimal):

```php
add_filter( 'wcb_rest_prepare_application', function ( array $data, WP_Post $app, $request, string $viewer_role ): array {
    if ( 'employer' !== $viewer_role ) {
        return $data;
    }
    $data['internal_notes'] = (string) get_post_meta( $app->ID, '_my_internal_notes', true );
    return $data;
}, 10, 4 );
```

## Lifecycle actions

Fire side effects on key plugin events:

| Action | Args |
|---|---|
| `wcb_job_created` | `(int $job_id, WP_REST_Request $request)` |
| `wcb_job_updated` | `(int $job_id, WP_REST_Request $request)` |
| `wcb_job_approved` | `(int $job_id)` |
| `wcb_job_rejected` | `(int $job_id, string $reason)` |
| `wcb_job_expired` | `(int $job_id)` |
| `wcb_job_deleted` | `(int $job_id)` |
| `wcb_application_submitted` | `(int $app_id, int $job_id, int $candidate_id)` |
| `wcb_application_status_changed` | `(int $app_id, string $old_status, string $new_status, string $reason, int $actor)` - fired once per real change, only by `ApplicationLifecycle::transition()` (1.8.0: `$reason`, `$actor`) |
| `wcb_application_withdrawn` | `(int $app_id, int $job_id, int $candidate_id)` - the application is kept with status `withdrawn` since 1.8.0 |
| `wcb_application_deleted` | `(int $app_id, int $job_id)` - fires before an application post is permanently deleted |
| `wcb_deadline_reminder` | `(int $user_id, int $job_id, int $days_left)` |
| `wcb_job_expiring_soon` | `(int $job_id, int $days_left)` - once per deadline, 3 days before, for the job's employer (1.8.0) |
| `wcb_featured_expired` | `(int $job_id)` |
| `wcb_logs_pruned` | `(string $cutoff)` - daily, after email history older than the retention setting is deleted; prune your own history with the same UTC cutoff (1.8.0) |

## One signal per notification

`wcb_notification_created` fires once per notification-worthy event. Free
fires it when an email is sent; an add-on that records the same event
(Pro's bell) returns false from
`wcb_email_announces_notification( bool $announce, string $email_id, int $user_id )`
and fires the signal itself, so push and BuddyNext never receive an event
twice.

## Community notification contract

For a centralised notification center (BuddyNext, or any add-on that wants
one inbox across every plugin): `wcb_notification_created` carries the
contract payload as its **second argument**, alongside the original payload
array documented above. Existing listeners registered with
`accepted_args = 1` (CB Pro's push module, BuddyNext's bridge) never receive
it — PHP only passes as many arguments as a listener asks for.

```php
add_action( 'wcb_notification_created', function ( array $legacy, ?array $contract ) {
    if ( null === $contract ) {
        return; // A transactional or admin-only email — no community object.
    }
    // $contract: recipient_id, type, actor_id, object_type ('job'|'application'),
    // object_id, message, url, group_key, notification_id.
}, 10, 2 );
```

`CommunityNotificationContract::build()` (`modules/notifications/class-community-notification-contract.php`)
is the one place the payload is assembled — every `AbstractEmail::send()` call
site that names an `object_type` in its `$context` argument gets a contract
payload; a call with no `object_type` (email verification, admin moderation
alerts, the job-pending-review notice) gets `null` and is skipped, since
those are not community-facing events.

| Filter/action | Args | Purpose |
|---|---|---|
| `wcb_community_notification_types` | `( array $types )` returns `slug => array{label,description,default_on}` | Declares every type Free's emails can fire, for a settings screen with one switch per type. |
| `wcb_community_notification_visible` | `( array $visible, int $viewer_id, array $targets )` returns `key => bool` | Answers whether the viewer may still see a bell row about a `job` or `application` object — hidden once trashed, or (for the employer's copy only) once the candidate withdraws. |
| `wcb_community_notification_removed` | `( string $object_type, int $object_id )` | Fires once a `job` or `application` is **permanently** deleted (never on trash/unpublish — that is what `wcb_community_notification_visible` covers). |

@since 1.8.0.

## Filter early-rejection on submission

Both job and application submissions pass through a "pre-submit" filter
where add-ons (anti-spam, validation, paywall checks) can return a
`WP_Error` to short-circuit the submission:

```php
add_filter( 'wcb_pre_application_submit', function( $err, $request ) {
    if ( /* validation fails */ ) {
        return new WP_Error( 'my_theme_blocked',
            __( 'Application blocked', 'my-theme' ),
            array( 'status' => 400 )
        );
    }
    return $err;
}, 10, 2 );
```

| Filter | Purpose |
|---|---|
| `wcb_pre_job_submit` | Short-circuit job creation |
| `wcb_pre_application_submit` | Short-circuit application submission |

## Job fields and terms (1.8.0)

Every job meta key (`_wcb_deadline`, `_wcb_salary_*`, `_wcb_board_id`,
`_wcb_remote`, `_wcb_featured`, `_wcb_apply_*`, `_wcb_company_*`) has a
registered `sanitize_callback` in `WCB\Modules\Jobs\JobsMeta`, so any
writer (REST, admin editor, importers, your own code) gets the same rules.
The create/update routes also validate input and answer `400` naming the
field. An employer moving a pending or draft job to `publish` gets the
status a new submission would (`wcb_job_default_status`); a rejected job
always returns to review.

| Filter | Args | Purpose |
|---|---|---|
| `wcb_job_allow_new_terms` | `$allow, $request` | Whether a submission may create new category / type / location / experience terms. Default: moderators only. Tags are always free-form. |

## Candidate files (1.8.0)

Resumes, CVs and generated resume PDFs are stored under
`uploads/wcb-private/<random>/` with `private` attachment status and are
downloaded only through `?wcb_file=<id>` (website) or
`GET /wcb/v1/files/{id}` (app), both gated by
`WCB\Core\PrivateFiles::can_download()`: the uploader, admins/moderators,
and both sides of an application that carries the file. Use
`PrivateFiles::url( $attachment_id )` when you output a link, never
`wp_get_attachment_url()`. Site Health reports when the web server serves
the folder directly (nginx ignores its .htaccess).

| Filter | Args | Purpose |
|---|---|---|
| `wcb_private_file_can_download` | `$allowed, $attachment_id, $user_id` | Grant download to another audience (Pro: whoever may open the public resume the file belongs to). |

## Job page (1.8.0)

| Action | Args | When |
|---|---|---|
| `wcb_job_single_after_description` | `(int $job_id)` | Right after the job description; Pro prints the job's custom field "Additional details" (public fields to visitors, "Employer only" to the job's employer, "Admin only" to staff). |

Job listing sidebar chips for custom fields toggle `meta_<key>` in the listing's `activeFilters` (`actions.toggleMetaChip` with context `{ metaKey, metaValue }`); a `meta_<key>` URL param is applied on first paint. Pro handles its "Filterable" fields through `wcb_job_search_args`.

## Job search (1.8.0)

`WCB\Modules\Jobs\JobSearch` builds every job list query: GET /jobs and /search, the job listings block's first paint, the `/jobs/` archive and alert keyword matching. Keyword: every word must appear in the title, description or company name; title matches rank first. Filters take one slug or a comma list (any of). `sort`: `relevance` (default with a keyword), `newest` (featured first), `oldest`, `salary`, `closing`; the default without a keyword is Settings > Jobs "Default order".

| Filter | Args | Purpose |
|---|---|---|
| `wcb_job_search_args` | `array $args, array $params` | Change the WP_Query args of a job search (Pro adds the radius bounding box). Put every result-changing value in `$args`: the REST cache key is built from them. |

## Search engines (1.8.0)

| Filter | Args | Purpose |
|---|---|---|
| `wcb_job_posting_schema` | `array $schema, WP_Post $job, array $data` | Change a job's JobPosting (built from the job's REST data `$data`), or return `[]` to leave the job out of Google for Jobs. |

`WCB\Modules\Seo\SeoModule::job_posting( $job )` and `::organization( $company_id )` return the markup arrays. Location country is term meta `_wcb_country` on `wcb_location` (REST-visible).

## Moderation (1.8.0)

A ban (`_wcb_employer_banned` user meta, from any writer) hides the
member's published and pending jobs, company and resume; removing the
meta restores them. Enough reports hide a job as pending. Hidden posts
carry `_wcb_hidden_by` (`ban` or `reports`) and `_wcb_hidden_status`
(the status to restore); read them with
`WCB\Modules\Moderation\HiddenContent::reason( $post_id )`. The
status change skips the transition hooks (no credit charge, emails or
alerts) and fires `save_post_{post_type}` once so list caches refresh.

| Hook | Args | When |
|---|---|---|
| `wcb_job_reported` | `(int $job_id, string $reason, int $user_id)` | After a report is stored and, at the threshold, the job hidden |
| `wcb_member_reported` | `(int $user_id, string $reason, int $reporter_id)` | After a member report is stored |
| `wcb_member_flags_resolved` | `(int $user_id)` | Reports dismissed from the Candidates or Employers list |

`ModerationModule::alert_due( $count )` is the rule the owner email and
Pro's bell share: alert on the first open report and on the one that
reaches the auto-hide threshold.

## Personal data (1.8.0)

One registry drives the WordPress privacy exporter and eraser (Tools >
Export/Erase Personal Data) and user deletion (`delete_user`), so an
add-on that stores personal data registers once and is covered by all
three.

| Filter | Args | Purpose |
|---|---|---|
| `wcb_personal_data_providers` | `array $providers` | Add `'key' => [ 'label' => string, 'export' => callable, 'erase' => callable ]`. Both callables receive `[ 'user_id' => int, 'email' => string ]` (`user_id` is 0 for a guest). `export` returns WordPress export items; `erase` returns `[ 'removed' => int, 'retained' => int, 'messages' => string[] ]`. Providers run in key order, one per privacy-tool page. |

## Sign-up and accounts (1.8.0)

Both sign-up routes (`/candidates/register`, `/employers/register`) run one
gate before an account is created: the plugin's anti-spam check (honeypot +
CAPTCHA), a per-IP limit, then core's `registration_errors` filter so
third-party anti-spam plugins see these sign-ups too. When the
**Email Verification** setting is on (default for new installs), the new
account stays signed out until the link in the "Confirm your email address"
email is opened; sign-in is refused with `wcb_email_unverified` until then.
Signing up while logged in adds the member role instead of replacing an
existing one (administrators and editors keep theirs).

| Hook | Type | Args | Purpose |
|---|---|---|---|
| `wcb_pre_registration` | filter | `$error, $request` | Return a `WP_Error` to refuse a sign-up before the account exists. |
| `wcb_registration_rate_limit` | filter | `$limit` | Sign-ups one IP may make per hour. Default 5, 0 disables. |
| `wcb_email_verification_requested` | action | `$user_id, $verify_url` | A new account needs to confirm its email. The confirmation email listens here. |

## Job payment (1.8.0)

Free has no prices. Every place a job starts costing calls
`WCB\Modules\Jobs\JobPayment::charge( $job_id, $event )`, which applies
`wcb_job_payment`; Pro's credits answer it. Events: `create` (after the job
is inserted, before `wcb_job_created`, so a job that can't be paid for is
removed before anyone is told), `resubmit` (a rejected job sent back),
`republish` (an expired or closed job brought back) and `board_change`
(before any other field changes; a refused move restores the old board).

A failed charge is a 402 `wcb_insufficient_credits` whose `data` carries
`cost`, `balance` and `purchase_url` (from `wcb_credit_purchase_url`), so the
website and the app can send the employer straight to a purchase.

A job a moderator approves that its employer can't pay for stays `pending`
with post meta `_wcb_awaiting_payment` = `1` (the approve route answers 402
`wcb_awaiting_payment`); job payloads expose it as `awaiting_payment`, the
employer dashboard labels it "Awaiting payment" and wp-admin Jobs has an
"Awaiting payment" view. Pro publishes it once the balance covers it.

| Hook | Type | Args | Purpose |
|---|---|---|---|
| `wcb_job_payment` | filter | `$paid, $job_id, $event` | Return true when the job is paid for or free, a WP_Error (402, see `JobPayment::insufficient()`) when not. |
| `wcb_job_republish_credit_cost` | filter (applied by Pro since 1.8.0) | `$cost, $post, $previous` | Credits charged to bring an expired or closed job back. |
| `wcb_featured_upgrade_cost` | filter | `$cost` | What featuring a job costs (Pro prices it). 0 hides the job-form checkbox and the My Jobs Feature action. |
| `wcb_job_featured_expired` | action | `$job_id` | A job's featured period ended (daily sweep). Pro emails the employer and adds a bell notification. |

**Featured (1.8.0).** A job asks to be featured with `featured: true` on
`POST /jobs` (or Pro's `POST /jobs/{id}/feature`); both go through
`JobPayment::charge( $job_id, 'feature' )`, and the handler that takes payment
sets `_wcb_featured`. A job is still posted when the upgrade can't be paid for;
the create response then carries `feature_error`. Moderators write `featured`
directly on `PATCH /jobs/{id}`. Every listing orders featured first, then
newest: `JobsMeta::featured_first( $args )` flags a query
(`wcb_featured_first`) and one `posts_clauses` join does the ordering, so REST
pages and the first server render agree.

**Dashboard slots.** `wcb_module_renders` now receives the asking surface as a
second argument (`employer-dashboard`, `candidate-dashboard`,
`archive-toolbar`) so an extension renders only where its slot is shown. Pro
fills `credits_panel` on the employer dashboard: the Credits tab
(`#credits`), where every purchase link and gateway return lands.

## Settings, pages and setup (1.8.0)

Every key in the `wcb_settings` option is defined once in
`WCB\Admin\SettingsSchema` with its default and its cleaning rule. Settings
forms post a hidden `_wcb_form` marker, so a save merges only the keys that
form posted over what is stored (an unticked checkbox posts `0` and saves
false). A key that is not in the schema is never written by a form.

Every Career Board page (title, slug, block content, Pages-tab copy) is
defined once in `WCB\Admin\Pages::definitions()`. The setup wizard's Pages
step, Settings > Pages "Create Missing Pages", the Pages tab and the page
resolver all read it. `Pages::create_missing()` keeps a page that already
resolves, adopts a published page that already carries the block, and only
then creates one at the canonical slug.

The setup wizard saves each settings step through
`POST /wcb/v1/wizard/settings` (`settings` = key => value; any schema key plus
WordPress's `users_can_register`), which uses the same sanitizer.

| Hook | Type | Args | Purpose |
|---|---|---|---|
| `wcb_settings_schema` | filter | `$fields` | Add setting keys: `$fields['my_key'] = array( 'default' => false, 'sanitize' => 'rest_sanitize_boolean' )`. Pro registers its keys here. |
| `wcb_settings_sanitize` | filter | `$output, $input` | Last look at the cleaned option before it is saved. |
| `wcb_page_definitions` | filter | `$defs` | Add a page: `title`, `slug`, `content` (block markup), `label`, `desc`, optional `aliases` (older slugs still accepted). Register the key in `wcb_settings_schema` too. Pro adds Find Candidates (`resume_archive_page`, slug `find-candidates`, alias `find-resumes`) and Job Map (`job_map_page`). |
| `wcb_wizard_required_pages` | filter, deprecated 1.8.0 | `$defs` | Use `wcb_page_definitions`. Still applied. |
| `wcb_page_settings` | filter, deprecated 1.8.0 | `$rows` | Use `wcb_page_definitions`. Still applied (label and desc). |
| `wcb_wizard_steps` | filter | `$steps` | Add wizard steps (`title`, `template`, `button_text`). A step template that renders inputs named after schema keys plus the shared footer (`admin/views/wizard-steps/_footer.php`) saves with no JavaScript of its own. |
| `wcb_safer_defaults_notice` | filter | `$items` | The list shown once to owners of sites that predate the 1.8.0 defaults. |
| `wcb_apply_ai_notice` | filter | `$text, $job_id` | Notice shown above Submit Application (and in app-config `apply_ai_notice`) when AI reads applications. Empty hides it. |

**Brand.** One colour and logo (`accent_color`, `logo_id` in `wcb_settings`,
set under Settings > Brand) for emails, app-config and Pro's PWA manifest.
Read it with `WCB\Core\Brand::color()` and `WCB\Core\Brand::logo_url( $size )`
rather than the raw keys. The 1.3.4 upgrade moves an existing site's email
header colour and logo into the Brand.

**CAPTCHA.** `WCB\Modules\AntiSpam\AntiSpamModule::active()` answers which
provider is in force (chosen AND both keys set): Turnstile, reCAPTCHA v3 or
reCAPTCHA v2 (invisible badge). The web forms and app-config
(`captcha_required`, `captcha.provider`, `captcha.site_key`) both read it.

## Active-job quota (free tier)

`JobsEndpoint::check_active_job_limit()` gates job create and republish
on an opt-in per-employer cap. Default 0 = unlimited, so the quota is
inert until a site filters it.

| Filter | Args | Purpose |
|---|---|---|
| `wcb_employer_active_job_limit` | `$limit, $user_id, $request` | Max concurrently active jobs. 0 = unlimited. |
| `wcb_employer_active_job_statuses` | `$statuses, $user_id` | Statuses that occupy a slot. Default `['publish']`. |
| `wcb_employer_active_job_limit_message` | `$message, $limit, $count` | Copy on the 403. |

Two contract notes that are easy to break in a refactor:

1. **Skipped wholesale when `wcb_credits_enabled` is true.** Credits and
   the quota are alternative volume controls, never stacked — an
   employer must not pay a credit and still be refused.
2. **The republish check excludes the job being republished** from its
   own count, so reopening a listing while under the cap of the
   employer's *other* live jobs succeeds. Counting it would make the
   last slot permanently unusable.

Counted via `posts_per_page => 1` + `found_posts` — one COUNT, never a
hydrated result set (an agency account can hold thousands of listings).

## Convention

- **`wcb_*`** — customer-facing extension surface. Stable.
- **`wcbp_*`** — Pro-internal hooks. May change between Pro versions; not
  intended for theme integrators.
- All form-fields filters return the same group/field schema documented at
  the top of this file. Use it once, use it everywhere.

## REST controller carve-outs

The architecture rule (see `CLAUDE.md`) is that every REST route ships as an
Endpoint class under `WCB\Api\Endpoints\` (Free) or `WCB\Pro\Api\Endpoints\`
(Pro), extends `WCB\Api\RestController` / `WCB\Pro\Api\Pro_REST_Controller`,
and gets registered through the central `register_rest_routes()` loop in
`core/class-plugin.php` (Free) or `core/class-pro-plugin.php` (Pro).

Three classes are documented exceptions. Each still extends `RestController`
(so it inherits `check_ability()`, `permission_error()`, `$this->namespace`),
but it calls `register_rest_route()` directly inside its own
`register_routes()` instead of being added to the central Endpoint registry.
They sit alongside their feature, not in the api/endpoints directory:

| Routes | File:line | Why it carves out |
|---|---|---|
| `POST /wcb/v1/wizard/create-pages`, `/wizard/sample-data`, `/wizard/complete`, `/wizard/remove-sample-data` | `admin/class-setup-wizard.php:189,199,216,226` | First-run admin wizard. The class lives under `WCB\Admin\` because it owns the admin-side activation hook + the localized JS handle (`wcb-wizard`); pulling its 4 routes into `api/endpoints/` would split one feature across two namespaces. |
| `POST /wcb/v1/jobs/(id)/approve`, `/jobs/(id)/reject` | `modules/moderation/class-moderation-module.php:60,73` | Moderation lives as a self-contained module under `WCB\Modules\Moderation\`. The two routes are part of that module's contract (alongside its filter `wcb_moderate_jobs_ability_check`); moving them to `api/endpoints/` would orphan them from the rest of the module. |
| `POST /wcb/v1/wizard/activate-license`, `/wizard/setup-credits`, `/wizard/create-pro-pages` | Pro: `wp-career-board-pro/admin/class-pro-setup-wizard.php:167,184,206` | Same reasoning as Free's setup wizard — Pro extension steps that belong with the wizard's admin code, not in `api/endpoints/`. |

These are the documented exceptions to the "all REST routes go through
Endpoint classes registered in `register_rest_routes()`" rule. New REST
work should still ship as an Endpoint class unless the route is part of an
already-co-located feature module (admin wizard, self-contained module).
Reviewers seeing direct `register_rest_route()` calls in any *other* file
should flag it.

## App sign-in credentials

`WCB\Auth\AppCredentials` trades a member's ordinary WordPress login for a core
Application Password, because core will not: its Basic auth validates against
stored application passwords only, so the core route that mints one already
requires one, and every core path to a first credential runs through wp-admin
under cookie auth.

It is not a second authentication system — `wp_authenticate()` does the actual
authentication exactly as wp-login.php does, so every `authenticate` filter on
the site still runs. This class only decides what to hand back once core says yes.

| Hook | Type | Args | Purpose |
|---|---|---|---|
| `wcb_app_password_login_enabled` | filter | `$on` | Whether the exchange is available. Backs the `app_password_login` setting, **default OFF**. |
| `wcb_app_password_max_failures` | filter | `$max` | Failed sign-ins per bucket before lockout. Default 5. |
| `wcb_app_password_max_attempts_per_ip` | filter | `$max` | Total attempts per IP per hour. Default 20. |
| `wcb_app_password_client_ip_header` | filter | `$header` | `$_SERVER` key carrying the real client IP. Empty by default. |
| `wcb_app_credential_issued` | action | `$user_id, $app_id, $app_name` | A member exchanged their password for a credential. |
| `wcb_app_credential_revoked` | action | `$user_id, $uuid` | A member revoked their own credential (app sign-out). |

Three contract notes worth keeping:

1. **The default is OFF, and that is not conservatism for its own sake.** This
   route accepts real account passwords, and most sites never install the app.
   Turning it on for everyone to serve the minority that do is the wrong trade —
   especially since `AppConnect`'s browser hand-off is the primary sign-in flow
   and needs no switch at all. The app's sign-in screen hides password entry when
   the site reports the feature off, so members see the secure path, not a broken
   button.

2. **`wcb_app_credential_issued` deliberately does not carry the credential.**
   A listener that logged it would undo the reason the class is careful with it
   everywhere else.

3. **`wcb_app_password_client_ip_header` is empty by default on purpose.**
   `REMOTE_ADDR` is the only value a PHP process can trust; behind a proxy it is
   the proxy's address, identical for every visitor, which turns the per-IP
   ceiling into a site-wide outage. But reading a forwarded header by default is
   the opposite mistake — anyone can send one, making the limiter bypassable. So
   the owner names the header their own proxy always overwrites:

   ```php
   add_filter( 'wcb_app_password_client_ip_header', fn() => 'HTTP_CF_CONNECTING_IP' );
   ```

   The leftmost address is taken and validated, so a malformed header degrades to
   `REMOTE_ADDR` rather than poisoning a rate-limit bucket key.
