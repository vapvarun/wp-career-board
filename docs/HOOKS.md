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
| `wcb_jobs_query_args` | Modify the WP_Query args used by the listings block on first paint |
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
| `wcb_application_status_changed` | `(int $app_id, string $old_status, string $new_status)` |
| `wcb_deadline_reminder` | `(int $user_id, int $job_id, int $days_left)` |
| `wcb_featured_expired` | `(int $job_id)` |

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
